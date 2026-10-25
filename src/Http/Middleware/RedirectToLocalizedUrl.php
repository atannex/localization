<?php

declare(strict_types=1);

namespace Atannex\Localization\Http\Middleware;

use Atannex\Localization\Localization;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps prefixed URLs canonical (prefix strategy; added to Route::localized()
 * groups). Alias: 'localize.redirect'.
 *
 *   /en/about  → /about       when the default locale's prefix is hidden (301)
 *   /about     → /fr/about    when the visitor prefers French (302, Vary: Accept-Language)
 *
 * Only safe methods are redirected; bots without preferences stay put.
 */
final class RedirectToLocalizedUrl
{
    public function __construct(private readonly Localization $localization) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->localization->strategy() !== 'prefix' || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $urls = $this->localization->urls();
        [$prefix] = $urls->split('/'.$request->path());

        if ($prefix !== null && $urls->hidesPrefix($prefix)) {
            return new RedirectResponse($this->localization->url($prefix, $request->fullUrl()), 301);
        }

        if ($prefix === null && $this->localization->config('redirect_to_preferred', true)) {
            $preferred = $this->localization->detect($request, $this->preferenceDetectors());

            if ($preferred['detector'] !== null && ! $urls->hidesPrefix($preferred['locale'])) {
                return (new RedirectResponse($this->localization->url($preferred['locale'], $request->fullUrl()), 302))
                    ->setVary(['Accept-Language', 'Cookie'], false);
            }
        }

        return $next($request);
    }

    /**
     * Every configured detector except those reading the URL itself.
     *
     * @return list<string>
     */
    private function preferenceDetectors(): array
    {
        return array_values(array_diff((array) $this->localization->config('detectors', []), ['route', 'domain']));
    }
}
