@extends('layouts.app', ['title' => 'Masuk'])

{{-- UC-05: hanya pegawai dan pemilik yang login. --}}
@section('content')
    <section class="card narrow">
        <div class="form-card-head">
            <img src="{{ asset('images/logo.webp') }}" alt="" width="56" height="56">
            <h1>Masuk</h1>
            <p>Khusus pegawai dan pemilik Lola Sport.</p>
        </div>
        <div class="alert alert-info small demo-accounts">
            <strong>Akun demo untuk mencoba</strong>
            <dl>
                <dt>Pegawai</dt><dd>pegawai@lola.test</dd>
                <dt>Pemilik</dt><dd>pemilik@lola.test</dd>
                <dt>Kata sandi</dt><dd>{{ \Database\Seeders\DatabaseSeeder::DEMO_PASSWORD }}</dd>
            </dl>
        </div>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Kata sandi</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-block" type="submit">Masuk</button>
        </form>
    </section>
@endsection
