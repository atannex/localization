<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Rules\SupportedLocale;
use Atannex\Localization\Rules\Translations;
use Atannex\Localization\Tests\TestCase;
use Illuminate\Support\Facades\Validator;

final class RulesTest extends TestCase
{
    public function test_supported_locale(): void
    {
        $this->assertTrue(Validator::make(['l' => 'fr'], ['l' => new SupportedLocale])->passes());
        $this->assertFalse(Validator::make(['l' => 'fr-CM'], ['l' => new SupportedLocale])->passes());
        $this->assertTrue(Validator::make(['l' => 'fr-CM'], ['l' => new SupportedLocale(loose: true)])->passes());

        $errors = Validator::make(['l' => 'de'], ['l' => new SupportedLocale])->errors();
        $this->assertSame('The l must be one of: en, fr, pt_BR, ar.', $errors->first('l'));
    }

    public function test_translations_maps(): void
    {
        $rule = Translations::make()->requireDefault()->max(5);

        $this->assertTrue(Validator::make(['t' => ['en' => 'Hi', 'fr' => null]], ['t' => $rule])->passes());

        $errors = Validator::make(['t' => ['fr' => 'Bonjour', 'de' => 'Hallo']], ['t' => $rule])->errors()->get('t');
        $this->assertSame([
            'The fr translation of the t may not be longer than 5 characters.',
            'The t contains an unsupported language (de).',
            'The t needs a version in English.',
        ], $errors);

        $this->assertFalse(Validator::make(['t' => ['Hi']], ['t' => Translations::make()])->passes());
        $this->assertFalse(Validator::make(['t' => ['en' => 'x']], ['t' => Translations::make()->require('*')])->passes());
    }
}
