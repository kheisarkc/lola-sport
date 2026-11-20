@extends('layouts.app', ['title' => 'Cek pesanan'])

{{-- UC-04: cek status memakai kode booking + nomor WhatsApp (KF-05). --}}
@section('content')
    <section class="card narrow">
        <div class="form-card-head">
            <h1>Cek pesanan</h1>
            <p>Masukkan kode booking dan nomor WhatsApp yang dipakai saat memesan.</p>
        </div>
        <form method="POST" action="{{ route('reservations.lookup') }}">
            @csrf
            <div class="field">
                <label for="code">Kode booking</label>
                <input id="code" name="code" value="{{ old('code') }}" placeholder="LS-XXXXXX" autocapitalize="characters" required>
            </div>
            <div class="field">
                <label for="customer_phone">Nomor WhatsApp</label>
                <input id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" inputmode="tel" placeholder="08xxxxxxxxxx" required>
            </div>
            <button class="btn btn-block" type="submit">Lihat reservasi</button>
        </form>
    </section>
@endsection
