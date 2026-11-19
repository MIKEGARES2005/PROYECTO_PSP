<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CarnetIdentidad implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (preg_match('/\A[0-9]{5,15}\z/', $value) !== 1) {
            $fail('El campo :attribute debe contener entre 5 y 15 dígitos, sin letras ni separadores.');
        }
    }
}
