@extends('layouts.app', ['title' => 'Ringkasan'])

{{--
    UC-14 ringkasan pemilik. Hanya melihat, tidak mengubah pesanan atau pembayaran.
    Uang dihitung pada tanggal diterima/diverifikasi; reservasi pada tanggal main.
--}}
@section('content')
    @php
        $s = $summary;
        $money = $s['by_method'];
        $daily = collect($s['daily']);
        $maxDaily = max(1, $daily->max('total'));
        $maxPeak = max(1, collect($s['peak_hours'])->max('hours'));
        $txLink = route('owner.transactions', $period->query());
    @endphp

    <div class="page-head page-head-tight">
        <h1>Ringkasan</h1>
        <p>{{ $period->label() }}</p>
    </div>

    @include('owner.partials.period', ['route' => 'owner.revenue'])

    <div class="kpi-grid">
        @include('owner.partials.kpi', ['label' => 'Uang masuk', 'value' => rupiah($s['money']['now']), 'now' => $s['money']['now'], 'before' => $s['money']['before'], 'unit' => 'rupiah'])
        @include('owner.partials.kpi', ['label' => 'Reservasi', 'value' => $s['bookings']['now'], 'now' => $s['bookings']['now'], 'before' => $s['bookings']['before'], 'unit' => 'count'])
        @include('owner.partials.kpi', ['label' => 'Lapangan terpakai', 'value' => persen($s['occupancy']['now']), 'now' => $s['occupancy']['now'], 'before' => $s['occupancy']['before'], 'unit' => 'percent'])
        <div class="kpi kpi-plain">
            <span class="kpi-label">Belum dilunasi</span>
            <strong class="kpi-value">{{ rupiah($s['outstanding']['total']) }}</strong>
            <span class="kpi-before">{{ $s['outstanding']['count'] > 0 ? $s['outstanding']['count'].' pesanan, jadwal hari ini dan nanti' : 'Tidak ada sisa tagihan' }}</span>
        </div>
    </div>

    <section class="card">
        <div class="card-title-row">
            <h2>Uang masuk</h2>
            <a href="{{ $txLink }}">{{ $s['money']['count'] }} transaksi &rarr;</a>
        </div>
        @if ($s['money']['count'] === 0)
            <p class="muted">Belum ada transaksi pada periode ini (Rp0).</p>
        @else
            <ul class="split-list">
                <li>
                    <span>Tunai <small>{{ $money['cash_count'] }} transaksi di tempat</small></span>
                    <strong>{{ rupiah($money['cash']) }}</strong>
                </li>
                <li>
                    <span>QRIS <small>{{ $money['qris_count'] }} transaksi · lewat web {{ rupiah($money['qris_web']) }}, di tempat {{ rupiah($money['qris_counter']) }}</small></span>
                    <strong>{{ rupiah($money['qris']) }}</strong>
                </li>
            </ul>
            <div class="split-bar" role="img" aria-label="Tunai {{ rupiah($money['cash']) }}, QRIS {{ rupiah($money['qris']) }}">
                <span class="split-cash" style="flex-grow: {{ $money['cash'] }}"></span>
                <span class="split-qris" style="flex-grow: {{ $money['qris'] }}"></span>
            </div>
            <p class="small muted">Total QRIS bisa dicocokkan dengan riwayat QRIS di aplikasi bank/merchant.</p>

            <h3>Menurut jenis</h3>
            <ul class="split-list">
                @foreach (\App\Services\RevenueReport::KINDS as $kind => $label)
                    <li><span>{{ $label }}</span><strong>{{ rupiah($s['by_kind'][$kind]) }}</strong></li>
                @endforeach
                @if ($s['forfeited']['count'] > 0)
                    <li class="is-note">
                        <span>Termasuk DP hangus <small>{{ $s['forfeited']['count'] }} pesanan batal/tidak datang, uang tidak dikembalikan</small></span>
                        <strong>{{ rupiah($s['forfeited']['total']) }}</strong>
                    </li>
                @endif
            </ul>
        @endif
    </section>

    @if ($s['by_staff']->isNotEmpty())
        <section class="card">
            <h2>Dicatat per penjaga</h2>
            <p class="small muted">Siapa yang mencatat tunai dan memeriksa bukti QRIS. Berguna untuk mencocokkan uang tunai di kasir.</p>
            <ul class="split-list">
                @foreach ($s['by_staff'] as $row)
                    <li>
                        <span>{{ $row['name'] }} <small>Tunai {{ rupiah($row['cash']) }} · QRIS {{ rupiah($row['qris']) }}</small></span>
                        <strong>{{ rupiah($row['cash'] + $row['qris']) }}</strong>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($period->days() > 1)
        <section class="card">
            @php $best = $daily->sortByDesc('total')->first(); @endphp
            <h2>Uang masuk per hari</h2>
            @if ($best['total'] > 0)
                <p class="small muted">Paling tinggi {{ rupiah($best['total']) }} pada {{ $best['date']->locale('id')->translatedFormat('D, j M') }}.</p>
            @endif
            <div class="day-chart" aria-hidden="true">
                @foreach ($daily as $day)
                    <div class="day-chart-col" title="{{ $day['date']->locale('id')->translatedFormat('D, j M') }}: {{ rupiah($day['total']) }}">
                        <span class="day-chart-bar" style="height: {{ max($day['total'] > 0 ? 3 : 0, round($day['total'] / $maxDaily * 100)) }}%"></span>
                        @if ($period->days() <= 7)
                            <small>{{ $day['date']->locale('id')->translatedFormat('D') }}</small>
                        @elseif ($loop->first || $loop->last || $day['date']->day % 5 === 0)
                            <small>{{ $day['date']->day }}</small>
                        @else
                            <small>&nbsp;</small>
                        @endif
                    </div>
                @endforeach
            </div>
            <details class="day-list">
                <summary>Angka per hari</summary>
                <ul class="split-list">
                    @foreach ($daily as $day)
                        <li>
                            <span>{{ $day['date']->locale('id')->translatedFormat('D, j M') }} <small>{{ $day['count'] }} transaksi</small></span>
                            <strong>{{ rupiah($day['total']) }}</strong>
                        </li>
                    @endforeach
                </ul>
            </details>
        </section>
    @endif

    <section class="card">
        <h2>Jam paling ramai</h2>
        @if (empty($s['peak_hours']))
            <p class="muted">Belum ada reservasi yang jadi pada periode ini.</p>
        @else
            <p class="small muted">Berapa kali tiap jam dipakai, semua lapangan digabung.</p>
            <ul class="bar-list">
                @foreach ($s['peak_hours'] as $row)
                    <li>
                        <span>{{ sprintf('%02d.00', $row['hour']) }}</span>
                        <span class="bar-track"><span class="bar" style="width: {{ round($row['hours'] / $maxPeak * 100) }}%"></span></span>
                        <strong>{{ $row['hours'] }}&times;</strong>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card">
        <h2>Per lapangan</h2>
        <p class="small muted">Menurut tanggal main. Terpakai = jam terpesan dibanding jam buka (07.00–01.00).</p>
        <ul class="split-list">
            @foreach ($s['per_court'] as $row)
                <li>
                    <span>{{ $row['court']->name }} <small>{{ $row['count'] }} reservasi · {{ $row['hours'] }} jam</small></span>
                    <strong>{{ persen($row['percent']) }}</strong>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
