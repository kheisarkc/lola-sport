<?php

namespace App\Enums;

/**
 * Status reservasi. Status pembayaran dihitung terpisah dari pembayaran yang diterima.
 */
enum BookingStatus: string
{
    case Pending = 'pending';       // menunggu bukti DP atau lunas, slot ditahan sementara
    case Review = 'review';         // bukti dikirim, menunggu pegawai
    case Confirmed = 'confirmed';   // DP atau pelunasan diterima, slot aman
    case CheckedIn = 'checked_in';  // pelanggan sudah datang dan main
    case NoShow = 'no_show';        // pelanggan tidak datang
    case Cancelled = 'cancelled';
    case Expired = 'expired';       // bukti bayar tidak dikirim sampai batas waktu

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Review => 'Menunggu verifikasi',
            self::Confirmed => 'Terkonfirmasi',
            self::CheckedIn => 'Hadir',
            self::NoShow => 'Tidak hadir',
            self::Cancelled => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
        };
    }

    /**
     * Status yang membuat slot tidak bisa dipesan orang lain.
     * Daftar ini sama dengan constraint bookings_no_overlap di database.
     *
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::Pending, self::Review, self::Confirmed, self::CheckedIn, self::NoShow];
    }

    /**
     * Status yang masih bisa menerima pembayaran atau dibatalkan.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Review, self::Confirmed];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }
}
