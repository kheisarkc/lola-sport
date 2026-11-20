<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * UC-05: login pegawai dan pemilik.
 */
class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], attributes: ['password' => 'kata sandi']);

        $credentials['email'] = strtolower($credentials['email']);
        if (! Auth::attempt($credentials)) {
            return back()->onlyInput('email')->withErrors(['email' => 'Email atau kata sandi salah.']);
        }
        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        return redirect()->intended($user->homeUrl());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
