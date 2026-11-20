@extends('layouts.app', ['title' => 'Pesanan baru'])

{{--
    UC-07: pesanan dari pelanggan yang datang langsung atau menelepon.
    Pegawai mengetuk jam kosong, mengisi nama dan WA, lalu mencatat uang yang diterima saat itu.
--}}
@section('content')
    @php
        $court = $row['court'] ?? null;
        $slots = collect($row['slots'] ?? []);
        $dateParam = $date->format('Y-m-d');
    @endphp

    <div class="page-head">
        <h1>Pesanan baru</h1>
        <p>Untuk pelanggan yang datang langsung atau menelepon. Pesanan tersimpan setelah uangnya diterima.</p>
    </div>

    @include('staff.partials.date-picker', ['route' => 'staff.bookings.create', 'params' => ['lapangan' => $court?->id], 'allowPast' => false])

    @if ($court === null)
        <div class="card">Belum ada lapangan yang aktif.</div>
    @else
        <nav class="court-tabs" aria-label="Pilih lapangan">
            @foreach ($courts as $c)
                <a href="{{ route('staff.bookings.create', ['tanggal' => $dateParam, 'lapangan' => $c->id]) }}"
                   @class(['active' => $c->id === $court->id])
                   @if ($c->id === $court->id) aria-current="page" @endif>{{ $c->name }}</a>
            @endforeach
        </nav>

        <form method="POST" action="{{ route('staff.bookings.store') }}" id="walk-in" class="card">
            @csrf
            <input type="hidden" name="court_id" value="{{ $court->id }}">
            <input type="hidden" name="date" value="{{ $dateParam }}">
            <input type="hidden" name="hour" id="hour" value="{{ old('hour') }}">
            <input type="hidden" name="duration" id="duration" value="{{ old('duration', 1) }}">

            <fieldset class="field">
                <legend>1. Ketuk jam main <span class="hint">{{ rupiah($court->hourly_rate) }}/jam. Bisa beberapa jam berurutan.</span></legend>
                <div class="staff-slots">
                    @foreach ($slots as $slot)
                        @if ($slot['status'] === 'available')
                            <button type="button" class="staff-slot" data-index="{{ $loop->index }}" data-hour="{{ $slot['start']->hour }}"
                                    data-start="{{ jam($slot['start']) }}" data-end="{{ jam($slot['start']->addHour()) }}" aria-pressed="false">
                                {{ jam($slot['start']) }}
                            </button>
                        @elseif ($slot['status'] !== 'past')
                            <span class="staff-slot is-taken" title="{{ $slot['status'] === 'blocked' ? 'Ditutup' : 'Terisi' }}">
                                {{ jam($slot['start']) }}<small>{{ $slot['status'] === 'blocked' ? 'Ditutup' : 'Terisi' }}</small>
                            </span>
                        @endif
                    @endforeach
                </div>
                @if ($slots->where('status', 'available')->isEmpty())
                    <p class="muted">Tidak ada jam kosong di lapangan ini pada tanggal ini.</p>
                @endif
                <p class="picked" id="picked" aria-live="polite">Belum ada jam dipilih.</p>
            </fieldset>

            <div class="field">
                <label for="customer_name">2. Nama pemesan</label>
                <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required maxlength="80" autocomplete="off">
            </div>
            <div class="field">
                <label for="customer_phone">Nomor WhatsApp</label>
                <input id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" inputmode="tel" placeholder="0812xxxxxxxx" required>
            </div>

            <fieldset class="field">
                <legend>3. Uang yang diterima sekarang</legend>
                <div class="money-choice">
                    <label>
                        <input type="radio" name="pembayaran" value="dp" @checked(old('pembayaran') === 'dp') required>
                        <span>DP <strong data-money="dp">-</strong></span>
                    </label>
                    <label>
                        <input type="radio" name="pembayaran" value="lunas" @checked(old('pembayaran') === 'lunas')>
                        <span>Lunas <strong data-money="lunas">-</strong></span>
                    </label>
                </div>
            </fieldset>

            <fieldset class="field">
                <legend>4. Dibayar pakai</legend>
                <div class="money-choice">
                    <label>
                        <input type="radio" name="cara" value="tunai" @checked(old('cara') === 'tunai') required>
                        <span>Tunai</span>
                    </label>
                    <label>
                        <input type="radio" name="cara" value="qris" @checked(old('cara') === 'qris')>
                        <span>QRIS <small>cek uang masuk dulu</small></span>
                    </label>
                </div>
            </fieldset>

            <button class="btn btn-lg btn-block" type="submit" id="save">Simpan pesanan</button>
        </form>

        <script>
            (function () {
                var rate = {{ $court->hourly_rate }}, dpPercent = {{ \App\Models\Setting::current()->dp_percent }};
                var buttons = Array.prototype.slice.call(document.querySelectorAll('.staff-slot[data-index]'));
                var picked = [];
                var rupiah = function (n) { return 'Rp' + n.toLocaleString('id-ID'); };

                buttons.forEach(function (b) {
                    b.addEventListener('click', function () { choose(Number(b.dataset.index)); });
                });

                function choose(i) {
                    var min = Math.min.apply(null, picked), max = Math.max.apply(null, picked);
                    if (picked.length === 0) picked = [i];
                    else if (picked.indexOf(i) !== -1) picked = (i === min || i === max) ? picked.filter(function (x) { return x !== i; }) : [i];
                    else if (i === max + 1 || i === min - 1) picked.push(i);
                    else picked = [i];
                    render();
                }

                function render() {
                    var on = buttons.filter(function (b) { return picked.indexOf(Number(b.dataset.index)) !== -1; })
                        .sort(function (a, b) { return a.dataset.index - b.dataset.index; });
                    buttons.forEach(function (b) {
                        var sel = on.indexOf(b) !== -1;
                        b.classList.toggle('selected', sel);
                        b.setAttribute('aria-pressed', sel ? 'true' : 'false');
                    });
                    var total = rate * on.length;
                    document.querySelector('[data-money="dp"]').textContent = on.length ? rupiah(Math.ceil(total * dpPercent / 100)) : '-';
                    document.querySelector('[data-money="lunas"]').textContent = on.length ? rupiah(total) : '-';
                    document.getElementById('hour').value = on.length ? on[0].dataset.hour : '';
                    document.getElementById('duration').value = on.length || 1;
                    document.getElementById('picked').textContent = on.length
                        ? on.length + ' jam · ' + on[0].dataset.start + '–' + on[on.length - 1].dataset.end + ' · ' + rupiah(total)
                        : 'Belum ada jam dipilih.';
                }

                document.getElementById('walk-in').addEventListener('submit', function (e) {
                    if (!document.getElementById('hour').value) {
                        e.preventDefault();
                        document.getElementById('picked').textContent = 'Pilih jam main dulu.';
                        document.getElementById('picked').classList.add('is-error');
                    }
                });

                // Setelah gagal validasi, pilihan jam sebelumnya dipulihkan.
                var oldHour = document.getElementById('hour').value, oldDuration = Number(document.getElementById('duration').value) || 1;
                var start = buttons.filter(function (b) { return b.dataset.hour === oldHour; })[0];
                if (start) for (var k = 0; k < oldDuration; k++) choose(Number(start.dataset.index) + k);
            })();
        </script>
    @endif
@endsection
