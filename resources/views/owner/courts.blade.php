@extends('layouts.app', ['title' => 'Pengaturan'])

{{-- UC-16: lapangan, harga, dan aturan pemesanan (KF-18, KF-25). --}}
@section('content')
    <div class="page-head page-head-tight">
        <h1>Pengaturan</h1>
        <p>Perubahan hanya berlaku untuk pesanan baru. Pesanan lama tetap memakai harga dan aturan saat dipesan.</p>
    </div>

    <section class="card">
        <h2>Aturan pemesanan</h2>
        <form method="POST" action="{{ route('owner.settings.update') }}" class="inline-form">
            @csrf
            @method('PUT')
            <div class="field">
                <label for="dp_percent">Minimal DP (%)</label>
                <input id="dp_percent" type="number" name="dp_percent" min="1" max="100" value="{{ old('dp_percent', $setting->dp_percent) }}" required>
            </div>
            <div class="field">
                <label for="hold_minutes">Batas kirim bukti bayar (menit)</label>
                <input id="hold_minutes" type="number" name="hold_minutes" min="5" max="240" value="{{ old('hold_minutes', $setting->hold_minutes) }}" required>
            </div>
            <button class="btn" type="submit">Simpan aturan</button>
        </form>
        <p class="small muted" style="margin-top: .75rem">DP 50% dari pemilik (8 Nov 2026). Batas bayar 30 menit keputusan tim, bisa diubah di sini.</p>
    </section>

    <h2>Lapangan dan harga</h2>
    @foreach ($courts as $court)
        <form method="POST" action="{{ route('owner.courts.update', $court) }}" class="card inline-form">
            @csrf
            @method('PUT')
            @include('owner.partials.court-fields', ['court' => $court, 'prefix' => 'court-'.$court->id])
            <button class="btn btn-outline" type="submit">Simpan</button>
        </form>
    @endforeach

    <details class="card add-court">
        <summary>+ Tambah lapangan</summary>
        <form method="POST" action="{{ route('owner.courts.store') }}" class="inline-form">
            @csrf
            @include('owner.partials.court-fields', ['court' => null, 'prefix' => 'new'])
            <button class="btn" type="submit">Tambah</button>
        </form>
    </details>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-outline btn-block">Keluar</button>
    </form>
@endsection
