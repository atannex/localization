<?php

declare(strict_types=1);

namespace Atannex\Localization;

use Atannex\Localization\Console\ExportCommand;
use Atannex\Localization\Console\ImportCommand;
use Atannex\Localization\Console\LocalesCommand;
use Atannex\Localization\Console\MissingCommand;
use Atannex\Localization\Console\StatusCommand;
use Atannex\Localization\Console\UnusedCommand;
use Atannex\Localization\Http\Controllers\SwitchLocaleController;
use Atannex\Localization\Http\Controllers\TranslationsController;
use Atannex\Localization\Http\Middleware\RedirectToLocalizedUrl;
use Atannex\Localization\Http\Middleware\SetLocale;
use Atannex\Localization\Queue\LocalePropagation;
use Atannex\Localization\Routing\LocalizedRoutes;
use Atannex\Localization\Translations\Catalogue;
use Atannex\Localization\Translations\Inventory;
use Atannex\Localization\Translations\MissingKeys;
use Atannex\Localization\Translations\Scanner;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Router;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LocalizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/localization.php', 'localization');

        $this->app->singleton(LocaleRegistry::class, fn (Application $app) => LocaleRegistry::fromConfig((array) $app['config']['localization']));

        $this->app->singleton(Negotiator::class, fn (Application $app) => new Negotiator(
            $app,
            $app->make(LocaleRegistry::class),
            (array) $app['config']['localization'],
        ));

        $this->app->singleton(Localization::class, fn (Application $app) => new Localization(
            $app,
            $app->make(LocaleRegistry::class),
            $app->make(Negotiator::class),
            (array) $app['config']['localization'],
        ));
        $this->app->alias(Localization::class, 'localization');

        $this->app->singleton(Catalogue::class, fn (Application $app) => new Catalogue(
            $app['files'],
            (string) ($app['config']['localization.lang_path'] ?? $app->langPath()),
        ));

        $this->app->bind(Scanner::class, function (Application $app) {
            $config = (array) $app['config']['localization.scanner'];

            return new Scanner(
                array_map(fn (string $path) => str_starts_with($path, '/') ? $path : $app->basePath($path), (array) ($config['paths'] ?? [])),
                (array) ($config['extensions'] ?? ['php']),
                (array) ($config['functions'] ?? ['__']),
                (array) ($config['exclude'] ?? []),
            );
        });

        $this->app->bind(Inventory::class, fn (Application $app) => new Inventory(
            $app->make(Localization::class),
            $app->make(Catalogue::class),
            $app->make(Scanner::class),
        ));

        $this->app->singleton(MissingKeys::class, fn (Application $app) => new MissingKeys(
            $app->make(Localization::class),
            (array) $app['config']['localization.missing'],
        ));
    }

    public function boot(Router $router): void
    {
        $config = (array) $this->app['config']['localization'];

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'localization');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'localization');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'localization');

        $this->registerMiddleware($router, $config);
        $this->registerRoutes($config);
        $this->registerBlade();
        $this->registerCookie($config);

        if ($config['queue'] ?? true) {
            (new LocalePropagation($this->app->make(Localization::class)))->register($this->app['events']);
        }

        if ($config['missing']['enabled'] ?? false) {
            $this->app->make(MissingKeys::class)->register($this->app['translator']);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/localization.php' => $this->app->configPath('localization.php')], 'localization-config');
            $this->publishes([__DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/localization')], 'localization-views');
            $this->publishes([__DIR__.'/../resources/lang' => $this->app->langPath('vendor/localization')], 'localization-lang');

            $this->commands([
                StatusCommand::class,
                MissingCommand::class,
                UnusedCommand::class,
                ExportCommand::class,
                ImportCommand::class,
                LocalesCommand::class,
            ]);

            AboutCommand::add('Localization', fn () => [
                'Locales' => implode(', ', $this->app->make(Localization::class)->codes()),
                'Default' => $this->app->make(Localization::class)->default()->code,
                'Strategy' => (string) ($config['strategy'] ?? 'none'),
                'Detectors' => implode(', ', (array) ($config['detectors'] ?? [])),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function registerMiddleware(Router $router, array $config): void
    {
        $router->aliasMiddleware('localize', SetLocale::class);
        $router->aliasMiddleware('localize.redirect', RedirectToLocalizedUrl::class);

        $groups = (array) ($config['middleware_groups'] ?? []);

        // The HTTP kernel owns the groups and re-syncs them to the router when
        // it is built, which may happen after this provider boots.
        $append = function (HttpKernel $kernel) use ($groups): void {
            foreach ($groups as $group) {
                if (method_exists($kernel, 'appendMiddlewareToGroup') && array_key_exists($group, $kernel->getMiddlewareGroups())) {
                    $kernel->appendMiddlewareToGroup((string) $group, SetLocale::class);
                }
            }
        };

        if ($groups !== []) {
            $this->app->resolved(HttpKernel::class) ? $append($this->app->make(HttpKernel::class)) : $this->app->afterResolving(HttpKernel::class, $append);
        }

        Route::macro('localized', function (Closure $routes, array $attributes = []) {
            /** @var Router $this */
            LocalizedRoutes::register($this, app(Localization::class), $routes, $attributes);
        });

        // Route::middleware('web')->localized(fn () => …). RouteRegistrar is only
        // macroable from Laravel 12; on 11 wrap Route::localized() in a group.
        if (method_exists(RouteRegistrar::class, 'macro')) {
            RouteRegistrar::macro('localized', function (Closure $routes) {
                /** @var RouteRegistrar $this */
                $this->group(fn (Router $router) => LocalizedRoutes::register($router, app(Localization::class), $routes));
            });
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function registerRoutes(array $config): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $codes = implode('|', array_map(fn (string $code) => preg_quote($code, '/'), $this->app->make(Localization::class)->codes()));

        if ($config['switcher']['enabled'] ?? true) {
            Route::middleware((array) ($config['switcher']['middleware'] ?? ['web']))
                ->post(trim((string) ($config['switcher']['path'] ?? 'locale'), '/').'/{locale?}', SwitchLocaleController::class)
                // Any well-formed tag reaches the controller, which accepts the supported
                // codes in any case or separator (pt-br → pt_BR) and 404s the rest.
                ->where('locale', '[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*')
                ->name((string) ($config['switcher']['name'] ?? 'locale.switch'));
        }

        if ($config['frontend']['enabled'] ?? false) {
            Route::middleware((array) ($config['frontend']['middleware'] ?? []))
                ->get(trim((string) ($config['frontend']['path'] ?? 'localization'), '/').'/{locale}.json', TranslationsController::class)
                ->where('locale', $codes)
                ->name('localization.translations');
        }
    }

    private function registerBlade(): void
    {
        // @locale('fr') … @endlocale renders the block in another locale.
        Blade::directive('locale', fn (string $expression) => "<?php app(\\Atannex\\Localization\\Localization::class)->push({$expression}); ?>");
        Blade::directive('endlocale', fn () => '<?php app(\\Atannex\\Localization\\Localization::class)->pop(); ?>');

        // lang="pt-BR" dir="ltr" for <html>.
        Blade::directive('localeAttributes', fn () => '<?php $__locale = app(\\Atannex\\Localization\\Localization::class)->current(); echo \'lang="\'.e($__locale->tag()).\'" dir="\'.e($__locale->direction).\'"\'; unset($__locale); ?>');

        Blade::if('rtl', fn (?string $locale = null) => app(Localization::class)->isRtl($locale));
        Blade::if('multilingual', fn () => app(Localization::class)->isMultilingual());
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function registerCookie(array $config): void
    {
        // The choice is not sensitive, and pages cached without a session still read it.
        EncryptCookies::except([(string) ($config['cookie']['name'] ?? 'locale')]);
    }
}
