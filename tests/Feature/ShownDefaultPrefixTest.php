<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class ShownDefaultPrefixTest extends TestCase
{
    protected array $localization = ['strategy' => 'prefix', 'hide_default' => false, 'detectors' => ['route', 'header']];

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->localized(function () {
            Route::get('/about', fn () => route('about'))->name('about');
        });
    }

    public function test_every_locale_is_prefixed_and_url_defaults_fill_the_parameter(): void
    {
        $this->get('/en/about')->assertOk()->assertSee('http://localhost/en/about');
        $this->get('/fr/about')->assertOk()->assertSee('http://localhost/fr/about');
        $this->get('/about')->assertNotFound();
    }
}
