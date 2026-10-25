<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\LocaleRegistry;
use Atannex\Localization\Localization;
use Atannex\Localization\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

final class BladeTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('/page', fn () => Blade::render(<<<'BLADE'
            <html @localeAttributes>
            <x-localization::meta />
            [@locale('fr'){{ __('localization::messages.language') }}@endlocale|{{ __('localization::messages.language') }}]
            @rtl RTL @else LTR @endrtl
            <x-localization::switcher />
            <x-localization::switcher variant="select" flags />
            BLADE));
    }

    public function test_layout_helpers_render_for_the_current_locale(): void
    {
        $html = $this->withHeader('Accept-Language', 'ar')->get('/page?x=1')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="ar_SA">', $html);
        $this->assertStringContainsString('<meta property="og:locale:alternate" content="fr_FR">', $html);
        $this->assertStringNotContainsString('og:locale:alternate" content="ar_SA"', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="pt-BR" href="http://localhost/page?x=1&amp;lang=pt_BR">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="http://localhost/page?x=1">', $html);
        $this->assertStringContainsString('[Langue|اللغة]', $html);
        $this->assertStringContainsString('RTL', $html);
    }

    public function test_the_switcher_posts_to_each_locale(): void
    {
        $html = $this->get('/page')->getContent();

        $this->assertStringContainsString('action="http://localhost/locale/fr"', $html);
        $this->assertMatchesRegularExpression('/aria-current="true"\s+data-localization-locale="en"/', $html);
        $this->assertStringContainsString('action="http://localhost/locale"', $html);
        $this->assertStringContainsString('🇧🇷 Português (Brasil)', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_nothing_multilingual_renders_for_a_single_locale(): void
    {
        config(['localization.locales' => ['en']]);
        foreach ([LocaleRegistry::class, Localization::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        $html = $this->get('/page')->getContent();

        $this->assertStringNotContainsString('hreflang', $html);
        $this->assertStringNotContainsString('data-localization-switcher', $html);
    }
}
