<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Events\MissingTranslationKey;
use Atannex\Localization\Exceptions\MissingTranslation;
use Atannex\Localization\Facades\Localization;
use Atannex\Localization\Tests\TestCase;
use Atannex\Localization\Translations\MissingKeys;
use Illuminate\Support\Facades\Event;

final class MissingKeysTest extends TestCase
{
    protected array $localization = ['missing' => ['enabled' => true, 'ignore' => ['debug.*']]];

    public function test_missing_keys_are_reported_once_per_locale(): void
    {
        Event::fake([MissingTranslationKey::class]);

        __('nav.unknown');
        __('nav.unknown');
        __('Plain text in the source language');
        __('debug.ignored');
        Localization::using('fr', fn () => __('Plain text in the source language'));

        Event::assertDispatchedTimes(MissingTranslationKey::class, 2);
        Event::assertDispatched(MissingTranslationKey::class, fn ($e) => $e->key === 'nav.unknown' && $e->locale === 'en');
        Event::assertDispatched(MissingTranslationKey::class, fn ($e) => $e->key === 'Plain text in the source language' && $e->locale === 'fr');

        $this->assertSame(['en' => ['nav.unknown'], 'fr' => ['Plain text in the source language']], app(MissingKeys::class)->seen());
    }

    public function test_keys_can_be_recorded_to_a_file(): void
    {
        $path = sys_get_temp_dir().'/localization-missing-'.uniqid().'.json';
        $keys = new MissingKeys(app(\Atannex\Localization\Localization::class), ['record' => true, 'record_path' => $path]);

        $keys->handle('a.b', [], 'fr');
        $keys->handle('c.d', [], 'fr');
        $keys->flush();
        $keys->handle('a.b', [], 'ar');
        $keys->flush();

        $this->assertSame(['fr' => ['a.b', 'c.d'], 'ar' => ['a.b']], MissingKeys::recorded($path));
        @unlink($path);
    }

    public function test_it_can_throw(): void
    {
        $keys = new MissingKeys(app(\Atannex\Localization\Localization::class), ['throw' => true]);

        $this->expectException(MissingTranslation::class);
        $keys->handle('nav.missing', [], 'fr');
    }
}
