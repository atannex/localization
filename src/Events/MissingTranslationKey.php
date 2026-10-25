<?php

declare(strict_types=1);

namespace Atannex\Localization\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * __() found no translation for $key in $locale.
 */
final class MissingTranslationKey
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(
        public readonly string $key,
        public readonly string $locale,
        public readonly array $replace = [],
    ) {}
}
