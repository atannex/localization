<?php

declare(strict_types=1);

namespace Atannex\Localization\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The application's locale changed (Localization::set(), the middleware, using()).
 */
final class LocaleChanged
{
    use Dispatchable;

    public function __construct(public readonly string $locale, public readonly ?string $previous) {}
}
