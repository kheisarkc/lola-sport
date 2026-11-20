<?php

use App\Enums\BookingStatus;
use App\Models\Booking;

// UC-01, KF-01, KF-02, KNF-03, KNF-06

it('shows every slot from 07.00 until 00.00 with prices without login', function () {
    $this->get('/?tanggal=2026-11-12')
        ->assertOk()
        ->assertSee('Futsal Sintetis')
        ->assertSee('Rp130.000/jam')
        ->assertSee('Rp160.000/jam')
        ->assertSee('Pilih Futsal Sintetis jam 07.00')
        ->assertSee('Pilih Futsal Sintetis jam 00.00')
        ->assertDontSee('Pilih Futsal Sintetis jam 06.00')
        ->assertDontSee('Pilih Futsal Sintetis jam 01.00');
});

it('marks booked, blocked and past slots and hides customer identity', function () {
    bookAsCustomer(['date' => '2026-11-10', 'hour' => 12, 'duration' => 1]);
    $this->actingAs(staff())->post('/pegawai/tutup-slot', [
        'court_id' => court()->id, 'date' => '2026-11-10', 'hour' => 14, 'duration' => 1, 'reason' => 'Perbaikan jaring',
    ]);
    auth()->logout();

    $this->get('/')
        ->assertOk()
        ->assertDontSee('Pilih Futsal Sintetis jam 09.00')  // sudah lewat (sekarang 10.00)
        ->assertDontSee('Pilih Futsal Sintetis jam 12.00')  // terisi
        ->assertDontSee('Pilih Futsal Sintetis jam 14.00')  // ditutup
        ->assertSee('Pilih Futsal Sintetis jam 13.00')
        ->assertSee('Ditutup')
        ->assertDontSee('Raka Fiktif')
        ->assertDontSee('0812')
        ->assertDontSee('Perbaikan jaring');
});

it('falls back to today for dates outside the next 30 days', function () {
    $this->get('/?tanggal=2026-12-11')->assertOk()->assertSee('Selasa, 10 November 2026');
    $this->get('/?tanggal=2026-12-10')->assertOk()->assertSee('Kamis, 10 Desember 2026');
});

it('treats 00.30 as still part of the previous operating day', function () {
    $this->travelTo(now()->setDate(2026, 11, 11)->setTime(0, 30));

    $this->get('/')->assertOk()->assertSee('Selasa, 10 November 2026');
});

it('expires stale holds when the schedule is opened', function () {
    $booking = bookAsCustomer();
    $this->travel(31)->minutes();

    $this->get('/?tanggal=2026-11-12')->assertSee('Pilih Futsal Sintetis jam 19.00');
    expect($booking->refresh()->status)->toBe(BookingStatus::Expired)
        ->and(Booking::query()->count())->toBe(1);
});
