<?php

declare(strict_types=1);

namespace Atannex\Localization\Stores;

use Atannex\Localization\Contracts\Store;
use Illuminate\Http\Request;
use Throwable;

/**
 * Saves the choice on the signed-in user through setPreferredLocale()
 * (Concerns\HasPreferredLocale provides it). Users without it are skipped.
 */
final class UserStore implements Store
{
    public function remember(Request $request, string $locale): void
    {
        try {
            $user = $request->user();
        } catch (Throwable) {
            return;
        }

        if (is_object($user) && method_exists($user, 'setPreferredLocale')) {
            $user->setPreferredLocale($locale);
        }
    }
}
