{{--
    Tombol utama sesuai keadaan pesanan. Dipakai di kartu "Hari ini" dan di detail pesanan.
    Urutan: bukti menunggu -> sisa bayar -> pelanggan datang -> lewat jam.
--}}
@php
    $proof = $booking->payments->firstWhere('status', \App\Enums\PaymentStatus::Pending);
    $left = $booking->total - $booking->paidAmount();
    $arriveFrom = $booking->starts_at->subMinutes((int) config('lola.check_in_early_minutes'));
    $confirmed = $booking->status === \App\Enums\BookingStatus::Confirmed;
@endphp

@if ($booking->isBlock())
    {{-- Lapangan ditutup tidak punya tindakan utama. --}}
@elseif ($proof)
    <a class="btn btn-lg btn-block" href="{{ route('staff.payments.index') }}#bukti-{{ $proof->id }}">Cek bukti {{ rupiah($proof->amount) }}</a>
@elseif ($confirmed && $left > 0)
    <div class="pay-pair">
        @foreach (['tunai' => 'Terima tunai '.rupiah($left), 'qris' => 'QRIS di tempat'] as $cara => $text)
            <form method="POST" action="{{ route('staff.payments.cash', $booking) }}"
                  onsubmit="return confirm({{ Js::from(($cara === 'qris' ? 'Uang QRIS ' : 'Tunai ').rupiah($left).' dari '.$booking->customer_name.' sudah diterima?') }});">
                @csrf
                <input type="hidden" name="amount" value="{{ $left }}">
                <input type="hidden" name="cara" value="{{ $cara }}">
                <button @class(['btn btn-lg btn-block', 'btn-outline' => $cara === 'qris']) type="submit">{{ $text }}</button>
            </form>
        @endforeach
    </div>
@elseif ($confirmed && now()->lt($booking->ends_at))
    @if (now()->gte($arriveFrom))
        <form method="POST" action="{{ route('staff.bookings.check-in', $booking) }}">
            @csrf
            <button class="btn btn-lg btn-block" type="submit">Pelanggan datang</button>
        </form>
    @else
        <p class="next-note">Lunas. Tombol "Pelanggan datang" muncul mulai {{ jam($arriveFrom) }}.</p>
    @endif
@elseif ($confirmed)
    <form method="POST" action="{{ route('staff.bookings.no-show', $booking) }}"
          onsubmit="return confirm({{ Js::from($booking->customer_name.' tidak datang? Uang yang sudah dibayar tidak dikembalikan.') }});">
        @csrf
        <button class="btn btn-lg btn-block btn-outline" type="submit">Tandai tidak datang</button>
    </form>
@elseif ($booking->status === \App\Enums\BookingStatus::Pending)
    <p class="next-note">Menunggu pelanggan kirim bukti sampai {{ jam($booking->expires_at) }}.</p>
@endif
