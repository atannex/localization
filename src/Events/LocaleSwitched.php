<?php

declare(strict_types=1);

namespace Atannex\Localization\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * Someone chose a locale explicitly (the switcher, Localization::persist()).
 */
final class LocaleSwitched
{
    use Dispatchable;

    public function __construct(public readonly string $locale, public readonly ?string $previous, public readonly Request $request) {}
}
