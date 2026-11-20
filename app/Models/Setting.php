<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aturan pemesanan yang boleh diubah pemilik (KF-25).
 * DP 50% dari owner (8 Nov 2026); tahan 30 menit (keputusan tim 10 Nov 2026, sama dengan web demo awal).
 *
 * @property int $dp_percent
 * @property int $hold_minutes
 */
class Setting extends Model
{
    protected $fillable = ['dp_percent', 'hold_minutes'];

    protected function casts(): array
    {
        return ['dp_percent' => 'integer', 'hold_minutes' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['dp_percent' => 50, 'hold_minutes' => 30]);
    }

    /**
     * Minimal DP dalam rupiah bulat, dibulatkan ke atas.
     */
    public function minimumDp(int $total): int
    {
        return intdiv($total * $this->dp_percent + 99, 100);
    }
}
