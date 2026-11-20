<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rentang tanggal laporan pemilik beserta rentang pembandingnya.
 *
 * Pilihan cepat: hari ini, 7 hari, bulan ini, bulan lalu. Bisa juga tanggal bebas
 * lewat ?dari=&sampai= (paling panjang 93 hari). Pembanding "bulan ini" adalah
 * tanggal yang sama di bulan lalu (1-9 Nov dibanding 1-9 Okt), supaya adil.
 */
final class ReportPeriod
{
    public const PRESETS = [
        'hari-ini' => 'Hari ini',
        '7-hari' => '7 hari',
        'bulan-ini' => 'Bulan ini',
        'bulan-lalu' => 'Bulan lalu',
    ];

    public const DEFAULT = 'bulan-ini';

    private const MAX_DAYS = 93;

    private function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly CarbonImmutable $previousFrom,
        public readonly CarbonImmutable $previousTo,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = $request->validate([
            'periode' => ['nullable', Rule::in(array_keys(self::PRESETS))],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ], attributes: ['dari' => 'tanggal awal', 'sampai' => 'tanggal akhir']);

        $today = CarbonImmutable::today();
        if (empty($data['dari']) && empty($data['sampai'])) {
            return self::preset($data['periode'] ?? self::DEFAULT, $today);
        }

        $to = CarbonImmutable::parse($data['sampai'] ?? $today->format('Y-m-d'));
        $from = CarbonImmutable::parse($data['dari'] ?? $to->format('Y-m-d'));
        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }
        $length = (int) $from->diffInDays($to) + 1;

        return new self('pilih', $from, $to, $from->subDays($length), $from->subDay());
    }

    public static function preset(string $key, CarbonImmutable $today): self
    {
        return match ($key) {
            'hari-ini' => new self($key, $today, $today, $today->subDay(), $today->subDay()),
            '7-hari' => new self($key, $today->subDays(6), $today, $today->subDays(13), $today->subDays(7)),
            'bulan-lalu' => new self(
                $key,
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->startOfDay(),
                $today->subMonthsNoOverflow(2)->startOfMonth(),
                $today->subMonthsNoOverflow(2)->endOfMonth()->startOfDay(),
            ),
            default => self::monthToDate($today),
        };
    }

    private static function monthToDate(CarbonImmutable $today): self
    {
        $lastMonth = $today->subMonthNoOverflow()->startOfMonth();
        $previousTo = $lastMonth->setDay(min($today->day, $lastMonth->daysInMonth));

        return new self('bulan-ini', $today->startOfMonth(), $today, $lastMonth, $previousTo);
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    public function previousDays(): int
    {
        return (int) $this->previousFrom->diffInDays($this->previousTo) + 1;
    }

    /**
     * Parameter URL untuk membuka periode yang sama di halaman lain.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return $this->key === 'pilih'
            ? ['dari' => $this->from->format('Y-m-d'), 'sampai' => $this->to->format('Y-m-d')]
            : ['periode' => $this->key];
    }

    public function label(): string
    {
        return self::range($this->from, $this->to);
    }

    public function previousLabel(): string
    {
        return $this->key === 'hari-ini' ? 'kemarin' : self::range($this->previousFrom, $this->previousTo);
    }

    /** "10 Nov 2026", "1–9 Nov 2026", "28 Okt – 3 Nov 2026". */
    public static function range(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $f = fn (CarbonImmutable $d, string $format) => $d->locale('id')->translatedFormat($format);

        return match (true) {
            $from->isSameDay($to) => $f($to, 'j M Y'),
            $from->isSameMonth($to) => $from->day.'–'.$f($to, 'j M Y'),
            $from->isSameYear($to) => $f($from, 'j M').' – '.$f($to, 'j M Y'),
            default => $f($from, 'j M Y').' – '.$f($to, 'j M Y'),
        };
    }
}
