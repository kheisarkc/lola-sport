<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Omzet = uang yang sudah diterima (DP + pelunasan + bayar penuh) dan
 * dihitung pada tanggal diverifikasi (asumsi kelompok, KF-20).
 *
 * Setiap angka bisa ditelusuri ke baris transaksinya (KF-21), dan
 * jumlah per jenis selalu sama dengan total.
 */
class RevenueReport
{
    public const KINDS = ['dp' => 'DP', 'settlement' => 'Pelunasan', 'full' => 'Bayar penuh'];

    public function receivedBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $this->approvedPayments($from, $to)->sum('amount');
    }

    /**
     * @return array{
     *     total: int,
     *     by_kind: array<string, int>,
     *     daily: list<array{date: CarbonImmutable, total: int, count: int}>,
     *     transactions: Collection<int, Payment>,
     *     bookings_per_court: Collection<int, array{court: Court, count: int<0, max>, hours: int}>
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $payments = $this->approvedPayments($from, $to)
            ->with('booking.court', 'reviewer')
            ->orderBy('reviewed_at')
            ->get();

        $firstPaymentIds = $this->firstPaymentIds($payments);
        $byKind = array_fill_keys(array_keys(self::KINDS), 0);
        foreach ($payments as $payment) {
            $kind = match (true) {
                $firstPaymentIds->get($payment->booking_id) !== $payment->id => 'settlement',
                $payment->amount >= $payment->booking->total => 'full',
                default => 'dp',
            };
            $payment->setAttribute('kind', $kind);
            $byKind[$kind] += $payment->amount;
        }

        return [
            'total' => (int) $payments->sum('amount'),
            'by_kind' => $byKind,
            'daily' => $this->daily($payments, $from, $to),
            'transactions' => $payments->reverse()->values(),
            'bookings_per_court' => $this->bookingsPerCourt($from, $to),
        ];
    }

    /**
     * @return Builder<Payment>
     */
    private function approvedPayments(CarbonImmutable $from, CarbonImmutable $to)
    {
        return Payment::query()
            ->where('status', PaymentStatus::Approved)
            ->where('reviewed_at', '>=', $from->startOfDay())
            ->where('reviewed_at', '<', $to->startOfDay()->addDay());
    }

    /**
     * Pembayaran pertama yang diterima adalah DP (atau bayar penuh bila langsung lunas).
     * Pembayaran setelahnya adalah pelunasan. Riwayat di luar rentang tanggal ikut dilihat,
     * jadi pelunasan hari ini tetap terbaca pelunasan walaupun DP-nya bulan lalu.
     *
     * @param  Collection<int, Payment>  $payments
     * @return Collection<int, int> booking_id => id pembayaran pertama
     */
    private function firstPaymentIds(Collection $payments): Collection
    {
        return Payment::query()
            ->where('status', PaymentStatus::Approved)
            ->whereIn('booking_id', $payments->pluck('booking_id')->unique())
            ->orderBy('reviewed_at')
            ->orderBy('id')
            ->get(['id', 'booking_id'])
            ->unique('booking_id')
            ->mapWithKeys(fn (Payment $p) => [$p->booking_id => $p->id]);
    }

    /**
     * Satu baris per tanggal, termasuk tanggal tanpa transaksi (Rp0).
     *
     * @param  Collection<int, Payment>  $payments
     * @return list<array{date: CarbonImmutable, total: int, count: int}>
     */
    private function daily(Collection $payments, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $grouped = $payments->groupBy(fn (Payment $p) => $p->reviewed_at?->format('Y-m-d'));
        $rows = [];
        for ($date = $from->startOfDay(); $date->lte($to); $date = $date->addDay()) {
            $items = $grouped->get($date->format('Y-m-d'), collect());
            $rows[] = ['date' => $date, 'total' => (int) $items->sum('amount'), 'count' => $items->count()];
        }

        return $rows;
    }

    /**
     * Jumlah reservasi dan jam terpakai per lapangan menurut tanggal main (KF-22).
     *
     * @return Collection<int, array{court: Court, count: int<0, max>, hours: int}>
     */
    private function bookingsPerCourt(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $bookings = Booking::query()
            ->where('source', '!=', BookingSource::Block)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::NoShow])
            ->whereBetween('play_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get();

        return Court::query()->orderBy('id')->get()->map(function (Court $court) use ($bookings) {
            $mine = $bookings->where('court_id', $court->id);

            return [
                'court' => $court,
                'count' => $mine->count(),
                'hours' => (int) $mine->sum(fn (Booking $b) => $b->durationHours()),
            ];
        });
    }
}
