<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TelefonoBoliviano implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (preg_match('/\A[0-9]{8}\z/', $value) !== 1) {
            $fail('El campo :attribute debe contener exactamente 8 dígitos.');
        }
    }
}
