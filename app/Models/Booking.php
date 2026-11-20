<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property int $court_id
 * @property BookingSource $source
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property CarbonImmutable $play_date
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $hourly_rate
 * @property int $total
 * @property int $minimum_dp
 * @property BookingStatus $status
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $checked_in_at
 * @property string|null $note
 * @property-read Court $court
 */
class Booking extends Model
{
    protected $guarded = ['id'];

    /** URL memakai kode booking (LS-XXXXXX) yang juga dipegang pelanggan dan pegawai. */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected function casts(): array
    {
        return [
            'source' => BookingSource::class,
            'status' => BookingStatus::class,
            'play_date' => 'immutable_date',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime',
            'hourly_rate' => 'integer',
            'total' => 'integer',
            'minimum_dp' => 'integer',
        ];
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /** @return HasMany<AuditEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AuditEvent::class)->orderBy('id');
    }

    public function isBlock(): bool
    {
        return $this->source === BookingSource::Block;
    }

    public function durationHours(): int
    {
        return (int) $this->starts_at->diffInHours($this->ends_at);
    }

    /**
     * Total uang yang sudah diverifikasi untuk reservasi ini.
     * Di halaman daftar, payments di-eager-load supaya tidak query berulang.
     * Di dalam transaksi relasi tidak dimuat, jadi angkanya dibaca langsung dari database.
     */
    public function paidAmount(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->where('status', PaymentStatus::Approved)->sum('amount');
        }

        return (int) $this->payments()->where('status', PaymentStatus::Approved)->sum('amount');
    }

    public function remainingAmount(): int
    {
        return $this->total - $this->paidAmount();
    }

    public function hasPendingPayment(): bool
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->contains('status', PaymentStatus::Pending);
        }

        return $this->payments()->where('status', PaymentStatus::Pending)->exists();
    }

    /**
     * Status pembayaran terpisah dari status reservasi.
     */
    public function paymentLabel(): string
    {
        if ($this->isBlock()) {
            return '-';
        }
        $paid = $this->paidAmount();

        return match (true) {
            $paid === 0 => 'Belum bayar',
            $paid >= $this->total => 'Lunas',
            default => 'DP diterima',
        };
    }
}
