<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Facades\Localization;
use Atannex\Localization\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class DomainStrategyTest extends TestCase
{
    protected array $localization = [
        'strategy' => 'domain',
        'domains' => ['example.com' => 'en', 'example.fr' => 'fr'],
        'detectors' => ['domain', 'header'],
    ];

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('/about', fn () => app()->getLocale());
    }

    public function test_the_host_decides_and_urls_swap_hosts(): void
    {
        $this->get('http://example.fr/about')->assertSee('fr')->assertHeader('Content-Language', 'fr');
        $this->get('http://EXAMPLE.com/about')->assertSee('en');

        $this->assertSame('https://example.fr/about?q=1', Localization::url('fr', 'https://example.com/about?q=1'));
        // A locale without a domain keeps the current host.
        $this->assertSame('https://example.com/about', Localization::url('ar', 'https://example.com/about'));
    }
}
