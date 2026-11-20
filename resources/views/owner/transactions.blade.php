@extends('layouts.app', ['title' => 'Transaksi'])

{{-- UC-15: rincian uang masuk yang membentuk angka di Ringkasan (KF-21). Hanya melihat. --}}
@section('content')
    @php
        $filterParams = $filter === 'semua' ? [] : ['cara' => $filter];
    @endphp

    <div class="page-head page-head-tight">
        <h1>Transaksi</h1>
        <p>{{ $period->label() }}</p>
    </div>

    @include('owner.partials.period', ['route' => 'owner.transactions', 'params' => $filterParams])

    <nav class="group-chips" aria-label="Saring transaksi">
        @foreach (\App\Http\Controllers\Owner\RevenueController::FILTERS as $key => $label)
            <a href="{{ route('owner.transactions', $period->query() + ($key === 'semua' ? [] : ['cara' => $key])) }}"
               @class(['group-chip', 'is-active' => $filter === $key]) @if ($filter === $key) aria-current="true" @endif>
                {{ $label }} <b>{{ $counts[$key] }}</b>
            </a>
        @endforeach
    </nav>

    <div class="tx-total">
        <span>{{ $transactions->count() }} transaksi</span>
        <strong>{{ rupiah($total) }}</strong>
    </div>

    @if ($transactions->isEmpty())
        <section class="card empty-state">
            <p class="muted">Belum ada transaksi pada periode ini (Rp0).</p>
        </section>
    @else
        <ul class="tx-list">
            @foreach ($transactions as $payment)
                @php $booking = $payment->booking; @endphp
                <li class="tx-card">
                    <div class="tx-top">
                        <strong class="tx-amount">{{ rupiah($payment->amount) }}</strong>
                        <span class="badge badge-money">{{ \App\Services\RevenueReport::KINDS[$payment->kind] }}</span>
                        <span class="badge">{{ $payment->method->label() }}{{ $payment->method === \App\Enums\PaymentMethod::Qris && ! $payment->proof_path ? ' di tempat' : '' }}</span>
                        @if (\App\Services\OwnerSummary::isForfeited($payment))
                            <span class="badge badge-{{ $booking->status->value }}">Hangus · {{ $booking->status->label() }}</span>
                        @endif
                    </div>
                    <p class="tx-who">{{ $booking->customer_name }} · <span class="booking-card-code">{{ $booking->code }}</span></p>
                    <p class="tx-meta">
                        Main {{ $booking->play_date->locale('id')->translatedFormat('D, j M') }}, {{ jam($booking->starts_at) }}–{{ jam($booking->ends_at) }} · {{ $booking->court->name }}<br>
                        Diterima {{ waktu($payment->reviewed_at) }}@if ($payment->reviewer) oleh {{ $payment->reviewer->name }}@endif
                    </p>
                </li>
            @endforeach
        </ul>
    @endif

    <a class="btn btn-outline btn-block" href="{{ route('owner.transactions.export', $period->query() + $filterParams) }}">Unduh CSV (Excel)</a>
    <p class="small muted center">Isi file sama dengan daftar di atas.</p>
@endsection
