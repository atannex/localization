<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Facades\Localization;
use Atannex\Localization\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class PrefixStrategyTest extends TestCase
{
    protected array $localization = ['strategy' => 'prefix', 'detectors' => ['route', 'cookie', 'header']];

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function () {
            Route::localized(function () {
                Route::get('/', fn () => 'home:'.app()->getLocale())->name('home');
                Route::get('/about', fn () => 'about:'.app()->getLocale().'|'.localized_route('about'))->name('about');
                Route::post('/contact', fn () => 'sent:'.app()->getLocale())->name('contact');
            });
        });
    }

    public function test_the_default_locale_is_unprefixed_and_others_are_prefixed(): void
    {
        $this->get('/about')->assertOk()->assertSee('about:en|http://localhost/about');
        $this->get('/fr/about')->assertOk()->assertSee('about:fr|http://localhost/fr/about')->assertHeader('Content-Language', 'fr');
        $this->get('/pt_BR/about')->assertOk()->assertSee('about:pt_BR');
        $this->get('/fr')->assertOk()->assertSee('home:fr');
        $this->get('/de/about')->assertNotFound();
    }

    public function test_the_default_prefix_redirects_to_the_canonical_url(): void
    {
        $this->get('/en/about?x=1')->assertStatus(301)->assertRedirect('/about?x=1');
    }

    public function test_unprefixed_pages_redirect_to_the_preferred_locale(): void
    {
        $this->withHeader('Accept-Language', 'fr')->get('/about')->assertStatus(302)->assertRedirect('/fr/about');
        // English preference, no preference, and unsafe methods stay put.
        $this->withHeader('Accept-Language', 'en-US')->get('/about')->assertOk();
        $this->withHeader('Accept-Language', 'fr')->post('/contact')->assertOk()->assertSee('sent:en');
        $this->flushHeaders()->get('/about')->assertOk();
        $this->withUnencryptedCookie('locale', 'ar')->get('/about')->assertRedirect('/ar/about');
    }

    public function test_urls_translate_between_locales(): void
    {
        $this->assertSame('http://localhost/fr/about?a=1#top', Localization::url('fr', 'http://localhost/about?a=1#top'));
        $this->assertSame('http://localhost/about', Localization::url('en', 'http://localhost/fr/about'));
        $this->assertSame('http://localhost/ar', Localization::url('ar', 'http://localhost/fr'));
        $this->assertSame('/pt_BR/about', Localization::url('pt_BR', '/FR/about'));
        $this->assertSame('https://elsewhere.test/about', Localization::url('fr', 'https://elsewhere.test/about'));

        $this->assertSame([
            'en' => 'http://localhost/about',
            'fr' => 'http://localhost/fr/about',
            'pt_BR' => 'http://localhost/pt_BR/about',
            'ar' => 'http://localhost/ar/about',
        ], Localization::alternates('http://localhost/fr/about'));

        $this->assertSame('http://localhost/about', Localization::neutralUrl('http://localhost/ar/about'));
    }

    public function test_named_routes_resolve_in_any_locale(): void
    {
        $this->assertSame('http://localhost/fr/about', localized_route('about', locale: 'fr'));
        $this->assertSame('http://localhost/about', localized_route('about', locale: 'en'));
        $this->assertSame('/ar', localized_route('home', locale: 'ar', absolute: false));
    }

    public function test_the_switcher_returns_to_the_same_page_in_the_new_locale(): void
    {
        $this->from('/fr/about?a=1')->post('/locale/ar')->assertRedirect('/ar/about?a=1');
        $this->from('/fr/about')->post('/locale/en')->assertRedirect('/about');
    }
}
