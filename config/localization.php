<?php

/*
|--------------------------------------------------------------------------
| atannex/localization
|--------------------------------------------------------------------------
|
| Everything about the languages an application speaks: which ones, how a
| request picks one, how URLs carry it, how it is remembered, how content
| and numbers are translated and formatted, and how translation coverage
| is tracked. Every option has a working default.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | A list of codes ('en', 'fr', 'pt_BR'…) or code => overrides. Anything left
    | out (English name, native name, script, direction, regional/Open Graph
    | code, flag, currency) comes from the built-in catalog of ~110 locales,
    | so ['en', 'fr', 'ar'] is a complete configuration. Unknown codes are
    | allowed when you give at least a 'name'.
    |
    | Codes are used exactly as written: in URLs, cookies, JSON columns and
    | lang/ directories. 'pt_BR' and 'pt-BR' are both accepted on input.
    |
    */

    'locales' => [
        'en',
    ],

    // The default locale (null: the first supported one). It is also the
    // fallback for anything not translated, unless 'fallback' says otherwise.
    'default' => null,

    // Where missing translations fall back to: a code, or null for the default.
    // A per-locale chain can be given too: ['fr_CA' => ['fr', 'en']].
    'fallback' => null,

    'fallback_chains' => [],

    /*
    |--------------------------------------------------------------------------
    | URL strategy
    |--------------------------------------------------------------------------
    |
    | none    one address answers in every language (the locale comes from
    |         the detectors below). Simplest; keep Vary headers on.
    | prefix  /fr/about. Register routes with Route::localized(fn () => …).
    | domain  example.fr / fr.example.com, mapped in 'domains'.
    |
    */

    'strategy' => 'none',

    // prefix strategy: serve the default locale without a prefix (/about, /fr/about).
    'hide_default' => true,

    // prefix strategy: redirect unprefixed GET requests in localized route
    // groups to the visitor's negotiated locale (/about → /fr/about).
    'redirect_to_preferred' => true,

    // domain strategy: host => locale code.
    'domains' => [
        // 'example.fr' => 'fr',
    ],

    /*
    |--------------------------------------------------------------------------
    | Detection
    |--------------------------------------------------------------------------
    |
    | The detectors run in this order and the first supported answer wins;
    | when none answers, the default locale is used. Add your own by class
    | name (implementing Atannex\Localization\Contracts\Detector).
    |
    |   route    {locale} route parameter / URL prefix (prefix strategy)
    |   domain   host map (domain strategy)
    |   query    ?lang=fr
    |   user     the signed-in user's preferredLocale() (HasLocalePreference)
    |   session  the session value
    |   cookie   the switcher's cookie
    |   header   Accept-Language, with q-values and region matching (fr-CM → fr)
    |   geo      a country header set by your CDN (CF-IPCountry…), via 'countries'
    |
    */

    'detectors' => ['route', 'domain', 'query', 'user', 'session', 'cookie', 'header'],

    'query_parameter' => 'lang',

    'route_parameter' => 'locale',

    'session_key' => 'locale',

    'cookie' => [
        'name' => 'locale',
        'minutes' => 60 * 24 * 365,
        // Readable by JavaScript and kept out of cookie encryption, so a cached
        // page can still show the right switcher state.
        'http_only' => false,
        'same_site' => 'lax',
    ],

    'geo' => [
        'headers' => ['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Country-Code'],
        // ISO country => locale code.
        'countries' => [
            // 'FR' => 'fr', 'BE' => 'fr', 'CM' => 'fr',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    |
    | Where an explicit choice (the switcher, Localization::persist()) is
    | remembered: cookie, session, and/or the signed-in user. The user store
    | calls setPreferredLocale() (see Concerns\HasPreferredLocale).
    |
    */

    'persist' => ['cookie', 'user'],

    /*
    |--------------------------------------------------------------------------
    | Applying the locale
    |--------------------------------------------------------------------------
    */

    'apply' => [
        'carbon' => true,         // Carbon::setLocale()
        'number' => true,         // Illuminate\Support\Number::useLocale()
        'php_setlocale' => false, // setlocale(LC_ALL, …) – process wide, off by default
        'url_defaults' => true,   // URL::defaults([route_parameter => locale]) under prefix strategy
    ],

    'headers' => [
        'content_language' => true,
        // The same address may answer in several languages: tell caches which
        // request headers decide it (only under the 'none' strategy).
        'vary' => ['Accept-Language', 'Cookie'],
    ],

    // Middleware groups that get SetLocale automatically ([] to add it yourself
    // with the 'localize' alias).
    'middleware_groups' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Language switcher route
    |--------------------------------------------------------------------------
    |
    | POST {path}/{locale}: persists the choice and returns to the same page
    | (in the new language's URL under prefix/domain strategies). JSON
    | requests get {"locale": …, "redirect": …}.
    |
    */

    'switcher' => [
        'enabled' => true,
        'path' => 'locale',
        'name' => 'locale.switch',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Translatable models (Concerns\HasTranslations)
    |--------------------------------------------------------------------------
    */

    'translatable' => [
        // Fall back to the fallback chain when a translation is missing.
        'fallback' => true,
        // …and then to any translation at all.
        'fallback_any' => false,
        // Keep null / '' as real translations instead of "missing".
        'allow_null' => false,
        'allow_empty' => false,
        // toArray()/toJson(): 'current' (the value in the current locale) or
        // 'all' (every translation as an object).
        'serialize' => 'current',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    |
    | Jobs remember the locale they were dispatched in and run in it.
    |
    */

    'queue' => true,

    /*
    |--------------------------------------------------------------------------
    | Missing translation keys
    |--------------------------------------------------------------------------
    |
    | Every __() key without a translation in the current locale can be logged,
    | recorded to storage (for `localization:missing --recorded`), turned into
    | an exception (handy in tests) and announces a MissingTranslation event.
    |
    */

    'missing' => [
        'enabled' => false,
        'log' => false,
        'log_channel' => null,
        'record' => false,
        'record_path' => storage_path('framework/localization-missing.json'),
        'throw' => false,
        // Keys matching these patterns are ignored (validation.custom.* etc.).
        'ignore' => ['validation.custom.*', 'validation.attributes.*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scanner and catalogue tooling
    |--------------------------------------------------------------------------
    */

    'scanner' => [
        'paths' => ['app', 'resources/views', 'resources/js', 'routes', 'src'],
        'extensions' => ['php', 'js', 'ts', 'vue', 'jsx', 'tsx'],
        'functions' => ['__', 'trans', 'trans_choice', '@lang', '@choice', 'Lang::get', 'Lang::choice', '$t', 'i18n.t'],
        'exclude' => ['vendor', 'node_modules', 'storage'],
    ],

    // lang directory (null: the application's lang path).
    'lang_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Front-end translations
    |--------------------------------------------------------------------------
    |
    | GET {path}/{locale}.json serves the locale's translations (JSON strings
    | plus the listed PHP groups) with an ETag, for JavaScript. Off by default.
    |
    */

    'frontend' => [
        'enabled' => false,
        'path' => 'localization',
        'groups' => [],
        'middleware' => [],
        'max_age' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatting
    |--------------------------------------------------------------------------
    */

    'formatting' => [
        // Used by Format::currency() when no currency is given and the locale
        // has none configured.
        'currency' => 'USD',
        // date presets for Format::date($value, 'long') (Carbon isoFormat tokens).
        'dates' => [
            'short' => 'L',
            'medium' => 'll',
            'long' => 'LL',
            'full' => 'dddd LL',
            'time' => 'LT',
            'datetime' => 'LLL',
        ],
    ],

];
