<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReservationRequest;
use App\Models\Booking;
use App\Models\User;
use App\Services\HoldExpiry;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\ScheduleBoard;
use App\Support\OperatingHours;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pegawai mencatat reservasi WA/walk-in (UC-07), membatalkan (UC-10),
 * dan mencatat kehadiran (UC-12).
 */
class BookingController extends Controller
{
    public function __construct(private ReservationService $reservations) {}

    /**
     * Pegawai memilih lapangan lewat tab, lalu mengetuk jam kosong seperti di halaman pelanggan.
     */
    public function create(Request $request, ScheduleBoard $board): View
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'lapangan' => ['nullable', 'integer'],
        ]);
        $date = isset($data['tanggal'])
            ? CarbonImmutable::parse($data['tanggal'])->startOfDay()
            : OperatingHours::today();
        $rows = $board->forDate($date);
        $row = $rows->first(fn (array $r) => $r['court']->id === (int) old('court_id', $data['lapangan'] ?? 0)) ?? $rows->first();

        return view('staff.bookings.create', [
            'date' => $date,
            'today' => OperatingHours::today(),
            'courts' => $rows->pluck('court'),
            'row' => $row,
        ]);
    }

    /**
     * Pesanan dari pegawai hanya sah bila uangnya diterima saat itu juga (DP atau lunas),
     * jadi reservasi dan pembayaran tunainya disimpan dalam satu transaksi.
     */
    public function store(ReservationRequest $request, PaymentService $payments): RedirectResponse
    {
        $request->validate([
            'pembayaran' => ['required', 'in:dp,lunas'],
            'cara' => ['required', 'in:tunai,qris'],
        ], [
            'pembayaran.required' => 'Pilih uang yang diterima: DP atau lunas.',
            'cara.required' => 'Pilih cara bayar: tunai atau QRIS.',
        ]);
        $staff = $this->staff($request);
        $method = $request->input('cara') === 'qris' ? PaymentMethod::Qris : PaymentMethod::Cash;

        $booking = DB::transaction(function () use ($request, $payments, $staff, $method) {
            $booking = $this->reservations->create($request->reservation(), BookingSource::Staff, $staff);
            $amount = $request->input('pembayaran') === 'lunas' ? $booking->total : $booking->minimum_dp;
            $payments->recordCash($booking, $amount, $staff, $method);

            return $booking;
        });

        return redirect()->route('staff.bookings.show', $booking)
            ->with('success', 'Pesanan '.$booking->code.' tersimpan dan uangnya tercatat.');
    }

    public function show(Booking $booking, HoldExpiry $holds): View
    {
        $holds->expireStale($booking->court_id);

        return view('staff.bookings.show', [
            'booking' => $booking->refresh()->load('court', 'payments.reviewer', 'events.actor'),
        ]);
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        $this->reservations->cancel($booking, $this->staff($request), $data['reason'] ?? null);

        $message = $booking->isBlock() ? 'Slot dibuka kembali.' : 'Reservasi dibatalkan.';

        return redirect()->route('staff.bookings.show', $booking)->with('success', $message);
    }

    public function checkIn(Request $request, Booking $booking): RedirectResponse
    {
        $this->reservations->checkIn($booking, $this->staff($request));

        return back()->with('success', 'Pelanggan ditandai hadir. Main sampai '.jam($booking->ends_at).'.');
    }

    public function noShow(Request $request, Booking $booking): RedirectResponse
    {
        $this->reservations->markNoShow($booking, $this->staff($request));

        return back()->with('success', 'Pelanggan ditandai tidak hadir.');
    }

    private function staff(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
