<?php

declare(strict_types=1);

namespace Atannex\Localization\Exceptions;

use Illuminate\Database\Eloquent\Model;

final class AttributeIsNotTranslatable extends LocalizationException
{
    public static function make(string $key, Model $model): self
    {
        $translatable = implode(', ', method_exists($model, 'getTranslatableAttributes') ? $model->getTranslatableAttributes() : []);

        return new self("[{$key}] is not translatable on ".$model::class.". Translatable attributes: {$translatable}.");
    }
}
