<?php

declare(strict_types=1);

namespace Atannex\Localization\Detectors;

use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\LocaleRegistry;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Http\Request;
use Throwable;

/**
 * The signed-in user's preferredLocale() (Laravel's HasLocalePreference,
 * which also makes their notifications and mail use it).
 */
final class UserDetector implements Detector
{
    public function detect(Request $request, LocaleRegistry $locales): ?string
    {
        try {
            $user = $request->user();
        } catch (Throwable) {
            return null; // no auth configured for this request
        }

        if (! $user instanceof HasLocalePreference) {
            return null;
        }

        $locale = $user->preferredLocale();

        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}
