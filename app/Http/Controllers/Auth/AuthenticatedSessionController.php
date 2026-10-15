<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * POST /login runs behind throttle:login (named limiter, AppServiceProvider).
     */
    public function store(Request $request): RedirectResponse
    {
        // Inline validation for now; moves to a Form Request in stage 8.
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // attempt(): finds the user by email, checks the hash, logs in. The second
        // argument sets the long-lived "remember me" cookie.
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // One message for "no such user" and "wrong password": do not reveal which emails exist.
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // New session id after login: an id planted before login (session fixation) becomes useless.
        $request->session()->regenerate();

        // Back to the page that sent the guest to the login form (stored by the "auth" middleware).
        return redirect()->intended(route('home'))->with('status', __('ui.auth.flash.logged_in'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        // Drop all session data and issue a new CSRF token.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('ui.auth.flash.logged_out'));
    }
}
