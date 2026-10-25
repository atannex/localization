<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Facades\Localization;
use Atannex\Localization\Tests\TestCase;

final class QueryStrategyTest extends TestCase
{
    public function test_alternates_use_the_query_parameter_when_the_query_detector_is_on(): void
    {
        $this->assertSame('http://localhost/news?page=2&lang=fr', Localization::url('fr', 'http://localhost/news?page=2'));
        $this->assertSame('/news?lang=ar', Localization::url('ar', '/news?lang=fr'));
        $this->assertSame('http://localhost/news?page=2', Localization::neutralUrl('http://localhost/news?lang=fr&page=2'));
    }

    public function test_without_the_query_detector_urls_are_unchanged(): void
    {
        config(['localization.detectors' => ['cookie', 'header']]);
        $this->app->forgetInstance(\Atannex\Localization\Localization::class);
        Localization::clearResolvedInstances();

        $this->assertSame('http://localhost/news', Localization::url('fr', 'http://localhost/news'));
    }
}
