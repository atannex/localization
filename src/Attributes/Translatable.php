<?php

declare(strict_types=1);

namespace Atannex\Localization\Attributes;

use Attribute;

/**
 * Declares a model's translatable attributes, as an alternative to the
 * $translatable property:
 *
 *     #[Translatable('title', 'description')]
 *     class Post extends Model { use HasTranslations; }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Translatable
{
    /** @var list<string> */
    public readonly array $columns;

    public function __construct(string|array ...$columns)
    {
        $this->columns = array_values(array_map('strval', array_merge(...array_map(fn ($column) => (array) $column, $columns))));
    }
}
