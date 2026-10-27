# atannex/localization

[![Tests](https://github.com/atannex/localization/actions/workflows/tests.yml/badge.svg)](https://github.com/atannex/localization/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](composer.json)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-FF2D20?logo=laravel&logoColor=white)](composer.json)
[![License: MIT](https://img.shields.io/badge/License-MIT-2ea44f.svg)](LICENSE)

Localization for Laravel in one package: which languages you support, how a request picks one, how URLs carry it, how the choice is remembered, how content and numbers are translated, and how you know what is still untranslated.

```php
// config/localization.php
'locales' => ['en', 'fr', 'ar'],          // names, scripts, direction, currency: from the built-in catalog

Localization::current();                    // Locale { code: "fr", native: "Français", dir: "ltr", … }
Localization::using('ar', fn () => $mail->render());
Localization::url('fr');                    // this page in French
Localization::format()->currency(1234.5);   // "1 234,50 €"

$post->title;                               // current language, falling back along the chain
Post::whereTranslationLike('title', 'élection')->orderByTranslation('title')->get();
```

What you get:

- **Locale registry** with a catalog of ~110 locales (English and native names, script, direction, regional/Open Graph code, currency, flag). `['en', 'fr', 'pt_BR']` is a complete configuration; any key can be overridden and custom keys are kept.
- **Tag matching** the way browsers and people write tags: `pt-br`, `PT_BR`, `fr-CM` → `fr`, `zh-Hant-HK` → `zh_Hant`, `pt-AO` → `pt_BR`.
- **Negotiation** through an ordered chain of detectors: route prefix, domain, `?lang=`, the user's preference, session, cookie, `Accept-Language` (q-values), a CDN country header, or your own.
- **Three URL strategies**: one address for every language, `/fr/…` prefixes (`Route::localized()`, hidden default, canonical redirects), or one domain per language. `Localization::url()`, `alternates()` and `localized_route()` work the same in all three.
- **A language switcher** route (POST, no JavaScript needed, never redirects off-site, JSON-aware) and Blade components for the switcher, `hreflang` links and `og:locale` tags.
- **Persistence** of explicit choices in a cookie, the session and/or the user (`HasPreferredLocale`), which also makes Laravel's notifications and mail use it.
- **Translatable Eloquent attributes** (`HasTranslations`) without another dependency: fallback chains, legacy plain values, mutators and accessors, events, completeness reports, and query scopes compiled for MySQL, MariaDB, PostgreSQL, SQLite and SQL Server. API-compatible with spatie/laravel-translatable for the common methods.
- **Formatting** per locale: numbers, currency, percentages, compact numbers, file sizes, ordinals, spelled-out numbers, date presets, relative times, durations, month and weekday names, and lists ("A, B et C"). Uses ext-intl when loaded; falls back to built-in rules when it isn't.
- **Queues** run each job in the locale it was dispatched in.
- **Missing keys**: track untranslated `__()` keys at runtime (log, record, throw, event).
- **Tooling**: `localization:status` (coverage), `missing` (with `--fix`), `unused` (dynamic keys respected), `export` / `import` (CSV/JSON for translators) and `locales`.
- **Filament plugin**: a topbar switcher, a "you are editing the French version" banner, no fallback in forms, and translated relationship labels.
- **Validation rules**: `SupportedLocale`, `Translations`.

## Install

```bash
composer require atannex/localization
php artisan vendor:publish --tag=localization-config
```

The service provider is auto-discovered. `SetLocale` is added to the `web` middleware group (`middleware_groups`), the `locale` cookie is excluded from encryption, and the switcher route is registered at `POST /locale/{locale}`.

## Configure

```php
return [
    'locales' => [
        'en',
        'fr' => ['currency' => 'XAF'],      // override catalog values
        'bri' => ['name' => 'Bakweri'],     // codes outside the catalog need a name
    ],
    'default' => null,                       // null: the first locale
    'fallback' => null,                      // null: the default
    'fallback_chains' => ['fr_CA' => ['fr', 'en']],

    'strategy' => 'none',                    // none | prefix | domain
    'detectors' => ['route', 'domain', 'query', 'user', 'session', 'cookie', 'header'],
    'persist' => ['cookie', 'user'],
];
```

Every option is documented in [config/localization.php](config/localization.php).

## Locales

```php
use Atannex\Localization\Facades\Localization;

Localization::codes();                // ['en', 'fr', 'ar']
Localization::default();              // Locale
Localization::current();              // the active Locale
Localization::others();               // every locale but the current one
Localization::match('fr-CM');         // 'fr'
Localization::fallbacks('fr_CA');     // ['fr', 'en']

$locale = Localization::get('ar');
$locale->native;                      // "العربية"
$locale->tag();                       // "ar"   (BCP 47, for lang="" and hreflang)
$locale->regional();                  // "ar_SA" (Open Graph)
$locale->isRtl();                     // true
$locale->flag();                      // "🇸🇦"
$locale->currency;                    // "SAR"
```

Switching:

```php
Localization::set('fr');                          // translator, its fallback, Carbon, Number
Localization::using('ar', fn () => view('mail')->render());   // restored afterwards, even on exceptions
Localization::each(fn ($locale) => route('feed'));            // ['en' => …, 'fr' => …]
```

`set()` also points the translator's fallback at the locale's chain, so a missing `fr_CA` string comes from `fr` before `en`.

## How a request picks a locale

The detectors in `detectors` run in order; the first one that names a supported locale wins, otherwise the default applies.

| Detector | Reads |
|---|---|
| `route` | the `{locale}` parameter, or a supported first path segment under the prefix strategy |
| `domain` | the host, through `domains` |
| `query` | `?lang=fr` (`query_parameter`) |
| `user` | `$request->user()->preferredLocale()` (Laravel's `HasLocalePreference`) |
| `session` | the session value (`session_key`) |
| `cookie` | the switcher's cookie |
| `header` | `Accept-Language`, honouring q-values, with region and script matching |
| `geo` | a country header from your CDN (`CF-IPCountry`…), through `geo.countries` |

Add your own, then list its name in `detectors` where it should run:

```php
Localization::extendDetector('subdomain', fn (Request $request) => explode('.', $request->getHost())[0]);
```

Or a class, listed by class name and resolved from the container:

```php
use Atannex\Localization\Contracts\Detector;
use Atannex\Localization\LocaleRegistry;
use Illuminate\Http\Request;

final class AppHeaderDetector implements Detector
{
    public function detect(Request $request, LocaleRegistry $locales): ?string
    {
        return $request->header('X-App-Locale');   // any tag; null lets the next detector try
    }
}

// config/localization.php
'detectors' => ['route', 'query', 'user', AppHeaderDetector::class, 'cookie', 'header'],
```

A detector returns a tag, not a supported code: `fr-CM` or `PT_BR` is matched like any other. Device or client details (a mobile app's header, a User-Agent library's verdict) come in this way; the package does not parse User-Agents itself.

`SetLocale` (alias `localize`) applies the result and sets `Content-Language`. Under the `none` strategy it also adds `Vary: Accept-Language, Cookie`, because one address answers in several languages. `->middleware('localize:fr')` forces a locale.

Events: `LocaleNegotiated` (with the detector that answered), `LocaleChanged`, `LocaleSwitched`.

## URLs

### One address (`none`)

The page has the same URL in every language. If the `query` detector is enabled, `Localization::url('fr')` returns `?lang=fr` links, which gives search engines and shared links a URL per language.

### Prefixes (`prefix`)

```php
Route::middleware('web')->group(fn () => Route::localized(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/about', AboutController::class)->name('about');
}));

// Laravel 12 can chain it: Route::middleware('web')->localized(fn () => …);
```

| | `hide_default => true` (default) | `hide_default => false` |
|---|---|---|
| English | `/about` (name `about`) | `/en/about` (name `about`) |
| French | `/fr/about` (name `localized.about`) | `/fr/about` (name `about`) |

- `/en/about` redirects to `/about` with a 301 when the default is hidden.
- `/about` redirects to `/fr/about` with a 302 for visitors who prefer French (`redirect_to_preferred`), on GET and HEAD only.
- `URL::defaults()` fills `{locale}`, so `route('about')` works inside prefixed pages.
- `localized_route('about')` and `Localization::route('about', [], 'fr')` link correctly in every setup.
- An unprefixed localized URL always means the default locale. The URL is authoritative there.

### Domains (`domain`)

```php
'strategy' => 'domain',
'domains' => ['example.com' => 'en', 'example.fr' => 'fr'],
'detectors' => ['domain', 'user', 'cookie', 'header'],
```

### In any strategy

```php
Localization::url('fr');                     // the current page in French
Localization::url('fr', 'https://example.com/about?x=1#top');
Localization::alternates();                  // ['en' => …, 'fr' => …]
Localization::neutralUrl();                  // the x-default address
localized_url('ar');
```

URLs on other hosts are returned unchanged.

## Switcher and Blade

```blade
<html lang="{{ localization()->current()->tag() }}" dir="{{ locale_direction() }}">
{{-- or: <html @localeAttributes> --}}
<head>
    <x-localization::meta />          {{-- og:locale, og:locale:alternate and hreflang links --}}
    <x-localization::hreflang />      {{-- hreflang links only --}}
</head>

<x-localization::switcher />                       {{-- a button per language --}}
<x-localization::switcher variant="select" flags />
<x-localization::switcher variant="details" codes />

@locale('fr') {{ __('Welcome') }} @endlocale       {{-- renders the block in French --}}
@rtl … @endrtl
@multilingual … @endmultilingual
```

The switcher posts to `POST /locale/{locale}` (or `POST /locale` with a `locale` field). The route:

- saves the choice in the `persist` stores,
- sends the visitor back to the page they were on, at its URL in the new language, never to another site,
- returns `{"locale": "fr", "redirect": "…"}` to JSON requests.

Publish the views to restyle them (`--tag=localization-views`), or build your own with `Localization::switchUrl($code)`.

## Remembering a choice

```php
use Atannex\Localization\Concerns\HasPreferredLocale;
use Illuminate\Contracts\Translation\HasLocalePreference;

class User extends Authenticatable implements HasLocalePreference
{
    use HasPreferredLocale;

    // a column ('locale' by default) or a JSON path
    protected string $preferredLocaleAttribute = 'settings->locale';
}

$user->setPreferredLocale('pt-br');   // stored as 'pt_BR'; unsupported values are ignored
$user->preferredLocale();             // read by the user detector, notifications and mail
Localization::persist('fr');          // runs the configured stores and fires LocaleSwitched
```

The user preference follows the account to every device; the cookie stays with one browser. With the default order the account wins. To let each device keep its own choice, put `cookie` before `user` in `detectors`.

## Translatable models

```php
use Atannex\Localization\Concerns\HasTranslations;

class Post extends Model
{
    use HasTranslations;

    public array $translatable = ['title', 'body'];
}

// or: #[Translatable('title', 'body')]
```

Each column stores JSON (`{"en": "…", "fr": "…"}`). A `json` or `text` column works.

```php
$post->title;                                  // current locale, then its fallbacks
$post->title = 'Bonjour';                      // sets the current locale
$post->title = ['en' => 'Hello', 'fr' => 'Bonjour'];
$post->setTranslation('title', 'fr', 'Bonjour');
$post->getTranslation('title', 'fr', false);   // exact, no fallback ('' if missing)
$post->getTranslations('title');               // ['en' => …, 'fr' => …]
$post->in('fr')->title;                        // a copy that reads French
$post->forgetTranslation('title', 'fr');
$post->replaceTranslations('title', ['en' => 'Only English']);

$post->missingTranslations('fr');              // ['body']
$post->isFullyTranslated('fr');
$post->translationProgress();                  // ['en' => 1.0, 'fr' => 0.5]
```

- **Values as values.** A list (`[['type' => 'p', …]]`) or an array whose keys aren't locales is a value, not a set of translations. Assigning `[]` stores an empty value for the current locale.
- **Existing data.** Plain text or a JSON list written before a column became translatable reads as the default locale, so nothing goes blank between deploying and migrating.
- **Fallback.** Configured globally (`translatable.fallback`, `fallback_any`), per model (`$useFallbackLocale`), or per block: `Localization::withoutFallback(fn () => …)`. `Localization::strictWhen($condition)` empties serialisation (forms) when the condition holds; pass `reads: true` to cover attribute reads too.
- **Serialisation.** `toArray()` gives the current locale's value, or every translation with `translatable.serialize => 'all'` or `$translationsInArray`.
- **Mutators and accessors** apply to each translation. A `TranslationSet` event is dispatched when a value changes.

### Queries

```php
Post::whereTranslation('title', 'Bonjour');               // in any supported locale
Post::whereTranslation('title', 'Bonjour', 'fr');         // in one locale
Post::orWhereTranslation('title', 'Hello', 'en');
Post::whereTranslationLike(['title', 'body'], 'élect');   // case-insensitive; % and _ are literal
Post::orderByTranslation('title');                        // current locale with fallback; '' counts as missing
Post::selectTranslation('title', as: 'label');
Post::whereHasTranslation('title', 'fr');
Post::whereMissingTranslation('title', 'fr');             // the translation queue
$post->translatedColumnSql('title');                      // for raw SQL
```

The SQL follows the connection: `json_extract` on MySQL, MariaDB and SQLite, `->>` on PostgreSQL, `json_value` on SQL Server. MySQL comparisons use `utf8mb4_unicode_ci`, as a plain text column would.

## Formatting

```php
$format = Localization::format('fr');      // or format() for the current locale

$format->number(1234.5);                   // 1 234,5
$format->currency(1234.5, 'EUR');          // 1 234,50 €
$format->percent(0.256);                   // 25,6 %
$format->compact(1_234_000);               // 1,2M
$format->fileSize(1536);                   // 1,5 KB
$format->ordinal(1);                       // 1er
$format->spell(42);                        // quarante-deux (intl)
$format->date($date, 'long');              // 24 octobre 2026 (presets in formatting.dates, or any isoFormat)
$format->relative($date);                  // il y a 3 heures
$format->duration(3725);                   // 1 heure et 2 minutes
$format->list(['A', 'B', 'C']);            // A, B et C
$format->months();                         // [1 => 'janvier', …]
Localization::languageName('de', 'fr');    // Allemand
```

`Number::useLocale()` and `Carbon::setLocale()` follow the active locale, so Laravel's own helpers agree.

## Queues

With `queue => true`, each job payload carries the locale it was dispatched in, and the job runs in that locale. The previous locale is restored afterwards, including for nested sync jobs.

## Missing translations

```php
'missing' => ['enabled' => true, 'log' => true, 'record' => true, 'throw' => false],
```

Every `__()` key with no translation dispatches `MissingTranslationKey` once per request and locale. It can also be logged, recorded to a file (read with `localization:missing --recorded`) or thrown, which is useful in tests. In the default locale, text keys like `__('Sign in')` are the text itself, so only group keys such as `nav.home` count as missing there.

## Commands

```bash
php artisan localization:status                 # coverage per locale
php artisan localization:missing fr             # keys used in code or other locales but untranslated
php artisan localization:missing fr --fix --placeholder=source
php artisan localization:unused --locale=en     # keys no code uses (keys built at runtime are kept)
php artisan localization:export fr --missing --output=fr.csv
php artisan localization:import fr.csv fr       # key,source,translation or {"key": "translation"}
php artisan localization:locales [--catalog]
php artisan about                               # includes a Localization section
```

The scanner reads `__()`, `trans()`, `trans_choice()`, `@lang`, `@choice`, `Lang::get()`, `$t()` and `i18n.t()` in PHP, Blade and JS. Keys built at runtime (`__("desks.{$desk}")`, `__('menu.'.$item)`) are recorded as prefixes, so `unused` never removes them.

## Front-end translations

```php
'frontend' => ['enabled' => true, 'path' => 'localization', 'groups' => ['js']],
```

`GET /localization/fr.json` returns the JSON strings plus the listed groups as dot keys, with an ETag and `Cache-Control`.

## Filament

```php
use Atannex\Localization\Filament\LocalizationPlugin;

$panel
    ->plugin(LocalizationPlugin::make())
    ->middleware([/* … */, SubstituteBindings::class, \Atannex\Localization\Http\Middleware\SetLocale::class, DispatchServingFilamentEvent::class]);
```

The plugin adds:

- a topbar language switcher,
- a banner above create and edit pages of translatable resources saying which language version is open,
- no fallback in forms, so an untranslated field is empty and saving never copies one language into another (`->strict(reads: true)` extends this to tables),
- relationship selects and filters that show translated titles instead of raw JSON,
- case-insensitive table search.

Each part can be turned off (`->switcher(false)`, `->editingBanner(false)`…). `->setLocale()` appends the middleware for you.

## Validation

```php
use Atannex\Localization\Rules\SupportedLocale;
use Atannex\Localization\Rules\Translations;

'locale' => ['required', new SupportedLocale],                    // or new SupportedLocale(loose: true)
'title'  => ['required', Translations::make()->requireDefault()->max(255)],
'name'   => [Translations::make()->require('*')],
```

Messages are translated into English, French, Spanish, German, Portuguese (pt and pt_BR) and Arabic.

## Testing

```bash
composer test
```

The suite runs on PHP 8.2–8.4 with Laravel 11 and 12.

## License

MIT
