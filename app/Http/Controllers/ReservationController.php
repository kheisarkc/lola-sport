<?php

namespace App\Http\Controllers;

use App\Enums\BookingSource;
use App\Http\Requests\ReservationRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Setting;
use App\Services\HoldExpiry;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\ScheduleBoard;
use App\Support\OperatingHours;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Alur pelanggan tanpa akun: pesan (UC-02), cek status (UC-04), kirim bukti (UC-03).
 *
 * Pelanggan boleh membuka detail reservasi bila baru saja memesan atau sudah
 * memasukkan kode booking + nomor WhatsApp yang cocok. Hak akses itu disimpan di session.
 */
class ReservationController extends Controller
{
    public const ACCESS_KEY = 'reservasi_saya';

    /** Pilihan bayar (dp/lunas) per kode booking, dipakai di langkah kirim bukti. */
    private const PLAN_KEY = 'rencana_bayar';

    public function create(Request $request, ScheduleBoard $board): View|RedirectResponse
    {
        $data = $request->validate([
            'lapangan' => ['required', 'integer', 'exists:courts,id'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'jam' => ['required', 'integer', Rule::in(OperatingHours::startHours())],
            'durasi' => ['nullable', 'integer', 'min:1', 'max:18'],
        ]);
        $court = Court::query()->findOrFail($data['lapangan']);
        $date = CarbonImmutable::parse($data['tanggal'])->startOfDay();
        $start = OperatingHours::slotStart($date, (int) $data['jam']);
        $maxDuration = $board->maxDurationFrom($court, $date, $start);

        $duration = (int) ($data['durasi'] ?? 1);

        if ($maxDuration === 0 || $duration > $maxDuration) {
            return redirect()->route('schedule', ['tanggal' => $date->format('Y-m-d')])
                ->withErrors(['aturan' => 'Sebagian jam yang kamu pilih sudah tidak tersedia. Pilih jam lain.']);
        }
        $setting = Setting::current();
        $total = $court->hourly_rate * $duration;

        return view('reservations.create', [
            'court' => $court,
            'date' => $date,
            'start' => $start,
            'duration' => $duration,
            'total' => $total,
            'minimumDp' => $setting->minimumDp($total),
            'setting' => $setting,
        ]);
    }

    public function store(ReservationRequest $request, ReservationService $reservations): RedirectResponse
    {
        $booking = $reservations->create($request->reservation(), BookingSource::Website);
        $this->grantAccess($request, $booking);
        $request->session()->put(self::PLAN_KEY.'.'.$booking->code, $request->input('pembayaran') === 'lunas' ? 'lunas' : 'dp');

        return redirect()->route('reservations.show', $booking)
            ->with('success', 'Reservasi tersimpan. Simpan kode booking '.$booking->code.'.');
    }

    public function lookupForm(): View
    {
        return view('reservations.lookup');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:12'],
            'customer_phone' => ['required', 'string', 'max:20'],
        ], attributes: ['code' => 'kode booking', 'customer_phone' => 'nomor WhatsApp']);

        $code = strtoupper(trim($data['code']));
        $booking = Booking::query()
            ->where('code', str_starts_with($code, 'LS-') ? $code : 'LS-'.$code)
            ->where('customer_phone', PhoneNumber::normalize($data['customer_phone']))
            ->first();

        if ($booking === null) {
            return back()->withInput()->withErrors(['code' => 'Kode booking dan nomor WhatsApp tidak cocok.']);
        }
        $this->grantAccess($request, $booking);

        return redirect()->route('reservations.show', $booking);
    }

    public function show(Request $request, Booking $booking, HoldExpiry $holds): View|RedirectResponse
    {
        if (! $this->hasAccess($request, $booking)) {
            return redirect()->route('reservations.lookup');
        }
        // Pilihan DP/lunas dibuat di langkah 2; tautan "ganti" di langkah 3 hanya mengubah pilihan itu.
        if (in_array($request->query('bayar'), ['dp', 'lunas'], true)) {
            $request->session()->put(self::PLAN_KEY.'.'.$booking->code, $request->query('bayar'));

            return redirect()->route('reservations.show', $booking);
        }
        $holds->expireStale($booking->court_id);

        return view('reservations.show', [
            'booking' => $booking->refresh()->load('court', 'payments'),
            // Null bila halaman dibuka dari perangkat lain lewat Cek reservasi: pelanggan memilih di sini.
            'plan' => $request->session()->get(self::PLAN_KEY.'.'.$booking->code),
        ]);
    }

    public function cancel(Request $request, Booking $booking, ReservationService $reservations): RedirectResponse
    {
        if (! $this->hasAccess($request, $booking)) {
            return redirect()->route('reservations.lookup');
        }
        $reservations->cancelUnpaidHold($booking);
        $request->session()->forget(self::PLAN_KEY.'.'.$booking->code);

        return redirect()->route('schedule', ['tanggal' => $booking->play_date->format('Y-m-d')])
            ->with('success', 'Pesanan '.$booking->code.' dibatalkan. Jam itu sudah bisa dipesan lagi.');
    }

    public function pay(Request $request, Booking $booking, PaymentService $payments): RedirectResponse
    {
        if (! $this->hasAccess($request, $booking)) {
            return redirect()->route('reservations.lookup');
        }
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            // KNF-03: bukti hanya gambar/PDF kecil dan disimpan privat.
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ], attributes: ['amount' => 'nominal', 'proof' => 'bukti pembayaran']);

        $payments->submitProof($booking, (int) $data['amount'], $request->file('proof'));

        return redirect()->route('reservations.show', $booking)
            ->with('success', 'Bukti terkirim. Pegawai akan memeriksanya.');
    }

    private function grantAccess(Request $request, Booking $booking): void
    {
        $codes = $request->session()->get(self::ACCESS_KEY, []);
        $request->session()->put(self::ACCESS_KEY, array_values(array_unique([...$codes, $booking->code])));
    }

    private function hasAccess(Request $request, Booking $booking): bool
    {
        return ! $booking->isBlock()
            && in_array($booking->code, $request->session()->get(self::ACCESS_KEY, []), true);
    }
}
