<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\User;
use App\Services\ReservationService;
use App\Support\OperatingHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * UC-13: pegawai menutup slot untuk perawatan atau acara.
 */
class BlockController extends Controller
{
    public function create(): View
    {
        return view('staff.blocks.create', [
            'courts' => Court::query()->where('active', true)->orderBy('id')->get(),
            'hours' => OperatingHours::startHours(),
            'today' => OperatingHours::today(),
        ]);
    }

    public function store(Request $request, ReservationService $reservations): RedirectResponse
    {
        $data = $request->validate([
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'hour' => ['required', 'integer', Rule::in(OperatingHours::startHours())],
            'duration' => ['required', 'integer', 'min:1', 'max:18'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
        ], attributes: ['court_id' => 'lapangan', 'date' => 'tanggal', 'hour' => 'jam mulai', 'duration' => 'durasi', 'reason' => 'alasan']);

        /** @var User $staff */
        $staff = $request->user();
        $block = $reservations->block([
            'court_id' => (int) $data['court_id'],
            'date' => $data['date'],
            'hour' => (int) $data['hour'],
            'duration' => (int) $data['duration'],
            'reason' => $data['reason'],
        ], $staff);

        return redirect()->route('staff.schedule', ['tanggal' => $block->play_date->format('Y-m-d')])
            ->with('success', 'Slot '.$block->court->name.' ditutup.');
    }
}
