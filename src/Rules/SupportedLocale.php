<?php

declare(strict_types=1);

namespace Atannex\Localization\Rules;

use Atannex\Localization\Localization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value is a supported locale code (exactly, or any tag that matches
 * one when $loose: 'fr-CM' for 'fr').
 */
final class SupportedLocale implements ValidationRule
{
    public function __construct(private readonly bool $loose = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $localization = app(Localization::class);

        $valid = is_string($value) && ($this->loose ? $localization->match($value) !== null : $localization->supports($value));

        if (! $valid) {
            $fail('localization::validation.supported_locale')->translate([
                'locales' => implode(', ', $localization->codes()),
            ]);
        }
    }
}
