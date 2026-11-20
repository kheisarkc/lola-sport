<?php

namespace App\Enums;

enum BookingSource: string
{
    case Website = 'website'; // dipesan pelanggan lewat web (UC-02)
    case Staff = 'staff';     // dicatat pegawai dari WA atau walk-in (UC-07)
    case Block = 'block';     // slot ditutup pegawai untuk perawatan/acara (UC-13)

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Staff => 'Dicatat pegawai',
            self::Block => 'Slot ditutup',
        };
    }
}
