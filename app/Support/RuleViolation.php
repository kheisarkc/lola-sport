<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Pelanggaran aturan bisnis ditampilkan sebagai pesan error biasa di form,
 * lalu pengguna dikembalikan ke halaman sebelumnya.
 */
class RuleViolation
{
    public static function because(string $message): ValidationException
    {
        return ValidationException::withMessages(['aturan' => $message]);
    }
}
