<?php

declare(strict_types=1);

namespace Atannex\Localization\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

/**
 * A request's locale was decided; $detector is the detector that answered (null: the default).
 */
final class LocaleNegotiated
{
    use Dispatchable;

    public function __construct(public readonly string $locale, public readonly ?string $detector, public readonly Request $request) {}
}
