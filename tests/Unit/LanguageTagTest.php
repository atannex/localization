<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Unit;

use Atannex\Localization\LanguageTag;
use PHPUnit\Framework\TestCase;

final class LanguageTagTest extends TestCase
{
    public function test_it_parses_language_script_and_region(): void
    {
        $this->assertSame(['language' => 'zh', 'script' => 'Hant', 'region' => 'TW'], LanguageTag::parse('ZH_hant-tw'));
        $this->assertSame(['language' => 'pt', 'script' => null, 'region' => 'BR'], LanguageTag::parse('pt-br'));
        $this->assertSame(['language' => 'es', 'script' => null, 'region' => '419'], LanguageTag::parse('es-419'));
        $this->assertSame(['language' => 'en', 'script' => null, 'region' => 'US'], LanguageTag::parse('en-US-u-ca-gregory'));
    }

    public function test_it_normalises_between_bcp47_and_posix(): void
    {
        $this->assertSame('pt-BR', LanguageTag::toBcp47('pt_br'));
        $this->assertSame('zh_Hant_TW', LanguageTag::toPosix('zh-hant-tw'));
        $this->assertSame('fr', LanguageTag::toBcp47('FR'));
        $this->assertTrue(LanguageTag::isWellFormed('sr_Latn'));
        $this->assertFalse(LanguageTag::isWellFormed('../etc'));
        $this->assertFalse(LanguageTag::isWellFormed('title'));
    }

    public function test_accept_language_is_sorted_by_quality_keeping_header_order_for_ties(): void
    {
        $this->assertSame(
            ['fr-CM', 'en', 'fr', '*'],
            LanguageTag::parseAcceptLanguage('fr;q=0.8, fr-CM, *;q=0.1, en'),
        );
    }

    public function test_accept_language_refuses_zero_quality_and_garbage(): void
    {
        $this->assertSame(['de'], LanguageTag::parseAcceptLanguage('en;q=0, de, <script>, ;q=1'));
        $this->assertSame([], LanguageTag::parseAcceptLanguage(''));
        $this->assertSame([], LanguageTag::parseAcceptLanguage(null));
    }
}
