@extends('layouts.app', ['title' => 'Pesan lapangan'])

{{-- UC-02 langkah 2: jam sudah dipilih di jadwal; pelanggan isi nama + WA (KF-03) dan pilih DP atau lunas. --}}
@php
    $end = $start->addHours($duration);
    $plan = old('pembayaran', 'dp');
    $changeUrl = route('schedule', [
        'tanggal' => $date->format('Y-m-d'), 'lapangan' => $court->id, 'jam' => $start->hour, 'durasi' => $duration,
    ]).'#pilih-lapangan';
@endphp

@section('content')
    <x-booking-steps :current="2" />

    <div class="page-head">
        <h1>Data dan pembayaran</h1>
        <p>Cek lagi jam yang kamu pilih, lalu isi data pemesan.</p>
    </div>

    <div class="booking-layout">
        <section class="card booking-summary" aria-labelledby="ringkasan">
            <img class="summary-photo" src="{{ $court->photoUrl() }}" alt="Foto {{ $court->name }} di Lola Sport">
            <div class="summary-body">
                <div class="summary-title">
                    <h2 id="ringkasan">{{ $court->name }}</h2>
                    <a href="{{ $changeUrl }}">Ubah jam</a>
                </div>
                <dl class="summary">
                    <dt>Tanggal</dt><dd>{{ tanggal($start) }}</dd>
                    <dt>Jam</dt><dd>{{ jam($start) }}–{{ jam($end) }} WIB</dd>
                    <dt>Durasi</dt><dd>{{ $duration }} jam × {{ rupiah($court->hourly_rate) }}</dd>
                </dl>
                <div class="total-row">
                    <span>Total sewa</span>
                    <strong>{{ rupiah($total) }}</strong>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="data-pemesan">
            <form method="POST" action="{{ route('reservations.store') }}">
                @csrf
                <input type="hidden" name="court_id" value="{{ $court->id }}">
                <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                <input type="hidden" name="hour" value="{{ $start->hour }}">
                <input type="hidden" name="duration" value="{{ $duration }}">

                <h2 id="data-pemesan">Data pemesan</h2>
                <div class="field">
                    <label for="customer_name">Nama pemesan</label>
                    <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" autocomplete="name" placeholder="Raka Pratama" required maxlength="80">
                </div>
                <div class="field">
                    <label for="customer_phone">Nomor WhatsApp
                        <span class="hint">Dipakai untuk cek status reservasi bersama kode booking.</span>
                    </label>
                    <input id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" inputmode="tel" autocomplete="tel" placeholder="081234567890" required>
                </div>

                <fieldset class="pay-choice">
                    <legend>Mau bayar berapa sekarang?</legend>
                    <label class="pay-option">
                        <input type="radio" name="pembayaran" value="dp" @checked($plan !== 'lunas')>
                        <span class="pay-option-text">
                            <strong>Bayar DP {{ $setting->dp_percent }}%</strong>
                            <small>Sisa {{ rupiah($total - $minimumDp) }} dilunasi sebelum main, lewat web atau tunai.</small>
                        </span>
                        <span class="pay-option-amount">{{ rupiah($minimumDp) }}</span>
                    </label>
                    <label class="pay-option">
                        <input type="radio" name="pembayaran" value="lunas" @checked($plan === 'lunas')>
                        <span class="pay-option-text">
                            <strong>Bayar lunas</strong>
                            <small>Tidak perlu bayar lagi di tempat.</small>
                        </span>
                        <span class="pay-option-amount">{{ rupiah($total) }}</span>
                    </label>
                </fieldset>

                <p class="small muted">
                    Setelah pesan, slot ditahan {{ $setting->hold_minutes }} menit untuk kamu. Kirim bukti bayar sebelum waktunya habis.
                </p>
                <button class="btn btn-block btn-lg" type="submit">Pesan dan bayar</button>
            </form>
        </section>
    </div>
@endsection
