<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ISBN-13: 13 digits whose weighted sum (weights 1, 3, 1, 3, ...) is divisible by 10.
 * Expects digits only — StoreBookRequest::prepareForValidation() strips dashes/spaces.
 *
 * A rule class (vs. a closure rule): reusable, testable on its own, and named in the
 * rules array like a built-in: ['required', new ValidIsbn13].
 */
class ValidIsbn13 implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{13}$/', $value)) {
            // A translation key + translate(): the message comes from lang/{uk,en}/validation.php.
            $fail('validation.isbn13')->translate();

            return;
        }

        $sum = 0;
        foreach (str_split($value) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        if ($sum % 10 !== 0) {
            $fail('validation.isbn13_checksum')->translate();
        }
    }
}
