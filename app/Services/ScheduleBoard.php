<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Support\OperatingHours;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Menyusun jadwal satu hari operasional per lapangan (UC-01 dan UC-06).
 */
class ScheduleBoard
{
    public function __construct(private HoldExpiry $holds) {}

    /**
     * @return Collection<int, array{court: Court, slots: list<array{start: CarbonImmutable, status: string, booking: Booking|null}>}>
     */
    public function forDate(CarbonImmutable $date): Collection
    {
        $this->holds->expireStale();

        $bookings = Booking::query()
            ->whereIn('status', BookingStatus::occupying())
            ->where('starts_at', '<', OperatingHours::closesAt($date))
            ->where('ends_at', '>', OperatingHours::opensAt($date))
            ->get();

        return Court::query()->where('active', true)->orderBy('id')->get()
            ->map(fn (Court $court) => [
                'court' => $court,
                'slots' => $this->slotsFor($court, $date, $bookings->where('court_id', $court->id)),
            ]);
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @return list<array{start: CarbonImmutable, status: string, booking: Booking|null}>
     */
    private function slotsFor(Court $court, CarbonImmutable $date, Collection $bookings): array
    {
        $slots = [];
        foreach (OperatingHours::slots($date) as $start) {
            $end = $start->addHour();
            $booking = $bookings->first(fn (Booking $b) => $b->starts_at->lt($end) && $b->ends_at->gt($start));

            $status = match (true) {
                $booking?->isBlock() => 'blocked',
                $booking !== null => 'booked',
                ! $start->isFuture() => 'past',
                default => 'available',
            };
            $slots[] = ['start' => $start, 'status' => $status, 'booking' => $booking];
        }

        return $slots;
    }

    /**
     * Durasi terpanjang (jam) yang masih kosong mulai dari slot tertentu.
     */
    public function maxDurationFrom(Court $court, CarbonImmutable $date, CarbonImmutable $start): int
    {
        $row = $this->forDate($date)->first(fn (array $row) => $row['court']->id === $court->id);
        if ($row === null) {
            return 0;
        }
        $hours = 0;
        foreach ($row['slots'] as $slot) {
            if ($slot['start']->lt($start)) {
                continue;
            }
            if ($slot['status'] !== 'available') {
                break;
            }
            $hours++;
        }

        return $hours;
    }
}
