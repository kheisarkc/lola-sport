<?php

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Setting;

// UC-02, UC-04, KF-03 sampai KF-08, KF-26

it('creates a reservation without an account and shows the booking code', function () {
    $response = $this->post('/pesan', reservationInput());

    $booking = Booking::query()->firstOrFail();
    $response->assertRedirect('/reservasi/'.$booking->code);
    expect($booking->code)->toMatch('/^LS-[A-Z0-9]{6}$/')
        ->and($booking->source)->toBe(BookingSource::Website)
        ->and($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->customer_phone)->toBe('081234567890')
        ->and($booking->total)->toBe(260000)
        ->and($booking->minimum_dp)->toBe(130000)
        ->and($booking->expires_at->format('H:i'))->toBe('10:30');

    $this->get('/reservasi/'.$booking->code)
        ->assertOk()
        ->assertSee($booking->code)
        ->assertSee('Menunggu pembayaran')
        ->assertSee('Rp130.000');
});

it('validates name, WhatsApp number and start hour', function () {
    $this->post('/pesan', reservationInput(['customer_phone' => '12345']))->assertSessionHasErrors('customer_phone');
    $this->post('/pesan', reservationInput(['customer_name' => '']))->assertSessionHasErrors('customer_name');
    $this->post('/pesan', reservationInput(['hour' => 6]))->assertSessionHasErrors('hour');
    $this->post('/pesan', reservationInput(['hour' => 1]))->assertSessionHasErrors('hour');
    expect(Booking::query()->count())->toBe(0);
});

it('rejects overlapping slots but allows adjacent ones', function () {
    bookAsCustomer();

    $this->post('/pesan', reservationInput(['hour' => 20, 'duration' => 1]))->assertSessionHasErrors('aturan');
    $this->post('/pesan', reservationInput(['hour' => 18, 'duration' => 2]))->assertSessionHasErrors('aturan');
    $this->post('/pesan', reservationInput(['hour' => 21, 'duration' => 1]))->assertSessionHasNoErrors();
    $this->post('/pesan', reservationInput(['hour' => 17, 'duration' => 2]))->assertSessionHasNoErrors();
    expect(Booking::query()->count())->toBe(3);
});

it('allows playing until 01.00 and stores it on the starting operating date', function () {
    $booking = bookAsCustomer(['hour' => 23, 'duration' => 2]);

    expect($booking->play_date->format('Y-m-d'))->toBe('2026-11-12')
        ->and($booking->starts_at->format('Y-m-d H:i'))->toBe('2026-11-12 23:00')
        ->and($booking->ends_at->format('Y-m-d H:i'))->toBe('2026-11-13 01:00');

    $midnight = bookAsCustomer(['date' => '2026-11-13', 'hour' => 0, 'duration' => 1]);
    expect($midnight->starts_at->format('Y-m-d H:i'))->toBe('2026-11-14 00:00');
});

it('rejects past times, closing-time overruns and dates beyond 30 days', function () {
    $this->post('/pesan', reservationInput(['date' => '2026-11-10', 'hour' => 9, 'duration' => 1]))->assertSessionHasErrors('aturan');
    $this->post('/pesan', reservationInput(['hour' => 0, 'duration' => 2]))->assertSessionHasErrors('aturan');
    $this->post('/pesan', reservationInput(['date' => '2026-12-11']))->assertSessionHasErrors('aturan');
    $this->post('/pesan', reservationInput(['date' => '2026-12-10']))->assertSessionHasNoErrors();
});

it('limits the hold to the start time', function () {
    $this->travelTo(now()->setTime(10, 45));
    $booking = bookAsCustomer(['date' => '2026-11-10', 'hour' => 11, 'duration' => 1]);

    expect($booking->expires_at->format('H:i'))->toBe('11:00');
});

it('uses DP percentage and hold minutes set by the owner for new bookings', function () {
    Setting::current()->update(['dp_percent' => 30, 'hold_minutes' => 60]);
    $booking = bookAsCustomer(['duration' => 1]);

    expect($booking->minimum_dp)->toBe(39000)
        ->and($booking->expires_at->format('H:i'))->toBe('11:00');
});

it('rounds the minimum DP up to whole rupiah', function () {
    court()->update(['hourly_rate' => 130001]);
    $booking = bookAsCustomer(['duration' => 1]);

    expect($booking->minimum_dp)->toBe(65001);
});

it('opens a reservation only with the matching code and WhatsApp number', function () {
    $booking = bookAsCustomer();
    $this->flushSession();

    $this->get('/reservasi/'.$booking->code)->assertRedirect('/cek');
    $this->post('/cek', ['code' => $booking->code, 'customer_phone' => '089999999999'])->assertSessionHasErrors('code');
    $this->get('/reservasi/'.$booking->code)->assertRedirect('/cek');

    $short = strtolower(substr($booking->code, 3));
    $this->post('/cek', ['code' => $short, 'customer_phone' => '+62 812 3456 7890'])->assertRedirect('/reservasi/'.$booking->code);
    $this->get('/reservasi/'.$booking->code)->assertOk()->assertSee('Raka Fiktif');
});

it('does not open booking codes of closed slots', function () {
    $this->actingAs(staff())->post('/pegawai/tutup-slot', [
        'court_id' => court()->id, 'date' => '2026-11-12', 'hour' => 8, 'duration' => 1, 'reason' => 'Perawatan',
    ]);
    $block = Booking::query()->firstOrFail();
    auth()->logout();

    $this->get('/reservasi/'.$block->code)->assertRedirect('/cek');
});

it('refuses reservations on inactive courts', function () {
    court()->update(['active' => false]);

    $this->post('/pesan', reservationInput())->assertSessionHasErrors('aturan');
});

// Alur tiga langkah: pilih beberapa jam di jadwal, lalu pilih DP atau lunas.
it('opens the booking form for the hours chosen on the schedule', function () {
    $this->get('/pesan?lapangan='.court()->id.'&tanggal=2026-11-12&jam=19&durasi=2')
        ->assertOk()
        ->assertSee('19.00–21.00')
        ->assertSee('Rp260.000')
        ->assertSee('Bayar DP 50%')
        ->assertSee('Rp130.000')
        ->assertSee('Bayar lunas');
});

it('shows the calendar date of play for hours after midnight', function () {
    // Tanggal operasional Kamis 12 November; jam 00.00 jatuh pada Jumat 13 November.
    $this->get('/?tanggal=2026-11-12')
        ->assertOk()
        ->assertSee('Pilih '.court()->name.' jam 00.00, Jumat, 13 November 2026')
        ->assertSee('Jum, 13 Nov');

    $this->get('/pesan?lapangan='.court()->id.'&tanggal=2026-11-12&jam=0&durasi=1')
        ->assertOk()
        ->assertSee('Jumat, 13 November 2026')
        ->assertDontSee('Kamis, 12 November 2026');

    $this->post('/pesan', reservationInput(['hour' => 0, 'duration' => 1]))->assertRedirect();
    $booking = Booking::query()->latest('id')->firstOrFail();

    $this->get('/reservasi/'.$booking->code)
        ->assertOk()
        ->assertSee('Jumat, 13 November 2026')
        ->assertDontSee('Kamis, 12 November 2026');
});

it('sends the customer back to the schedule when a chosen hour is no longer free', function () {
    bookAsCustomer(['hour' => 20, 'duration' => 1]);

    $this->get('/pesan?lapangan='.court()->id.'&tanggal=2026-11-12&jam=19&durasi=2')
        ->assertRedirect('/?tanggal=2026-11-12')
        ->assertSessionHasErrors('aturan');
});

it('carries the DP or full choice from step 2 into step 3 without asking again', function () {
    $this->post('/pesan', reservationInput(['pembayaran' => 'lunas']))->assertRedirect();
    $booking = Booking::query()->latest('id')->firstOrFail();

    $this->get('/reservasi/'.$booking->code)
        ->assertOk()
        ->assertSee('Menunggu pembayaran')
        ->assertDontSee('Menunggu DP')
        ->assertSeeInOrder(['Bayar lunas', 'Rp260.000'])
        ->assertSee('name="amount" value="260000"', false)
        ->assertDontSee('Mau bayar DP atau lunas?')
        ->assertDontSee('tunai');

    $this->post('/pesan', reservationInput(['hour' => 22, 'duration' => 1]))->assertRedirect();
    $dp = Booking::query()->latest('id')->firstOrFail();

    $this->get('/reservasi/'.$dp->code)
        ->assertSeeInOrder(['Bayar DP', 'Rp65.000'])
        ->assertSee('name="amount" value="65000"', false)
        ->assertDontSee('Mau bayar DP atau lunas?');
});

it('lets the customer switch between DP and full payment on step 3', function () {
    $booking = bookAsCustomer();

    $this->get('/reservasi/'.$booking->code.'?bayar=lunas')->assertRedirect('/reservasi/'.$booking->code);
    $this->get('/reservasi/'.$booking->code)->assertSee('name="amount" value="260000"', false);

    $this->get('/reservasi/'.$booking->code.'?bayar=dp')->assertRedirect('/reservasi/'.$booking->code);
    $this->get('/reservasi/'.$booking->code)->assertSee('name="amount" value="130000"', false);
});

it('asks for DP or full payment when the reservation is opened from another device', function () {
    $booking = bookAsCustomer(['pembayaran' => 'lunas']);
    $this->flushSession();

    $this->post('/cek', ['code' => $booking->code, 'customer_phone' => '081234567890'])->assertRedirect();
    $this->get('/reservasi/'.$booking->code)
        ->assertOk()
        ->assertSee('Mau bayar DP atau lunas?')
        ->assertSee('value="130000" checked', false);
});

it('offers cash only for the remaining payment', function () {
    $booking = bookAsCustomer();
    $this->post('/reservasi/'.$booking->code.'/bayar', ['amount' => 130000, 'proof' => proofFile()]);
    $this->actingAs(staff())->post('/pegawai/pembayaran/'.$booking->payments()->firstOrFail()->id.'/terima');
    auth()->logout();

    $this->get('/reservasi/'.$booking->code)
        ->assertOk()
        ->assertSeeInOrder(['Pelunasan', 'Rp130.000'])
        ->assertSee('Sisa ini juga bisa dibayar tunai ke pegawai sebelum main.');
});

it('rejects an unknown payment choice', function () {
    $this->post('/pesan', reservationInput(['pembayaran' => 'cicil']))->assertSessionHasErrors('pembayaran');
});

it('lets the customer cancel an unpaid hold so the hour opens again', function () {
    $booking = bookAsCustomer();

    $this->post('/reservasi/'.$booking->code.'/batal')
        ->assertRedirect('/?tanggal=2026-11-12')
        ->assertSessionHas('success');
    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and(AuditEvent::query()->where('action', 'cancelled_by_customer')->count())->toBe(1);

    // Jam yang sama langsung bisa dipesan lagi tanpa menunggu batas waktu tahan.
    $this->post('/pesan', reservationInput())->assertRedirect();
    expect(Booking::query()->where('status', BookingStatus::Pending)->count())->toBe(1);
});

it('does not let the customer cancel after sending a proof or without access', function () {
    $booking = bookAsCustomer();
    $this->post('/reservasi/'.$booking->code.'/bayar', ['amount' => 130000, 'proof' => proofFile()]);

    $this->get('/reservasi/'.$booking->code)->assertDontSee('Batalkan pesanan');
    $this->post('/reservasi/'.$booking->code.'/batal')->assertSessionHasErrors('aturan');
    expect($booking->refresh()->status)->toBe(BookingStatus::Review);

    $other = bookAsCustomer(['hour' => 22, 'duration' => 1]);
    $this->flushSession();
    $this->post('/reservasi/'.$other->code.'/batal')->assertRedirect('/cek');
    expect($other->refresh()->status)->toBe(BookingStatus::Pending);
});

it('shows open reservations of this browser on the schedule so they can be resumed', function () {
    $booking = bookAsCustomer();

    $this->get('/')
        ->assertOk()
        ->assertSee('Pesanan kamu')
        ->assertSee($booking->code)
        ->assertSee('Lanjut bayar')
        ->assertSee('/reservasi/'.$booking->code, false);

    $this->post('/reservasi/'.$booking->code.'/batal');
    $this->get('/')->assertDontSee('Pesanan kamu');

    $kept = bookAsCustomer(['hour' => 22, 'duration' => 1]);
    $this->flushSession();
    $this->get('/')->assertDontSee('Pesanan kamu')->assertDontSee($kept->code);
});

it('tells the customer what to do next at every status and offers WhatsApp only to ask', function () {
    $booking = bookAsCustomer();
    $page = fn () => $this->get('/reservasi/'.$booking->code);

    $page()->assertSee('Bayar lewat QRIS lalu kirim bukti sebelum')->assertDontSee('Tanya lewat WhatsApp');

    $this->post('/reservasi/'.$booking->code.'/bayar', ['amount' => 130000, 'proof' => proofFile()]);
    $page()->assertSee('Penjaga sedang mengecek uang masuk')
        ->assertSee('Tanya lewat WhatsApp')
        ->assertSee('Bukti bayar tetap diunggah di halaman ini')
        ->assertSee('wa.me/'.config('lola.whatsapp'), false);

    $payment = $booking->payments()->firstOrFail();
    $this->actingAs(staff())->post("/pegawai/pembayaran/{$payment->id}/tolak", ['reason' => 'Uang belum masuk']);
    auth()->logout();
    $page()->assertSee('Bukti ditolak:')->assertSee('Uang belum masuk')->assertSee('Pesanan dibatalkan')->assertSee('Tanya lewat WhatsApp');

    $booking = bookAsCustomer(['hour' => 14]);
    $page = fn () => $this->get('/reservasi/'.$booking->code);
    $this->post('/reservasi/'.$booking->code.'/bayar', ['amount' => 260000, 'proof' => proofFile()]);
    $this->actingAs(staff())->post('/pegawai/pembayaran/'.$booking->payments()->where('status', 'pending')->firstOrFail()->id.'/terima');
    auth()->logout();
    $page()->assertSee('Jadwal kamu aman dan sudah lunas')->assertSee('Mau batal atau pindah jam?');
});
