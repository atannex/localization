<?php

declare(strict_types=1);

namespace Atannex\Localization\Concerns;

use Atannex\Localization\Attributes\Translatable;
use Atannex\Localization\Events\TranslationSet;
use Atannex\Localization\Exceptions\AttributeIsNotTranslatable;
use Atannex\Localization\LanguageTag;
use Atannex\Localization\Localization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Translatable Eloquent attributes. Each translatable column holds JSON,
 * {"en": "…", "fr": "…"}; reading the attribute gives the current locale's
 * value and falls back along the locale's fallback chain.
 *
 *     class Post extends Model
 *     {
 *         use HasTranslations;
 *
 *         public array $translatable = ['title', 'body'];   // or #[Translatable('title', 'body')]
 *     }
 *
 *     $post->title;                                 // current locale, with fallback
 *     $post->title = 'Bonjour';                     // sets the current locale
 *     $post->title = ['en' => 'Hi', 'fr' => 'Salut'];
 *     $post->getTranslation('title', 'fr', false);  // exact, no fallback
 *     $post->in('fr')->title;                       // a copy reading French
 *     $post->missingTranslations('fr');             // ['body']
 *
 *     Post::whereTranslation('title', 'Salut')->get();          // any locale
 *     Post::whereTranslationLike('title', 'sal')->get();        // case-insensitive
 *     Post::orderByTranslation('title')->get();                 // current locale + fallback
 *     Post::whereMissingTranslation('title', 'fr')->count();    // to translate
 *
 * The scopes compile to the connection's JSON syntax (MySQL, MariaDB,
 * PostgreSQL, SQLite, SQL Server). Values written before a column became
 * translatable (plain text, or a JSON list) read as the default locale.
 *
 * API-compatible with spatie/laravel-translatable for the common methods.
 */
trait HasTranslations
{
    protected ?string $translationLocale = null;

    /** @var array<class-string, list<string>> */
    protected static array $translatableColumnsCache = [];

    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(array_fill_keys($this->getTranslatableAttributes(), 'array'));
    }

    // ---------------------------------------------------------------- declaration

    /**
     * @return list<string>
     */
    public function getTranslatableAttributes(): array
    {
        $declared = property_exists($this, 'translatable') && is_array($this->translatable) ? $this->translatable : [];

        return array_values(array_unique([...$declared, ...static::translatableColumnsFromAttribute()]));
    }

    public function isTranslatableAttribute(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes(), true);
    }

    /**
     * @return list<string>
     */
    protected static function translatableColumnsFromAttribute(): array
    {
        if (isset(static::$translatableColumnsCache[static::class])) {
            return static::$translatableColumnsCache[static::class];
        }

        $reflection = new ReflectionClass(static::class);
        $columns = [];

        do {
            foreach ($reflection->getAttributes(Translatable::class) as $attribute) {
                $columns = [...$columns, ...$attribute->newInstance()->columns];
            }
        } while ($columns === [] && ($reflection = $reflection->getParentClass()));

        return static::$translatableColumnsCache[static::class] = $columns;
    }

    // ---------------------------------------------------------------- locale of this instance

    public function setLocale(string $locale): static
    {
        $this->translationLocale = $locale;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->translationLocale ?: app()->getLocale();
    }

    /** A copy of the model that reads (and writes) $locale. */
    public function in(string $locale): static
    {
        return (clone $this)->setLocale($locale);
    }

    public static function usingLocale(string $locale): static
    {
        return (new static)->setLocale($locale);
    }

    /**
     * @param  'read'|'serialize'  $context
     */
    public function useFallbackLocale(string $context = 'read'): bool
    {
        if (property_exists($this, 'useFallbackLocale')) {
            return (bool) $this->useFallbackLocale;
        }

        return static::localization()->usesFallback($context);
    }

    // ---------------------------------------------------------------- Eloquent hooks

    public function getAttributeValue($key): mixed
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        return $this->getTranslation($key, $this->getLocale(), $this->useFallbackLocale());
    }

    public function setAttribute($key, $value)
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::setAttribute($key, $value);
        }

        // [] is the empty value (no blocks, no tags) in this locale, not "forget
        // every translation": that is forgetTranslations().
        if (is_array($value) && $value !== [] && $this->isTranslationMap($value)) {
            return $this->setTranslations($key, $value);
        }

        return $this->setTranslation($key, $this->getLocale(), $value);
    }

    /**
     * Translatable columns serialise as the current locale's value (config
     * localization.translatable.serialize = 'current'), or as every
     * translation ('all'). A model may set $translationsInArray.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();
        $mode = property_exists($this, 'translationsInArray')
            ? ($this->translationsInArray ? 'all' : 'current')
            : (string) static::localization()->config('translatable.serialize', 'current');

        foreach ($this->getTranslatableAttributes() as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $attributes[$key] = $mode === 'all'
                ? $this->getTranslations($key)
                : $this->getTranslation($key, $this->getLocale(), $this->useFallbackLocale('serialize'));
        }

        return $attributes;
    }

    // ---------------------------------------------------------------- reading

    public function translate(string $key, string $locale = '', bool $useFallbackLocale = true): mixed
    {
        return $this->getTranslation($key, $locale !== '' ? $locale : $this->getLocale(), $useFallbackLocale);
    }

    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true): mixed
    {
        $this->guardAgainstNonTranslatableAttribute($key);

        $translations = $this->getTranslations($key);
        $resolved = $this->resolveTranslationLocale($translations, $locale, $useFallbackLocale);

        if ($this->getAttributeFromArray($key) === null) {
            $value = null;
        } else {
            $value = $resolved !== null ? $translations[$resolved] : null;
            $value ??= static::localization()->config('translatable.allow_null', false) ? null : '';
        }

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        if ($this->hasAttributeMutator($key)) {
            return $this->mutateAttributeMarkedAttribute($key, $value);
        }

        return $value;
    }

    public function getTranslationWithFallback(string $key, string $locale): mixed
    {
        return $this->getTranslation($key, $locale, true);
    }

    public function getTranslationWithoutFallback(string $key, string $locale): mixed
    {
        return $this->getTranslation($key, $locale, false);
    }

    /**
     * One attribute's translations (locale => value), or every translatable
     * attribute's (attribute => [locale => value]) when $key is null.
     *
     * @param  list<string>|null  $allowedLocales
     * @return array<string, mixed>
     */
    public function getTranslations(?string $key = null, ?array $allowedLocales = null): array
    {
        if ($key === null) {
            $all = [];

            foreach ($this->getTranslatableAttributes() as $attribute) {
                $all[$attribute] = $this->getTranslations($attribute, $allowedLocales);
            }

            return $all;
        }

        $this->guardAgainstNonTranslatableAttribute($key);

        $config = static::localization()->config('translatable', []);

        return array_filter(
            $this->decodeTranslations($this->getAttributeFromArray($key)),
            fn (mixed $value, int|string $locale) => ($value !== null || ($config['allow_null'] ?? false))
                && ($value !== '' || ($config['allow_empty'] ?? false))
                && ($allowedLocales === null || in_array((string) $locale, $allowedLocales, true)),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllTranslations(): array
    {
        return $this->getTranslations();
    }

    /**
     * @return list<string>
     */
    public function getTranslatedLocales(string $key): array
    {
        return array_map('strval', array_keys($this->getTranslations($key)));
    }

    public function hasTranslation(string $key, ?string $locale = null): bool
    {
        return array_key_exists($locale ?? $this->getLocale(), $this->getTranslations($key));
    }

    /**
     * Every locale with at least one translated attribute.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = [];

        foreach ($this->getTranslatableAttributes() as $key) {
            $locales = [...$locales, ...$this->getTranslatedLocales($key)];
        }

        return array_values(array_unique($locales));
    }

    // ---------------------------------------------------------------- completeness

    /**
     * Translatable attributes that have a value in the default locale but
     * none in $locale.
     *
     * @return list<string>
     */
    public function missingTranslations(?string $locale = null): array
    {
        $locale ??= $this->getLocale();
        $source = static::localization()->default()->code;
        $missing = [];

        foreach ($this->getTranslatableAttributes() as $key) {
            $translations = $this->getTranslations($key);

            if ((isset($translations[$source]) || $locale === $source) && ! isset($translations[$locale])) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    public function isFullyTranslated(?string $locale = null): bool
    {
        return $this->missingTranslations($locale) === [];
    }

    /**
     * Share of the attributes filled in the default locale that are also
     * filled in each locale, 0–1: ['en' => 1.0, 'fr' => 0.5].
     *
     * @return array<string, float>
     */
    public function translationProgress(): array
    {
        $source = static::localization()->default()->code;
        $filled = array_filter($this->getTranslatableAttributes(), fn (string $key) => $this->hasTranslation($key, $source));
        $progress = [];

        foreach (static::localization()->codes() as $code) {
            $done = count(array_filter($filled, fn (string $key) => $this->hasTranslation($key, $code)));
            $progress[$code] = $filled === [] ? 1.0 : round($done / count($filled), 4);
        }

        return $progress;
    }

    // ---------------------------------------------------------------- writing

    public function setTranslation(string $key, string $locale, mixed $value): static
    {
        $this->guardAgainstNonTranslatableAttribute($key);

        $translations = $this->decodeTranslations($this->getAttributeFromArray($key));
        $old = $translations[$locale] ?? null;

        if ($this->hasSetMutator($key)) {
            $this->{'set'.Str::studly($key).'Attribute'}($value, $locale);
            $value = $this->attributes[$key] ?? null;
        } elseif ($this->hasAttributeSetMutator($key)) {
            $this->setAttributeMarkedMutatedAttributeValue($key, $value);
            $value = $this->attributes[$key] ?? null;
        }

        $translations[$locale] = $value;

        $this->attributes[$key] = $this->encodeTranslations($translations);

        if ($old !== $value) {
            event(new TranslationSet($this, $key, $locale, $old, $value));
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    public function setTranslations(string $key, array $translations): static
    {
        $this->guardAgainstNonTranslatableAttribute($key);

        if ($translations === []) {
            $this->attributes[$key] = $this->encodeTranslations([]);

            return $this;
        }

        foreach ($translations as $locale => $value) {
            $this->setTranslation($key, (string) $locale, $value);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    public function replaceTranslations(string $key, array $translations): static
    {
        $this->guardAgainstNonTranslatableAttribute($key);
        $this->attributes[$key] = $this->encodeTranslations([]);

        return $this->setTranslations($key, $translations);
    }

    public function forgetTranslation(string $key, string $locale): static
    {
        $this->guardAgainstNonTranslatableAttribute($key);

        $translations = $this->decodeTranslations($this->getAttributeFromArray($key));
        unset($translations[$locale]);

        $this->attributes[$key] = $this->encodeTranslations($translations);

        return $this;
    }

    public function forgetTranslations(string $key, bool $asNull = false): static
    {
        $this->guardAgainstNonTranslatableAttribute($key);
        $this->attributes[$key] = $asNull ? null : $this->encodeTranslations([]);

        return $this;
    }

    public function forgetAllTranslations(string $locale): static
    {
        foreach ($this->getTranslatableAttributes() as $key) {
            $this->forgetTranslation($key, $locale);
        }

        return $this;
    }

    // ---------------------------------------------------------------- query scopes

    /**
     * SQL for the column's value in $locale (default: current), falling
     * back along the locale's fallback chain unless $fallback is false.
     * Empty strings count as missing. On MySQL/MariaDB the result is
     * compared and sorted case-insensitively, like a plain text column.
     */
    public function translatedColumnSql(string $column, ?string $locale = null, bool $fallback = true): string
    {
        $locale ??= app()->getLocale();
        $grammar = $this->getConnection()->getQueryGrammar();
        $table = $this->getTable();

        $chain = $fallback ? [$locale, ...static::localization()->fallbacks($locale)] : [$locale];
        $chain = array_values(array_unique($chain));

        $paths = array_map(fn (string $code) => 'nullif('.$grammar->wrap("{$table}.{$column}->{$code}").", '')", $chain);
        $sql = count($paths) === 1 ? $paths[0] : 'coalesce('.implode(', ', $paths).')';

        return match ($this->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => "convert({$sql} using utf8mb4) collate utf8mb4_unicode_ci",
            default => $sql,
        };
    }

    /**
     * Rows whose $column equals $value in $locale, or in any supported locale
     * when $locale is null.
     */
    public function scopeWhereTranslation(Builder $query, string $column, mixed $value, ?string $locale = null, string $operator = '='): Builder
    {
        $locales = $locale !== null ? [$locale] : static::localization()->codes();
        $operator = in_array($operator, ['=', '!=', '<>', '<', '>', '<=', '>='], true) ? $operator : '=';

        return $query->where(function (Builder $query) use ($column, $value, $locales, $operator) {
            foreach ($locales as $code) {
                $query->orWhereRaw($this->translatedColumnSql($column, $code, false)." {$operator} ?", [$value]);
            }
        });
    }

    public function scopeOrWhereTranslation(Builder $query, string $column, mixed $value, ?string $locale = null, string $operator = '='): Builder
    {
        return $query->orWhere(fn (Builder $query) => $this->scopeWhereTranslation($query, $column, $value, $locale, $operator));
    }

    /**
     * Case-insensitive substring search in one or more columns, in the given
     * locales (default: every supported locale). % and _ are literal.
     *
     * @param  string|list<string>  $columns
     * @param  list<string>|null  $locales
     */
    public function scopeWhereTranslationLike(Builder $query, string|array $columns, string $search, ?array $locales = null): Builder
    {
        $locales ??= static::localization()->codes();
        $columns = (array) $columns;

        if ($columns === [] || $locales === []) {
            return $query->whereRaw('1 = 0');
        }

        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
        // Backslash is MySQL's default LIKE escape (and '\' would not parse there).
        $escape = in_array($this->getConnection()->getDriverName(), ['mysql', 'mariadb'], true) ? '' : " escape '\\'";

        return $query->where(function (Builder $query) use ($columns, $locales, $pattern, $escape) {
            foreach ($columns as $column) {
                foreach ($locales as $code) {
                    $query->orWhereRaw('lower('.$this->translatedColumnSql($column, $code, false).") like ?{$escape}", [$pattern]);
                }
            }
        });
    }

    /**
     * @param  string|list<string>  $columns
     * @param  list<string>|null  $locales
     */
    public function scopeOrWhereTranslationLike(Builder $query, string|array $columns, string $search, ?array $locales = null): Builder
    {
        return $query->orWhere(fn (Builder $query) => $this->scopeWhereTranslationLike($query, $columns, $search, $locales));
    }

    public function scopeOrderByTranslation(Builder $query, string $column, string $direction = 'asc', ?string $locale = null): Builder
    {
        return $query->orderByRaw($this->translatedColumnSql($column, $locale).' '.(strtolower($direction) === 'desc' ? 'desc' : 'asc'));
    }

    /**
     * Adds the column's translated value to the select, as $as (default: the column).
     */
    public function scopeSelectTranslation(Builder $query, string $column, ?string $locale = null, ?string $as = null): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->getTable().'.*');
        }

        return $query->selectRaw($this->translatedColumnSql($column, $locale).' as '.$this->getConnection()->getQueryGrammar()->wrap($as ?? $column));
    }

    /** Rows that have a non-empty value for $column in $locale (default: current). */
    public function scopeWhereHasTranslation(Builder $query, string $column, ?string $locale = null): Builder
    {
        return $query->whereRaw($this->translatedColumnSql($column, $locale ?? app()->getLocale(), false).' is not null');
    }

    /** Rows with $column filled in the default locale but not in $locale: the translation queue. */
    public function scopeWhereMissingTranslation(Builder $query, string $column, ?string $locale = null): Builder
    {
        $default = static::localization()->default()->code;
        $locale ??= app()->getLocale();

        return $query
            ->whereRaw($this->translatedColumnSql($column, $default, false).' is not null')
            ->whereRaw($this->translatedColumnSql($column, $locale, false).' is null');
    }

    public function scopeWhereLocale(Builder $query, string $column, string $locale): Builder
    {
        return $this->scopeWhereHasTranslation($query, $column, $locale);
    }

    /**
     * @param  list<string>  $locales
     */
    public function scopeWhereLocales(Builder $query, string $column, array $locales): Builder
    {
        return $query->where(function (Builder $query) use ($column, $locales) {
            foreach ($locales as $locale) {
                $query->orWhereRaw($this->translatedColumnSql($column, $locale, false).' is not null');
            }
        });
    }

    public function scopeWhereJsonContainsLocale(Builder $query, string $column, string $locale, mixed $value, string $operator = '='): Builder
    {
        return $this->scopeWhereTranslation($query, $column, $value, $locale, $operator);
    }

    /**
     * @param  list<string>  $locales
     */
    public function scopeWhereJsonContainsLocales(Builder $query, string $column, array $locales, mixed $value, string $operator = '='): Builder
    {
        return $query->where(function (Builder $query) use ($column, $locales, $value, $operator) {
            foreach ($locales as $locale) {
                $query->orWhere(fn (Builder $query) => $this->scopeWhereTranslation($query, $column, $value, $locale, $operator));
            }
        });
    }

    // ---------------------------------------------------------------- internals

    protected static function localization(): Localization
    {
        return app(Localization::class);
    }

    protected function guardAgainstNonTranslatableAttribute(string $key): void
    {
        if (! $this->isTranslatableAttribute($key)) {
            throw AttributeIsNotTranslatable::make($key, $this);
        }
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    protected function resolveTranslationLocale(array $translations, string $locale, bool $useFallback): ?string
    {
        if (array_key_exists($locale, $translations)) {
            return $locale;
        }

        if (! $useFallback) {
            return null;
        }

        $localization = static::localization();
        $chain = $localization->supports($locale) ? $localization->fallbacks($locale) : [$localization->fallback()->code];

        if (method_exists($this, 'getFallbackLocale') && is_string($custom = $this->getFallbackLocale())) {
            array_unshift($chain, $custom);
        }

        foreach ($chain as $candidate) {
            if (array_key_exists($candidate, $translations)) {
                return $candidate;
            }
        }

        if ($translations !== [] && $localization->config('translatable.fallback_any', false)) {
            return (string) array_key_first($translations);
        }

        return null;
    }

    /**
     * The stored value as locale => value. A value that is not a locale map
     * (plain text, a JSON list, a scalar) is a pre-translation value and
     * belongs to the default locale.
     *
     * @return array<string, mixed>
     */
    protected function decodeTranslations(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        if (is_string($raw) && json_last_error() !== JSON_ERROR_NONE) {
            return [static::localization()->default()->code => $raw]; // legacy plain text
        }

        // "{}" is an empty set of translations; "[]" is a pre-translation empty list.
        if ($decoded === []) {
            return is_string($raw) && str_starts_with(ltrim($raw), '[') ? [static::localization()->default()->code => []] : [];
        }

        if (is_array($decoded) && $this->isTranslationMap($decoded)) {
            return $decoded;
        }

        return [static::localization()->default()->code => $decoded];
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    protected function encodeTranslations(array $translations): string
    {
        return (string) json_encode($translations === [] ? new \stdClass : $translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Whether an array is locale => value: every key a language tag and at
     * least one a supported locale (so ['type' => 'quote'] is a value).
     *
     * @param  array<mixed>  $value
     */
    protected function isTranslationMap(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        if (array_is_list($value)) {
            return false;
        }

        $supported = false;

        foreach (array_keys($value) as $key) {
            if (! is_string($key) || ! LanguageTag::isWellFormed($key)) {
                return false;
            }

            $supported = $supported || static::localization()->supports($key);
        }

        return $supported;
    }
}
