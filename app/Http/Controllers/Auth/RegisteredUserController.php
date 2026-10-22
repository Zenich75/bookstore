<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        // validated(): only fields that have rules. role is neither validated nor
        // fillable, so every sign-up is a reader (DB default) whatever extra fields
        // the request contains. Password: "hashed" cast.
        $user = User::create($request->validated());

        // The framework listens for Registered and sends the verification email
        // (SendEmailVerificationNotification) because User implements MustVerifyEmail.
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
