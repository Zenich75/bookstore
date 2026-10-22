<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Guests only — the "guest" route middleware already guarantees it.
     */
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            // "confirmed": password_confirmation must match. It has no rule of its own,
            // so it is not part of validated() and never reaches User::create().
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
