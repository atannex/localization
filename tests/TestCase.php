<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests;

use Atannex\Localization\LocalizationServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @var array<string, mixed> config overrides for the next app */
    protected array $localization = [];

    protected function getPackageProviders($app): array
    {
        return [LocalizationServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('localization.locales', ['en', 'fr', 'pt_BR', 'ar']);
        $app['config']->set('localization.queue', true);

        foreach ($this->localization as $key => $value) {
            $app['config']->set("localization.{$key}", $value);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Member');
            $table->string('locale')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }
}
