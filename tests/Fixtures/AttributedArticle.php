<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Fixtures;

use Atannex\Localization\Attributes\Translatable;
use Atannex\Localization\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Translatable('title', 'body')]
class AttributedArticle extends Model
{
    use HasTranslations;

    protected $table = 'articles';

    protected $guarded = [];

    protected function title(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value === null ? null : strtoupper($value));
    }
}
