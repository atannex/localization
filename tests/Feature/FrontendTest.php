<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

final class FrontendTest extends TestCase
{
    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->root = sys_get_temp_dir().'/localization-front-'.uniqid();
        (new Filesystem)->ensureDirectoryExists("{$this->root}/fr");
        file_put_contents("{$this->root}/fr.json", json_encode(['Hello' => 'Bonjour']));
        file_put_contents("{$this->root}/fr/js.php", "<?php return ['menu' => ['open' => 'Ouvrir']];");
        file_put_contents("{$this->root}/fr/secret.php", "<?php return ['key' => 'hidden'];");

        $app['config']->set('localization.lang_path', $this->root);
        $app['config']->set('localization.frontend', ['enabled' => true, 'path' => 'i18n', 'groups' => ['js'], 'middleware' => [], 'max_age' => 60]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_translations_are_served_with_an_etag(): void
    {
        $response = $this->get('/i18n/fr.json')->assertOk()->assertExactJson(['Hello' => 'Bonjour', 'js.menu.open' => 'Ouvrir']);
        $etag = $response->headers->get('ETag');

        $this->assertNotEmpty($etag);
        $this->assertStringContainsString('max-age=60', $response->headers->get('Cache-Control'));
        $this->withHeader('If-None-Match', $etag)->get('/i18n/fr.json')->assertStatus(304);
        $this->get('/i18n/de.json')->assertNotFound();
    }
}
