<?php

declare(strict_types=1);

namespace Atannex\Localization\Console;

use Atannex\Localization\LocaleRegistry;
use Atannex\Localization\Localization;
use Illuminate\Console\Command;

final class LocalesCommand extends Command
{
    protected $signature = 'localization:locales
        {--catalog : List every locale in the built-in catalog instead}
        {--json : Output JSON}';

    protected $description = 'List the supported locales and their details';

    public function handle(Localization $localization): int
    {
        $locales = $this->option('catalog')
            ? array_map(fn (string $code) => LocaleRegistry::describe($code), array_keys(LocaleRegistry::catalog()))
            : array_values($localization->locales());

        if ($this->option('json')) {
            $this->line((string) json_encode(array_map(fn ($locale) => $locale->toArray(), $locales), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table(
            ['Code', 'Tag', 'Name', 'Native', 'Script', 'Dir', 'Regional', 'Currency', 'Flag', ''],
            array_map(fn ($locale) => [
                $locale->code, $locale->tag(), $locale->name, $locale->native, $locale->script, $locale->direction,
                $locale->regional(), $locale->currency ?? '', $locale->flag() ?? '',
                $localization->supports($locale->code) ? ($localization->isDefault($locale->code) ? 'default' : 'supported') : '',
            ], $locales),
        );

        return self::SUCCESS;
    }
}
