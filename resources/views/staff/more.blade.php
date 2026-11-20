@extends('layouts.app', ['title' => 'Lainnya'])

@section('content')
    <div class="page-head">
        <h1>Lainnya</h1>
        <p>Masuk sebagai {{ auth()->user()->name }}.</p>
    </div>

    <nav class="menu-list" aria-label="Menu lainnya">
        <a href="{{ route('staff.blocks.create') }}">
            <strong>Tutup lapangan</strong>
            <span>Untuk perbaikan atau acara, supaya jam itu tidak bisa dipesan.</span>
        </a>
        <a href="{{ route('schedule') }}" target="_blank" rel="noopener">
            <strong>Lihat jadwal versi pelanggan</strong>
            <span>Tampilan yang dilihat pelanggan di web.</span>
        </a>
    </nav>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-lg btn-block btn-outline">Keluar</button>
    </form>
@endsection
