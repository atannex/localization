<?php

declare(strict_types=1);

namespace Atannex\Localization\Routing;

use Atannex\Localization\LocaleRegistry;

/**
 * Rewrites a URL into another locale's version, following the strategy:
 *
 *   prefix  /fr/about ⇄ /about (default hidden) ⇄ /de/about
 *   domain  https://example.fr/about ⇄ https://example.com/about
 *   none    the same address; with the query detector on, ?lang=fr
 *
 * URLs on other hosts are returned untouched.
 */
final class UrlLocalizer
{
    /**
     * @param  array<string, mixed>  $config  the localization config
     */
    public function __construct(
        private readonly LocaleRegistry $locales,
        private readonly array $config,
        private readonly string $root,
    ) {}

    public function strategy(): string
    {
        return (string) ($this->config['strategy'] ?? 'none');
    }

    /**
     * @param  bool  $addQuery  none strategy: add ?lang= even when the URL has none
     *                          (false keeps clean URLs clean, e.g. after switching)
     */
    public function localize(string $url, string $locale, bool $addQuery = true): string
    {
        $locale = $this->locales->get($locale)->code;
        $parts = parse_url($url);

        if ($parts === false || ! $this->isInternal($parts)) {
            return $url;
        }

        $path = '/'.ltrim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);

        switch ($this->strategy()) {
            case 'prefix':
                [, $path] = $this->split($path);
                $path = $this->prefixed($path, $locale);
                break;

            case 'domain':
                $host = $this->hostFor($locale);
                if ($host !== null) {
                    $parts['host'] = $host;
                    unset($parts['port']);
                }
                break;

            default:
                if (in_array('query', (array) ($this->config['detectors'] ?? []), true)
                    && ($addQuery || array_key_exists($this->queryParameter(), $query))) {
                    $query[$this->queryParameter()] = $locale;
                }
        }

        // Keep "https://example.com" as written rather than adding a slash.
        $parts['path'] = $path === '/' && ($parts['path'] ?? '') === '' && $query === [] ? '' : $path;
        $parts['query'] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $this->build($parts);
    }

    /**
     * The address that lets the visitor's preferences decide (hreflang
     * x-default): without ?lang= under the none strategy, the default
     * locale's URL otherwise.
     */
    public function neutral(string $url): string
    {
        if (! in_array($this->strategy(), ['prefix', 'domain'], true)) {
            $parts = parse_url($url);

            if ($parts === false || ! $this->isInternal($parts)) {
                return $url;
            }

            parse_str($parts['query'] ?? '', $query);
            unset($query[$this->queryParameter()]);
            $parts['query'] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

            return $this->build($parts);
        }

        return $this->localize($url, $this->locales->default()->code);
    }

    /**
     * The path without its locale prefix, and that locale (prefix strategy).
     *
     * @return array{0: ?string, 1: string}
     */
    public function split(string $path): array
    {
        $path = '/'.ltrim($path, '/');

        if (preg_match('#^/([^/]+)(/.*)?$#', $path, $match) && $this->locales->supports($match[1])) {
            return [$match[1], $match[2] ?? '/'];
        }

        // Prefixes are case-insensitive on the way in (/FR/about, /pt-br/).
        if (isset($match[1]) && ($code = $this->exact($match[1])) !== null) {
            return [$code, $match[2] ?? '/'];
        }

        return [null, $path];
    }

    public function prefixed(string $path, string $locale): string
    {
        $path = '/'.ltrim($path, '/');

        if ($this->hidesPrefix($locale)) {
            return $path;
        }

        return '/'.$locale.($path === '/' ? '' : $path);
    }

    public function hidesPrefix(string $locale): bool
    {
        return ($this->config['hide_default'] ?? true) && $locale === $this->locales->default()->code;
    }

    public function hostFor(string $locale): ?string
    {
        foreach ((array) ($this->config['domains'] ?? []) as $host => $code) {
            if ($code === $locale) {
                return strtolower((string) $host);
            }
        }

        return null;
    }

    private function queryParameter(): string
    {
        return (string) ($this->config['query_parameter'] ?? 'lang');
    }

    private function exact(string $segment): ?string
    {
        foreach ($this->locales->codes() as $code) {
            if (strcasecmp(str_replace('-', '_', $segment), $code) === 0) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function isInternal(array $parts): bool
    {
        if (! isset($parts['host'])) {
            return true; // relative URL
        }

        $host = strtolower((string) $parts['host']);
        $known = array_map('strtolower', array_keys((array) ($this->config['domains'] ?? [])));
        $root = strtolower((string) parse_url($this->root, PHP_URL_HOST));

        return $host === $root || in_array($host, $known, true);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function build(array $parts): string
    {
        $url = '';

        if (isset($parts['host'])) {
            $url .= (isset($parts['scheme']) ? $parts['scheme'].':' : '').'//';
            $url .= isset($parts['user']) ? $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@' : '';
            $url .= $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        }

        $url .= $parts['path'] ?? '/';
        $url .= ($parts['query'] ?? '') !== '' ? '?'.$parts['query'] : '';
        $url .= isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $url;
    }
}
