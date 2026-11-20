<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Court;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data awal demo. Akun memakai nama fiktif.
 * Sumber harga dicatat di docs/PROJECT.md.
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'DemoLola2026!';

    public function run(): void
    {
        User::query()->firstOrCreate(['email' => 'pegawai@lola.test'], [
            'name' => 'Pegawai Demo',
            'password' => self::DEMO_PASSWORD,
            'role' => UserRole::Staff,
        ]);
        User::query()->firstOrCreate(['email' => 'pemilik@lola.test'], [
            'name' => 'Pemilik Demo',
            'password' => self::DEMO_PASSWORD,
            'role' => UserRole::Owner,
        ]);

        Setting::current();

        // Lapangan hanya diisi saat masih kosong, supaya perubahan pemilik tidak tertimpa.
        if (Court::query()->exists()) {
            return;
        }
        Court::create(['name' => 'Futsal Sintetis', 'sport' => 'futsal', 'hourly_rate' => 130000]); // WA K Pasya 11 Okt 2026
        Court::create(['name' => 'Futsal Vinil', 'sport' => 'futsal', 'hourly_rate' => 160000]);    // WA K Pasya 11 Okt 2026
        Court::create(['name' => 'Badminton 1', 'sport' => 'badminton', 'hourly_rate' => 35000]);   // WA owner K Ica, 8 Nov 2026
        Court::create(['name' => 'Badminton 2', 'sport' => 'badminton', 'hourly_rate' => 35000]);   // wawancara: 2 lapangan badminton
    }
}
