<?php

declare(strict_types=1);

namespace Atannex\Localization;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;

/**
 * One supported locale: its code (as configured: 'en', 'pt_BR'…) and what
 * there is to know about it. Immutable; built by the LocaleRegistry.
 *
 * @implements Arrayable<string, mixed>
 */
final class Locale implements Arrayable, JsonSerializable, Stringable
{
    /**
     * @param  array<string, mixed>  $extra  any other configured keys
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $native,
        public readonly string $script = 'Latn',
        public readonly string $direction = 'ltr',
        public readonly string $regional = '',
        public readonly ?string $currency = null,
        public readonly array $extra = [],
    ) {}

    /** ISO 639 language: 'pt' for pt_BR. */
    public function language(): string
    {
        return LanguageTag::parse($this->code)['language'];
    }

    /** ISO 3166 region, from the code or the regional code: 'BR' for pt_BR. */
    public function region(): ?string
    {
        return LanguageTag::parse($this->code)['region']
            ?? LanguageTag::parse($this->regional ?: $this->code)['region'];
    }

    /** BCP 47 tag for HTML lang / hreflang / Content-Language: 'pt-BR'. */
    public function tag(): string
    {
        return LanguageTag::toBcp47($this->code);
    }

    /** POSIX / Open Graph style code: 'pt_BR'. */
    public function regional(): string
    {
        return $this->regional !== '' ? $this->regional : LanguageTag::toPosix($this->code);
    }

    public function isRtl(): bool
    {
        return $this->direction === 'rtl';
    }

    /** The region's flag emoji (🇧🇷), or null when there is no region. */
    public function flag(): ?string
    {
        if (isset($this->extra['flag'])) {
            return (string) $this->extra['flag'];
        }

        $region = $this->region();

        if ($region === null || ! preg_match('/^[A-Z]{2}$/', $region)) {
            return null;
        }

        return mb_chr(0x1F1E6 + ord($region[0]) - 65).mb_chr(0x1F1E6 + ord($region[1]) - 65);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'code', 'name', 'native', 'script', 'direction', 'currency' => $this->{$key},
            'dir' => $this->direction,
            'regional', 'og' => $this->regional(),
            'tag' => $this->tag(),
            'flag' => $this->flag(),
            default => $this->extra[$key] ?? $default,
        };
    }

    public function is(Locale|string|null $locale): bool
    {
        return $locale !== null && $this->code === ($locale instanceof self ? $locale->code : $locale);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'tag' => $this->tag(),
            'name' => $this->name,
            'native' => $this->native,
            'script' => $this->script,
            'dir' => $this->direction,
            'regional' => $this->regional(),
            'currency' => $this->currency,
            'flag' => $this->flag(),
        ] + $this->extra;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
