<?php

namespace App\Enums;

enum UserRole: string
{
    case Staff = 'staff';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Pegawai',
            self::Owner => 'Pemilik',
        };
    }
}
