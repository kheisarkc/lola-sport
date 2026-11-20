@extends('layouts.app', ['title' => 'Jadwal'])

{{-- UC-01: jadwal publik. Nama dan nomor pemesan tidak ditampilkan (KNF-03). --}}
@php
    $sports = collect($board)->groupBy(fn ($row) => $row['court']->sport);
    $cheapest = collect($board)->min(fn ($row) => $row['court']->hourly_rate);
    $nextDate = $date->addDay();
    $hasNextDate = $nextDate->lte($lastDate);
@endphp

@section('hero')
    <section class="hero">
        <img class="hero-photo" src="{{ asset('images/hero.webp') }}" alt="" width="1360" height="798" fetchpriority="high">
        <div class="hero-inner">
            <h1>Lola Sport Bogor</h1>
            <ul class="hero-meta">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
                    Kota Bogor
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    Buka setiap hari 07.00–01.00 WIB
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3a15 15 0 0 1 0 18M3 12h18"/></svg>
                    {{ $sports->map(fn ($rows) => $rows->first()['court']->sportLabel())->join(' & ') ?: 'Futsal & Badminton' }}
                </li>
            </ul>
            <div class="hero-actions">
                <a class="btn btn-lg" href="#pilih-lapangan">Lihat jadwal</a>
                @if ($cheapest)
                    <div class="hero-price">
                        <small>Mulai dari</small>
                        <strong>{{ rupiah($cheapest) }}</strong> <span>/jam</span>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection

@section('content')
    @if ($myBookings->isNotEmpty())
        <section class="card my-bookings" aria-labelledby="pesanan-kamu">
            <h2 id="pesanan-kamu">Pesanan kamu</h2>
            <ul>
                @foreach ($myBookings as $mine)
                    <li>
                        <div>
                            <strong>{{ $mine->court->name }}</strong>
                            <span>{{ tanggal($mine->starts_at) }} · {{ jam($mine->starts_at) }}–{{ jam($mine->ends_at) }} WIB</span>
                            <span class="small">
                                {{ $mine->code }} · {{ $mine->status->label() }}
                                @if ($mine->status === \App\Enums\BookingStatus::Pending)
                                    · bayar sebelum {{ jam($mine->expires_at) }} WIB
                                @endif
                            </span>
                        </div>
                        <a class="btn btn-small" href="{{ route('reservations.show', $mine) }}">
                            {{ $mine->status === \App\Enums\BookingStatus::Pending ? 'Lanjut bayar' : 'Lihat' }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <x-booking-steps :current="1" />

    <div class="booking-head" id="pilih-lapangan">
        <h2 class="section-title">Pilih lapangan</h2>
        @if ($sports->count() > 1)
            <div class="chips" role="group" aria-label="Pilih cabang olahraga">
                <button type="button" class="chip active" data-sport="semua" aria-pressed="true">Semua</button>
                @foreach ($sports as $sport => $rows)
                    <button type="button" class="chip" data-sport="{{ $sport }}" aria-pressed="false">{{ $rows->first()['court']->sportLabel() }}</button>
                @endforeach
            </div>
        @endif
    </div>

    <div class="picker">
        <div class="picker-head">
            <h2>{{ tanggal($date) }}</h2>
            <div class="legend" aria-label="Keterangan warna slot">
                <span class="l-available">Kosong</span>
                <span class="l-booked">Terisi</span>
                <span class="l-blocked">Ditutup</span>
            </div>
        </div>
        <div class="date-scroller">
            <button type="button" class="date-arrow" data-scroll="-1" aria-label="Tanggal sebelumnya">&lsaquo;</button>
            <nav class="date-strip" aria-label="Pilih tanggal">
                @for ($day = $firstDate; $day->lte($lastDate); $day = $day->addDay())
                    <a href="{{ route('schedule', ['tanggal' => $day->format('Y-m-d')]) }}#pilih-lapangan"
                       @class(['active' => $day->equalTo($date)])
                       @if ($day->equalTo($date)) aria-current="date" @endif>
                        @if ($day->equalTo($firstDate))
                            Hari ini
                        @elseif ($day->equalTo($firstDate->addDay()))
                            Besok
                        @else
                            {{ $day->locale('id')->translatedFormat('D') }}
                        @endif
                        <strong>{{ $day->format('j') }} {{ $day->locale('id')->translatedFormat('M') }}</strong>
                    </a>
                @endfor
            </nav>
            <button type="button" class="date-arrow" data-scroll="1" aria-label="Tanggal berikutnya">&rsaquo;</button>
        </div>
    </div>

    @forelse ($board as $row)
        @php
            $slots = collect($row['slots']);
            $available = $slots->where('status', 'available')->count();
            $past = $slots->where('status', 'past')->count();
        @endphp
        <section class="card court-card" data-sport="{{ $row['court']->sport }}" aria-labelledby="court-{{ $row['court']->id }}">
            <div class="court-layout">
                <div class="court-photo">
                    <img src="{{ $row['court']->photoUrl() }}" alt="Foto {{ $row['court']->name }} di Lola Sport" loading="lazy">
                </div>
                <div class="court-body">
                    <div class="court-head">
                        <div>
                            <span class="sport-tag sport-{{ $row['court']->sport }}">{{ $row['court']->sportLabel() }}</span>
                            <h3 id="court-{{ $row['court']->id }}">{{ $row['court']->name }}</h3>
                            <p class="court-summary">
                                @if ($available > 0)
                                    <strong>{{ $available }} jam kosong</strong> di tanggal ini
                                @else
                                    Tidak ada jam kosong di tanggal ini
                                @endif
                            </p>
                        </div>
                        <div class="price-block">
                            <small>Harga</small>
                            <span class="price">{{ rupiah($row['court']->hourly_rate) }}/jam</span>
                        </div>
                    </div>

                    @if ($available === 0)
                        <div class="court-full">
                            <span>Jadwal di tanggal ini sudah penuh.</span>
                            @if ($hasNextDate)
                                <a href="{{ route('schedule', ['tanggal' => $nextDate->format('Y-m-d')]) }}#pilih-lapangan">Lihat besok &rarr;</a>
                            @endif
                        </div>
                    @endif

                    <div @class(['slots', 'hide-past' => $past > 0])>
                        @foreach ($row['slots'] as $slot)
                            @php
                                // Jam 00.00 ke atas sudah masuk tanggal kalender berikutnya.
                                $nextDay = ! $slot['start']->isSameDay($date);
                                $slotNote = $nextDay ? $slot['start']->locale('id')->translatedFormat('D, j M') : '60 menit';
                            @endphp
                            @if ($slot['status'] === 'available')
                                <a class="slot slot-available"
                                   href="{{ route('reservations.create', ['lapangan' => $row['court']->id, 'tanggal' => $date->format('Y-m-d'), 'jam' => $slot['start']->hour, 'durasi' => 1]) }}"
                                   data-court="{{ $row['court']->id }}"
                                   data-court-name="{{ $row['court']->name }}"
                                   data-index="{{ $loop->index }}"
                                   data-hour="{{ $slot['start']->hour }}"
                                   data-start="{{ jam($slot['start']) }}"
                                   data-end="{{ jam($slot['start']->addHour()) }}"
                                   data-price="{{ $row['court']->hourly_rate }}"
                                   aria-label="Pilih {{ $row['court']->name }} jam {{ jam($slot['start']) }}{{ $nextDay ? ', '.tanggal($slot['start']) : '' }}">
                                    <span class="slot-check" aria-hidden="true">&check;</span>
                                    <small>{{ $slotNote }}</small>
                                    <b>{{ jam($slot['start']) }}–{{ jam($slot['start']->addHour()) }}</b>
                                    <span class="slot-price">{{ rupiah($row['court']->hourly_rate) }}</span>
                                </a>
                            @else
                                <span class="slot slot-{{ $slot['status'] }}">
                                    <small>{{ $slotNote }}</small>
                                    <b>{{ jam($slot['start']) }}–{{ jam($slot['start']->addHour()) }}</b>
                                    <span class="slot-price">{{ ['booked' => 'Terisi', 'blocked' => 'Ditutup', 'past' => 'Lewat'][$slot['status']] }}</span>
                                </span>
                            @endif
                        @endforeach
                    </div>
                    @if ($past > 0)
                        <button type="button" class="slot-toggle" aria-expanded="false"
                                data-show="{{ $past }} jam sudah lewat · <u>tampilkan</u>"
                                data-hide="<u>Sembunyikan jam yang sudah lewat</u>">
                            {{ $past }} jam sudah lewat · <u>tampilkan</u>
                        </button>
                    @endif
                </div>
            </div>
        </section>
    @empty
        <div class="card">Belum ada lapangan yang aktif.</div>
    @endforelse

    <section class="card steps-card" aria-labelledby="cara-pesan">
        <h2 id="cara-pesan">Cara pesan</h2>
        <ol class="steps">
            <li><div><strong>Pilih jam kosong</strong><span>Ketuk satu atau beberapa jam berurutan, lalu tekan Lanjut. Tanpa daftar akun.</span></div></li>
            <li><div><strong>Bayar DP {{ $setting->dp_percent }}% atau lunas</strong><span>Kirim buktinya paling lambat {{ $setting->hold_minutes }} menit setelah memesan. Lewat dari itu, slot dibuka lagi untuk orang lain.</span></div></li>
            <li><div><strong>Datang dan main</strong><span>Lunasi sisanya sebelum main, lewat web atau tunai di tempat.</span></div></li>
        </ol>
    </section>

    <div class="select-bar" id="select-bar" hidden data-base="{{ route('reservations.create') }}" data-date="{{ $date->format('Y-m-d') }}">
        <div class="select-bar-inner">
            <div class="select-bar-info">
                <span id="select-bar-detail"></span>
                <strong id="select-bar-total"></strong>
            </div>
            <button type="button" class="btn btn-outline" id="select-bar-clear">Batal</button>
            <a class="btn" id="select-bar-next" href="#">Lanjut</a>
        </div>
    </div>

    <script>
        (function () {
            // Filter cabang olahraga.
            var chips = document.querySelectorAll('.chip[data-sport]');
            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    var sport = chip.dataset.sport;
                    chips.forEach(function (c) {
                        c.classList.toggle('active', c === chip);
                        c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
                    });
                    document.querySelectorAll('.court-card').forEach(function (card) {
                        card.hidden = sport !== 'semua' && card.dataset.sport !== sport;
                    });
                });
            });

            // Langkah 1: pilih satu atau beberapa jam berurutan di satu lapangan.
            var bar = document.getElementById('select-bar');
            var links = Array.prototype.slice.call(document.querySelectorAll('a.slot-available[data-court]'));
            var chosen = { court: null, idx: [] };
            var rupiah = function (n) { return 'Rp' + n.toLocaleString('id-ID'); };

            links.forEach(function (link) {
                link.setAttribute('role', 'button');
                link.setAttribute('aria-pressed', 'false');
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    choose(link.dataset.court, Number(link.dataset.index));
                });
                // Tautan ini berperan sebagai tombol, jadi Space juga harus memilih slot.
                link.addEventListener('keydown', function (event) {
                    if (event.key !== ' ') return;
                    event.preventDefault();
                    choose(link.dataset.court, Number(link.dataset.index));
                });
            });

            function choose(court, i) {
                var min = Math.min.apply(null, chosen.idx), max = Math.max.apply(null, chosen.idx);
                if (chosen.court !== court || chosen.idx.length === 0) {
                    chosen = { court: court, idx: [i] };
                } else if (chosen.idx.indexOf(i) !== -1) {
                    chosen.idx = (i === min || i === max) ? chosen.idx.filter(function (x) { return x !== i; }) : [i];
                } else if (i === max + 1 || i === min - 1) {
                    chosen.idx.push(i);
                } else {
                    chosen.idx = [i];
                }
                render();
            }

            function render() {
                var picked = links.filter(function (l) {
                    return l.dataset.court === chosen.court && chosen.idx.indexOf(Number(l.dataset.index)) !== -1;
                }).sort(function (a, b) { return a.dataset.index - b.dataset.index; });
                links.forEach(function (l) {
                    var on = picked.indexOf(l) !== -1;
                    l.classList.toggle('selected', on);
                    l.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                bar.hidden = picked.length === 0;
                document.body.classList.toggle('has-select-bar', picked.length > 0);
                if (picked.length === 0) return;
                var first = picked[0], last = picked[picked.length - 1];
                document.getElementById('select-bar-detail').textContent =
                    first.dataset.courtName + ' · ' + picked.length + ' jam · ' + first.dataset.start + '–' + last.dataset.end;
                document.getElementById('select-bar-total').textContent = rupiah(Number(first.dataset.price) * picked.length);
                document.getElementById('select-bar-next').href = bar.dataset.base + '?' + new URLSearchParams({
                    lapangan: first.dataset.court, tanggal: bar.dataset.date, jam: first.dataset.hour, durasi: picked.length
                }).toString();
            }

            document.getElementById('select-bar-clear').addEventListener('click', function () {
                chosen = { court: null, idx: [] };
                render();
            });

            // Kembali dari langkah 2 lewat "Ubah jam": pilihan sebelumnya dipulihkan.
            var params = new URLSearchParams(location.search);
            var first = links.filter(function (l) {
                return l.dataset.court === params.get('lapangan') && l.dataset.hour === params.get('jam');
            })[0];
            if (first) {
                var count = Math.max(1, Number(params.get('durasi')) || 1);
                for (var k = 0; k < count; k++) choose(first.dataset.court, Number(first.dataset.index) + k);
            }

            // Tampilkan/sembunyikan jam yang sudah lewat.
            document.querySelectorAll('.slot-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var slots = btn.previousElementSibling;
                    var hidden = slots.classList.toggle('hide-past');
                    btn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
                    btn.innerHTML = hidden ? btn.dataset.show : btn.dataset.hide;
                });
            });

            // Strip tanggal: tanggal terpilih langsung terlihat, panah untuk geser.
            var strip = document.querySelector('.date-strip');
            if (strip) {
                var active = strip.querySelector('a.active');
                if (active) strip.scrollLeft = active.offsetLeft - strip.offsetLeft - 8;
                document.querySelectorAll('.date-arrow').forEach(function (arrow) {
                    arrow.addEventListener('click', function () {
                        var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                        strip.scrollBy({ left: Number(arrow.dataset.scroll) * strip.clientWidth * 0.8, behavior: calm ? 'auto' : 'smooth' });
                    });
                });
            }
        })();
    </script>
@endsection
