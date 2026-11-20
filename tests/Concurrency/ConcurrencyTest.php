<?php

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditEvent;
use App\Models\Court;
use App\Models\User;
use App\Services\ReservationService;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/*
 * Tes ini memakai dua proses PHP sungguhan yang berjalan bersamaan, bukan simulasi.
 * Data tidak di-rollback (proses lain tidak bisa melihat transaksi tes), jadi
 * setiap tes memakai lapangan baru.
 */
uses(TestCase::class);

/**
 * @return list<array{ok: bool}>
 */
function runTogether(array $first, array $second): array
{
    $start = microtime(true) + 3;
    $processes = [];
    foreach ([$first, $second] as $job) {
        $process = new Process(
            [PHP_BINARY, '-c', (string) php_ini_loaded_file(), base_path('tests/concurrency-worker.php'), json_encode([...$job, 'start' => $start])],
            base_path(),
            ['APP_ENV' => 'testing', 'DB_DATABASE' => 'lola_demo_test'],
        );
        $process->setTimeout(45);
        $process->start();
        $processes[] = $process;
    }

    return array_map(function (Process $process) {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }, $processes);
}

beforeEach(function () {
    $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertSuccessful();
    $this->seed();
    $this->staff = User::query()->where('email', 'pegawai@lola.test')->firstOrFail();
    $this->court = Court::create(['name' => 'Uji '.bin2hex(random_bytes(5)), 'sport' => 'futsal', 'hourly_rate' => 100000]);
    $this->data = [
        'court_id' => $this->court->id,
        'date' => now()->addDays(3)->format('Y-m-d'),
        'hour' => 19,
        'duration' => 2,
        'customer_name' => 'Uji Bersamaan',
        'customer_phone' => '081200000000',
    ];
    $this->booking = fn () => app(ReservationService::class)->create($this->data, BookingSource::Website);
});

it('creates only one booking when two customers book the same slot at once', function () {
    $job = ['action' => 'book', 'staff' => $this->staff->id, 'data' => $this->data];

    $results = runTogether($job, $job);

    expect(array_column($results, 'ok'))->toContain(true, false)
        ->and($this->court->bookings()->count())->toBe(1);
});

it('approves a proof only once when two staff press approve together', function () {
    $booking = ($this->booking)();
    $payment = $booking->payments()->create(['amount' => 100000, 'method' => 'qris', 'status' => PaymentStatus::Pending]);
    $job = ['action' => 'approve', 'staff' => $this->staff->id, 'payment' => $payment->id];

    $results = runTogether($job, $job);

    expect(array_column($results, 'ok'))->toContain(true, false)
        ->and($booking->paidAmount())->toBe(100000)
        ->and(AuditEvent::query()->where('booking_id', $booking->id)->where('action', 'payment_approved')->count())->toBe(1);
});

it('never accepts more cash than the bill', function () {
    $booking = ($this->booking)();
    $job = ['action' => 'cash', 'staff' => $this->staff->id, 'booking' => $booking->id, 'amount' => 200000];

    $results = runTogether($job, $job);

    expect(array_column($results, 'ok'))->toContain(true, false)
        ->and($booking->paidAmount())->toBe(200000);
});

it('serializes cancellation against approval', function () {
    $booking = ($this->booking)();
    $payment = $booking->payments()->create(['amount' => 100000, 'method' => 'qris', 'status' => PaymentStatus::Pending]);

    runTogether(
        ['action' => 'cancel', 'staff' => $this->staff->id, 'booking' => $booking->id],
        ['action' => 'approve', 'staff' => $this->staff->id, 'payment' => $payment->id],
    );

    // Urutan mana pun, hasil akhirnya konsisten: dibatalkan, dan bukti tidak menggantung.
    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($payment->refresh()->status)->toBeIn([PaymentStatus::Approved, PaymentStatus::Rejected])
        ->and($booking->paidAmount())->toBeIn([0, 100000]);
});
