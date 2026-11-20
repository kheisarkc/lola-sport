<?php

use App\Models\Court;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\RevenueReport;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;

// UC-14, UC-15, UC-16, KF-20, KF-21, KF-22, KF-25

function paidBooking(array $overrides, int $amount): void
{
    $booking = bookAsCustomer($overrides);
    test()->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => $amount]);
    auth()->logout();
}

it('counts received money by verification date in WIB', function () {
    paidBooking([], 130000);
    paidBooking(['hour' => 21], 130000);
    [$first, $second] = Payment::query()->orderBy('id')->get();
    $first->update(['reviewed_at' => CarbonImmutable::parse('2026-11-01 23:59:59')]);
    $second->update(['reviewed_at' => CarbonImmutable::parse('2026-11-02 00:00:00')]);

    $report = app(RevenueReport::class)->build(CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2026-11-02'));

    expect($report['total'])->toBe(130000)
        ->and($report['transactions']->pluck('id')->all())->toBe([$second->id]);
});

it('splits DP, settlement and full payment so the parts always equal the total', function () {
    $booking = bookAsCustomer(); // 260.000
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);
    Payment::query()->first()->update(['reviewed_at' => CarbonImmutable::parse('2026-11-05 10:00')]);
    $this->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);
    auth()->logout();
    paidBooking(['hour' => 21], 260000);

    $report = app(RevenueReport::class)->build(CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10'));

    // Pelunasan hari ini tetap terbaca pelunasan walaupun DP-nya di luar rentang.
    expect($report['by_kind'])->toBe(['dp' => 0, 'settlement' => 130000, 'full' => 260000])
        ->and(array_sum($report['by_kind']))->toBe($report['total'])
        ->and($report['transactions']->sum('amount'))->toBe($report['total']);
});

it('keeps forfeited DP of cancelled bookings in revenue and ignores pending proofs', function () {
    $booking = bookAsCustomer();
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);
    $this->post("/pegawai/reservasi/{$booking->code}/batal");
    auth()->logout();
    $other = bookAsCustomer(['hour' => 21]);
    $this->post("/reservasi/{$other->code}/bayar", ['amount' => 130000, 'proof' => proofFile()]);

    $this->actingAs(owner())->get('/pemilik')
        ->assertOk()
        ->assertSee('Rp130.000')
        ->assertSee('Termasuk DP hangus');
    $this->get('/pemilik/transaksi')
        ->assertOk()
        ->assertSee($booking->code)
        ->assertSee('Hangus · Dibatalkan')
        ->assertDontSee($other->code);
    $this->get('/pemilik/transaksi?cara=tunai')->assertSee($booking->code);
    $this->get('/pemilik/transaksi?cara=qris')->assertDontSee($booking->code)->assertSee('Belum ada transaksi');
});

it('shows zero and every day of the period on the dashboard', function () {
    $this->actingAs(owner())->get('/pemilik?dari=2026-11-01&sampai=2026-11-03')
        ->assertOk()
        ->assertSee('Rp0')
        ->assertSee('Belum ada transaksi')
        ->assertSee('Min, 1 Nov')
        ->assertSee('Sel, 3 Nov');

    $this->get('/pemilik?dari=2026-11-05&sampai=2026-11-01')->assertSessionHasErrors('sampai');
});

it('counts bookings and hours per court by play date', function () {
    paidBooking([], 130000);
    bookAsCustomer(['hour' => 21]); // belum DP, tidak dihitung

    $report = app(RevenueReport::class)->build(CarbonImmutable::parse('2026-11-12'), CarbonImmutable::parse('2026-11-12'));
    $row = $report['bookings_per_court']->first(fn ($r) => $r['court']->name === 'Futsal Sintetis');

    expect($row['count'])->toBe(1)->and($row['hours'])->toBe(2);
});

it('changes prices for new bookings only', function () {
    $old = bookAsCustomer();
    $this->actingAs(owner())->put('/pemilik/lapangan/'.court()->id, [
        'name' => 'Futsal Sintetis', 'sport' => 'futsal', 'hourly_rate' => 150000, 'active' => '1',
    ])->assertSessionHasNoErrors();
    auth()->logout();
    $new = bookAsCustomer(['hour' => 21]);

    expect($old->refresh()->total)->toBe(260000)->and($new->total)->toBe(300000);
});

it('adds courts and hides inactive ones from the schedule', function () {
    $this->actingAs(owner());
    $this->post('/pemilik/lapangan', ['name' => 'Badminton 3', 'sport' => 'badminton', 'hourly_rate' => 35000, 'active' => '1'])
        ->assertSessionHasNoErrors();
    $this->post('/pemilik/lapangan', ['name' => 'Badminton 3', 'sport' => 'badminton', 'hourly_rate' => 35000])
        ->assertSessionHasErrors('name');
    $court = Court::query()->where('name', 'Badminton 3')->firstOrFail();
    $this->put("/pemilik/lapangan/{$court->id}", ['name' => 'Badminton 3', 'sport' => 'badminton', 'hourly_rate' => 35000]);
    auth()->logout();

    $this->get('/')->assertSee('Pilih Badminton 2 jam 12.00')->assertDontSee('Pilih Badminton 3 jam');
});

it('validates and saves DP and hold settings', function () {
    $this->actingAs(owner());
    $this->put('/pemilik/pengaturan', ['dp_percent' => 0, 'hold_minutes' => 30])->assertSessionHasErrors('dp_percent');
    $this->put('/pemilik/pengaturan', ['dp_percent' => 40, 'hold_minutes' => 300])->assertSessionHasErrors('hold_minutes');
    $this->put('/pemilik/pengaturan', ['dp_percent' => 40, 'hold_minutes' => 45])->assertSessionHasNoErrors();

    expect(Setting::current()->dp_percent)->toBe(40)->and(Setting::current()->hold_minutes)->toBe(45);
});

it('compares each period with the one before it', function () {
    $today = CarbonImmutable::parse('2026-11-10');
    $month = ReportPeriod::preset('bulan-ini', $today);
    $week = ReportPeriod::preset('7-hari', $today);
    $lastMonth = ReportPeriod::preset('bulan-lalu', $today);
    $endOfMarch = ReportPeriod::preset('bulan-ini', CarbonImmutable::parse('2027-03-31'));

    expect($month->label())->toBe('1–10 Nov 2026')
        ->and($month->previousLabel())->toBe('1–10 Okt 2026')
        ->and($week->label())->toBe('4–10 Nov 2026')
        ->and($week->previousLabel())->toBe('28 Okt – 3 Nov 2026')
        ->and($lastMonth->label())->toBe('1–31 Okt 2026')
        ->and($lastMonth->previousLabel())->toBe('1–30 Sep 2026')
        // 31 Maret dibanding 1-28 Februari, tidak meluber ke Maret.
        ->and($endOfMarch->previousLabel())->toBe('1–28 Feb 2027')
        ->and(ReportPeriod::preset('hari-ini', $today)->previousLabel())->toBe('kemarin');
});

it('shows money today against yesterday, split by cash and QRIS and by staff', function () {
    paidBooking([], 130000); // tunai hari ini
    $web = bookAsCustomer(['hour' => 21]);
    $this->post("/reservasi/{$web->code}/bayar", ['amount' => 260000, 'proof' => proofFile()]);
    $this->actingAs(staff())->post('/pegawai/pembayaran/'.$web->payments()->first()->id.'/terima');
    auth()->logout();
    paidBooking(['hour' => 15], 130000);
    Payment::query()->orderByDesc('id')->first()->update(['reviewed_at' => CarbonImmutable::parse('2026-11-09 20:00')]);

    $this->actingAs(owner())->get('/pemilik?periode=hari-ini')
        ->assertOk()
        ->assertSeeInOrder(['Uang masuk', 'Rp390.000', 'Naik 200%', 'Kemarin: Rp130.000'])
        ->assertSeeInOrder(['Tunai', 'Rp130.000', 'QRIS', 'lewat web Rp260.000', 'Rp260.000'])
        ->assertSeeInOrder(['Dicatat per penjaga', staff()->name, 'Tunai Rp130.000 · QRIS Rp260.000', 'Rp390.000']);
});

it('counts reservations, court usage, busiest hours and unpaid balance', function () {
    paidBooking([], 130000); // 12 Nov 19.00-21.00, sisa Rp130.000
    paidBooking(['hour' => 20, 'duration' => 1, 'court_id' => Court::query()->where('name', 'Futsal Vinil')->value('id')], 160000);
    bookAsCustomer(['hour' => 9]); // belum bayar, tidak dihitung

    $this->actingAs(owner())->get('/pemilik?dari=2026-11-12&sampai=2026-11-12')
        ->assertOk()
        ->assertSeeInOrder(['Reservasi', '2'])
        // 3 jam / (1 hari x 4 lapangan x 18 jam) = 4,2%.
        ->assertSeeInOrder(['Lapangan terpakai', '4,2%'])
        ->assertSeeInOrder(['Belum dilunasi', 'Rp130.000', '1 pesanan'])
        ->assertSeeInOrder(['Jam paling ramai', '20.00', '2×', '19.00', '1×'])
        // Futsal Sintetis 2 dari 18 jam buka = 11,1%.
        ->assertSeeInOrder(['Futsal Sintetis', '1 reservasi · 2 jam', '11,1%']);
});

it('downloads the same transactions as CSV', function () {
    paidBooking([], 130000);
    $code = Payment::query()->first()->booking->code;

    $response = $this->actingAs(owner())->get('/pemilik/transaksi/unduh?periode=hari-ini');
    $response->assertOk()->assertDownload('transaksi-lola-20261110-20261110.csv');
    $csv = $response->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBFditerima,kode,pelanggan")
        ->toContain($code)
        ->toContain('Tunai')
        ->toContain('130000');
    $this->get('/pemilik/transaksi/unduh?periode=hari-ini&cara=qris')->assertOk();
});

it('gives the owner a bottom menu and no buttons that change bookings', function () {
    paidBooking([], 130000);

    $this->actingAs(owner())->get('/pemilik')
        ->assertOk()
        ->assertSeeInOrder(['Ringkasan', 'Transaksi', 'Pengaturan'])
        ->assertDontSee('Terima tunai')
        ->assertDontSee('Uang sudah masuk');
    $this->get('/pemilik/lapangan')->assertOk()->assertSee('Keluar')->assertSee('Aturan pemesanan');
    $this->get('/pemilik?periode=tahun-ini')->assertSessionHasErrors('periode');
});
