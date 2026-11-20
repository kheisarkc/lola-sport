<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $action
 * @property array<string, mixed>|null $details
 * @property CarbonImmutable $created_at
 * @property-read User|null $actor
 */
class AuditEvent extends Model
{
    public const LABELS = [
        'created' => 'Reservasi dibuat',
        'blocked' => 'Slot ditutup',
        'expired' => 'Kedaluwarsa otomatis',
        'proof_submitted' => 'Bukti pembayaran dikirim',
        'payment_approved' => 'Pembayaran diterima',
        'payment_rejected' => 'Pembayaran ditolak',
        'cash_recorded' => 'Tunai dicatat',
        'counter_qris_recorded' => 'QRIS di tempat dicatat',
        'cancelled' => 'Dibatalkan',
        'checked_in' => 'Pelanggan hadir',
        'no_show' => 'Pelanggan tidak hadir',
    ];

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
