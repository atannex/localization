<?php

declare(strict_types=1);

namespace Atannex\Localization\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A translatable attribute received a value in one locale (not yet saved).
 */
final class TranslationSet
{
    use Dispatchable;

    public function __construct(
        public readonly Model $model,
        public readonly string $key,
        public readonly string $locale,
        public readonly mixed $old,
        public readonly mixed $new,
    ) {}
}
