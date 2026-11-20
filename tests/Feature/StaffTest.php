<?php

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Payment;

// UC-05, UC-06, UC-07, UC-10, UC-12, UC-13, KNF-02

it('shows the demo accounts on the login page', function () {
    $this->get('/masuk')
        ->assertOk()
        ->assertSee('Akun demo untuk mencoba')
        ->assertSeeInOrder(['Pegawai', 'pegawai@lola.test', 'Pemilik', 'pemilik@lola.test', 'Kata sandi', 'DemoLola2026!']);
});

it('logs staff and owner in to their own home page', function () {
    $this->post('/masuk', ['email' => 'pegawai@lola.test', 'password' => 'salah'])->assertSessionHasErrors('email');
    $this->post('/masuk', ['email' => 'PEGAWAI@lola.test', 'password' => 'DemoLola2026!'])->assertRedirect('/pegawai');
    $this->post('/keluar')->assertRedirect('/masuk');
    $this->post('/masuk', ['email' => 'pemilik@lola.test', 'password' => 'DemoLola2026!'])->assertRedirect('/pemilik');
});

it('keeps staff and owner pages behind the right role', function () {
    $this->get('/pegawai')->assertRedirect('/masuk');
    $this->get('/pemilik')->assertRedirect('/masuk');

    $this->actingAs(staff())->get('/pegawai')->assertOk();
    $this->get('/pemilik')->assertForbidden();
    $this->get('/pemilik/lapangan')->assertForbidden();

    $this->actingAs(owner())->get('/pemilik')->assertOk();
    $this->get('/pegawai')->assertForbidden();
    $this->get('/pegawai/verifikasi')->assertForbidden();
});

it('opens a booking by its code and returns 404 for unknown codes or ids', function () {
    $booking = bookAsCustomer();

    $this->actingAs(staff())->get("/pegawai/reservasi/{$booking->code}")->assertOk()->assertSee($booking->customer_name);
    $this->get('/pegawai/reservasi/LS-TIDAKADA')->assertNotFound();
    $this->get('/pegawai/pembayaran/bukan-angka/bukti')->assertNotFound();
});

it('lists bookings of the operating day with customer details for staff', function () {
    bookAsCustomer();
    bookAsCustomer(['hour' => 23, 'duration' => 2, 'customer_name' => 'Tim Malam']);

    $this->actingAs(staff())->get('/pegawai?tanggal=2026-11-12')
        ->assertOk()
        ->assertSee('Raka Fiktif')
        ->assertSee('081234567890')
        ->assertSee('Tim Malam')
        ->assertSee('23.00–01.00');
});

it('records walk-in reservations only together with the cash received', function () {
    $this->actingAs(staff())->post('/pegawai/reservasi', reservationInput(['customer_name' => 'Walk In']))
        ->assertSessionHasErrors('pembayaran');
    expect(Booking::query()->count())->toBe(0);

    $this->post('/pegawai/reservasi', reservationInput(['customer_name' => 'Walk In', 'pembayaran' => 'dp', 'cara' => 'tunai']))
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->firstOrFail();
    expect($booking->source)->toBe(BookingSource::Staff)
        ->and($booking->created_by)->toBe(staff()->id)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->paidAmount())->toBe(130000);

    // Pesanan dari pegawai sudah dibayar, jadi tidak ikut kedaluwarsa.
    $this->travel(2)->hours();
    $this->get('/pegawai')->assertOk();
    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('records a fully paid walk-in reservation paid with QRIS at the counter', function () {
    $this->actingAs(staff())->post('/pegawai/reservasi', reservationInput(['pembayaran' => 'lunas']))
        ->assertSessionHasErrors('cara');

    $this->post('/pegawai/reservasi', reservationInput(['pembayaran' => 'lunas', 'cara' => 'qris']))
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->firstOrFail();
    $payment = $booking->payments()->firstOrFail();
    expect($booking->paymentLabel())->toBe('Lunas')
        ->and($payment->method)->toBe(PaymentMethod::Qris)
        ->and($payment->status)->toBe(PaymentStatus::Approved)
        ->and($payment->proof_path)->toBeNull();
    $this->get("/pegawai/reservasi/{$booking->code}")->assertSee('QRIS di tempat dicatat');
});

it('records the remaining payment at the counter in cash or QRIS', function () {
    $booking = bookAsCustomer();
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);
    $this->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000, 'cara' => 'qris'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'QRIS Rp130.000 dicatat.');

    expect($booking->payments()->pluck('method')->map->value->all())->toBe(['cash', 'qris']);
});

it('shows the new walk-in form with tappable free hours', function () {
    bookAsCustomer(['hour' => 19, 'duration' => 1]);

    $this->actingAs(staff())->get('/pegawai/reservasi/baru?tanggal=2026-11-12&lapangan='.court()->id)
        ->assertOk()
        ->assertSee('Ketuk jam main')
        ->assertSee('data-hour="18"', false)
        ->assertDontSee('data-hour="19"', false)
        ->assertSee('Terisi');
});

it('finds bookings by code, name, or phone number', function () {
    $booking = bookAsCustomer(['customer_name' => 'Budi Cari']);
    bookAsCustomer(['hour' => 21, 'duration' => 1, 'customer_name' => 'Orang Lain', 'customer_phone' => '085700001111']);

    $this->actingAs(staff());
    $this->get('/pegawai?cari='.strtolower($booking->code))->assertSee('Budi Cari')->assertDontSee('Orang Lain');
    $this->get('/pegawai?cari=budi')->assertSee('Budi Cari')->assertDontSee('Orang Lain');
    $this->get('/pegawai?cari=0857-0000')->assertSee('Orang Lain')->assertDontSee('Budi Cari');
    $this->get('/pegawai?cari=tidakada')->assertSee('Tidak ada pesanan yang cocok.');
});

it('lists every waiting proof on one page and groups bookings on the day screen', function () {
    $first = bookAsCustomer(['customer_name' => 'Pertama']);
    $second = bookAsCustomer(['hour' => 21, 'duration' => 1, 'customer_name' => 'Kedua']);
    $this->post("/reservasi/{$first->code}/bayar", ['amount' => 130000, 'proof' => proofFile()]);
    $this->post("/reservasi/{$second->code}/bayar", ['amount' => 65000, 'proof' => proofFile()]);

    $this->actingAs(staff())->get('/pegawai/verifikasi')
        ->assertSeeInOrder(['Pertama', 'Kedua'])
        ->assertSee('Uang sudah masuk');
    $this->get('/pegawai?tanggal=2026-11-12')
        ->assertSee('2 bukti bayar perlu dicek')
        ->assertSee('Perlu cek bukti bayar');

    $payment = $first->payments()->firstOrFail();
    $this->post("/pegawai/pembayaran/{$payment->id}/terima")->assertRedirect('/pegawai/verifikasi');
    $this->get('/pegawai/verifikasi')->assertDontSee($first->code)->assertSee($second->code);
    $this->get('/pegawai?tanggal=2026-11-12')->assertSee('Belum lunas')->assertSee('Terima tunai Rp130.000');
});

it('offers a date strip and a calendar to pick any date', function () {
    $this->actingAs(staff())->get('/pegawai')
        ->assertSee('Hari ini')
        ->assertSee('Besok')
        ->assertSee('type="date"', false)
        ->assertSee('tanggal=2026-11-16', false);
    $this->get('/pegawai?tanggal=2026-12-01')->assertOk()->assertSee('1 Des');
});

it('reminds staff to mark bookings whose time has passed', function () {
    $booking = bookAsCustomer(['date' => '2026-11-10', 'hour' => 12, 'duration' => 1]);
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);
    $this->travelTo(now()->setTime(14, 0));

    $this->get('/pegawai')->assertSee('Lewat jam, belum ditandai')->assertSee('Tandai tidak datang');
});

it('shows one main action on each booking card', function () {
    $dp = bookAsCustomer(['customer_name' => 'Masih DP']);
    $this->actingAs(staff())->post("/pegawai/reservasi/{$dp->code}/tunai", ['amount' => 130000]);

    $this->get('/pegawai?tanggal=2026-11-12')
        ->assertSee('Terima tunai Rp130.000')
        ->assertSee('+ Pesanan baru');
});

it('cancels before start, rejects pending proofs and keeps received DP', function () {
    $booking = bookAsCustomer();
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);
    auth()->logout();
    $this->post("/reservasi/{$booking->code}/bayar", ['amount' => 130000, 'method' => 'qris', 'proof' => proofFile()]);

    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/batal", ['reason' => 'Minta batal lewat WA'])
        ->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->paidAmount())->toBe(130000)
        ->and(Payment::query()->where('status', PaymentStatus::Pending)->count())->toBe(0);

    // Slot terbuka lagi untuk orang lain.
    auth()->logout();
    $this->post('/pesan', reservationInput())->assertSessionHasNoErrors();
});

it('refuses cancellation after the start time', function () {
    $booking = bookAsCustomer(['date' => '2026-11-10', 'hour' => 11, 'duration' => 1]);
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);
    $this->travelTo(now()->setTime(11, 0));

    $this->post("/pegawai/reservasi/{$booking->code}/batal")->assertSessionHasErrors('aturan');
});

it('allows check-in only when fully paid and keeps the end time when late', function () {
    $booking = bookAsCustomer(['date' => '2026-11-10', 'hour' => 19, 'duration' => 2]);
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);

    $this->travelTo(now()->setTime(19, 20)); // telat 20 menit
    $this->post("/pegawai/reservasi/{$booking->code}/hadir")->assertSessionHasErrors('aturan');

    $this->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);
    $this->post("/pegawai/reservasi/{$booking->code}/hadir")->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(BookingStatus::CheckedIn)
        ->and($booking->checked_in_at->format('H:i'))->toBe('19:20')
        ->and($booking->ends_at->format('H:i'))->toBe('21:00');
});

it('allows check-in at most 60 minutes early', function () {
    $booking = bookAsCustomer(['date' => '2026-11-10', 'hour' => 19, 'duration' => 1]);
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 130000]);

    $this->travelTo(now()->setTime(17, 59));
    $this->post("/pegawai/reservasi/{$booking->code}/hadir")->assertSessionHasErrors('aturan');
    $this->travelTo(now()->setTime(18, 0));
    $this->post("/pegawai/reservasi/{$booking->code}/hadir")->assertSessionHasNoErrors();
});

it('marks no-show only after the start time', function () {
    $booking = bookAsCustomer(['date' => '2026-11-10', 'hour' => 19, 'duration' => 1]);
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 65000]);

    $this->post("/pegawai/reservasi/{$booking->code}/tidak-hadir")->assertSessionHasErrors('aturan');
    $this->travelTo(now()->setTime(19, 15));
    $this->post("/pegawai/reservasi/{$booking->code}/tidak-hadir")->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(BookingStatus::NoShow);
});

it('closes and reopens a slot', function () {
    $this->actingAs(staff());
    $input = ['court_id' => court()->id, 'date' => '2026-11-12', 'hour' => 8, 'duration' => 2, 'reason' => 'Perawatan lantai'];

    $this->post('/pegawai/tutup-slot', $input)->assertSessionHasNoErrors();
    $this->post('/pegawai/tutup-slot', $input)->assertSessionHasErrors('aturan');
    $block = Booking::query()->firstOrFail();
    expect($block->source)->toBe(BookingSource::Block)->and($block->total)->toBe(0);

    auth()->logout();
    $this->post('/pesan', reservationInput(['hour' => 9, 'duration' => 1]))->assertSessionHasErrors('aturan');

    $this->actingAs(staff())->post("/pegawai/reservasi/{$block->code}/batal")->assertSessionHasNoErrors();
    auth()->logout();
    $this->post('/pesan', reservationInput(['hour' => 9, 'duration' => 1]))->assertSessionHasNoErrors();
});

it('writes an audit trail with actor names', function () {
    $booking = bookAsCustomer();
    $this->actingAs(staff())->post("/pegawai/reservasi/{$booking->code}/tunai", ['amount' => 260000]);

    expect(AuditEvent::query()->where('booking_id', $booking->id)->pluck('action')->all())
        ->toBe(['created', 'cash_recorded']);
    $this->get("/pegawai/reservasi/{$booking->code}")->assertOk()->assertSee('Tunai dicatat')->assertSee('Pegawai Demo');
});
