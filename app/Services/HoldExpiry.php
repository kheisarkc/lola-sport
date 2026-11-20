<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;

/**
 * KF-08: reservasi yang belum mengirim bukti DP sampai batas waktu
 * otomatis kedaluwarsa, lalu slotnya terbuka lagi.
 *
 * Dijalankan setiap kali jadwal dibaca atau diubah, jadi tidak perlu cron.
 */
class HoldExpiry
{
    public function __construct(private AuditLog $audit) {}

    public function expireStale(?int $courtId = null): void
    {
        $stale = Booking::query()
            ->where('status', BookingStatus::Pending)
            ->where('expires_at', '<=', now())
            ->when($courtId, fn ($query) => $query->where('court_id', $courtId))
            ->get();

        foreach ($stale as $booking) {
            // Syarat status diulang di UPDATE supaya aman bila pelanggan
            // mengirim bukti pada detik yang sama.
            $changed = Booking::query()
                ->whereKey($booking->id)
                ->where('status', BookingStatus::Pending)
                ->update(['status' => BookingStatus::Expired]);

            if ($changed === 1) {
                $this->audit->record($booking, null, 'expired');
            }
        }
    }
}
