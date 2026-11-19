<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Limit attempts per account as well as per IP (the route throttle), so guessing one account's password
        // cannot get around the limit by changing IP address.
        $key = 'login:'.mb_strtolower($credentials['email']);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            Audit::record('auth.failed', "Sign-in blocked for {$credentials['email']}: too many attempts");

            return back()->withErrors(['email' => 'Too many sign-in attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.'])
                ->onlyInput('email');
        }

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            Audit::record('auth.failed', "Failed sign-in for {$credentials['email']}");

            return back()->withErrors(['email' => 'The email or password is incorrect.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        Audit::record('auth.login', 'Signed in');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Audit::record('auth.logout', 'Signed out');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
