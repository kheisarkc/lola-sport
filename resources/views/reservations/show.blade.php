@extends('layouts.app', ['title' => 'Reservasi '.$booking->code])

{{-- UC-04 status reservasi dan UC-03 kirim bukti pembayaran. --}}
@section('content')
    @php
        $paid = $booking->paidAmount();
        $remaining = $booking->total - $paid;
        $waiting = $booking->hasPendingPayment();
        $canPay = $booking->status->isActive() && $remaining > 0 && ! $waiting;
        $isPending = $booking->status === \App\Enums\BookingStatus::Pending;
        // Pilihan dari langkah 2 (atau dari isian yang gagal divalidasi). Null berarti pelanggan belum memilih.
        $choice = old('amount') !== null ? ((int) old('amount') === $remaining ? 'lunas' : 'dp') : $plan;
        $dueNow = $choice === 'lunas' ? $remaining : $booking->minimum_dp;
        // Penolakan terakhir ditampilkan selama pelanggan belum mengirim bukti baru.
        $last = $booking->payments->sortByDesc('id')->first();
        $rejected = $last?->status === \App\Enums\PaymentStatus::Rejected ? $last : null;
        $showWhatsApp = $booking->status === \App\Enums\BookingStatus::Review
            || $booking->status === \App\Enums\BookingStatus::Confirmed
            || ($booking->status === \App\Enums\BookingStatus::Cancelled && $rejected);
        $whatsApp = 'https://wa.me/'.config('lola.whatsapp').'?text='.rawurlencode('Halo Lola Sport, saya mau tanya pesanan '.$booking->code.'.');
    @endphp

    @if ($isPending)
        <x-booking-steps :current="3" />
    @endif

    <div class="page-head">
        <a class="back-link" href="{{ route('schedule') }}">&larr; Kembali ke jadwal</a>
        <h1>Reservasi kamu</h1>
        <p>Simpan kode ini. Kode dan nomor WhatsApp dipakai untuk membuka halaman ini lagi.</p>
        <span class="code-box">{{ $booking->code }}</span>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <h2>Detail</h2>
            <dl class="summary">
                <dt>Status</dt><dd><x-status :status="$booking->status" /></dd>
                <dt>Pembayaran</dt><dd>{{ $booking->paymentLabel() }}</dd>
                <dt>Lapangan</dt><dd>{{ $booking->court->name }}</dd>
                <dt>Tanggal</dt><dd>{{ tanggal($booking->starts_at) }}</dd>
                <dt>Jam</dt><dd>{{ jam($booking->starts_at) }}–{{ jam($booking->ends_at) }} WIB</dd>
                <dt>Pemesan</dt><dd>{{ $booking->customer_name }}</dd>
                <dt>Total sewa</dt><dd>{{ rupiah($booking->total) }}</dd>
                <dt>Sudah diterima</dt><dd>{{ rupiah($paid) }}</dd>
                <dt>Sisa</dt><dd>{{ rupiah($remaining) }}</dd>
            </dl>

            @if ($isPending)
                <div class="alert alert-info small">
                    Bayar lewat QRIS lalu kirim bukti sebelum <strong>{{ waktu($booking->expires_at) }}</strong>@if ($canPay) <span class="countdown" data-deadline="{{ $booking->expires_at->toIso8601String() }}"></span>@endif.
                    Lewat dari itu pesanan batal otomatis.
                </div>
                @if ($booking->payments->isEmpty())
                    <form method="POST" action="{{ route('reservations.cancel', $booking) }}"
                          onsubmit="return confirm('Batalkan pesanan {{ $booking->code }}? Jam ini akan dibuka lagi untuk orang lain.');">
                        @csrf
                        <button class="btn btn-danger btn-block" type="submit">Batalkan pesanan</button>
                    </form>
                    <p class="small muted cancel-note">Salah pilih jam? Batalkan dulu, lalu pilih lagi dari jadwal.</p>
                @endif
            @elseif ($booking->status === \App\Enums\BookingStatus::Review)
                <div class="alert alert-info small">Bukti diterima. Penjaga sedang mengecek uang masuk. Buka lagi halaman ini untuk melihat hasilnya.</div>
            @elseif ($booking->status === \App\Enums\BookingStatus::Expired)
                <div class="alert alert-error small">Bukti pembayaran tidak dikirim sampai batas waktu. Silakan pesan ulang dari jadwal.</div>
            @elseif ($booking->status === \App\Enums\BookingStatus::Confirmed && $remaining > 0)
                <div class="alert alert-success small">Jadwal kamu aman. Sisa {{ rupiah($remaining) }} dibayar sebelum main, lewat QRIS di sini atau tunai di tempat.</div>
            @elseif ($booking->status === \App\Enums\BookingStatus::Confirmed)
                <div class="alert alert-success small">Jadwal kamu aman dan sudah lunas. Tunjukkan kode booking ke penjaga saat datang.</div>
            @elseif ($booking->status === \App\Enums\BookingStatus::CheckedIn)
                <div class="alert alert-success small">Selamat bermain.</div>
            @elseif ($booking->status === \App\Enums\BookingStatus::Cancelled && $rejected && $paid === 0)
                <div class="alert alert-error small">
                    <strong>Bukti ditolak:</strong> {{ $rejected->rejection_reason }}.
                    Pesanan dibatalkan dan jamnya dibuka lagi. Kalau uang sudah terkirim, chat penjaga lewat WhatsApp dengan menyebut kode ini.
                </div>
            @elseif (in_array($booking->status, [\App\Enums\BookingStatus::Cancelled, \App\Enums\BookingStatus::NoShow], true))
                <div class="alert alert-error small">
                    Pesanan ini sudah selesai ({{ strtolower($booking->status->label()) }}).
                    @if ($paid > 0) Uang yang sudah dibayar tidak dikembalikan. @endif
                </div>
            @endif

            @if ($showWhatsApp)
                <a class="btn btn-outline btn-block" href="{{ $whatsApp }}" target="_blank" rel="noopener">Tanya lewat WhatsApp</a>
                <p class="small muted cancel-note">
                    @if ($booking->status === \App\Enums\BookingStatus::Confirmed)
                        Mau batal atau pindah jam? Chat penjaga dengan menyebut kode booking.
                    @else
                        Untuk bertanya. Bukti bayar tetap diunggah di halaman ini.
                    @endif
                </p>
            @endif
        </section>

        <section class="card">
            <h2>Kirim bukti pembayaran</h2>
            @if ($waiting)
                <p>Bukti kamu sedang diperiksa pegawai. Halaman ini akan menampilkan hasilnya.</p>
            @elseif (! $canPay)
                <p class="muted">Tidak ada pembayaran yang perlu dikirim.</p>
            @else
                <form method="POST" action="{{ route('reservations.pay', $booking) }}" enctype="multipart/form-data">
                    @csrf
                    @if ($paid === 0 && $choice !== null)
                        <input type="hidden" name="amount" value="{{ $dueNow }}">
                        <div class="pay-fixed">
                            <span>
                                {{ $choice === 'lunas' ? 'Bayar lunas' : 'Bayar DP' }}
                                <a class="pay-switch" href="{{ route('reservations.show', [$booking, 'bayar' => $choice === 'lunas' ? 'dp' : 'lunas']) }}">
                                    Ganti ke {{ $choice === 'lunas' ? 'DP '.rupiah($booking->minimum_dp) : 'lunas '.rupiah($remaining) }}
                                </a>
                            </span>
                            <strong>{{ rupiah($dueNow) }}</strong>
                        </div>
                    @elseif ($paid === 0)
                        <fieldset class="pay-choice">
                            <legend>Mau bayar DP atau lunas?</legend>
                            <label class="pay-option">
                                <input type="radio" name="amount" value="{{ $booking->minimum_dp }}" checked>
                                <span class="pay-option-text">
                                    <strong>Bayar DP</strong>
                                    <small>Sisa {{ rupiah($remaining - $booking->minimum_dp) }} dilunasi sebelum main.</small>
                                </span>
                                <span class="pay-option-amount">{{ rupiah($booking->minimum_dp) }}</span>
                            </label>
                            <label class="pay-option">
                                <input type="radio" name="amount" value="{{ $remaining }}">
                                <span class="pay-option-text">
                                    <strong>Bayar lunas</strong>
                                    <small>Tidak perlu bayar lagi di tempat.</small>
                                </span>
                                <span class="pay-option-amount">{{ rupiah($remaining) }}</span>
                            </label>
                        </fieldset>
                    @else
                        <input type="hidden" name="amount" value="{{ $remaining }}">
                        <div class="pay-fixed">
                            <span>Pelunasan</span>
                            <strong>{{ rupiah($remaining) }}</strong>
                        </div>
                    @endif

                    <div class="qris-box">
                        <div class="qris-code" aria-hidden="true"></div>
                        <div>
                            <strong>Bayar lewat QRIS Lola Sport</strong>
                            <p class="small muted">Simulasi untuk demo, belum ada QRIS asli. Setelah bayar, unggah foto atau PDF buktinya di bawah.</p>
                        </div>
                    </div>

                    <div class="field">
                        <label for="proof">Bukti pembayaran <span class="hint">JPG, PNG, atau PDF, maksimal 2 MB.</span></label>
                        <input id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                    <button class="btn btn-block btn-lg" type="submit">Kirim bukti</button>
                    @if ($paid > 0)
                        {{-- Tunai hanya untuk pelunasan; pembayaran pertama lewat QRIS supaya slot aman. --}}
                        <p class="small muted pay-cash-note">Sisa ini juga bisa dibayar tunai ke pegawai sebelum main.</p>
                    @endif
                </form>
            @endif
        </section>
    </div>

    @if ($booking->payments->isNotEmpty())
        <section class="card">
            <h2>Riwayat pembayaran</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Dikirim</th><th>Metode</th><th class="right">Nominal</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($booking->payments as $payment)
                        <tr>
                            <td class="nowrap">{{ waktu($payment->created_at) }}</td>
                            <td>{{ $payment->method->label() }}</td>
                            <td class="right">{{ rupiah($payment->amount) }}</td>
                            <td>
                                <span class="badge badge-{{ $payment->status->value }}">{{ $payment->status->label() }}</span>
                                @if ($payment->rejection_reason)
                                    <div class="small muted">{{ $payment->rejection_reason }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <script>
        (function () {
            var el = document.querySelector('.countdown');
            if (!el) return;
            var deadline = new Date(el.dataset.deadline).getTime();
            function tick() {
                var left = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
                el.textContent = left > 0
                    ? '(sisa ' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0') + ')'
                    : '(waktu habis, muat ulang halaman)';
                if (left > 0) setTimeout(tick, 1000);
            }
            tick();
        })();
    </script>

@endsection
