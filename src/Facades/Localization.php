<?php

declare(strict_types=1);

namespace Atannex\Localization\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Atannex\Localization\LocaleRegistry registry()
 * @method static array<string, \Atannex\Localization\Locale> locales()
 * @method static list<string> codes()
 * @method static bool supports(\Atannex\Localization\Locale|string|null $code)
 * @method static \Atannex\Localization\Locale|null find(\Atannex\Localization\Locale|string|null $code)
 * @method static \Atannex\Localization\Locale get(\Atannex\Localization\Locale|string $code)
 * @method static string|null match(?string $tag)
 * @method static \Atannex\Localization\Locale default()
 * @method static bool isDefault(\Atannex\Localization\Locale|string|null $code = null)
 * @method static \Atannex\Localization\Locale fallback(\Atannex\Localization\Locale|string|null $code = null)
 * @method static list<string> fallbacks(\Atannex\Localization\Locale|string|null $code = null)
 * @method static bool isMultilingual()
 * @method static array<string, \Atannex\Localization\Locale> others()
 * @method static \Atannex\Localization\Locale current()
 * @method static string code()
 * @method static \Atannex\Localization\Locale set(\Atannex\Localization\Locale|string $locale)
 * @method static mixed using(\Atannex\Localization\Locale|string|null $locale, callable $callback)
 * @method static \Atannex\Localization\Locale push(\Atannex\Localization\Locale|string $locale)
 * @method static \Atannex\Localization\Locale pop()
 * @method static array<string, mixed> each(callable $callback)
 * @method static string direction(\Atannex\Localization\Locale|string|null $code = null)
 * @method static bool isRtl(\Atannex\Localization\Locale|string|null $code = null)
 * @method static array{locale: string, detector: ?string} detect(\Illuminate\Http\Request $request, ?array $only = null)
 * @method static string negotiate(\Illuminate\Http\Request $request)
 * @method static void extendDetector(string $name, \Atannex\Localization\Contracts\Detector|\Closure $detector)
 * @method static \Atannex\Localization\Locale persist(\Atannex\Localization\Locale|string $locale, ?\Illuminate\Http\Request $request = null)
 * @method static void extendStore(string $name, \Atannex\Localization\Contracts\Store|\Closure $store)
 * @method static string strategy()
 * @method static \Atannex\Localization\Routing\UrlLocalizer urls()
 * @method static string url(\Atannex\Localization\Locale|string $locale, ?string $url = null)
 * @method static string neutralUrl(?string $url = null)
 * @method static array<string, string> alternates(?string $url = null)
 * @method static string route(string $name, array $parameters = [], \Atannex\Localization\Locale|string|null $locale = null, bool $absolute = true)
 * @method static string switchUrl(\Atannex\Localization\Locale|string|null $locale = null)
 * @method static mixed withoutFallback(?callable $callback = null)
 * @method static void strictWhen(\Closure $condition, bool $reads = false)
 * @method static bool usesFallback(string $context = 'read')
 * @method static mixed config(?string $key = null, mixed $default = null)
 * @method static \Atannex\Localization\Formatting\Formatter format(\Atannex\Localization\Locale|string|null $locale = null)
 * @method static string languageName(string $code, \Atannex\Localization\Locale|string|null $in = null)
 *
 * @see \Atannex\Localization\Localization
 */
final class Localization extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Atannex\Localization\Localization::class;
    }
}
