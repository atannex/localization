<?php

declare(strict_types=1);

namespace Atannex\Localization\Exceptions;

final class MissingTranslation extends LocalizationException
{
    public static function make(string $key, string $locale): self
    {
        return new self("Missing translation for [{$key}] in [{$locale}].");
    }
}
