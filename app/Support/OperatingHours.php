<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Hari operasional Lola Sport: 07.00 sampai 01.00 hari berikutnya (KNF-06).
 *
 * Contoh: slot 00.00-01.00 pada malam Sabtu dicatat sebagai tanggal Jumat,
 * karena masih bagian dari hari operasional Jumat.
 */
class OperatingHours
{
    public static function openHour(): int
    {
        return (int) config('lola.open_hour');
    }

    public static function closeHour(): int
    {
        return (int) config('lola.close_hour');
    }

    public static function opensAt(CarbonImmutable $date): CarbonImmutable
    {
        return $date->startOfDay()->setHour(self::openHour());
    }

    public static function closesAt(CarbonImmutable $date): CarbonImmutable
    {
        $close = $date->startOfDay()->setHour(self::closeHour());

        // Jam tutup 01.00 lebih kecil dari jam buka 07.00, berarti tutup besoknya.
        return self::closeHour() <= self::openHour() ? $close->addDay() : $close;
    }

    /**
     * Jam mulai semua slot satu jam dalam satu hari operasional.
     *
     * @return list<CarbonImmutable>
     */
    public static function slots(CarbonImmutable $date): array
    {
        $slots = [];
        $start = self::opensAt($date);
        while ($start->lt(self::closesAt($date))) {
            $slots[] = $start;
            $start = $start->addHour();
        }

        return $slots;
    }

    /**
     * Pilihan jam mulai yang valid, misalnya [7, 8, ..., 23, 0].
     *
     * @return list<int>
     */
    public static function startHours(): array
    {
        return array_map(fn (CarbonImmutable $slot) => $slot->hour, self::slots(CarbonImmutable::today()));
    }

    /**
     * Ubah pilihan "tanggal + jam" menjadi waktu sebenarnya.
     * Jam 00.00 pada tanggal operasional X berarti 00.00 tanggal X+1.
     */
    public static function slotStart(CarbonImmutable $date, int $hour): CarbonImmutable
    {
        $start = $date->startOfDay()->setHour($hour);

        return $hour < self::openHour() ? $start->addDay() : $start;
    }

    /**
     * Tanggal operasional yang sedang berjalan sekarang.
     */
    public static function today(): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        return $now->hour < self::closeHour() ? $now->subDay()->startOfDay() : $now->startOfDay();
    }

    public static function lastBookableDate(): CarbonImmutable
    {
        return self::today()->addDays((int) config('lola.max_days_ahead'));
    }
}
