<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Unit;

use Atannex\Localization\Exceptions\InvalidConfiguration;
use Atannex\Localization\Exceptions\UnsupportedLocale;
use Atannex\Localization\LocaleRegistry;
use PHPUnit\Framework\TestCase;

final class LocaleRegistryTest extends TestCase
{
    public function test_codes_are_completed_from_the_catalog(): void
    {
        $registry = new LocaleRegistry(['en', 'ar', 'pt_BR']);

        $this->assertSame(['en', 'ar', 'pt_BR'], $registry->codes());
        $this->assertSame('en', $registry->default()->code);

        $arabic = $registry->get('ar');
        $this->assertSame('العربية', $arabic->native);
        $this->assertTrue($arabic->isRtl());
        $this->assertSame('ar_SA', $arabic->regional());

        $brazil = $registry->get('pt_BR');
        $this->assertSame('pt-BR', $brazil->tag());
        $this->assertSame('pt', $brazil->language());
        $this->assertSame('BR', $brazil->region());
        $this->assertSame('🇧🇷', $brazil->flag());
        $this->assertSame('BRL', $brazil->currency);
    }

    public function test_overrides_and_extra_keys_win(): void
    {
        $registry = new LocaleRegistry(['en' => ['regional' => 'en_US', 'currency' => 'USD', 'icon' => 'us'], 'fr']);

        $this->assertSame('en_US', $registry->get('en')->regional());
        $this->assertSame('USD', $registry->get('en')->currency);
        $this->assertSame('us', $registry->get('en')->get('icon'));
        $this->assertSame('en_US', $registry->get('en')->get('og'));
    }

    public function test_unknown_regional_variants_inherit_their_language(): void
    {
        $locale = LocaleRegistry::describe('fr_LU');

        $this->assertSame('French (LU)', $locale->name);
        $this->assertSame('Français', $locale->native);
        $this->assertSame('fr_LU', $locale->regional());
    }

    public function test_unknown_languages_need_a_name(): void
    {
        $this->assertSame('Bakweri', (new LocaleRegistry(['bri' => ['name' => 'Bakweri']]))->get('bri')->name);

        $this->expectException(InvalidConfiguration::class);
        new LocaleRegistry(['qqx']);
    }

    public function test_configuration_errors_are_reported(): void
    {
        $this->expectException(InvalidConfiguration::class);
        new LocaleRegistry(['en'], 'fr');
    }

    public function test_unsupported_locales_throw(): void
    {
        $this->expectException(UnsupportedLocale::class);
        (new LocaleRegistry(['en']))->get('de');
    }

    public function test_tags_match_supported_codes(): void
    {
        $registry = new LocaleRegistry(['en', 'fr', 'pt_BR', 'zh_Hant']);

        $this->assertSame('pt_BR', $registry->match('pt-br'));
        $this->assertSame('pt_BR', $registry->match('PT_BR'));
        $this->assertSame('fr', $registry->match('fr-CM'));
        $this->assertSame('pt_BR', $registry->match('pt-AO'));
        $this->assertSame('pt_BR', $registry->match('pt'));
        $this->assertSame('zh_Hant', $registry->match('zh-Hant-HK'));
        $this->assertNull($registry->match('de-DE'));
        $this->assertNull($registry->match('*'));
        $this->assertNull($registry->match(''));
    }

    public function test_negotiation_takes_the_first_tag_that_matches(): void
    {
        $registry = new LocaleRegistry(['en', 'de']);

        $this->assertSame('de', $registry->negotiate(['fr-FR', 'de-AT', 'en']));
        $this->assertNull($registry->negotiate(['fr', 'es']));
    }

    public function test_fallback_chains(): void
    {
        $registry = new LocaleRegistry(['en', 'fr', 'fr_CA', 'de'], fallback: 'fr', chains: ['de' => ['en']]);

        $this->assertSame(['fr', 'en'], $registry->fallbacks('fr_CA'));
        $this->assertSame(['en', 'fr'], $registry->fallbacks('de'));
        $this->assertSame(['en'], $registry->fallbacks('fr'));
        $this->assertSame(['fr'], $registry->fallbacks('en'));
        $this->assertSame('fr', $registry->fallback('fr_CA')->code);
    }
}
