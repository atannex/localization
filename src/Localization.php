<?php

declare(strict_types=1);

namespace Atannex\Localization;

use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\Contracts\Store;
use Atannex\Localization\Events\LocaleChanged;
use Atannex\Localization\Events\LocaleNegotiated;
use Atannex\Localization\Events\LocaleSwitched;
use Atannex\Localization\Exceptions\InvalidConfiguration;
use Atannex\Localization\Formatting\Formatter;
use Atannex\Localization\Routing\UrlLocalizer;
use Atannex\Localization\Stores\CookieStore;
use Atannex\Localization\Stores\SessionStore;
use Atannex\Localization\Stores\UserStore;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Number;

/**
 * The entry point (facade: Localization). Which locales exist, which one is
 * active, how a request picks one, how a choice is remembered, what a URL
 * is in another locale, and locale-aware formatting.
 */
final class Localization
{
    private ?UrlLocalizer $urls = null;

    /** @var array<string, Formatter> */
    private array $formatters = [];

    /** @var array<string, Store|Closure(Request, string): void> */
    private array $stores = [];

    /** @var list<array{0: Closure(): bool, 1: bool}> condition, applies to reads too */
    private array $strictWhen = [];

    private int $strictDepth = 0;

    /** @var list<string> locales saved by push() */
    private array $stack = [];

    /**
     * @param  array<string, mixed>  $config  the localization config
     */
    public function __construct(
        private readonly Container $app,
        private readonly LocaleRegistry $registry,
        private readonly Negotiator $negotiator,
        private readonly array $config,
    ) {}

    // ---------------------------------------------------------------- locales

    public function registry(): LocaleRegistry
    {
        return $this->registry;
    }

    /**
     * @return array<string, Locale>
     */
    public function locales(): array
    {
        return $this->registry->all();
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return $this->registry->codes();
    }

    public function supports(Locale|string|null $code): bool
    {
        return $this->registry->supports($code);
    }

    public function find(Locale|string|null $code): ?Locale
    {
        return $this->registry->find($code);
    }

    public function get(Locale|string $code): Locale
    {
        return $this->registry->get($code);
    }

    /** The supported code for any tag ('fr-CM' → 'fr'), or null. */
    public function match(?string $tag): ?string
    {
        return $this->registry->match($tag);
    }

    public function default(): Locale
    {
        return $this->registry->default();
    }

    public function isDefault(Locale|string|null $code = null): bool
    {
        return ($code === null ? $this->current()->code : (string) $code) === $this->default()->code;
    }

    public function fallback(Locale|string|null $code = null): Locale
    {
        return $this->registry->fallback($code);
    }

    /**
     * @return list<string>
     */
    public function fallbacks(Locale|string|null $code = null): array
    {
        return $this->registry->fallbacks($code ?? $this->current());
    }

    /** True when more than one locale is supported (show a switcher). */
    public function isMultilingual(): bool
    {
        return $this->registry->count() > 1;
    }

    /**
     * Every supported locale except the current one.
     *
     * @return array<string, Locale>
     */
    public function others(): array
    {
        return array_diff_key($this->locales(), [$this->current()->code => true]);
    }

    // ---------------------------------------------------------------- active locale

    /** The active locale (the application locale, or the default if that is unsupported). */
    public function current(): Locale
    {
        return $this->registry->find($this->app->getLocale()) ?? $this->registry->default();
    }

    public function code(): string
    {
        return $this->current()->code;
    }

    /**
     * Makes $locale (any matching tag) the application's locale: translator,
     * translator fallback, Carbon, Number and optionally setlocale().
     */
    public function set(Locale|string $locale): Locale
    {
        $target = $this->registry->find($locale)
            ?? $this->registry->find($this->registry->match((string) $locale))
            ?? $this->registry->get($locale); // throws UnsupportedLocale

        $previous = $this->app->getLocale();

        $this->app->setLocale($target->code);
        $this->app['translator']->setFallback($this->registry->fallbacks($target)[0] ?? $target->code);

        $apply = (array) ($this->config['apply'] ?? []);

        if ($apply['carbon'] ?? true) {
            Carbon::setLocale($target->code);
        }

        if (($apply['number'] ?? true) && method_exists(Number::class, 'useLocale')) {
            Number::useLocale($target->code);
        }

        if ($apply['php_setlocale'] ?? false) {
            $regional = $target->regional();
            setlocale(LC_ALL, "{$regional}.UTF-8", "{$regional}.utf8", $regional, $target->code);
        }

        if ($previous !== $target->code) {
            event(new LocaleChanged($target->code, $previous));
        }

        return $target;
    }

    /**
     * Runs $callback in $locale and restores the previous locale afterwards,
     * even when it throws.
     *
     * @template TReturn
     *
     * @param  callable(Locale): TReturn  $callback
     * @return TReturn
     */
    public function using(Locale|string|null $locale, callable $callback): mixed
    {
        if ($locale === null) {
            return $callback($this->current());
        }

        $previous = $this->app->getLocale();
        $target = $this->set($locale);

        try {
            return $callback($target);
        } finally {
            $this->registry->supports($previous) ? $this->set($previous) : $this->app->setLocale($previous);
        }
    }

    /** Switches to $locale until the matching pop() (Blade's @locale … @endlocale). */
    public function push(Locale|string $locale): Locale
    {
        $this->stack[] = $this->app->getLocale();

        return $this->set($locale);
    }

    public function pop(): Locale
    {
        $previous = array_pop($this->stack);

        return $previous !== null && $this->registry->supports($previous) ? $this->set($previous) : $this->current();
    }

    /**
     * Runs $callback once per supported locale.
     *
     * @template TReturn
     *
     * @param  callable(Locale): TReturn  $callback
     * @return array<string, TReturn>
     */
    public function each(callable $callback): array
    {
        $results = [];

        foreach ($this->codes() as $code) {
            $results[$code] = $this->using($code, $callback);
        }

        return $results;
    }

    public function direction(Locale|string|null $code = null): string
    {
        return ($code === null ? $this->current() : $this->get($code))->direction;
    }

    public function isRtl(Locale|string|null $code = null): bool
    {
        return $this->direction($code) === 'rtl';
    }

    // ---------------------------------------------------------------- negotiation

    /**
     * The locale a request asks for (see localization.detectors).
     *
     * @param  list<string>|null  $only  limit to these detectors
     * @return array{locale: string, detector: ?string}
     */
    public function detect(Request $request, ?array $only = null): array
    {
        return $this->negotiator->negotiate($request, $only);
    }

    /** Negotiates and announces a request's locale (does not apply it). */
    public function negotiate(Request $request): string
    {
        ['locale' => $locale, 'detector' => $detector] = $this->detect($request);

        event(new LocaleNegotiated($locale, $detector, $request));

        return $locale;
    }

    /**
     * Adds a named detector for localization.detectors.
     *
     * @param  Detector|Closure(Request, LocaleRegistry): ?string  $detector
     */
    public function extendDetector(string $name, Detector|Closure $detector): void
    {
        $this->negotiator->extend($name, $detector);
    }

    // ---------------------------------------------------------------- persistence

    /**
     * Remembers an explicit choice in the configured stores and announces it.
     */
    public function persist(Locale|string $locale, ?Request $request = null): Locale
    {
        $target = $this->get($locale);
        $request ??= $this->app->make('request');
        $previous = $this->current()->code;

        foreach ((array) ($this->config['persist'] ?? []) as $name) {
            $store = $this->store((string) $name);
            $store instanceof Closure ? $store($request, $target->code) : $store->remember($request, $target->code);
        }

        event(new LocaleSwitched($target->code, $previous, $request));

        return $target;
    }

    /**
     * Adds a named store for localization.persist.
     *
     * @param  Store|Closure(Request, string): void  $store
     */
    public function extendStore(string $name, Store|Closure $store): void
    {
        $this->stores[$name] = $store;
    }

    private function store(string $name): Store|Closure
    {
        return $this->stores[$name] ??= match ($name) {
            'cookie' => new CookieStore($this->app->make('cookie'), (array) ($this->config['cookie'] ?? [])),
            'session' => new SessionStore((string) ($this->config['session_key'] ?? 'locale')),
            'user' => new UserStore,
            default => class_exists($name) && is_subclass_of($name, Store::class)
                ? $this->app->make($name)
                : throw new InvalidConfiguration("Unknown locale store [{$name}]."),
        };
    }

    // ---------------------------------------------------------------- URLs

    public function strategy(): string
    {
        return $this->urls()->strategy();
    }

    public function urls(): UrlLocalizer
    {
        return $this->urls ??= new UrlLocalizer($this->registry, $this->config, (string) $this->app['url']->to('/'));
    }

    /** $url (default: the current one) in another locale. */
    public function url(Locale|string $locale, ?string $url = null): string
    {
        return $this->urls()->localize($url ?? $this->app['url']->full(), (string) $locale);
    }

    /** The current page (or $url) at the address that lets the visitor's preferences decide. */
    public function neutralUrl(?string $url = null): string
    {
        return $this->urls()->neutral($url ?? $this->app['url']->full());
    }

    /**
     * The current page (or $url) in every locale: code => URL.
     *
     * @return array<string, string>
     */
    public function alternates(?string $url = null): array
    {
        $alternates = [];

        foreach ($this->codes() as $code) {
            $alternates[$code] = $this->url($code, $url);
        }

        return $alternates;
    }

    /**
     * A named route in a locale. Under the prefix strategy routes registered
     * with Route::localized() exist in a hidden-default copy (the plain name)
     * and a prefixed copy ('localized.' + name).
     *
     * @param  array<string, mixed>  $parameters
     */
    public function route(string $name, array $parameters = [], Locale|string|null $locale = null, bool $absolute = true): string
    {
        $locale = (string) ($locale ?? $this->current());
        $router = $this->app['router'];
        $url = $this->app['url'];

        if ($this->strategy() !== 'prefix') {
            return $this->url($locale, $url->route($name, $parameters, $absolute));
        }

        $parameter = (string) ($this->config['route_parameter'] ?? 'locale');
        $prefixed = Routing\LocalizedRoutes::NAME_PREFIX.$name;

        if (! $this->urls()->hidesPrefix($locale) && $router->has($prefixed)) {
            return $url->route($prefixed, [$parameter => $locale] + $parameters, $absolute);
        }

        $route = $router->getRoutes()->getByName($name);

        if ($route !== null && in_array($parameter, $route->parameterNames(), true)) {
            return $url->route($name, [$parameter => $locale] + $parameters, $absolute);
        }

        return $url->route($name, $parameters, $absolute);
    }

    /** Where the switcher posts to change to $locale (without one: posts a 'locale' field). */
    public function switchUrl(Locale|string|null $locale = null): string
    {
        $name = (string) ($this->config['switcher']['name'] ?? 'locale.switch');

        return $this->app['url']->route($name, $locale === null ? [] : ['locale' => (string) $locale]);
    }

    // ---------------------------------------------------------------- translatable models

    /**
     * Translatable attributes return only the exact locale (no fallback)
     * while $callback runs.
     *
     * @template TReturn
     *
     * @param  (callable(): TReturn)|null  $callback
     * @return TReturn|null
     */
    public function withoutFallback(?callable $callback = null): mixed
    {
        $this->strictDepth++;

        try {
            return $callback ? $callback() : null;
        } finally {
            $this->strictDepth--;
        }
    }

    /**
     * No fallback whenever $condition holds, e.g. inside an admin panel so
     * editors see untranslated fields as empty. By default this applies to
     * serialisation (toArray(), which fills forms) only, so titles in tables
     * and breadcrumbs still fall back; pass $reads to cover attribute reads.
     *
     * @param  Closure(): bool  $condition
     */
    public function strictWhen(Closure $condition, bool $reads = false): void
    {
        $this->strictWhen[] = [$condition, $reads];
    }

    /**
     * Whether translatable attributes fall back right now.
     *
     * @param  'read'|'serialize'  $context
     */
    public function usesFallback(string $context = 'read'): bool
    {
        if ($this->strictDepth > 0 || ! ($this->config['translatable']['fallback'] ?? true)) {
            return false;
        }

        foreach ($this->strictWhen as [$condition, $reads]) {
            if (($reads || $context === 'serialize') && $condition()) {
                return false;
            }
        }

        return true;
    }

    /** The localization config, or one key of it (dot notation). */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->config : data_get($this->config, $key, $default);
    }

    // ---------------------------------------------------------------- formatting

    public function format(Locale|string|null $locale = null): Formatter
    {
        $locale = $locale === null ? $this->current() : $this->get($locale);

        return $this->formatters[$locale->code] ??= new Formatter($locale, (array) ($this->config['formatting'] ?? []));
    }

    /** A language's name in $in (default: in itself), via intl when available. */
    public function languageName(string $code, Locale|string|null $in = null): string
    {
        $locale = $this->registry->find($code) ?? LocaleRegistry::describe($code, ['name' => $code]);
        $in = $in === null ? $locale->code : (string) $in;

        if ($in === $locale->code) {
            return $locale->native;
        }

        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayName(str_replace('_', '-', $locale->code), str_replace('_', '-', $in));

            if (is_string($name) && $name !== '' && $name !== $locale->code) {
                return mb_convert_case(mb_substr($name, 0, 1), MB_CASE_TITLE).mb_substr($name, 1);
            }
        }

        return $locale->name;
    }
}
