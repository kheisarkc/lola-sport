<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingSource;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\HoldExpiry;
use App\Support\OperatingHours;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UC-06: layar "Hari ini" pegawai. Pesanan satu tanggal dalam bentuk kartu,
 * plus pencarian pesanan lewat kode, nama, atau nomor WhatsApp.
 */
class ScheduleController extends Controller
{
    public function index(Request $request, HoldExpiry $holds): View
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'cari' => ['nullable', 'string', 'max:80'],
        ]);
        $date = isset($data['tanggal'])
            ? CarbonImmutable::parse($data['tanggal'])->startOfDay()
            : OperatingHours::today();
        $search = trim((string) ($data['cari'] ?? ''));
        $holds->expireStale();

        return view('staff.schedule', [
            'date' => $date,
            'today' => OperatingHours::today(),
            'search' => $search,
            'results' => $search === '' ? null : $this->search($search),
            'waiting' => Payment::waitingCount(),
            'bookings' => Booking::query()
                ->with('court', 'payments')
                ->whereDate('play_date', $date)
                ->orderBy('starts_at')
                ->get(),
        ]);
    }

    /**
     * @return Collection<int, Booking>
     */
    private function search(string $term)
    {
        $phone = PhoneNumber::normalize($term);

        return Booking::query()
            ->with('court', 'payments')
            ->where('source', '!=', BookingSource::Block)
            ->where(function (Builder $query) use ($term, $phone) {
                $query->where('code', strtoupper($term))
                    ->orWhere('customer_name', 'ilike', '%'.addcslashes($term, '%_\\').'%');
                if (strlen((string) $phone) >= 4 && ctype_digit((string) $phone)) {
                    $query->orWhere('customer_phone', 'like', '%'.$phone.'%');
                }
            })
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();
    }
}
