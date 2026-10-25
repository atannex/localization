<?php

declare(strict_types=1);

namespace Atannex\Localization\Translations;

use Symfony\Component\Finder\Finder;

/**
 * Finds the translation keys used in source files: __('…'), trans(),
 * trans_choice(), @lang(), @choice(), Lang::get(), $t() and whatever else
 * localization.scanner.functions lists.
 *
 * Keys built at runtime (__("desks.{$name}"), __('desks.'.$name)) are
 * reported as dynamic prefixes ("desks.") so tools can treat every key
 * under them as used.
 */
final class Scanner
{
    /**
     * @param  list<string>  $paths  absolute directories or files
     * @param  list<string>  $extensions
     * @param  list<string>  $functions
     * @param  list<string>  $exclude  directory names to skip
     */
    public function __construct(
        private readonly array $paths,
        private readonly array $extensions = ['php'],
        private readonly array $functions = ['__', 'trans', 'trans_choice', '@lang'],
        private readonly array $exclude = ['vendor', 'node_modules'],
    ) {}

    /**
     * keys: key => ["file:line", …]; dynamic: prefixes of keys built at runtime.
     *
     * @return array{keys: array<string, list<string>>, dynamic: list<string>}
     */
    public function scan(): array
    {
        $keys = [];
        $dynamic = [];

        foreach ($this->files() as $path => $contents) {
            foreach ($this->scanString($contents) as [$key, $offset, $isDynamic]) {
                if ($isDynamic) {
                    $dynamic[] = $key;

                    continue;
                }

                $keys[$key][] = $path.':'.(substr_count($contents, "\n", 0, $offset) + 1);
            }
        }

        ksort($keys);
        $dynamic = array_values(array_unique(array_filter($dynamic, fn (string $prefix) => $prefix !== '')));
        sort($dynamic);

        return ['keys' => $keys, 'dynamic' => $dynamic];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->scan()['keys']);
    }

    /**
     * Matches in one source text: [key, byte offset, is a dynamic prefix].
     *
     * @return list<array{0: string, 1: int, 2: bool}>
     */
    public function scanString(string $contents): array
    {
        $names = implode('|', array_map(fn (string $name) => preg_quote($name, '/'), $this->functions));
        // name( 'literal' | "literal" — then what follows tells literal from concatenation.
        $pattern = '/(?<![\w$>:.])(?:'.$names.')\s*\(\s*(?:(\')((?:\\\\.|[^\'\\\\])*)\'|(")((?:\\\\.|[^"\\\\])*)"|(`)((?:\\\\.|[^`\\\\])*)`)(\s*\.)?/s';

        if (! preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $found = [];

        foreach ($matches as $match) {
            $quote = $match[1][0] !== '' ? "'" : ($match[3][0] !== '' ? '"' : '`');
            $raw = $quote === "'" ? $match[2][0] : ($quote === '"' ? $match[4][0] : $match[6][0]);
            $concatenated = isset($match[7]) && $match[7][0] !== '' && $match[7][1] !== -1;
            $key = $quote === "'" ? str_replace(['\\\'', '\\\\'], ["'", '\\'], $raw) : stripcslashes($raw);

            // Interpolation ("desks.{$x}", `desks.${x}`) or concatenation ('desks.'.$x): a prefix.
            if ($quote !== "'" && preg_match('/\{?\$\{?/', $raw, $hit, PREG_OFFSET_CAPTURE)) {
                $found[] = [substr($raw, 0, $hit[0][1]), $match[0][1], true];

                continue;
            }

            if ($concatenated) {
                $found[] = [$key, $match[0][1], true];

                continue;
            }

            if ($key !== '') {
                $found[] = [$key, $match[0][1], false];
            }
        }

        return $found;
    }

    /**
     * @return iterable<string, string> relative-ish path => contents
     */
    private function files(): iterable
    {
        $directories = array_values(array_filter($this->paths, 'is_dir'));

        foreach (array_filter($this->paths, 'is_file') as $file) {
            yield $file => (string) file_get_contents($file);
        }

        if ($directories === []) {
            return;
        }

        $finder = Finder::create()
            ->files()
            ->in($directories)
            ->exclude($this->exclude)
            ->name(array_map(fn (string $extension) => '*.'.ltrim($extension, '.'), $this->extensions))
            ->ignoreDotFiles(true)
            ->ignoreVCS(true);

        foreach ($finder as $file) {
            yield $file->getPathname() => $file->getContents();
        }
    }
}
