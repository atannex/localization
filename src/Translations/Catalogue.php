<?php

declare(strict_types=1);

namespace Atannex\Localization\Translations;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

/**
 * The application's translation files: lang/{locale}.json (text keys) and
 * lang/{locale}/{group}.php (group keys, read as dot keys "group.key").
 * Reads, compares and writes them, keeping files sorted and readable.
 */
final class Catalogue
{
    /** @var array<string, array<string, string>> */
    private array $cache = [];

    public function __construct(
        private readonly Filesystem $files,
        private readonly string $path,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    /** True for "navigation.home"-style keys, false for text keys like "Sign in." */
    public static function isGroupKey(int|string $key): bool
    {
        // PHP turns numeric keys ("2026") into integers; those are text keys.
        return is_string($key) && (bool) preg_match('/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)+$/', $key);
    }

    /**
     * Locales that have files on disk.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        if (! $this->files->isDirectory($this->path)) {
            return [];
        }

        $locales = [];

        foreach ($this->files->files($this->path) as $file) {
            if ($file->getExtension() === 'json') {
                $locales[] = $file->getBasename('.json');
            }
        }

        foreach ($this->files->directories($this->path) as $directory) {
            if (basename($directory) !== 'vendor') {
                $locales[] = basename($directory);
            }
        }

        $locales = array_values(array_unique($locales));
        sort($locales);

        return $locales;
    }

    /**
     * @return array<string, string>
     */
    public function json(string $locale): array
    {
        $file = $this->jsonPath($locale);

        if (! $this->files->exists($file)) {
            return [];
        }

        $data = json_decode((string) $this->files->get($file), true);

        return is_array($data) ? array_map(fn ($value) => is_string($value) ? $value : (string) json_encode($value), $data) : [];
    }

    /**
     * @return list<string>
     */
    public function groups(string $locale): array
    {
        $directory = $this->path.DIRECTORY_SEPARATOR.$locale;

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $groups = array_map(fn ($file) => $file->getBasename('.php'), array_filter(
            $this->files->files($directory),
            fn ($file) => $file->getExtension() === 'php',
        ));

        sort($groups);

        return array_values($groups);
    }

    /**
     * A group's messages as dot keys ("desks.news" => "News").
     *
     * @return array<string, string>
     */
    public function group(string $locale, string $group): array
    {
        $file = $this->groupPath($locale, $group);

        if (! $this->files->exists($file)) {
            return [];
        }

        $data = $this->files->getRequire($file);

        return is_array($data) ? array_map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value), Arr::dot($data)) : [];
    }

    /**
     * Every message of a locale: text keys as they are, group keys as "group.key".
     *
     * @return array<string, string>
     */
    public function all(string $locale): array
    {
        if (isset($this->cache[$locale])) {
            return $this->cache[$locale];
        }

        $messages = $this->json($locale);

        foreach ($this->groups($locale) as $group) {
            foreach ($this->group($locale, $group) as $key => $value) {
                $messages["{$group}.{$key}"] = $value;
            }
        }

        return $this->cache[$locale] = $messages;
    }

    public function has(string $locale, string $key): bool
    {
        return array_key_exists($key, $this->all($locale));
    }

    /**
     * Keys of $reference with no message in $locale.
     *
     * @param  iterable<string>  $reference
     * @return list<string>
     */
    public function missing(string $locale, iterable $reference): array
    {
        $messages = $this->all($locale);
        $missing = [];

        foreach ($reference as $key) {
            if (! array_key_exists($key, $messages)) {
                $missing[] = (string) $key;
            }
        }

        sort($missing, SORT_STRING);

        return array_values(array_unique($missing));
    }

    /**
     * Writes messages: text keys into {locale}.json, group keys into their
     * group file. Existing messages are kept unless $overwrite.
     *
     * @param  array<string, string|null>  $messages
     * @return int the number of messages written
     */
    public function put(string $locale, array $messages, bool $overwrite = false): int
    {
        $json = $this->json($locale);
        $groups = [];
        $written = 0;

        foreach ($messages as $key => $value) {
            $key = (string) $key;
            $value = (string) $value;
            $group = self::isGroupKey($key) ? strtok($key, '.') : null;

            // A dotted key only goes to a group file when that group exists in
            // some locale; otherwise it is text ("e.g.") and goes to JSON.
            if ($group !== null && $this->groupExistsAnywhere($group)) {
                $groups[$group] ??= $this->groupArray($locale, $group);
                $item = substr($key, strlen($group) + 1);

                if ($overwrite || ! Arr::has($groups[$group], $item)) {
                    Arr::set($groups[$group], $item, $value);
                    $written++;
                }

                continue;
            }

            if ($overwrite || ! array_key_exists($key, $json)) {
                $json[$key] = $value;
                $written++;
            }
        }

        if ($written === 0) {
            return 0;
        }

        $this->writeJson($locale, $json);

        foreach ($groups as $group => $data) {
            $this->writeGroup($locale, $group, $data);
        }

        unset($this->cache[$locale]);

        return $written;
    }

    /**
     * Removes keys from a locale's files.
     *
     * @param  list<string>  $keys
     */
    public function forget(string $locale, array $keys): int
    {
        $json = $this->json($locale);
        $removed = 0;
        $groups = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $json)) {
                unset($json[$key]);
                $removed++;

                continue;
            }

            if (self::isGroupKey($key)) {
                $group = (string) strtok($key, '.');
                $groups[$group] ??= $this->groupArray($locale, $group);
                $item = substr($key, strlen($group) + 1);

                if (Arr::has($groups[$group], $item)) {
                    Arr::forget($groups[$group], $item);
                    $removed++;
                }
            }
        }

        if ($removed > 0) {
            $this->writeJson($locale, $json, keepEmpty: false);

            foreach ($groups as $group => $data) {
                $this->writeGroup($locale, $group, $data);
            }

            unset($this->cache[$locale]);
        }

        return $removed;
    }

    /**
     * What the front end needs: JSON messages plus the given groups as dot keys.
     *
     * @param  list<string>  $groups  ['*'] for every group
     * @return array<string, string>
     */
    public function forFrontend(string $locale, array $groups = []): array
    {
        $messages = $this->json($locale);
        $groups = in_array('*', $groups, true) ? $this->groups($locale) : $groups;

        foreach ($groups as $group) {
            foreach ($this->group($locale, $group) as $key => $value) {
                $messages["{$group}.{$key}"] = $value;
            }
        }

        ksort($messages);

        return $messages;
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    private function groupExistsAnywhere(string $group): bool
    {
        foreach ($this->locales() as $locale) {
            if ($this->files->exists($this->groupPath($locale, $group))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function groupArray(string $locale, string $group): array
    {
        $file = $this->groupPath($locale, $group);
        $data = $this->files->exists($file) ? $this->files->getRequire($file) : [];

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, string>  $messages
     */
    private function writeJson(string $locale, array $messages, bool $keepEmpty = true): void
    {
        if ($messages === [] && ! $keepEmpty && ! $this->files->exists($this->jsonPath($locale))) {
            return;
        }

        ksort($messages, SORT_NATURAL | SORT_FLAG_CASE);
        $this->files->ensureDirectoryExists($this->path);
        $this->files->put(
            $this->jsonPath($locale),
            json_encode($messages === [] ? new \stdClass : $messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeGroup(string $locale, string $group, array $data): void
    {
        $this->files->ensureDirectoryExists($this->path.DIRECTORY_SEPARATOR.$locale);
        $this->files->put($this->groupPath($locale, $group), "<?php\n\nreturn ".self::export($data).";\n");
    }

    /** var_export with short arrays and four-space indentation. */
    public static function export(array $data, int $depth = 1): string
    {
        if ($data === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth);
        $lines = [];
        $list = array_is_list($data);

        foreach ($data as $key => $value) {
            $rendered = is_array($value) ? self::export($value, $depth + 1) : var_export($value, true);
            $lines[] = $indent.($list ? '' : var_export($key, true).' => ').$rendered.',';
        }

        return "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth - 1).']';
    }

    private function jsonPath(string $locale): string
    {
        return $this->path.DIRECTORY_SEPARATOR.$locale.'.json';
    }

    private function groupPath(string $locale, string $group): string
    {
        return $this->path.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$group.'.php';
    }
}
