<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// UC-03, UC-08, UC-09, KF-06, KF-12, KF-13, KNF-05

function sendProof($booking, int $amount, ?UploadedFile $file = null)
{
    return test()->post("/reservasi/{$booking->code}/bayar", [
        'amount' => $amount,
        'proof' => $file ?? proofFile(),
    ]);
}

it('stores the proof privately and counts nothing until a staff member approves it', function () {
    $booking = bookAsCustomer(); // total 260.000, DP 130.000

    sendProof($booking, 130000)->assertSessionHasNoErrors();

    $payment = Payment::query()->firstOrFail();
    Storage::disk('local')->assertExists($payment->proof_path);
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Review)
        ->and($booking->paidAmount())->toBe(0);

    $this->actingAs(staff())->post("/pegawai/pembayaran/{$payment->id}/terima")->assertSessionHasNoErrors();
    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->paidAmount())->toBe(130000)
        ->and($booking->paymentLabel())->toBe('DP diterima');
});

it('keeps a booking under review after the hold deadline', function () {
    $booking = bookAsCustomer();
    sendProof($booking, 130000);
    $this->travel(2)->hours();

    $this->get('/?tanggal=2026-11-12');
    expect($booking->refresh()->status)->toBe(BookingStatus::Review);
});

it('enforces minimum DP, exact settlement and no overpayment', function () {
    $booking = bookAsCustomer();

    sendProof($booking, 129999)->assertSessionHasErrors('aturan');
    sendProof($booking, 260001)->assertSessionHasErrors('aturan');
    sendProof($booking, 130000)->assertSessionHasNoErrors();
    sendProof($booking, 130000)->assertSessionHasErrors('aturan'); // masih ada yang menunggu

    $this->actingAs(staff())->post('/pegawai/pembayaran/'.Payment::query()->first()->id.'/terima');
    auth()->logout();

    sendProof($booking, 100000)->assertSessionHasErrors('aturan');
    sendProof($booking, 130000)->assertSessionHasNoErrors();
});

it('accepts full payment at once', function () {
    $booking = bookAsCustomer();
    sendProof($booking, 260000);
    $this->actingAs(staff())->post('/pegawai/pembayaran/'.Payment::query()->first()->id.'/terima');

    expect($booking->refresh()->paymentLabel())->toBe('Lunas');
});

it('validates the proof file type and size', function () {
    $booking = bookAsCustomer();

    sendProof($booking, 130000, UploadedFile::fake()->create('jahat.html', 1, 'text/html'))->assertSessionHasErrors('proof');
    sendProof($booking, 130000, UploadedFile::fake()->create('besar.pdf', 2100, 'application/pdf'))->assertSessionHasErrors('proof');
    sendProof($booking, 130000, UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'))->assertSessionHasNoErrors();
});

it('cannot approve the same proof twice', function () {
    $booking = bookAsCustomer();
    sendProof($booking, 130000);
    $payment = Payment::query()->firstOrFail();

    $this->actingAs(staff());
    $this->post("/pegawai/pembayaran/{$payment->id}/terima")->assertSessionHasNoErrors();
    $this->post("/pegawai/pembayaran/{$payment->id}/terima")->assertSessionHasErrors('aturan');
    expect($booking->paidAmount())->toBe(130000);
});

it('requires a reason to reject and cancels the booking when the first DP proof is rejected', function () {
    $booking = bookAsCustomer();
    sendProof($booking, 130000);
    $payment = Payment::query()->firstOrFail();
    $this->travel(20)->minutes();

    $this->actingAs(staff());
    $this->post("/pegawai/pembayaran/{$payment->id}/tolak", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post("/pegawai/pembayaran/{$payment->id}/tolak", ['reason' => 'Nominal tidak ada di mutasi'])->assertSessionHasNoErrors();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->note)->toBe('Bukti DP ditolak: Nominal tidak ada di mutasi');
});

it('keeps a confirmed booking confirmed when a settlement proof is rejected', function () {
    $booking = bookAsCustomer();
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);
    auth()->logout();
    sendProof($booking, 130000);

    $settlement = Payment::query()->where('status', PaymentStatus::Pending)->firstOrFail();
    $this->actingAs(staff())->post("/pegawai/pembayaran/{$settlement->id}/tolak", ['reason' => 'Bukti buram']);

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->paidAmount())->toBe(130000);
});

it('records cash immediately as received money', function () {
    $booking = bookAsCustomer();
    $this->actingAs(staff());

    $this->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 50000])->assertSessionHasErrors('aturan');
    $this->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 260000])->assertSessionHasNoErrors();
    $this->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 1])->assertSessionHasErrors('aturan');

    $cash = Payment::query()->firstOrFail();
    expect($cash->status)->toBe(PaymentStatus::Approved)
        ->and($cash->reviewed_at)->not->toBeNull()
        ->and($booking->refresh()->paymentLabel())->toBe('Lunas');
});

it('shows proof files only to staff', function () {
    $booking = bookAsCustomer();
    sendProof($booking, 130000);
    $payment = Payment::query()->firstOrFail();

    $this->get("/pegawai/pembayaran/{$payment->id}/bukti")->assertRedirect('/masuk');
    $this->actingAs(owner())->get("/pegawai/pembayaran/{$payment->id}/bukti")->assertForbidden();
    $this->actingAs(staff())->get("/pegawai/pembayaran/{$payment->id}/bukti")->assertOk();
});

it('refuses payments for expired reservations', function () {
    $booking = bookAsCustomer();
    $this->travel(30)->minutes();

    sendProof($booking, 130000)->assertSessionHasErrors('aturan');
    expect($booking->refresh()->status)->toBe(BookingStatus::Expired);
});
