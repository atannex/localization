<?php

declare(strict_types=1);

namespace Atannex\Localization\Routing;

use Atannex\Localization\Http\Middleware\RedirectToLocalizedUrl;
use Atannex\Localization\Localization;
use Closure;
use Illuminate\Routing\Router;

/**
 * Route::localized(fn () => …): routes that exist in every locale.
 *
 *   prefix strategy, default hidden  /about (name 'about') and /{locale}/about
 *                                    (name 'localized.about', locale ∈ the others)
 *   prefix strategy, default shown   /{locale}/about only (name 'about'); URL
 *                                    defaults fill {locale}, so route('about') works
 *   none / domain                    the routes as they are
 *
 * Use localized_route('about') / Localization::route() to link in the
 * current (or any) locale whatever the strategy.
 */
final class LocalizedRoutes
{
    public const NAME_PREFIX = 'localized.';

    public static function register(Router $router, Localization $localization, Closure $routes, array $attributes = []): void
    {
        if ($localization->strategy() !== 'prefix') {
            $router->group($attributes, $routes);

            return;
        }

        $parameter = (string) $localization->config('route_parameter', 'locale');
        $middleware = array_merge((array) ($attributes['middleware'] ?? []), [RedirectToLocalizedUrl::class]);
        $codes = $localization->codes();
        $hidden = (bool) $localization->config('hide_default', true);

        if ($hidden) {
            // The default locale, unprefixed, under the routes' own names.
            $router->group(['middleware' => $middleware, 'localized' => true] + $attributes, $routes);
        }

        $router->group([
            'prefix' => trim(($attributes['prefix'] ?? '').'/{'.$parameter.'}', '/'),
            'as' => ($hidden ? self::NAME_PREFIX : '').($attributes['as'] ?? ''),
            'middleware' => $middleware,
            'localized' => true,
            // The default locale's prefix stays routable so it can redirect to the canonical URL.
            'where' => [$parameter => implode('|', array_map(fn (string $code) => preg_quote($code, '/'), $codes))],
        ] + $attributes, $routes);
    }
}
