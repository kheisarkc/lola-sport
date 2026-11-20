@extends('layouts.app', ['title' => 'Tutup lapangan'])

{{-- UC-13: menutup slot untuk perawatan atau acara. --}}
@section('content')
    <div class="page-head">
        <h1>Tutup lapangan</h1>
        <p>Jam yang ditutup tampil "Ditutup" di jadwal pelanggan dan tidak bisa dipesan.</p>
    </div>

    <section class="card" style="max-width: 560px">
        <form method="POST" action="{{ route('staff.blocks.store') }}">
            @csrf
            @include('staff.partials.slot-fields', ['defaults' => ['date' => $today->format('Y-m-d')]])
            <div class="field">
                <label for="reason">Alasan</label>
                <input id="reason" name="reason" value="{{ old('reason') }}" placeholder="Contoh: perbaikan rumput sintetis" required maxlength="200">
            </div>
            <button class="btn btn-lg btn-block" type="submit">Tutup lapangan</button>
        </form>
    </section>
    <p class="small muted">Untuk membuka lagi, buka kartunya di Hari ini lalu tekan "Buka lapangan lagi".</p>
@endsection
