<?php

namespace App\Http\Controllers\Owner;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OwnerSummary;
use App\Services\RevenueReport;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UC-14: ringkasan untuk pemilik. UC-15: rincian transaksi pembentuk angka omzet.
 * Pemilik hanya melihat; pesanan dan pembayaran diurus pegawai.
 */
class RevenueController extends Controller
{
    public const FILTERS = ['semua' => 'Semua', 'tunai' => 'Tunai', 'qris' => 'QRIS', 'hangus' => 'DP hangus'];

    public function index(Request $request, OwnerSummary $summary): View
    {
        $period = ReportPeriod::fromRequest($request);

        return view('owner.revenue', ['period' => $period, 'summary' => $summary->build($period)]);
    }

    public function transactions(Request $request, RevenueReport $report): View
    {
        $period = ReportPeriod::fromRequest($request);
        $filter = $this->filter($request);
        $all = $report->build($period->from, $period->to)['transactions'];
        $shown = $this->apply($all, $filter);

        return view('owner.transactions', [
            'period' => $period,
            'filter' => $filter,
            'counts' => collect(self::FILTERS)->map(fn ($label, $key) => $this->apply($all, $key)->count()),
            'transactions' => $shown,
            'total' => (int) $shown->sum('amount'),
        ]);
    }

    /**
     * Unduh CSV sesuai periode dan filter yang sedang dilihat (untuk Excel/analisis).
     */
    public function export(Request $request, RevenueReport $report): StreamedResponse
    {
        $period = ReportPeriod::fromRequest($request);
        $filter = $this->filter($request);
        $rows = $this->apply($report->build($period->from, $period->to)['transactions'], $filter);
        $name = 'transaksi-lola-'.$period->from->format('Ymd').'-'.$period->to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // supaya Excel membaca huruf dengan benar
            fputcsv($out, ['diterima', 'kode', 'pelanggan', 'lapangan', 'tanggal_main', 'jam_main', 'jenis', 'cara_bayar', 'dicatat_oleh', 'status_pesanan', 'nominal']);
            foreach ($rows->reverse() as $payment) {
                $booking = $payment->booking;
                fputcsv($out, [
                    $payment->reviewed_at?->format('Y-m-d H:i'),
                    $booking->code,
                    $booking->customer_name,
                    $booking->court->name,
                    $booking->play_date->format('Y-m-d'),
                    jam($booking->starts_at).'-'.jam($booking->ends_at),
                    RevenueReport::KINDS[$payment->getAttribute('kind')],
                    $payment->method->label(),
                    $payment->reviewer?->name,
                    $booking->status->label(),
                    $payment->amount,
                ]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filter(Request $request): string
    {
        $filter = (string) $request->query('cara', 'semua');

        return array_key_exists($filter, self::FILTERS) ? $filter : 'semua';
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return Collection<int, Payment>
     */
    private function apply(Collection $payments, string $filter): Collection
    {
        return match ($filter) {
            'tunai' => $payments->where('method', PaymentMethod::Cash)->values(),
            'qris' => $payments->where('method', PaymentMethod::Qris)->values(),
            'hangus' => $payments->filter(fn (Payment $p) => OwnerSummary::isForfeited($p))->values(),
            default => $payments,
        };
    }
}
