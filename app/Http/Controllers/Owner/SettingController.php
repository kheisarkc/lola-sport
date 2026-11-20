<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * KF-25: pemilik mengatur besar DP dan lama penahanan slot.
 * Perubahan hanya berlaku untuk reservasi baru.
 */
class SettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dp_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'hold_minutes' => ['required', 'integer', 'min:5', 'max:240'],
        ], attributes: ['dp_percent' => 'persentase DP', 'hold_minutes' => 'lama penahanan']);

        Setting::current()->update($data);

        return back()->with('success', 'Aturan pemesanan diperbarui.');
    }
}
