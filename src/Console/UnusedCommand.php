<?php

declare(strict_types=1);

namespace Atannex\Localization\Console;

use Atannex\Localization\Localization;
use Atannex\Localization\Translations\Inventory;
use Illuminate\Console\Command;

final class UnusedCommand extends Command
{
    protected $signature = 'localization:unused
        {--locale= : The locale whose files to check (default: the default locale, then every other)}
        {--delete : Remove the unused keys from the lang files}
        {--json : Output JSON}';

    protected $description = 'List translation keys no code uses (keys under dynamic prefixes are kept)';

    public function handle(Localization $localization, Inventory $inventory): int
    {
        $locales = $this->option('locale') ? [(string) $this->option('locale')] : $localization->codes();
        $report = [];

        foreach ($locales as $locale) {
            $report[$locale] = $inventory->unused($locale);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $dynamic = $inventory->scan()['dynamic'];
        if ($dynamic !== []) {
            $this->components->twoColumnDetail('Dynamic prefixes kept', implode(', ', $dynamic));
        }

        foreach ($report as $locale => $keys) {
            $this->components->twoColumnDetail("<options=bold>{$locale}</>", count($keys).' unused');

            foreach ($keys as $key) {
                $this->line('  <fg=gray>·</> '.$key);
            }

            if ($this->option('delete') && $keys !== []) {
                $removed = $inventory->catalogue()->forget($locale, $keys);
                $this->components->info("Removed {$removed} key(s) from the {$locale} files.");
            }
        }

        return self::SUCCESS;
    }
}
