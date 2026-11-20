<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $amount
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property string|null $proof_path
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $rejection_reason
 * @property CarbonImmutable $created_at
 * @property-read Booking $booking
 * @property-read User|null $reviewer
 */
class Payment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['proof_path'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Jumlah bukti yang menunggu diperiksa, untuk angka di menu "Cek bayar".
     */
    public static function waitingCount(): int
    {
        return self::query()->where('status', PaymentStatus::Pending)->count();
    }
}
