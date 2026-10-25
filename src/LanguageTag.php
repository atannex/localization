<?php

declare(strict_types=1);

namespace Atannex\Localization;

/**
 * Language tags as people and browsers write them ('pt-br', 'PT_BR',
 * 'zh-Hant-TW', 'en-US-u-ca-gregory') and the Accept-Language header.
 */
final class LanguageTag
{
    /**
     * @return array{language: string, script: ?string, region: ?string}
     */
    public static function parse(string $tag): array
    {
        $parts = preg_split('/[-_]/', trim($tag)) ?: [];
        $language = strtolower((string) array_shift($parts));
        $script = null;
        $region = null;

        foreach ($parts as $part) {
            if ($script === null && $region === null && preg_match('/^[a-z]{4}$/i', $part)) {
                $script = ucfirst(strtolower($part));
            } elseif ($region === null && preg_match('/^([a-z]{2}|\d{3})$/i', $part)) {
                $region = strtoupper($part);
            } else {
                break; // variants and extensions don't matter for matching
            }
        }

        return ['language' => $language, 'script' => $script, 'region' => $region];
    }

    /** 'pt_br' → 'pt-BR', 'zh_hant_tw' → 'zh-Hant-TW'. */
    public static function toBcp47(string $tag): string
    {
        return implode('-', array_filter(self::parse($tag), fn ($part) => $part !== null && $part !== ''));
    }

    /** 'pt-br' → 'pt_BR'. */
    public static function toPosix(string $tag): string
    {
        return str_replace('-', '_', self::toBcp47($tag));
    }

    /** Comparison key: case and separators ignored. */
    public static function key(string $tag): string
    {
        return strtolower(str_replace('_', '-', trim($tag)));
    }

    public static function isWellFormed(string $tag): bool
    {
        return (bool) preg_match('/^[a-z]{2,3}([-_][a-z0-9]{2,8})*$/i', trim($tag));
    }

    /**
     * Accept-Language → tags by preference ("fr-CM,fr;q=0.9,en;q=0.8,*;q=0.1"
     * → ['fr-CM', 'fr', 'en', '*']). Ties keep header order; q=0 is refused.
     *
     * @return list<string>
     */
    public static function parseAcceptLanguage(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        $entries = [];

        foreach (explode(',', $header) as $position => $item) {
            $pieces = array_map('trim', explode(';', $item));
            $tag = array_shift($pieces);

            if ($tag === '' || ($tag !== '*' && ! self::isWellFormed($tag))) {
                continue;
            }

            $quality = 1.0;
            foreach ($pieces as $piece) {
                if (preg_match('/^q\s*=\s*([01](?:\.\d{0,3})?)$/i', $piece, $match)) {
                    $quality = (float) $match[1];
                }
            }

            if ($quality > 0) {
                $entries[] = [$tag, $quality, $position];
            }
        }

        usort($entries, fn (array $a, array $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

        return array_map(fn (array $entry) => $entry[0], $entries);
    }
}
