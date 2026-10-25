<?php

declare(strict_types=1);

namespace Atannex\Localization;

use Atannex\Localization\Exceptions\InvalidConfiguration;
use Atannex\Localization\Exceptions\UnsupportedLocale;

/**
 * The supported locales (config localization.locales, completed from the
 * built-in catalog), the default and fallbacks, and matching of arbitrary
 * tags ('fr-CM', 'PT-br', 'zh-Hant-TW') to a supported code.
 */
final class LocaleRegistry
{
    /** @var array<string, Locale> */
    private array $locales = [];

    /** @var array<string, string> comparison key => code */
    private array $keys = [];

    private string $default;

    /** @var array<string, array<int, mixed>>|null */
    private static ?array $catalog = null;

    /**
     * @param  array<int|string, mixed>  $locales
     * @param  array<string, list<string>>  $chains
     */
    public function __construct(
        array $locales,
        ?string $default = null,
        private readonly ?string $fallback = null,
        private readonly array $chains = [],
    ) {
        foreach ($locales as $key => $value) {
            [$code, $overrides] = is_int($key) ? [(string) $value, []] : [(string) $key, (array) $value];

            $this->add($this->build($code, $overrides));
        }

        if ($this->locales === []) {
            throw new InvalidConfiguration('localization.locales must list at least one locale.');
        }

        $default ??= array_key_first($this->locales);

        if (! isset($this->locales[$default])) {
            throw new InvalidConfiguration("The default locale [{$default}] is not in localization.locales.");
        }

        $this->default = $default;
    }

    /**
     * @param  array<string, mixed>  $config  the localization config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            (array) ($config['locales'] ?? ['en']),
            $config['default'] ?? null,
            $config['fallback'] ?? null,
            (array) ($config['fallback_chains'] ?? []),
        );
    }

    /**
     * Metadata for any known code, supported or not.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function catalog(): array
    {
        return self::$catalog ??= require __DIR__.'/../resources/catalog.php';
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function describe(string $code, array $overrides = []): Locale
    {
        $catalog = self::catalog();
        $known = $catalog[$code] ?? $catalog[LanguageTag::toPosix($code)] ?? null;

        // An unknown regional variant inherits its language's script and direction.
        $parent = $catalog[LanguageTag::parse($code)['language']] ?? null;

        if ($known === null && $parent === null && ! isset($overrides['name'])) {
            throw new InvalidConfiguration("Unknown locale [{$code}]: give it at least a 'name' in localization.locales.");
        }

        $base = $known ?? [
            $parent ? $parent[0].' ('.strtoupper((string) LanguageTag::parse($code)['region']).')' : $code,
            $parent[1] ?? $code,
            $parent[2] ?? 'Latn',
            $parent[3] ?? 'ltr',
            LanguageTag::toPosix($code),
            null,
        ];

        $reserved = ['name', 'native', 'script', 'dir', 'direction', 'regional', 'og', 'currency'];

        return new Locale(
            code: $code,
            name: (string) ($overrides['name'] ?? $base[0]),
            native: (string) ($overrides['native'] ?? $base[1]),
            script: (string) ($overrides['script'] ?? $base[2]),
            direction: (string) ($overrides['dir'] ?? $overrides['direction'] ?? $base[3]),
            regional: (string) ($overrides['regional'] ?? $overrides['og'] ?? $base[4]),
            currency: isset($overrides['currency']) ? (string) $overrides['currency'] : $base[5],
            extra: array_diff_key($overrides, array_flip($reserved)),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function build(string $code, array $overrides): Locale
    {
        if (! LanguageTag::isWellFormed($code)) {
            throw new InvalidConfiguration("[{$code}] is not a valid locale code.");
        }

        return self::describe($code, $overrides);
    }

    private function add(Locale $locale): void
    {
        $this->locales[$locale->code] = $locale;
        $this->keys[LanguageTag::key($locale->code)] = $locale->code;
    }

    /**
     * @return array<string, Locale>
     */
    public function all(): array
    {
        return $this->locales;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->locales);
    }

    public function count(): int
    {
        return count($this->locales);
    }

    public function default(): Locale
    {
        return $this->locales[$this->default];
    }

    public function supports(Locale|string|null $code): bool
    {
        return $code !== null && isset($this->locales[(string) $code]);
    }

    public function find(Locale|string|null $code): ?Locale
    {
        return $code === null ? null : ($this->locales[(string) $code] ?? null);
    }

    public function get(Locale|string $code): Locale
    {
        return $this->find($code) ?? throw UnsupportedLocale::make((string) $code, $this->codes());
    }

    /**
     * Where $code's missing translations come from, best first, never
     * including $code itself: the configured chain, the language without
     * its region (fr_CA → fr), the fallback, then the default.
     *
     * @return list<string>
     */
    public function fallbacks(Locale|string $code): array
    {
        $code = (string) $code;
        $chain = $this->chains[$code] ?? [];

        $language = LanguageTag::parse($code)['language'];
        if (isset($this->locales[$language])) {
            $chain[] = $language;
        }

        $chain[] = $this->fallback ?? $this->default;
        $chain[] = $this->default;

        return array_values(array_filter(
            array_unique($chain),
            fn (string $candidate) => $candidate !== $code && $this->supports($candidate),
        ));
    }

    public function fallback(Locale|string|null $code = null): Locale
    {
        $code ??= $this->default;

        return $this->locales[$this->fallbacks($code)[0] ?? $this->default];
    }

    /**
     * The supported code for any tag, or null:
     *   1. exact, ignoring case and -/_ (pt-br → pt_BR)
     *   2. language + script (zh-Hant-TW → zh_Hant)
     *   3. language + region (en-US-x-foo → en_US)
     *   4. the bare language (fr-CM → fr)
     *   5. another region of the same language (pt-AO → pt_BR when only pt_BR is supported)
     */
    public function match(?string $tag): ?string
    {
        if ($tag === null || trim($tag) === '' || $tag === '*') {
            return null;
        }

        if (isset($this->keys[LanguageTag::key($tag)])) {
            return $this->keys[LanguageTag::key($tag)];
        }

        ['language' => $language, 'script' => $script, 'region' => $region] = LanguageTag::parse($tag);

        foreach (array_filter([$script ? "{$language}-{$script}" : null, $region ? "{$language}-{$region}" : null, $language]) as $candidate) {
            if (isset($this->keys[strtolower($candidate)])) {
                return $this->keys[strtolower($candidate)];
            }
        }

        foreach ($this->locales as $code => $locale) {
            if ($locale->language() === $language) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Best supported code for tags in order of preference (Accept-Language):
     * the first tag that matches at all wins, so "de-AT, fr" on an en/de
     * site is German, not French.
     *
     * @param  iterable<string>  $tags
     */
    public function negotiate(iterable $tags): ?string
    {
        foreach ($tags as $tag) {
            if (($code = $this->match($tag)) !== null) {
                return $code;
            }
        }

        return null;
    }
}
