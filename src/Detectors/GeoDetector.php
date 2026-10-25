<?php

declare(strict_types=1);

namespace Atannex\Localization\Detectors;

use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\LocaleRegistry;
use Illuminate\Http\Request;

/**
 * The visitor's country, from a header your CDN or proxy sets
 * (CF-IPCountry, CloudFront-Viewer-Country…), through localization.geo.countries.
 */
final class GeoDetector implements Detector
{
    /**
     * @param  list<string>  $headers
     * @param  array<string, string>  $countries
     */
    public function __construct(private readonly array $headers = [], private readonly array $countries = []) {}

    public function detect(Request $request, LocaleRegistry $locales): ?string
    {
        $countries = array_change_key_case($this->countries, CASE_UPPER);

        foreach ($this->headers as $header) {
            $country = strtoupper(trim((string) $request->headers->get($header)));

            if ($country !== '' && isset($countries[$country])) {
                return $countries[$country];
            }
        }

        return null;
    }
}
