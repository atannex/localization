<?php

declare(strict_types=1);

namespace Atannex\Localization\Http\Controllers;

use Atannex\Localization\Localization;
use Atannex\Localization\Translations\Catalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET {frontend.path}/{locale}.json: the locale's JSON strings plus the
 * configured PHP groups (dot keys), for JavaScript. Cached by ETag.
 */
final class TranslationsController
{
    public function __invoke(Request $request, Localization $localization, Catalogue $catalogue, string $locale): JsonResponse|Response
    {
        abort_unless($localization->supports($locale), 404);

        $messages = $catalogue->forFrontend($locale, (array) $localization->config('frontend.groups', []));
        $etag = '"'.hash('xxh128', $locale.json_encode($messages)).'"';
        $maxAge = (int) $localization->config('frontend.max_age', 3600);

        if (in_array($etag, $request->getETags(), true)) {
            return (new Response('', 304))->setEtag(trim($etag, '"'))->setPublic()->setMaxAge($maxAge);
        }

        return (new JsonResponse($messages, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->setEtag(trim($etag, '"'))
            ->setPublic()
            ->setMaxAge($maxAge);
    }
}
