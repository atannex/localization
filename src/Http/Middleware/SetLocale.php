<?php

declare(strict_types=1);

namespace Atannex\Localization\Http\Middleware;

use Atannex\Localization\Localization;
use Closure;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Negotiates the request's locale and applies it (translator, Carbon,
 * Number, URL defaults), then labels the response: Content-Language, and
 * Vary when one address answers in several languages. Alias: 'localize'.
 *
 * Pass a locale to force one: ->middleware('localize:fr').
 */
final class SetLocale
{
    public function __construct(
        private readonly Localization $localization,
        private readonly UrlGenerator $url,
    ) {}

    public function handle(Request $request, Closure $next, ?string $locale = null): Response
    {
        $code = $locale !== null && $this->localization->match($locale) !== null
            ? $this->localization->match($locale)
            : $this->localization->negotiate($request);

        $current = $this->localization->set($code);

        if ($this->localization->strategy() === 'prefix' && $this->localization->config('apply.url_defaults', true)
            && ! $this->localization->urls()->hidesPrefix($current->code) && method_exists($this->url, 'defaults')) {
            $this->url->defaults([(string) $this->localization->config('route_parameter', 'locale') => $current->code]);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($this->localization->config('headers.content_language', true) && ! $response->headers->has('Content-Language')) {
            $response->headers->set('Content-Language', $this->localization->current()->tag());
        }

        $vary = (array) $this->localization->config('headers.vary', []);

        if ($vary !== [] && $this->localization->strategy() === 'none' && $this->localization->isMultilingual()) {
            // One comma-separated header: proxies and header->get() see every value.
            $response->headers->set('Vary', implode(', ', array_values(array_unique([...$response->getVary(), ...$vary]))));
        }

        return $response;
    }
}
