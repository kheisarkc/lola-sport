{{-- Kartu satu pesanan di layar pegawai. Ketuk bagian atas untuk membuka detail. --}}
@php $withDate ??= false; @endphp
<article class="booking-card">
    <a class="booking-card-head" href="{{ route('staff.bookings.show', $booking) }}">
        <span class="booking-card-time">
            @if ($withDate){{ tanggal($booking->starts_at) }} · @endif{{ jam($booking->starts_at) }}–{{ jam($booking->ends_at) }}
            <span class="booking-card-court">{{ $booking->court->name }}</span>
        </span>
        @if ($booking->isBlock())
            <span class="booking-card-who">Lapangan ditutup · {{ $booking->note }}</span>
        @else
            <span class="booking-card-who">{{ $booking->customer_name }} · {{ $booking->customer_phone }}</span>
            <span class="booking-card-tags">
                <x-status :status="$booking->status" />
                <span class="badge badge-money">{{ $booking->paymentLabel() }}</span>
                <span class="booking-card-code">{{ $booking->code }}</span>
            </span>
        @endif
    </a>
    @include('staff.partials.next-action', ['booking' => $booking])
</article>
