<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// KF-07: database tetap menolak jadwal bentrok walaupun aturan di aplikasi terlewati.

it('blocks overlapping active bookings at the database level', function () {
    $booking = bookAsCustomer();
    $copy = $booking->replicate();
    $copy->code = 'LS-COPY01';

    expect(fn () => DB::transaction(fn () => $copy->save()))->toThrow(QueryException::class);
});

it('rejects non-positive payments at the database level', function () {
    $booking = bookAsCustomer();

    expect(fn () => DB::transaction(fn () => $booking->payments()->create([
        'amount' => 0, 'method' => 'cash', 'status' => 'approved',
    ])))->toThrow(QueryException::class);
});

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});
