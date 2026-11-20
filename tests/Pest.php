<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
 * Semua tes Feature memakai database lola_demo_test yang dikosongkan tiap tes.
 * Waktu dibekukan pada Selasa 10 November 2026 10.00 WIB supaya hasilnya tidak
 * bergantung pada jam saat tes dijalankan.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00:00'));
        $this->seed();
        Storage::fake('local');
    })
    ->in('Feature');

function staff(): User
{
    return User::query()->where('email', 'pegawai@lola.test')->firstOrFail();
}

function owner(): User
{
    return User::query()->where('email', 'pemilik@lola.test')->firstOrFail();
}

/** Futsal Sintetis Rp130.000/jam dari seeder. */
function court(): Court
{
    return Court::query()->where('name', 'Futsal Sintetis')->firstOrFail();
}

/**
 * Data form reservasi default: Kamis 12 Nov 2026, 19.00-21.00 (2 jam = Rp260.000).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function reservationInput(array $overrides = []): array
{
    return [
        'court_id' => court()->id,
        'date' => '2026-11-12',
        'hour' => 19,
        'duration' => 2,
        'customer_name' => 'Raka Fiktif',
        'customer_phone' => '0812-3456-7890',
        ...$overrides,
    ];
}

/**
 * Pelanggan memesan lewat web, lalu mengembalikan reservasinya.
 *
 * @param  array<string, mixed>  $overrides
 */
function bookAsCustomer(array $overrides = []): Booking
{
    test()->post('/pesan', reservationInput($overrides))->assertRedirect();

    return Booking::query()->latest('id')->firstOrFail();
}

function proofFile(): UploadedFile
{
    return UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf');
}
