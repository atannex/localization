<?php

declare(strict_types=1);

namespace Atannex\Localization\Console;

use Atannex\Localization\Localization;
use Atannex\Localization\Translations\Inventory;
use Atannex\Localization\Translations\MissingKeys;
use Illuminate\Console\Command;

final class MissingCommand extends Command
{
    protected $signature = 'localization:missing
        {locale?* : Locales to check (default: all supported)}
        {--recorded : Show the keys recorded at runtime (localization.missing.record) instead}
        {--fix : Add the missing keys to the lang files}
        {--placeholder=empty : With --fix, the value to write: empty, source or key}
        {--no-scan : Compare lang files only, without scanning the code}
        {--json : Output JSON}';

    protected $description = 'List (and optionally add) translation keys missing per locale';

    public function handle(Localization $localization, Inventory $inventory): int
    {
        $locales = $this->argument('locale') ?: $localization->codes();

        foreach ($locales as $locale) {
            if (! $localization->supports($locale)) {
                $this->components->error("Locale [{$locale}] is not supported.");

                return self::FAILURE;
            }
        }

        $inventory = $this->option('no-scan') ? $inventory->withoutScan() : $inventory;
        $report = [];

        foreach ($locales as $locale) {
            $report[$locale] = $this->option('recorded')
                ? (MissingKeys::recorded((string) $localization->config('missing.record_path'))[$locale] ?? [])
                : $inventory->missing($locale);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($report as $locale => $keys) {
            $total += count($keys);
            $this->components->twoColumnDetail("<options=bold>{$locale}</>", count($keys).' missing');

            foreach ($keys as $key) {
                $this->line('  <fg=gray>·</> '.$key);
            }

            if ($this->option('fix') && $keys !== []) {
                $messages = [];
                foreach ($keys as $key) {
                    $messages[$key] = match ($this->option('placeholder')) {
                        'source' => $inventory->source($key),
                        'key' => $key,
                        default => '',
                    };
                }

                $written = $inventory->catalogue()->put($locale, $messages);
                $this->components->info("Added {$written} key(s) to the {$locale} files.");
            }
        }

        if ($total === 0) {
            $this->components->info('Nothing missing.');
        }

        return self::SUCCESS;
    }
}
