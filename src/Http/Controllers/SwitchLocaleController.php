<?php

declare(strict_types=1);

namespace Atannex\Localization\Http\Controllers;

use Atannex\Localization\Localization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * POST {path}/{locale} (or POST {path} with a 'locale' field): the language switcher. Persists the choice (cookie,
 * session, user — localization.persist) and returns to the page the visitor
 * was on, in the chosen locale's URL. Never redirects off-site.
 *
 * A 'redirect' input may name the page to return to (same site only).
 * JSON requests receive {"locale": "fr", "redirect": "…"}.
 */
final class SwitchLocaleController
{
    public function __invoke(Request $request, Localization $localization, ?string $locale = null): RedirectResponse|JsonResponse
    {
        $locale ??= (string) $request->input('locale', '');
        $code = $localization->match($locale);

        // Only the supported codes themselves (any case, - or _), not loose matches.
        abort_if($code === null || strcasecmp(str_replace('-', '_', $locale), $code) !== 0, 404);

        $localization->persist($code, $request);
        $localization->set($code);

        $target = $localization->urls()->localize($this->back($request), $code, addQuery: false);

        if ($request->expectsJson()) {
            return new JsonResponse(['locale' => $code, 'redirect' => $target]);
        }

        return redirect()->to($target);
    }

    private function back(Request $request): string
    {
        $root = url('/');

        foreach ([$request->input('redirect'), url()->previous()] as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $absolute = str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//') ? url($candidate) : $candidate;

            if ($absolute === $root || str_starts_with($absolute, $root.'/') || str_starts_with($absolute, $root.'?')) {
                return $absolute;
            }
        }

        return $root;
    }
}
