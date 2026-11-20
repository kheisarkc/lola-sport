<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2f4a6b">
    <link rel="icon" href="{{ asset('images/logo.webp') }}" type="image/webp">
    <title>{{ $title ?? 'Jadwal' }} · Lola Sport</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap">
    {{-- ?v= berubah tiap CSS diubah, jadi browser boleh menyimpan CSS lama-lama tanpa tertinggal versi. --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    {{-- Halaman tujuan diambil lebih dulu saat link disentuh/ditunjuk, jadi pindah halaman terasa instan. --}}
    @verbatim
    <script type="speculationrules">
    {"prefetch": [{"where": {"and": [
        {"href_matches": "/*"},
        {"not": {"href_matches": "/*/unduh"}},
        {"not": {"selector_matches": ".slot-available, [data-no-prefetch]"}}
    ]}, "eagerness": "moderate"}]}
    </script>
    @endverbatim
</head>
<body @class(['is-panel' => auth()->check(), 'has-bottom-nav' => auth()->check(), 'is-staff' => auth()->user()?->isStaff(), 'is-owner' => auth()->user()?->isOwner()])>
<header @class(['topbar', 'topbar-public' => ! auth()->check()])>
    <div class="topbar-inner">
        @auth
            <a class="brand" href="{{ auth()->user()->homeUrl() }}">
                <img class="brand-logo" src="{{ asset('images/logo.webp') }}" alt="" width="40" height="40">
                <span class="brand-text">Lola Sport <small>{{ auth()->user()->role->label() }}</small></span>
            </a>
        @else
            <a class="brand" href="{{ route('schedule') }}">
                <img class="brand-logo" src="{{ asset('images/logo.webp') }}" alt="" width="40" height="40">
                <span class="brand-text">Lola Sport <small>Futsal &amp; Badminton</small></span>
            </a>
            <nav class="nav" aria-label="Menu utama">
                <a href="{{ route('schedule') }}" @class(['active' => request()->routeIs('schedule')])>Jadwal</a>
                <a href="{{ route('reservations.lookup') }}" @class(['active' => request()->routeIs('reservations.lookup')])>Cek pesanan</a>
            </nav>
        @endauth
    </div>
</header>

@yield('hero')

<main>
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

@if (auth()->user()?->isStaff())
    {{-- Menu pegawai di bawah layar: selalu terlihat dan mudah dijangkau jempol. --}}
    @php $waiting = \App\Models\Payment::waitingCount(); @endphp
    <nav class="bottom-nav" aria-label="Menu pegawai">
        <a href="{{ route('staff.schedule') }}" @class(['active' => request()->routeIs('staff.schedule', 'staff.bookings.show')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            Hari ini
        </a>
        <a href="{{ route('staff.payments.index') }}" @class(['active' => request()->routeIs('staff.payments.*')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>
            Cek bayar
            @if ($waiting > 0)<span class="nav-count" aria-label="{{ $waiting }} menunggu">{{ $waiting }}</span>@endif
        </a>
        <a href="{{ route('staff.bookings.create') }}" @class(['active' => request()->routeIs('staff.bookings.create')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
            Pesanan
        </a>
        <a href="{{ route('staff.more') }}" @class(['active' => request()->routeIs('staff.more', 'staff.blocks.*')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
            Lainnya
        </a>
    </nav>
@elseif (auth()->user()?->isOwner())
    {{-- Menu pemilik: hanya melihat angka dan mengatur harga/aturan. --}}
    <nav class="bottom-nav" aria-label="Menu pemilik">
        <a href="{{ route('owner.revenue') }}" @class(['active' => request()->routeIs('owner.revenue')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
            Ringkasan
        </a>
        <a href="{{ route('owner.transactions') }}" @class(['active' => request()->routeIs('owner.transactions')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/></svg>
            Transaksi
        </a>
        <a href="{{ route('owner.courts.index') }}" @class(['active' => request()->routeIs('owner.courts.*')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/></svg>
            Pengaturan
        </a>
    </nav>
@else
<footer class="footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <img src="{{ asset('images/logo.webp') }}" alt="" width="36" height="36">
            <div>
                <strong>Lola Sport Bogor</strong>
                Futsal &amp; Badminton · Buka 07.00–01.00 WIB
            </div>
        </div>
        <div class="footer-meta">
            <span>Prototipe Capstone Kelompok Arto, Telkom University</span>
            @guest
                <a class="staff-link" href="{{ route('login') }}">Masuk pegawai</a>
            @endguest
        </div>
    </div>
</footer>
@endif
<script>
    // Cegah tombol simpan/terima tertekan dua kali saat koneksi lambat.
    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented || event.target.method.toLowerCase() !== 'post') return;
        var buttons = event.target.querySelectorAll('button[type="submit"]');
        setTimeout(function () {
            buttons.forEach(function (b) { b.disabled = true; b.setAttribute('aria-busy', 'true'); });
        }, 0);
    });
    // Kembali lewat tombol back browser: tombol dinyalakan lagi.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[aria-busy="true"]').forEach(function (b) { b.disabled = false; b.removeAttribute('aria-busy'); });
    });
</script>
</body>
</html>
