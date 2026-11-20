{{-- Pilih periode laporan. $route = nama route, $params = parameter lain yang ikut (mis. filter). --}}
@php $params ??= []; @endphp
<div class="date-pick">
    <nav class="period-chips" aria-label="Pilih periode">
        @foreach (\App\Support\ReportPeriod::PRESETS as $key => $label)
            <a href="{{ route($route, $params + ['periode' => $key]) }}" @class(['active' => $period->key === $key])
               @if ($period->key === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <details class="period-custom" @if ($period->key === 'pilih') open @endif>
        <summary>Pilih tanggal sendiri</summary>
        <form method="GET" action="{{ route($route) }}" class="period-form">
            @foreach ($params as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <div class="field">
                <label for="dari">Dari</label>
                <input id="dari" type="date" name="dari" value="{{ $period->from->format('Y-m-d') }}" required>
            </div>
            <div class="field">
                <label for="sampai">Sampai</label>
                <input id="sampai" type="date" name="sampai" value="{{ $period->to->format('Y-m-d') }}" required>
            </div>
            <button class="btn" type="submit">Tampilkan</button>
        </form>
    </details>
</div>
