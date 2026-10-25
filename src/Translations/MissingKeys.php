<?php

declare(strict_types=1);

namespace Atannex\Localization\Translations;

use Atannex\Localization\Events\MissingTranslationKey;
use Atannex\Localization\Exceptions\MissingTranslation;
use Atannex\Localization\Localization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Translation\Translator;

/**
 * Watches __() for keys with no translation (localization.missing): logs
 * them, records them for `localization:missing --recorded`, throws, and
 * dispatches MissingTranslationKey.
 *
 * In the default locale, JSON-style keys ("Sign in") are the text itself,
 * so only group keys (navigation.home) count as missing there.
 */
final class MissingKeys
{
    /** @var array<string, array<string, true>> locale => key => true */
    private array $seen = [];

    private bool $flushing = false;

    public function __construct(
        private readonly Localization $localization,
        private readonly array $config,
    ) {}

    public function register(Translator $translator): void
    {
        $translator->handleMissingKeysUsing(function (string $key, array $replace, ?string $locale) {
            $this->handle($key, $replace, $locale ?? $this->localization->code());

            return null; // keep Laravel's default (the key itself)
        });

        if ($this->config['record'] ?? false) {
            app()->terminating(fn () => $this->flush());
        }
    }

    public function handle(string $key, array $replace, string $locale): void
    {
        if ($this->ignored($key, $locale) || isset($this->seen[$locale][$key])) {
            return;
        }

        $this->seen[$locale][$key] = true;

        event(new MissingTranslationKey($key, $locale, $replace));

        if ($this->config['log'] ?? false) {
            Log::channel($this->config['log_channel'] ?? null)->warning("Missing translation [{$key}] for locale [{$locale}].");
        }

        if ($this->config['throw'] ?? false) {
            throw MissingTranslation::make($key, $locale);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function seen(): array
    {
        return array_map(fn (array $keys) => array_keys($keys), $this->seen);
    }

    /** Merges this request's missing keys into the record file. */
    public function flush(): void
    {
        $path = (string) ($this->config['record_path'] ?? '');

        if ($path === '' || $this->seen === [] || $this->flushing) {
            return;
        }

        $this->flushing = true;
        $recorded = self::recorded($path);

        foreach ($this->seen() as $locale => $keys) {
            $recorded[$locale] = array_values(array_unique([...($recorded[$locale] ?? []), ...$keys]));
            sort($recorded[$locale]);
        }

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

        $this->seen = [];
        $this->flushing = false;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function recorded(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];

        return is_array($data) ? $data : [];
    }

    private function ignored(string $key, string $locale): bool
    {
        if ($key === '' || ! $this->localization->supports($locale)) {
            return true;
        }

        if ($locale === $this->localization->default()->code && ! Catalogue::isGroupKey($key)) {
            return true;
        }

        return Str::is((array) ($this->config['ignore'] ?? []), $key);
    }
}
