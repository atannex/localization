<?php

declare(strict_types=1);

namespace Atannex\Localization\Translations;

use Atannex\Localization\Localization;

/**
 * What should be translated, and what is: combines the keys used in code
 * (Scanner) with the lang files (Catalogue).
 *
 * Reference keys for a locale:
 *   - group keys: those of the default locale's files plus those used in code,
 *   - text keys (JSON): those used in code plus those any locale's JSON has;
 *     the default locale needs none (the key is its text).
 */
final class Inventory
{
    /** @var array{keys: array<string, list<string>>, dynamic: list<string>}|null */
    private ?array $scan = null;

    public function __construct(
        private readonly Localization $localization,
        private readonly Catalogue $catalogue,
        private readonly Scanner $scanner,
        private bool $scanCode = true,
    ) {}

    public function withoutScan(): self
    {
        $clone = clone $this;
        $clone->scanCode = false;

        return $clone;
    }

    public function catalogue(): Catalogue
    {
        return $this->catalogue;
    }

    /**
     * @return array{keys: array<string, list<string>>, dynamic: list<string>}
     */
    public function scan(): array
    {
        if (! $this->scanCode) {
            return ['keys' => [], 'dynamic' => []];
        }

        return $this->scan ??= $this->scanner->scan();
    }

    /**
     * @return list<string>
     */
    public function groupKeys(): array
    {
        $default = $this->localization->default()->code;
        $keys = array_keys(array_filter(
            $this->catalogue->all($default),
            fn (string $value, int|string $key) => Catalogue::isGroupKey($key) && $this->isGroupFileKey((string) $key),
            ARRAY_FILTER_USE_BOTH,
        ));

        foreach (array_map('strval', array_keys($this->scan()['keys'])) as $key) {
            if (Catalogue::isGroupKey($key) && $this->isGroupFileKey($key)) {
                $keys[] = $key;
            }
        }

        return $this->unique($keys);
    }

    /**
     * @return list<string>
     */
    public function textKeys(): array
    {
        $keys = [];

        foreach (array_map('strval', array_keys($this->scan()['keys'])) as $key) {
            if (! Catalogue::isGroupKey($key) || ! $this->isGroupFileKey($key)) {
                $keys[] = $key;
            }
        }

        foreach ($this->localization->codes() as $code) {
            $keys = [...$keys, ...array_map('strval', array_keys($this->catalogue->json($code)))];
        }

        return $this->unique($keys);
    }

    /**
     * @return list<string>
     */
    public function reference(string $locale): array
    {
        return $locale === $this->localization->default()->code
            ? $this->groupKeys()
            : $this->unique([...$this->groupKeys(), ...$this->textKeys()]);
    }

    /**
     * @return list<string>
     */
    public function missing(string $locale): array
    {
        return $this->catalogue->missing($locale, $this->reference($locale));
    }

    /**
     * Per supported locale: total reference keys, translated, missing and coverage (0–100).
     *
     * @return array<string, array{total: int, translated: int, missing: int, coverage: float}>
     */
    public function coverage(): array
    {
        $report = [];

        foreach ($this->localization->codes() as $code) {
            $total = count($this->reference($code));
            $missing = count($this->missing($code));

            $report[$code] = [
                'total' => $total,
                'translated' => $total - $missing,
                'missing' => $missing,
                'coverage' => $total === 0 ? 100.0 : round(($total - $missing) / $total * 100, 1),
            ];
        }

        return $report;
    }

    /**
     * Keys in a locale's files that no code uses, minus those under a
     * dynamic prefix (and Laravel's own groups, which the framework reads).
     *
     * @return list<string>
     */
    public function unused(string $locale): array
    {
        $scan = $this->scan();
        $used = $scan['keys'];
        $framework = ['validation', 'passwords', 'pagination', 'auth'];
        $unused = [];

        foreach (array_map('strval', array_keys($this->catalogue->all($locale))) as $key) {
            if (isset($used[$key]) || in_array(strtok($key, '.'), $framework, true)) {
                continue;
            }

            foreach ($scan['dynamic'] as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    continue 2;
                }
            }

            $unused[] = $key;
        }

        return $unused;
    }

    /** The default locale's text for a key (the key itself for text keys). */
    public function source(string $key): string
    {
        return (string) ($this->catalogue->all($this->localization->default()->code)[$key] ?? $key);
    }

    private function isGroupFileKey(string $key): bool
    {
        $group = (string) strtok($key, '.');

        foreach ($this->catalogue->locales() as $locale) {
            if (in_array($group, $this->catalogue->groups($locale), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function unique(array $keys): array
    {
        $keys = array_values(array_unique(array_map('strval', $keys)));
        sort($keys, SORT_STRING);

        return $keys;
    }
}
