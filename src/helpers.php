<?php

declare(strict_types=1);

use Atannex\Localization\Locale;
use Atannex\Localization\Localization;

if (! function_exists('localization')) {
    /** The localization manager, or a supported locale's details. */
    function localization(?string $locale = null): Localization|Locale
    {
        $manager = app(Localization::class);

        return $locale === null ? $manager : $manager->get($locale);
    }
}

if (! function_exists('localized_url')) {
    /** $url (default: the current page) in $locale (default: the current one). */
    function localized_url(?string $locale = null, ?string $url = null): string
    {
        $manager = app(Localization::class);

        return $manager->url($locale ?? $manager->code(), $url);
    }
}

if (! function_exists('localized_route')) {
    /**
     * A named route in $locale (default: the current one), whatever the URL strategy.
     *
     * @param  array<string, mixed>  $parameters
     */
    function localized_route(string $name, array $parameters = [], ?string $locale = null, bool $absolute = true): string
    {
        return app(Localization::class)->route($name, $parameters, $locale, $absolute);
    }
}

if (! function_exists('locale_direction')) {
    /** 'ltr' or 'rtl' for $locale (default: the current one). */
    function locale_direction(?string $locale = null): string
    {
        return app(Localization::class)->direction($locale);
    }
}
