<?php

declare(strict_types=1);

namespace Atannex\Localization\Detectors;

use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\LanguageTag;
use Atannex\Localization\LocaleRegistry;
use Illuminate\Http\Request;

/**
 * Accept-Language, honouring q-values, with region and script matching
 * (fr-CM → fr, pt-AO → pt_BR, zh-Hant-HK → zh_Hant).
 */
final class HeaderDetector implements Detector
{
    public function detect(Request $request, LocaleRegistry $locales): ?string
    {
        return $locales->negotiate(LanguageTag::parseAcceptLanguage($request->headers->get('Accept-Language')));
    }
}
