<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Setting;
use App\Services\ScheduleBoard;
use App\Support\OperatingHours;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UC-01: pelanggan melihat ketersediaan jadwal tanpa login.
 */
class ScheduleController extends Controller
{
    public function index(Request $request, ScheduleBoard $board): View
    {
        $date = $this->chosenDate($request);
        $slots = $board->forDate($date);

        return view('schedule.index', [
            'date' => $date,
            'board' => $slots,
            'myBookings' => $this->openBookingsOfThisBrowser($request),
            'setting' => Setting::current(),
            'firstDate' => OperatingHours::today(),
            'lastDate' => OperatingHours::lastBookableDate(),
        ]);
    }

    /**
     * Pesanan yang pernah dibuat atau dibuka di browser ini dan belum selesai, supaya pelanggan
     * yang kembali ke jadwal bisa melanjutkan pembayaran atau membatalkannya.
     *
     * @return Collection<int, Booking>
     */
    private function openBookingsOfThisBrowser(Request $request): Collection
    {
        $codes = $request->session()->get(ReservationController::ACCESS_KEY, []);
        if ($codes === []) {
            return new Collection;
        }

        return Booking::query()
            ->with('court')
            ->whereIn('code', $codes)
            ->whereIn('status', BookingStatus::active())
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->get()
            ->reject(fn (Booking $booking) => $booking->status === BookingStatus::Pending && $booking->expires_at->isPast())
            ->values();
    }

    /**
     * Tanggal di luar hari ini sampai 30 hari ke depan dikembalikan ke hari ini.
     */
    private function chosenDate(Request $request): CarbonImmutable
    {
        $today = OperatingHours::today();
        $input = $request->query('tanggal');
        if (! is_string($input) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
            return $today;
        }
        $date = CarbonImmutable::parse($input)->startOfDay();

        return $date->between($today, OperatingHours::lastBookableDate()) ? $date : $today;
    }
}
