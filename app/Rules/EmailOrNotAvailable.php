<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EmailOrNotAvailable implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_null($value)) {
            return;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (strtolower($value) === 'n/a') {
            return;
        }

        $fail(__('validation.email_or_not_available'));
    }
}
