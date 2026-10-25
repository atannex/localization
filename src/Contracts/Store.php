<?php

declare(strict_types=1);

namespace Atannex\Localization\Contracts;

use Illuminate\Http\Request;

/**
 * Somewhere an explicit locale choice is remembered: a cookie, the session,
 * the signed-in user… Configured in localization.persist.
 */
interface Store
{
    public function remember(Request $request, string $locale): void;
}
