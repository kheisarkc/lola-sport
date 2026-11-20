@extends('layouts.app', ['title' => 'Cek bayar'])

{{--
    UC-08: semua bukti yang menunggu, paling lama di atas. Yang diperiksa adalah uangnya
    di riwayat QRIS, karena foto bukti bisa diedit. Uang masuk omzet pada saat diterima.
--}}
@section('content')
    <div class="page-head page-head-tight">
        <h1>Cek bayar @if ($pending->isNotEmpty())<span class="group-count">{{ $pending->count() }}</span>@endif</h1>
    </div>

    @if ($pending->isEmpty())
        <div class="card empty-state">
            <p><strong>Semua bukti sudah dicek.</strong></p>
            <p class="muted">Bukti baru dari pelanggan akan muncul di sini.</p>
            <a class="btn btn-outline" href="{{ route('staff.schedule') }}">Ke Hari ini</a>
        </div>
    @else
        <div class="alert alert-info">
            Buka aplikasi QRIS Lola Sport dulu. Tekan <strong>Uang sudah masuk</strong> hanya kalau nominalnya
            ada di riwayat QRIS sekitar jam bukti dikirim. Foto bukti bisa diedit.
        </div>

        @foreach ($pending as $payment)
            @php $booking = $payment->booking; @endphp
            <section class="card proof-item" id="bukti-{{ $payment->id }}" aria-labelledby="bukti-judul-{{ $payment->id }}">
                <div class="proof-item-top">
                    @if (str_ends_with(strtolower((string) $payment->proof_path), '.pdf'))
                        <a class="proof-thumb proof-thumb-pdf" href="{{ route('staff.payments.proof', $payment) }}" target="_blank" rel="noopener">Buka PDF</a>
                    @else
                        <a class="proof-thumb" href="{{ route('staff.payments.proof', $payment) }}" target="_blank" rel="noopener" title="Ketuk untuk memperbesar">
                            <img src="{{ route('staff.payments.proof', $payment) }}" alt="Bukti bayar {{ $booking->code }}" loading="lazy">
                        </a>
                    @endif
                    <div class="proof-item-info">
                        <h2 id="bukti-judul-{{ $payment->id }}">{{ rupiah($payment->amount) }}</h2>
                        <p><strong>{{ $booking->customer_name }}</strong> · dikirim {{ jam($payment->created_at) }}@unless ($payment->created_at->isToday()), {{ tanggal($payment->created_at) }}@endunless</p>
                        <p class="small">
                            {{ $booking->court->name }}, {{ tanggal($booking->starts_at) }} {{ jam($booking->starts_at) }}–{{ jam($booking->ends_at) }}
                            · <a href="{{ route('staff.bookings.show', $booking) }}">{{ $booking->code }}</a>
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('staff.payments.approve', $payment) }}">
                    @csrf
                    <button class="btn btn-lg btn-block btn-success" type="submit">Uang sudah masuk</button>
                </form>
                <details class="reject">
                    <summary class="btn btn-block btn-danger">Tidak ada di riwayat QRIS</summary>
                    <form method="POST" action="{{ route('staff.payments.reject', $payment) }}">
                        @csrf
                        <div class="field">
                            <label for="reason-{{ $payment->id }}">Alasan, dikirim ke pelanggan</label>
                            <input id="reason-{{ $payment->id }}" name="reason" required minlength="3" maxlength="200" value="Uang belum masuk di riwayat QRIS">
                        </div>
                        <button class="btn btn-danger btn-block" type="submit">Tolak bukti</button>
                    </form>
                </details>
            </section>
        @endforeach
    @endif
@endsection
