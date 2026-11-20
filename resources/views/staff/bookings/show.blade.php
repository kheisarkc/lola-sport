@extends('layouts.app', ['title' => 'Pesanan '.$booking->code])

{{-- Detail pesanan untuk pegawai: tunai (UC-09), batal (UC-10), kehadiran (UC-12), buka slot (UC-13). --}}
@use('App\Enums\BookingStatus')

@section('content')
    @php
        $paid = $booking->paidAmount();
        $remaining = $booking->total - $paid;
        $hasProof = $booking->hasPendingPayment();
        $active = $booking->status->isActive();
        $wa = 'https://wa.me/62'.substr($booking->customer_phone, 1).'?text='.rawurlencode('Halo '.$booking->customer_name.', ini Lola Sport soal pesanan '.$booking->code.'.');
    @endphp

    <div class="page-head">
        <a class="back-link" href="{{ route('staff.schedule', ['tanggal' => $booking->play_date->format('Y-m-d')]) }}">&larr; {{ tanggal($booking->play_date) }}</a>
        <h1>{{ $booking->isBlock() ? 'Lapangan ditutup' : $booking->customer_name }}</h1>
        <div class="booking-card-tags">
            <span class="booking-card-code">{{ $booking->code }}</span>
            <x-status :status="$booking->status" />
            @unless ($booking->isBlock())
                <span class="badge badge-money">{{ $booking->paymentLabel() }}</span>
            @endunless
        </div>
    </div>

    <div class="detail-action">
        @include('staff.partials.next-action', ['booking' => $booking])

        @if ($booking->status === BookingStatus::Pending && $paid === 0 && ! $hasProof)
            {{-- Pelanggan web datang dan membayar di tempat sebelum batas waktunya. --}}
            <details class="reject">
                <summary class="btn btn-block btn-outline">Pelanggan bayar langsung di tempat</summary>
                <form method="POST" action="{{ route('staff.payments.cash', $booking) }}" class="card"
                      onsubmit="return confirm('Uangnya sudah diterima?');">
                    @csrf
                    <fieldset class="field">
                        <legend>Uang yang diterima</legend>
                        <div class="money-choice">
                            <label><input type="radio" name="amount" value="{{ $booking->minimum_dp }}" required><span>DP <strong>{{ rupiah($booking->minimum_dp) }}</strong></span></label>
                            <label><input type="radio" name="amount" value="{{ $remaining }}"><span>Lunas <strong>{{ rupiah($remaining) }}</strong></span></label>
                        </div>
                    </fieldset>
                    <fieldset class="field">
                        <legend>Dibayar pakai</legend>
                        <div class="money-choice">
                            <label><input type="radio" name="cara" value="tunai" required><span>Tunai</span></label>
                            <label><input type="radio" name="cara" value="qris"><span>QRIS</span></label>
                        </div>
                    </fieldset>
                    <button class="btn btn-lg btn-block" type="submit">Simpan pembayaran</button>
                </form>
            </details>
        @endif
    </div>

    <section class="card">
        <dl class="summary">
            <dt>Main</dt><dd>{{ tanggal($booking->starts_at) }}, {{ jam($booking->starts_at) }}–{{ jam($booking->ends_at) }} WIB</dd>
            <dt>Lapangan</dt><dd>{{ $booking->court->name }}</dd>
            @if ($booking->isBlock())
                <dt>Alasan</dt><dd>{{ $booking->note }}</dd>
            @else
                <dt>WhatsApp</dt><dd>{{ $booking->customer_phone }}</dd>
                <dt>Total</dt><dd>{{ rupiah($booking->total) }}</dd>
                <dt>Sudah bayar</dt><dd>{{ rupiah($paid) }}</dd>
                <dt>Sisa</dt><dd><strong>{{ rupiah($remaining) }}</strong></dd>
                <dt>Dipesan</dt><dd>{{ $booking->source === \App\Enums\BookingSource::Website ? 'Lewat web' : 'Dicatat pegawai' }}</dd>
                @if ($booking->status === BookingStatus::Pending)
                    <dt>Batas kirim bukti</dt><dd>{{ waktu($booking->expires_at) }}</dd>
                @endif
                @if ($booking->checked_in_at)
                    <dt>Datang</dt><dd>{{ waktu($booking->checked_in_at) }}</dd>
                @endif
                @if ($booking->note)
                    <dt>Catatan</dt><dd>{{ $booking->note }}</dd>
                @endif
            @endif
        </dl>
    </section>

    @if ($booking->isBlock())
        @if ($active)
            <form method="POST" action="{{ route('staff.bookings.cancel', $booking) }}"
                  onsubmit="return confirm('Buka lagi jam ini supaya bisa dipesan?');">
                @csrf
                <button class="btn btn-lg btn-block btn-outline" type="submit">Buka lapangan lagi</button>
            </form>
        @endif
    @else
        <div class="more-actions">
            <a class="btn btn-outline btn-block" href="{{ $wa }}" target="_blank" rel="noopener">Chat WhatsApp pelanggan</a>

            @if ($booking->status === BookingStatus::Confirmed && $booking->starts_at->isPast())
                <form method="POST" action="{{ route('staff.bookings.no-show', $booking) }}"
                      onsubmit="return confirm({{ Js::from($booking->customer_name.' tidak datang? Uang yang sudah dibayar tidak dikembalikan.') }});">
                    @csrf
                    <button class="btn btn-outline btn-block" type="submit">Pelanggan tidak datang</button>
                </form>
            @endif

            @if ($active && $booking->starts_at->isFuture())
                <details class="reject">
                    <summary class="btn btn-block btn-danger">Batalkan pesanan</summary>
                    <form method="POST" action="{{ route('staff.bookings.cancel', $booking) }}"
                          onsubmit="return confirm('Yakin batalkan pesanan ini? Tidak bisa diurungkan.');">
                        @csrf
                        <div class="field">
                            <label for="reason">Alasan</label>
                            <input id="reason" name="reason" maxlength="200" placeholder="Contoh: pelanggan minta batal lewat WA">
                        </div>
                        <p class="small">Uang yang sudah dibayar ({{ rupiah($paid) }}) tidak dikembalikan dan tetap tercatat.</p>
                        <button class="btn btn-danger btn-block" type="submit">Ya, batalkan</button>
                    </form>
                </details>
            @endif
        </div>

        <section class="card">
            <h2>Pembayaran</h2>
            @if ($booking->payments->isEmpty())
                <p class="muted">Belum ada pembayaran.</p>
            @else
                <ul class="pay-list">
                    @foreach ($booking->payments as $payment)
                        <li>
                            <div>
                                <strong>{{ rupiah($payment->amount) }}</strong> · {{ $payment->method->label() }}
                                @if ($payment->proof_path)
                                    · <a href="{{ route('staff.payments.proof', $payment) }}" target="_blank" rel="noopener">lihat bukti</a>
                                @endif
                                <div class="small muted">
                                    {{ waktu($payment->created_at) }}@if ($payment->reviewer), diperiksa {{ $payment->reviewer->name }}@endif
                                </div>
                                @if ($payment->rejection_reason)<div class="small">{{ $payment->rejection_reason }}</div>@endif
                            </div>
                            <span class="badge badge-{{ $payment->status->value }}">{{ $payment->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <section class="card">
        <h2>Riwayat perubahan</h2>
        <ul class="timeline">
            @foreach ($booking->events as $event)
                <li>
                    <strong>{{ $event->label() }}</strong>
                    <span class="muted small">· {{ $event->actor?->name ?? 'Sistem/pelanggan' }} · {{ waktu($event->created_at) }}</span>
                    @if (! empty($event->details['reason']))
                        <div class="small">{{ $event->details['reason'] }}</div>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endsection
