<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Samakan format nomor WhatsApp: "+62 812-3456" dan "0812 3456" jadi "08123456".
     */
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';

        return str_starts_with($digits, '62') ? '0'.substr($digits, 2) : $digits;
    }

    public static function isValid(string $normalized): bool
    {
        return preg_match('/^08\d{8,11}$/', $normalized) === 1;
    }
}
