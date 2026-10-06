<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SurelDomainUnsil implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_ends_with(strtolower($value), '@unsil.ac.id')) {
            $fail('Surel harus memakai domain unsil.ac.id.');
        }
    }
}
