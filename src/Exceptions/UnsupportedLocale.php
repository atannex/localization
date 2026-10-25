<?php

declare(strict_types=1);

namespace Atannex\Localization\Exceptions;

final class UnsupportedLocale extends LocalizationException
{
    /**
     * @param  list<string>  $supported
     */
    public static function make(string $locale, array $supported): self
    {
        return new self("Locale [{$locale}] is not supported. Supported: ".implode(', ', $supported).'.');
    }
}
