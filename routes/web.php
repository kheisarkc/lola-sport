<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Owner\CourtController;
use App\Http\Controllers\Owner\RevenueController;
use App\Http\Controllers\Owner\SettingController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\Staff\BlockController;
use App\Http\Controllers\Staff\BookingController;
use App\Http\Controllers\Staff\PaymentController;
use App\Http\Controllers\Staff\ScheduleController as StaffScheduleController;
use Illuminate\Support\Facades\Route;

// Booking dicari lewat kode LS-XXXXXX (Booking::getRouteKeyName); id numerik lain dibatasi agar input asal jadi 404.
Route::pattern('payment', '[0-9]+');
Route::pattern('court', '[0-9]+');

/*
| Pelanggan: tanpa akun (KF-01 sampai KF-06)
*/
Route::get('/', [ScheduleController::class, 'index'])->name('schedule');                      // UC-01
Route::get('/pesan', [ReservationController::class, 'create'])->name('reservations.create');   // UC-02
Route::post('/pesan', [ReservationController::class, 'store'])->name('reservations.store');
Route::get('/cek', [ReservationController::class, 'lookupForm'])->name('reservations.lookup'); // UC-04
Route::post('/cek', [ReservationController::class, 'lookup'])->middleware('throttle:lookup');
Route::get('/reservasi/{booking}', [ReservationController::class, 'show'])->name('reservations.show');
Route::post('/reservasi/{booking}/bayar', [ReservationController::class, 'pay'])->name('reservations.pay'); // UC-03
Route::post('/reservasi/{booking}/batal', [ReservationController::class, 'cancel'])->name('reservations.cancel');

/*
| Login pegawai dan pemilik (UC-05)
*/
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'show'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:login');
});
Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
| Pegawai
*/
Route::prefix('pegawai')->name('staff.')->middleware(['auth', 'can:staff'])->group(function () {
    Route::get('/', [StaffScheduleController::class, 'index'])->name('schedule');                       // UC-06
    Route::get('/reservasi/baru', [BookingController::class, 'create'])->name('bookings.create');       // UC-07
    Route::post('/reservasi', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/reservasi/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/reservasi/{booking}/batal', [BookingController::class, 'cancel'])->name('bookings.cancel');       // UC-10
    Route::post('/reservasi/{booking}/hadir', [BookingController::class, 'checkIn'])->name('bookings.check-in');    // UC-12
    Route::post('/reservasi/{booking}/tidak-hadir', [BookingController::class, 'noShow'])->name('bookings.no-show');
    Route::post('/reservasi/{booking}/tunai', [PaymentController::class, 'cash'])->name('payments.cash');           // UC-09

    Route::get('/verifikasi', [PaymentController::class, 'index'])->name('payments.index');                         // UC-08
    Route::get('/pembayaran/{payment}/bukti', [PaymentController::class, 'proof'])->name('payments.proof');
    Route::post('/pembayaran/{payment}/terima', [PaymentController::class, 'approve'])->name('payments.approve');
    Route::post('/pembayaran/{payment}/tolak', [PaymentController::class, 'reject'])->name('payments.reject');

    Route::view('/lainnya', 'staff.more')->name('more');

    Route::get('/tutup-slot', [BlockController::class, 'create'])->name('blocks.create');                          // UC-13
    Route::post('/tutup-slot', [BlockController::class, 'store'])->name('blocks.store');
});

/*
| Pemilik
*/
Route::prefix('pemilik')->name('owner.')->middleware(['auth', 'can:owner'])->group(function () {
    Route::get('/', [RevenueController::class, 'index'])->name('revenue');                 // UC-14
    Route::get('/transaksi', [RevenueController::class, 'transactions'])->name('transactions');   // UC-15
    Route::get('/transaksi/unduh', [RevenueController::class, 'export'])->name('transactions.export');
    Route::get('/lapangan', [CourtController::class, 'index'])->name('courts.index');      // UC-16
    Route::post('/lapangan', [CourtController::class, 'store'])->name('courts.store');
    Route::put('/lapangan/{court}', [CourtController::class, 'update'])->name('courts.update');
    Route::put('/pengaturan', [SettingController::class, 'update'])->name('settings.update');
});
