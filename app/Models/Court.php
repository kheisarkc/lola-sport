<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lapangan beserta harga per jam (KF-02, KF-18).
 *
 * @property int $id
 * @property string $name
 * @property string $sport
 * @property int $hourly_rate
 * @property bool $active
 */
class Court extends Model
{
    public const SPORTS = ['futsal' => 'Futsal', 'badminton' => 'Badminton'];

    protected $fillable = ['name', 'sport', 'hourly_rate', 'active'];

    protected function casts(): array
    {
        return ['hourly_rate' => 'integer', 'active' => 'boolean'];
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function sportLabel(): string
    {
        return self::SPORTS[$this->sport] ?? $this->sport;
    }

    /** Foto lapangan untuk jadwal publik; jatuh ke foto cabang bila belum ada. */
    public function photoUrl(): string
    {
        /** @var array<string, string> $byName */
        $byName = config('lola.court_photos', []);
        /** @var array<string, string> $bySport */
        $bySport = config('lola.sport_photos', []);

        return asset($byName[$this->name] ?? $bySport[$this->sport] ?? 'images/hero.webp');
    }
}
