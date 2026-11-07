<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

/** php artisan view:cache compiles every package view; each must compile with only Laravel installed. */
final class ViewCacheTest extends TestCase
{
    public function test_view_cache_compiles_the_package_views(): void
    {
        $this->assertSame(0, Artisan::call('view:cache'));
        Artisan::call('view:clear');
    }
}
