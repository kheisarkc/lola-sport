{{--
    Satu kotak angka dengan perbandingan periode sebelumnya.
    $label, $value (teks tampil), $now, $before (angka), $unit: 'rupiah' | 'count' | 'percent', $period.
--}}
@php
    $format = fn (int|float $n) => match ($unit) {
        'rupiah' => rupiah((int) $n),
        'percent' => persen($n),
        default => (string) $n,
    };
    $diff = round($now - $before, 1);
    $change = match (true) {
        $diff == 0 => 'Sama',
        $unit === 'percent' => ($diff > 0 ? 'Naik ' : 'Turun ').str_replace('%', '', persen(abs($diff))).' poin',
        $before == 0 => 'Naik',
        default => ($diff > 0 ? 'Naik ' : 'Turun ').round(abs($diff) / $before * 100).'%',
    };
@endphp
<div class="kpi">
    <span class="kpi-label">{{ $label }}</span>
    <strong class="kpi-value">{{ $value }}</strong>
    <span @class(['kpi-change', 'is-up' => $diff > 0, 'is-down' => $diff < 0])>{{ $change }}</span>
    <span class="kpi-before">{{ ucfirst($period->previousLabel()) }}: {{ $format($before) }}</span>
</div>
