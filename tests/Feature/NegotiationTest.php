<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Events\LocaleChanged;
use Atannex\Localization\Events\LocaleNegotiated;
use Atannex\Localization\Facades\Localization;
use Atannex\Localization\LocaleRegistry;
use Atannex\Localization\Negotiator;
use Atannex\Localization\Tests\Fixtures\Member;
use Atannex\Localization\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;

final class NegotiationTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('/page', fn () => app()->getLocale().'|'.Carbon::now()->locale);
        Route::middleware(['web', 'localize:fr'])->get('/forced', fn () => app()->getLocale());
    }

    public function test_the_default_applies_without_any_signal(): void
    {
        $this->get('/page')->assertOk()->assertSee('en|en')->assertHeader('Content-Language', 'en');
    }

    public function test_accept_language_is_honoured_with_region_matching(): void
    {
        $this->withHeader('Accept-Language', 'fr-CM,fr;q=0.9,en;q=0.5')->get('/page')
            ->assertSee('fr|fr')
            ->assertHeader('Content-Language', 'fr');

        $this->withHeader('Accept-Language', 'pt-PT')->get('/page')->assertHeader('Content-Language', 'pt-BR');
        $this->withHeader('Accept-Language', 'de-DE')->get('/page')->assertHeader('Content-Language', 'en');
    }

    public function test_vary_headers_are_added_when_one_address_serves_every_language(): void
    {
        $vary = $this->get('/page')->headers->get('Vary');

        $this->assertStringContainsString('Accept-Language', $vary);
        $this->assertStringContainsString('Cookie', $vary);
    }

    public function test_detectors_run_in_order(): void
    {
        $member = Member::create(['locale' => 'ar']);

        // query beats user, user beats cookie, cookie beats header
        $this->actingAs($member)->withUnencryptedCookie('locale', 'fr')->withHeader('Accept-Language', 'pt-BR')
            ->get('/page?lang=fr')->assertHeader('Content-Language', 'fr');

        $this->actingAs($member)->withUnencryptedCookie('locale', 'fr')->get('/page')->assertHeader('Content-Language', 'ar');

        $this->app['auth']->forgetGuards();
        $this->withUnencryptedCookie('locale', 'pt_BR')->withHeader('Accept-Language', 'fr')
            ->get('/page')->assertHeader('Content-Language', 'pt-BR');
    }

    public function test_unsupported_values_are_skipped(): void
    {
        $this->withUnencryptedCookie('locale', 'xx')->withHeader('Accept-Language', 'fr')
            ->get('/page?lang=../../etc')->assertHeader('Content-Language', 'fr');
    }

    public function test_a_middleware_parameter_forces_a_locale(): void
    {
        $this->withHeader('Accept-Language', 'ar')->get('/forced')->assertSee('fr');
    }

    public function test_session_and_geo_detectors(): void
    {
        config(['localization.detectors' => ['session', 'geo']]);
        config(['localization.geo.countries' => ['CM' => 'fr', 'BR' => 'pt_BR']]);
        $this->refreshLocalization();

        $this->withHeader('CF-IPCountry', 'br')->get('/page')->assertHeader('Content-Language', 'pt-BR');
        $this->withSession(['locale' => 'ar'])->get('/page')->assertHeader('Content-Language', 'ar');
    }

    public function test_custom_detectors_can_be_added(): void
    {
        config(['localization.detectors' => ['subdomain', 'header']]);
        $this->refreshLocalization();

        Localization::extendDetector('subdomain', fn (Request $request) => explode('.', $request->getHost())[0]);

        $this->get('http://fr.example.test/page')->assertHeader('Content-Language', 'fr');
        $this->get('http://www.example.test/page')->assertHeader('Content-Language', 'en');
    }

    public function test_events_are_dispatched_and_carbon_and_number_follow(): void
    {
        Event::fake([LocaleNegotiated::class, LocaleChanged::class]);

        $this->withHeader('Accept-Language', 'fr')->get('/page');

        Event::assertDispatched(LocaleNegotiated::class, fn ($event) => $event->locale === 'fr' && $event->detector === 'header');
        Event::assertDispatched(LocaleChanged::class, fn ($event) => $event->locale === 'fr' && $event->previous === 'en');
        $this->assertSame('fr', Carbon::getLocale());
        $this->assertSame('fr', Number::defaultLocale());
    }

    public function test_using_restores_the_previous_locale_even_after_an_exception(): void
    {
        $this->assertSame('Langue', Localization::using('fr', fn () => __('localization::messages.language')));
        $this->assertSame('en', app()->getLocale());

        try {
            Localization::using('ar', fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
        }

        $this->assertSame('en', app()->getLocale());
        $this->assertSame(['en' => 'Language', 'fr' => 'Langue', 'pt_BR' => 'Idioma', 'ar' => 'اللغة'], Localization::each(fn () => __('localization::messages.language')));
    }

    public function test_the_translator_falls_back_along_the_chain(): void
    {
        config(['localization.fallback_chains' => ['pt_BR' => ['fr']]]);
        $this->refreshLocalization();

        Localization::set('pt_BR');
        $this->assertSame('fr', app('translator')->getFallback());
        $this->assertTrue(Localization::isRtl('ar'));
        $this->assertSame('rtl', locale_direction('ar'));
    }

    private function refreshLocalization(): void
    {
        foreach ([LocaleRegistry::class, Negotiator::class, \Atannex\Localization\Localization::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        Localization::clearResolvedInstances();
    }
}
