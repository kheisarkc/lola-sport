<div class="field">
    <label for="{{ $prefix }}-name">Nama</label>
    <input id="{{ $prefix }}-name" name="name" value="{{ $court?->name }}" required maxlength="80">
</div>
<div class="field">
    <label for="{{ $prefix }}-sport">Jenis</label>
    <select id="{{ $prefix }}-sport" name="sport">
        @foreach ($sports as $value => $label)
            <option value="{{ $value }}" @selected($court?->sport === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="field">
    <label for="{{ $prefix }}-rate">Harga per jam (Rp)</label>
    <input id="{{ $prefix }}-rate" type="number" name="hourly_rate" min="1000" step="1000" value="{{ $court?->hourly_rate }}" required>
</div>
<div class="field" style="flex: 0 0 auto">
    <label class="check"><input type="checkbox" name="active" value="1" @checked($court?->active ?? true)> Aktif</label>
</div>
