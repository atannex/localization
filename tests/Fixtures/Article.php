<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Fixtures;

use Atannex\Localization\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['title', 'body'];
}
