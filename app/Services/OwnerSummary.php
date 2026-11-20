<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Support\OperatingHours;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Angka untuk layar Ringkasan pemilik (UC-14). Hanya membaca, tidak mengubah apa pun.
 *
 * - Uang masuk: sama dengan RevenueReport (dihitung pada tanggal uang diterima).
 * - Reservasi dan lapangan terpakai: menurut tanggal main, hanya pesanan yang jadi
 *   (terkonfirmasi, hadir, tidak datang). Tutup lapangan tidak dihitung.
 * - Lapangan terpakai = jam terpesan / (hari x lapangan aktif x jam buka per hari).
 */
class OwnerSummary
{
    public function __construct(private RevenueReport $revenue) {}

    /**
     * Uang dari pesanan yang batal atau tidak datang tetap milik Lola Sport (DP hangus).
     */
    public static function isForfeited(Payment $payment): bool
    {
        return in_array($payment->booking->status, [BookingStatus::Cancelled, BookingStatus::NoShow], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(ReportPeriod $period): array
    {
        $report = $this->revenue->build($period->from, $period->to);
        $payments = $report['transactions'];
        $played = $this->playedBookings($period->from, $period->to);
        $playedBefore = $this->playedBookings($period->previousFrom, $period->previousTo);
        $slotsPerDay = count(OperatingHours::startHours());
        $activeCourts = Court::query()->where('active', true)->count();

        $forfeited = $payments->filter(fn (Payment $p) => self::isForfeited($p));
        $qris = $payments->where('method', PaymentMethod::Qris);

        return [
            'money' => [
                'now' => $report['total'],
                'before' => $this->revenue->receivedBetween($period->previousFrom, $period->previousTo),
                'count' => $payments->count(),
            ],
            'bookings' => ['now' => $played->count(), 'before' => $playedBefore->count()],
            'occupancy' => [
                'now' => $this->percent($this->hours($played), $period->days() * $activeCourts * $slotsPerDay),
                'before' => $this->percent($this->hours($playedBefore), $period->previousDays() * $activeCourts * $slotsPerDay),
                'hours' => $this->hours($played),
            ],
            'outstanding' => $this->outstanding(),
            'by_method' => [
                'cash' => (int) $payments->where('method', PaymentMethod::Cash)->sum('amount'),
                'cash_count' => $payments->where('method', PaymentMethod::Cash)->count(),
                'qris' => (int) $qris->sum('amount'),
                'qris_count' => $qris->count(),
                'qris_web' => (int) $qris->whereNotNull('proof_path')->sum('amount'),
                'qris_counter' => (int) $qris->whereNull('proof_path')->sum('amount'),
            ],
            'by_kind' => $report['by_kind'],
            'forfeited' => ['total' => (int) $forfeited->sum('amount'), 'count' => $forfeited->count()],
            'by_staff' => $this->byStaff($payments),
            'daily' => $report['daily'],
            'peak_hours' => $this->peakHours($played),
            'per_court' => $report['bookings_per_court']->map(fn (array $row) => $row + [
                'percent' => $this->percent($row['hours'], $period->days() * $slotsPerDay),
            ]),
        ];
    }

    /**
     * @return Collection<int, Booking>
     */
    private function playedBookings(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Booking::query()
            ->where('source', '!=', BookingSource::Block)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::NoShow])
            ->whereBetween('play_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get();
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    private function hours(Collection $bookings): int
    {
        return (int) $bookings->sum(fn (Booking $b) => $b->durationHours());
    }

    /** Satu angka di belakang koma, supaya pemakaian kecil tidak tampil 0%. */
    private function percent(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    /**
     * Sisa yang belum dibayar dari pesanan terkonfirmasi yang jadwalnya hari ini atau nanti.
     *
     * @return array{total: int, count: int}
     */
    private function outstanding(): array
    {
        $bookings = Booking::query()
            ->with('payments')
            ->where('source', '!=', BookingSource::Block)
            ->where('status', BookingStatus::Confirmed)
            ->where('play_date', '>=', OperatingHours::today()->format('Y-m-d'))
            ->get()
            ->filter(fn (Booking $b) => $b->remainingAmount() > 0);

        return ['total' => (int) $bookings->sum(fn (Booking $b) => $b->remainingAmount()), 'count' => $bookings->count()];
    }

    /**
     * Siapa yang mencatat tunai atau memeriksa QRIS. Dipakai untuk mencocokkan setoran kas.
     *
     * @param  Collection<int, Payment>  $payments
     * @return Collection<int, array{name: string, cash: int, qris: int}>
     */
    private function byStaff(Collection $payments): Collection
    {
        return $payments
            ->groupBy(fn (Payment $p) => $p->reviewer->name ?? 'Tidak diketahui')
            ->map(fn (Collection $items, string $name) => [
                'name' => $name,
                'cash' => (int) $items->where('method', PaymentMethod::Cash)->sum('amount'),
                'qris' => (int) $items->where('method', PaymentMethod::Qris)->sum('amount'),
            ])
            ->sortByDesc(fn (array $row) => $row['cash'] + $row['qris'])
            ->values();
    }

    /**
     * Lima jam dengan pemakaian terbanyak. Pesanan 2 jam dihitung di kedua jamnya.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return list<array{hour: int, hours: int}>
     */
    private function peakHours(Collection $bookings): array
    {
        $counts = [];
        foreach ($bookings as $booking) {
            for ($slot = $booking->starts_at; $slot->lt($booking->ends_at); $slot = $slot->addHour()) {
                $counts[$slot->hour] = ($counts[$slot->hour] ?? 0) + 1;
            }
        }
        $rows = [];
        foreach ($counts as $hour => $count) {
            $rows[] = ['hour' => $hour, 'hours' => $count];
        }
        // Jam buka (07.00) lebih dulu dari 00.00 saat jumlahnya sama.
        $order = array_flip(OperatingHours::startHours());
        usort($rows, fn (array $a, array $b) => [$b['hours'], $order[$a['hour']] ?? 99] <=> [$a['hours'], $order[$b['hour']] ?? 99]);

        return array_slice($rows, 0, 5);
    }
}
