<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Tests\TestCase;
use Atannex\Localization\Translations\Catalogue;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

final class CommandsTest extends TestCase
{
    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->root = sys_get_temp_dir().'/localization-'.uniqid();
        $files = new Filesystem;
        $files->ensureDirectoryExists("{$this->root}/lang/en");
        $files->ensureDirectoryExists("{$this->root}/lang/fr");
        $files->ensureDirectoryExists("{$this->root}/src");

        $files->put("{$this->root}/lang/en/nav.php", "<?php return ['home' => 'Home', 'desks' => ['news' => 'News', 'sport' => 'Sport'], 'old' => 'Old'];");
        $files->put("{$this->root}/lang/fr/nav.php", "<?php return ['home' => 'Accueil'];");
        $files->put("{$this->root}/lang/fr.json", json_encode(['Sign in' => 'Se connecter', 'Orphan' => 'Orphelin', '2026' => '2026']));
        $files->put("{$this->root}/src/page.blade.php", "{{ __('Sign in') }} {{ __('Sign out') }} @lang('nav.home') {{ __('nav.missing') }} {{ __(\"nav.desks.{\$desk}\") }}");

        $app['config']->set('localization.locales', ['en', 'fr']);
        $app['config']->set('localization.lang_path', "{$this->root}/lang");
        $app['config']->set('localization.scanner.paths', ["{$this->root}/src"]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_status_reports_coverage(): void
    {
        // en: 4 group keys in its files + nav.missing used in code; fr also needs the 3 text keys.
        $this->assertSame(0, Artisan::call('localization:status', ['--json' => true]));
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(['total' => 5, 'translated' => 4, 'missing' => 1, 'coverage' => 80], $report['locales']['en']);
        // fr.json's numeric key "2026" counts as a text key.
        $this->assertSame(['total' => 9, 'translated' => 4, 'missing' => 5, 'coverage' => 44.4], $report['locales']['fr']);

        $this->artisan('localization:status')->expectsOutputToContain('Coverage')->assertSuccessful();
    }

    public function test_missing_lists_and_fixes_keys(): void
    {
        $this->artisan('localization:missing', ['locale' => ['fr']])
            ->expectsOutputToContain('nav.desks.news')
            ->expectsOutputToContain('Sign out')
            ->assertSuccessful();

        $this->artisan('localization:missing', ['locale' => ['fr'], '--fix' => true, '--placeholder' => 'source'])->assertSuccessful();

        $catalogue = app(Catalogue::class);
        $catalogue->flush();
        $this->assertSame('Sign out', $catalogue->json('fr')['Sign out']);
        $this->assertSame('News', $catalogue->group('fr', 'nav')['desks.news']);
        $this->assertSame('Accueil', $catalogue->group('fr', 'nav')['home']);
        $this->assertStringContainsString("'desks' => [", file_get_contents("{$this->root}/lang/fr/nav.php"));

        // The default locale only misses group keys used in code.
        $this->artisan('localization:missing', ['locale' => ['en'], '--json' => true])
            ->expectsOutputToContain('nav.missing')
            ->assertSuccessful();
    }

    public function test_unused_keys_spare_dynamic_prefixes(): void
    {
        $this->artisan('localization:unused', ['--locale' => 'en', '--json' => true])
            ->expectsOutputToContain('"nav.old"')
            ->doesntExpectOutputToContain('nav.desks.news')
            ->assertSuccessful();

        $this->artisan('localization:unused', ['--locale' => 'fr', '--delete' => true])->assertSuccessful();
        $this->assertArrayNotHasKey('Orphan', app(Catalogue::class)->json('fr'));
    }

    public function test_export_and_import_round_trip(): void
    {
        $csv = "{$this->root}/fr.csv";

        $this->artisan('localization:export', ['locale' => 'fr', '--missing' => true, '--output' => $csv])->assertSuccessful();
        $this->assertStringContainsString('nav.desks.sport,Sport,', file_get_contents($csv));

        file_put_contents($csv, str_replace('nav.desks.sport,Sport,', 'nav.desks.sport,Sport,"Sport, foot"', file_get_contents($csv)));
        $this->artisan('localization:import', ['file' => $csv, 'locale' => 'fr'])->assertSuccessful();

        $this->assertSame('Sport, foot', (new Catalogue(new Filesystem, "{$this->root}/lang"))->group('fr', 'nav')['desks.sport']);

        $json = "{$this->root}/fr.json.import.json";
        file_put_contents($json, json_encode(['nav.home' => 'Maison', 'New text' => 'Nouveau']));
        $this->artisan('localization:import', ['file' => $json, 'locale' => 'fr'])->assertSuccessful();
        $catalogue = new Catalogue(new Filesystem, "{$this->root}/lang");
        $this->assertSame('Accueil', $catalogue->group('fr', 'nav')['home'], 'kept without --overwrite');
        $this->assertSame('Nouveau', $catalogue->json('fr')['New text']);
    }

    public function test_locales_lists_details(): void
    {
        $this->artisan('localization:locales')->expectsOutputToContain('Français')->assertSuccessful();
        $this->artisan('localization:locales', ['--catalog' => true, '--json' => true])->expectsOutputToContain('"native": "日本語"')->assertSuccessful();
    }

    public function test_unsupported_locales_fail(): void
    {
        $this->artisan('localization:missing', ['locale' => ['de']])->assertFailed();
        $this->artisan('localization:export', ['locale' => 'de'])->assertFailed();
    }
}
