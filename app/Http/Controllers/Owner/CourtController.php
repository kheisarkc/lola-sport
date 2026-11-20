<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * UC-16: pemilik mengelola lapangan, harga, dan aturan pemesanan.
 * Harga baru hanya berlaku untuk reservasi baru, karena harga lama disalin ke reservasi.
 */
class CourtController extends Controller
{
    public function index(): View
    {
        return view('owner.courts', [
            'courts' => Court::query()->orderBy('id')->get(),
            'setting' => Setting::current(),
            'sports' => Court::SPORTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Court::create($this->validated($request));

        return back()->with('success', 'Lapangan ditambahkan.');
    }

    public function update(Request $request, Court $court): RedirectResponse
    {
        $court->update($this->validated($request, $court));

        return back()->with('success', 'Lapangan '.$court->name.' diperbarui.');
    }

    /**
     * @return array{name: string, sport: string, hourly_rate: int, active: bool}
     */
    private function validated(Request $request, ?Court $court = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('courts', 'name')->ignore($court?->id)],
            'sport' => ['required', Rule::in(array_keys(Court::SPORTS))],
            'hourly_rate' => ['required', 'integer', 'min:1000', 'max:10000000'],
        ], attributes: ['name' => 'nama lapangan', 'sport' => 'jenis', 'hourly_rate' => 'harga per jam']);

        return [
            'name' => $data['name'],
            'sport' => $data['sport'],
            'hourly_rate' => (int) $data['hourly_rate'],
            // Lapangan nonaktif tidak tampil di jadwal, reservasi lama tetap tersimpan.
            'active' => $request->boolean('active'),
        ];
    }
}
