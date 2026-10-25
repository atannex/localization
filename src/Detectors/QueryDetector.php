<?php

declare(strict_types=1);

namespace Atannex\Localization\Detectors;

use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\LocaleRegistry;
use Illuminate\Http\Request;

/**
 * ?lang=fr (the parameter is localization.query_parameter).
 */
final class QueryDetector implements Detector
{
    public function __construct(private readonly string $parameter = 'lang') {}

    public function detect(Request $request, LocaleRegistry $locales): ?string
    {
        $value = $request->query($this->parameter);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
