{{--
    Pilih tanggal: tujuh hari ke depan bisa langsung diketuk, tanggal lain lewat kalender.
    Parameter: $date, $today, $route, $params (query lain yang dibawa), $allowPast.
--}}
@php
    $params ??= [];
    $allowPast ??= true;
    $days = collect(range(0, 6))->map(fn ($i) => $today->addDays($i));
    $label = fn ($d) => $d->equalTo($today) ? 'Hari ini' : ($d->equalTo($today->addDay()) ? 'Besok' : $d->locale('id')->translatedFormat('D'));
@endphp
<div class="date-pick">
    <nav class="date-chips" aria-label="Pilih tanggal">
        @unless ($days->contains(fn ($d) => $d->equalTo($date)))
            <a class="active" aria-current="date" href="{{ route($route, [...$params, 'tanggal' => $date->format('Y-m-d')]) }}">
                {{ $date->locale('id')->translatedFormat('D') }}<strong>{{ $date->format('j') }} {{ $date->locale('id')->translatedFormat('M') }}</strong>
            </a>
        @endunless
        @foreach ($days as $day)
            <a href="{{ route($route, [...$params, 'tanggal' => $day->format('Y-m-d')]) }}"
               @class(['active' => $day->equalTo($date)])
               @if ($day->equalTo($date)) aria-current="date" @endif>
                {{ $label($day) }}<strong>{{ $day->format('j') }} {{ $day->locale('id')->translatedFormat('M') }}</strong>
            </a>
        @endforeach
    </nav>
    <form method="GET" action="{{ route($route) }}" class="date-calendar">
        @foreach ($params as $key => $value)
            @if ($value !== null)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <label for="tanggal-kalender">Tanggal lain</label>
        <input id="tanggal-kalender" type="date" name="tanggal" value="{{ $date->format('Y-m-d') }}"
               @unless ($allowPast) min="{{ $today->format('Y-m-d') }}" @endunless
               onchange="if (this.value) this.form.submit()">
        <noscript><button class="btn btn-outline" type="submit">Tampilkan</button></noscript>
    </form>
</div>
