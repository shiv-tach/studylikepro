<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts every way a Sri Lankan mobile is written and rejects anything else.
 *
 * Normalise the value before validating (or store through the User model's
 * setter) so the `unique` rule compares the canonical digits rather than the
 * spacing the visitor happened to type.
 */
class SriLankanMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhoneNumber::isValid($value)) {
            $fail(__('Enter a valid Sri Lankan mobile number, e.g. :example.', [
                'example' => PhoneNumber::DISPLAY_EXAMPLE,
            ]));
        }
    }
}
