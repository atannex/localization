<?php

declare(strict_types=1);

namespace Atannex\Localization\Detectors;

use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\LocaleRegistry;
use Illuminate\Http\Request;

/**
 * The host, through localization.domains (example.fr => fr). Matching is
 * case-insensitive and ignores the port.
 */
final class DomainDetector implements Detector
{
    /**
     * @param  array<string, string>  $domains
     */
    public function __construct(private readonly array $domains = []) {}

    public function detect(Request $request, LocaleRegistry $locales): ?string
    {
        $host = strtolower($request->getHost());

        foreach ($this->domains as $domain => $locale) {
            if (strtolower((string) $domain) === $host) {
                return (string) $locale;
            }
        }

        return null;
    }
}
