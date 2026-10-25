<?php

declare(strict_types=1);

namespace Atannex\Localization\Detectors;

use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\LocaleRegistry;
use Illuminate\Http\Request;

final class SessionDetector implements Detector
{
    public function __construct(private readonly string $key = 'locale') {}

    public function detect(Request $request, LocaleRegistry $locales): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get($this->key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
