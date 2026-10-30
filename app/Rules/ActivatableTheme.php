<?php

namespace App\Rules;

use App\Services\ThemeValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A `theme` setting value must name an installed theme that can serve the public
 * site now: complete presentation contract and usable built assets.
 */
class ActivatableTheme implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $problems = is_string($value)
            ? app(ThemeValidator::class)->activationProblems($value, app()->isProduction())
            : ['The theme ID must be a string.'];

        foreach ($problems as $problem) {
            $fail(__('This theme cannot be activated: :problem', ['problem' => $problem]));
        }
    }
}
