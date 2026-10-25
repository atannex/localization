<?php

declare(strict_types=1);

namespace Atannex\Localization;

use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\Detectors\CookieDetector;
use Atannex\Localization\Detectors\DomainDetector;
use Atannex\Localization\Detectors\GeoDetector;
use Atannex\Localization\Detectors\HeaderDetector;
use Atannex\Localization\Detectors\QueryDetector;
use Atannex\Localization\Detectors\RouteDetector;
use Atannex\Localization\Detectors\SessionDetector;
use Atannex\Localization\Detectors\UserDetector;
use Atannex\Localization\Exceptions\InvalidConfiguration;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Runs the configured detectors in order; the first answer that matches a
 * supported locale wins, the default locale otherwise.
 */
final class Negotiator
{
    /** @var array<string, Detector|Closure(Request, LocaleRegistry): ?string> */
    private array $extensions = [];

    /** @var array<string, Detector>|null */
    private ?array $resolved = null;

    /**
     * @param  array<string, mixed>  $config  the localization config
     */
    public function __construct(
        private readonly Container $container,
        private readonly LocaleRegistry $locales,
        private readonly array $config,
    ) {}

    /**
     * A named detector usable in localization.detectors.
     *
     * @param  Detector|Closure(Request, LocaleRegistry): ?string  $detector
     */
    public function extend(string $name, Detector|Closure $detector): void
    {
        $this->extensions[$name] = $detector;
        $this->resolved = null;
    }

    /**
     * @return array{locale: string, detector: ?string}
     */
    public function negotiate(Request $request, ?array $only = null): array
    {
        foreach ($this->detectors() as $name => $detector) {
            if ($only !== null && ! in_array($name, $only, true)) {
                continue;
            }

            $answer = $detector instanceof Closure ? $detector($request, $this->locales) : $detector->detect($request, $this->locales);
            $code = is_string($answer) ? $this->locales->match($answer) : null;

            if ($code !== null) {
                return ['locale' => $code, 'detector' => $name];
            }
        }

        return ['locale' => $this->locales->default()->code, 'detector' => null];
    }

    /**
     * @return array<string, Detector|Closure>
     */
    public function detectors(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $detectors = [];

        foreach ((array) ($this->config['detectors'] ?? []) as $name) {
            $detectors[(string) $name] = $this->make((string) $name);
        }

        return $this->resolved = $detectors;
    }

    private function make(string $name): Detector|Closure
    {
        if (isset($this->extensions[$name])) {
            return $this->extensions[$name];
        }

        $config = $this->config;

        return match ($name) {
            'route' => new RouteDetector((string) ($config['route_parameter'] ?? 'locale'), ($config['strategy'] ?? 'none') === 'prefix'),
            'domain' => new DomainDetector((array) ($config['domains'] ?? [])),
            'query' => new QueryDetector((string) ($config['query_parameter'] ?? 'lang')),
            'user' => new UserDetector,
            'session' => new SessionDetector((string) ($config['session_key'] ?? 'locale')),
            'cookie' => new CookieDetector((string) ($config['cookie']['name'] ?? 'locale')),
            'header', 'browser' => new HeaderDetector,
            'geo' => new GeoDetector((array) ($config['geo']['headers'] ?? []), (array) ($config['geo']['countries'] ?? [])),
            default => class_exists($name) && is_subclass_of($name, Detector::class)
                ? $this->container->make($name)
                : throw new InvalidConfiguration("Unknown locale detector [{$name}]."),
        };
    }
}
