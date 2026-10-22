<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * LoginRequest validates the input; authenticate() checks the credentials and
     * throttles failed attempts (moved there from the stage 7 throttle:login middleware).
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

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
