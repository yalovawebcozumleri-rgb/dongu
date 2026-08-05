<?php

namespace App\Http\Controllers\Supporter;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response { return Inertia::render('Supporter/Login'); }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt(['email' => mb_strtolower(trim($credentials['email'])), 'password' => $credentials['password']], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'E-posta veya şifre hatalı.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        if (! $request->user()->isSupporter() || $request->user()->status !== 'active') {
            Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Bu hesap destekçi paneline erişemiyor.']);
        }
        return redirect()->intended(route('supporter.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('supporter.login');
    }
}
