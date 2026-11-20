{{-- Pilihan lapangan, tanggal, jam, durasi. Dipakai form catat reservasi dan tutup slot. --}}
<div class="field">
    <label for="court_id">Lapangan</label>
    <select id="court_id" name="court_id" required>
        @foreach ($courts as $court)
            <option value="{{ $court->id }}" @selected(old('court_id', $defaults['court_id'] ?? null) == $court->id)>
                {{ $court->name }} · {{ rupiah($court->hourly_rate) }}/jam
            </option>
        @endforeach
    </select>
</div>
<div class="grid grid-3">
    <div class="field">
        <label for="date">Tanggal</label>
        <input id="date" type="date" name="date" value="{{ old('date', $defaults['date'] ?? '') }}" required>
    </div>
    <div class="field">
        <label for="hour">Jam mulai <span class="hint">00.00 ikut malam tanggal ini</span></label>
        <select id="hour" name="hour" required>
            @foreach ($hours as $hour)
                <option value="{{ $hour }}" @selected((string) old('hour', $defaults['hour'] ?? '') === (string) $hour)>
                    {{ sprintf('%02d.00', $hour) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="duration">Durasi (jam)</label>
        <input id="duration" type="number" name="duration" min="1" max="18" value="{{ old('duration', 1) }}" required>
    </div>
</div>
