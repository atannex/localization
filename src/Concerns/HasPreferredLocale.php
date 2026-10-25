<?php

declare(strict_types=1);

namespace Atannex\Localization\Concerns;

use Atannex\Localization\Localization;

/**
 * A model's preferred locale, for anything implementing Laravel's
 * HasLocalePreference (users, subscribers, contacts): the user detector
 * reads it, the user store writes it, and notifications and mail sent to
 * the model are written in it.
 *
 *     class User extends Authenticatable implements HasLocalePreference
 *     {
 *         use HasPreferredLocale;
 *
 *         // a column (default 'locale') or a JSON path:
 *         protected string $preferredLocaleAttribute = 'settings->locale';
 *     }
 */
trait HasPreferredLocale
{
    /** The stored locale if it is (or matches) a supported one, else null. */
    public function preferredLocale(): ?string
    {
        $stored = $this->readPreferredLocale();

        return is_string($stored) ? app(Localization::class)->match($stored) : null;
    }

    /**
     * Stores $locale (matched to a supported code). Unsupported values are
     * ignored; nothing is written when it does not change.
     */
    public function setPreferredLocale(string $locale, bool $save = true): void
    {
        $code = app(Localization::class)->match($locale);

        if ($code === null || $this->readPreferredLocale() === $code) {
            return;
        }

        $this->forceFill([$this->preferredLocaleAttribute() => $code]);

        if ($save && $this->exists) {
            $this->saveQuietly();
        }
    }

    public function preferredLocaleAttribute(): string
    {
        return property_exists($this, 'preferredLocaleAttribute') ? (string) $this->preferredLocaleAttribute : 'locale';
    }

    protected function readPreferredLocale(): mixed
    {
        [$column, $path] = array_pad(explode('->', $this->preferredLocaleAttribute(), 2), 2, null);

        $value = $this->getAttribute($column);

        if ($path === null) {
            return $value;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return data_get($value, str_replace('->', '.', $path));
    }
}
