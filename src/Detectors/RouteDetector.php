<?php

declare(strict_types=1);

namespace Atannex\Localization\Detectors;

use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\Http\Controllers\SwitchLocaleController;
use Atannex\Localization\LocaleRegistry;
use Illuminate\Http\Request;

/**
 * The {locale} route parameter, or (prefix strategy) a supported code as
 * the first path segment, even before routing has run. Under the prefix
 * strategy the URL is authoritative for Route::localized() routes: an
 * unprefixed one is the default locale.
 */
final class RouteDetector implements Detector
{
    public function __construct(private readonly string $parameter = 'locale', private readonly bool $prefix = false) {}

    public function detect(Request $request, LocaleRegistry $locales): ?string
    {
        $route = $request->route();

        // The switcher's {locale} is the locale being chosen, not the current one.
        if ($route !== null && $route->getControllerClass() === SwitchLocaleController::class) {
            return null;
        }

        $value = $route?->parameter($this->parameter);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (! $this->prefix) {
            return null;
        }

        $segment = $request->segment(1);

        if (is_string($segment) && $locales->supports($segment)) {
            return $segment;
        }

        // An unprefixed URL of a Route::localized() route is the default locale's page.
        return $route?->getAction('localized') ? $locales->default()->code : null;
    }
}
