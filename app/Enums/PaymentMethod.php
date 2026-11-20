<?php

namespace App\Enums;

/** Sesuai WA owner (K Ica, 8 Nov 2026): pembayaran lewat QRIS atau tunai di tempat. */
enum PaymentMethod: string
{
    case Qris = 'qris';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            self::Cash => 'Tunai',
        };
    }
}
