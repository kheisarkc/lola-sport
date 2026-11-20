@props(['current'])
{{-- Penanda posisi pelanggan di alur pesan: pilih jam -> data & bayar -> kirim bukti. --}}
<ol class="booking-steps" aria-label="Langkah pemesanan">
    @foreach (['Pilih jam', 'Data & bayar', 'Kirim bukti'] as $index => $label)
        <li @class(['done' => $index + 1 < $current, 'current' => $index + 1 === $current])
            @if ($index + 1 === $current) aria-current="step" @endif>
            <span>{{ $index + 1 }}</span>{{ $label }}
        </li>
    @endforeach
</ol>
