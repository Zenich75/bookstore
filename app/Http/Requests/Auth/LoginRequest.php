<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validation + authentication + brute-force protection in one place.
 *
 * Stage 7 throttled POST /login with route middleware (throttle:login, a named
 * limiter counting every request). Here the request counts failed attempts only
 * and clears the counter after a successful login.
 */
class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    /**
     * Stop at the first failing attribute: with no valid email there is no point
     * in also reporting the password (one message instead of two).
     *
     * @var bool
     */
    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // safe()->only(): exactly the validated credentials, nothing else from the input.
        if (! Auth::attempt($this->safe()->only(['email', 'password']), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey()); // decays after 60 s

            // One message for "no such user" and "wrong password": do not reveal which emails exist.
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * 5 failed attempts per email + IP: guessing one account's password from one
     * address is throttled, without locking out everyone behind the same NAT.
     *
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
