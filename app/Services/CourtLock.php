<?php

namespace App\Services;

use App\Models\Court;
use App\Support\RuleViolation;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Semua perubahan jadwal/pembayaran pada satu lapangan dijalankan bergiliran.
 *
 * Baris lapangan dikunci (SELECT ... FOR UPDATE), jadi dua pegawai/pelanggan
 * yang menekan tombol bersamaan tidak bisa membuat data dobel.
 */
class CourtLock
{
    public function __construct(private HoldExpiry $holds) {}

    /**
     * @template T
     *
     * @param  Closure(Court): T  $work
     * @return T
     */
    public function run(int $courtId, Closure $work): mixed
    {
        // Disimpan dulu di luar transaksi, supaya status kedaluwarsa tetap tersimpan
        // walaupun aksi berikutnya ditolak dan transaksinya dibatalkan.
        $this->holds->expireStale($courtId);

        try {
            return DB::transaction(function () use ($courtId, $work) {
                $court = Court::query()->lockForUpdate()->findOrFail($courtId);
                // Diulang setelah lapangan terkunci untuk hold yang habis tepat di detik ini.
                $this->holds->expireStale($court->id);

                return $work($court);
            });
        } catch (QueryException $exception) {
            // 23P01 = constraint bookings_no_overlap menolak jadwal bentrok.
            if ($exception->getCode() === '23P01') {
                throw RuleViolation::because('Jadwal itu baru saja terisi. Pilih jam lain.');
            }
            throw $exception;
        }
    }
}
