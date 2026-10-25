<?php

declare(strict_types=1);

namespace Atannex\Localization\Rules;

use Atannex\Localization\Localization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A translations map, {"en": "…", "fr": "…"}: keys are supported locales,
 * values are strings (or null), the required locales are filled, and
 * values respect a maximum length.
 *
 *     'title' => ['required', Translations::make()->requireDefault()->max(255)]
 */
final class Translations implements ValidationRule
{
    /** @var list<string> */
    private array $required = [];

    private ?int $max = null;

    public static function make(): self
    {
        return new self;
    }

    public function requireDefault(): self
    {
        $this->required[] = app(Localization::class)->default()->code;

        return $this;
    }

    /**
     * @param  list<string>|string  $locales  '*' for every supported locale
     */
    public function require(array|string $locales): self
    {
        $locales = $locales === '*' ? app(Localization::class)->codes() : (array) $locales;
        $this->required = array_values(array_unique([...$this->required, ...$locales]));

        return $this;
    }

    public function max(int $characters): self
    {
        $this->max = $characters;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $localization = app(Localization::class);

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $fail('localization::validation.translations')->translate();

            return;
        }

        foreach ($value as $locale => $text) {
            if (! $localization->supports((string) $locale)) {
                $fail('localization::validation.unsupported')->translate(['locale' => (string) $locale]);
            } elseif ($text !== null && ! is_string($text)) {
                $fail('localization::validation.translations')->translate();
            } elseif ($this->max !== null && is_string($text) && mb_strlen($text) > $this->max) {
                $fail('localization::validation.max')->translate(['locale' => (string) $locale, 'max' => $this->max]);
            }
        }

        foreach ($this->required as $locale) {
            if (! isset($value[$locale]) || trim((string) $value[$locale]) === '') {
                $fail('localization::validation.required')->translate(['language' => $localization->get($locale)->native]);
            }
        }
    }
}
