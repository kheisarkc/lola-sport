@extends('layouts.app', ['title' => 'Hari ini'])

{{--
    UC-06: layar utama pegawai. Pesanan satu tanggal dikelompokkan menurut apa yang
    perlu dilakukan, dari yang paling mendesak. Satu tombol utama per kartu.
--}}
@use('App\Enums\BookingStatus')

@section('content')
    @php
        $isToday = $date->equalTo($today);
        $groups = [
            'cek' => ['Perlu cek bukti bayar', fn ($b) => $b->hasPendingPayment()],
            'sisa' => ['Belum lunas', fn ($b) => $b->status === BookingStatus::Confirmed && $b->remainingAmount() > 0],
            'siap' => ['Lunas, tinggal datang', fn ($b) => $b->status === BookingStatus::Confirmed && $b->ends_at->isFuture()],
            'main' => ['Sedang main', fn ($b) => $b->status === BookingStatus::CheckedIn && $b->ends_at->isFuture()],
            'tunggu' => ['Menunggu pelanggan bayar', fn ($b) => $b->status === BookingStatus::Pending],
            'tutup' => ['Lapangan ditutup', fn ($b) => $b->isBlock() && $b->status->isActive() && $b->ends_at->isFuture()],
            // Jam main sudah lewat tapi pegawai lupa menandai datang/tidak datang.
            'lewat' => ['Lewat jam, belum ditandai', fn ($b) => $b->status === BookingStatus::Confirmed],
        ];
        $rest = $bookings;
        $sorted = [];
        foreach ($groups as $key => [$title, $match]) {
            $sorted[$key] = $rest->filter($match);
            $rest = $rest->diff($sorted[$key]);
        }
        $done = $rest;
        $openCount = $bookings->count() - $done->count();
    @endphp

    <div class="page-head page-head-tight">
        <h1>{{ $isToday ? 'Hari ini' : tanggal($date) }}</h1>
        @if ($isToday)<p>{{ tanggal($date) }}</p>@endif
    </div>

    @include('staff.partials.date-picker', ['route' => 'staff.schedule'])

    <form method="GET" action="{{ route('staff.schedule') }}" class="search-box" role="search">
        <label class="sr-only" for="cari">Cari pesanan</label>
        <input id="cari" name="cari" type="search" value="{{ $search }}" placeholder="Cari kode, nama, atau nomor WA" autocomplete="off">
        <button class="btn" type="submit">Cari</button>
    </form>

    @if ($results !== null)
        <section class="list-section" aria-labelledby="hasil-cari">
            <h2 id="hasil-cari">Hasil cari "{{ $search }}"</h2>
            @forelse ($results as $booking)
                @include('staff.partials.booking-card', ['booking' => $booking, 'withDate' => true])
            @empty
                <p class="muted">Tidak ada pesanan yang cocok.</p>
            @endforelse
            <p><a href="{{ route('staff.schedule', ['tanggal' => $date->format('Y-m-d')]) }}">Tutup hasil cari</a></p>
        </section>
    @else
        @if ($waiting > 0)
            <a class="attention" href="{{ route('staff.payments.index') }}">
                <span>{{ $waiting }} bukti bayar perlu dicek</span>
                <span aria-hidden="true">&rsaquo;</span>
            </a>
        @endif

        @if ($openCount > 0)
            <nav class="group-chips" aria-label="Lompat ke kelompok">
                @foreach ($groups as $key => [$title])
                    @if ($sorted[$key]->isNotEmpty())
                        <a href="#kelompok-{{ $key }}" class="group-chip group-{{ $key }}">{{ $title }} <b>{{ $sorted[$key]->count() }}</b></a>
                    @endif
                @endforeach
            </nav>
        @endif

        @foreach ($groups as $key => [$title])
            @if ($sorted[$key]->isNotEmpty())
                <section class="list-section group-{{ $key }}" id="kelompok-{{ $key }}" aria-labelledby="judul-{{ $key }}">
                    <h2 id="judul-{{ $key }}">{{ $title }} <span class="group-count">{{ $sorted[$key]->count() }}</span></h2>
                    @foreach ($sorted[$key] as $booking)
                        @include('staff.partials.booking-card', ['booking' => $booking])
                    @endforeach
                </section>
            @endif
        @endforeach

        @if ($openCount === 0)
            <div class="card empty-state">
                <p><strong>Belum ada pesanan yang perlu diurus {{ $isToday ? 'hari ini' : 'di tanggal ini' }}.</strong></p>
            </div>
        @endif

        <a class="btn btn-lg btn-block btn-outline" href="{{ route('staff.bookings.create', ['tanggal' => $date->format('Y-m-d')]) }}">+ Pesanan baru</a>

        @if ($done->isNotEmpty())
            <details class="done-list">
                <summary>{{ $done->count() }} pesanan selesai atau batal</summary>
                @foreach ($done as $booking)
                    @include('staff.partials.booking-card', ['booking' => $booking])
                @endforeach
            </details>
        @endif
    @endif
@endsection
