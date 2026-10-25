<?php

declare(strict_types=1);

namespace Atannex\Localization\Detectors;

use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\LocaleRegistry;
use Illuminate\Http\Request;

final class CookieDetector implements Detector
{
    public function __construct(private readonly string $name = 'locale') {}

    public function detect(Request $request, LocaleRegistry $locales): ?string
    {
        $value = $request->cookie($this->name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
