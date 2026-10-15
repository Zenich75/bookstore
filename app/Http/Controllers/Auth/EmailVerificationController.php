<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * verification.notice / verification.verify / verification.send — the route names
 * the "verified" middleware and the VerifyEmail notification expect.
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->intended(route('home'))
            : view('auth.verify-email');
    }

    /**
     * The link in the email is a signed URL (route middleware "signed"); the request
     * class also checks that {id}/{hash} belong to the logged-in user.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill(); // marks verified + fires the Verified event

        return redirect()->intended(route('home'))->with('status', __('ui.auth.flash.verified'));
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('home'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('ui.auth.flash.verification_sent'));
    }
}
