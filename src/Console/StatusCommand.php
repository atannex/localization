<?php

declare(strict_types=1);

namespace Atannex\Localization\Console;

use Atannex\Localization\Localization;
use Atannex\Localization\Translations\Inventory;
use Illuminate\Console\Command;

final class StatusCommand extends Command
{
    protected $signature = 'localization:status
        {--no-scan : Compare lang files only, without scanning the code}
        {--json : Output JSON}';

    protected $description = 'Translation coverage per supported locale';

    public function handle(Localization $localization, Inventory $inventory): int
    {
        $inventory = $this->option('no-scan') ? $inventory->withoutScan() : $inventory;
        $coverage = $inventory->coverage();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'default' => $localization->default()->code,
                'strategy' => $localization->strategy(),
                'locales' => $coverage,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Default locale', $localization->default()->code);
        $this->components->twoColumnDetail('URL strategy', $localization->strategy());
        $this->components->twoColumnDetail('Detectors', implode(' → ', (array) $localization->config('detectors', [])));
        $this->newLine();

        $rows = [];
        foreach ($coverage as $code => $row) {
            $locale = $localization->get($code);
            $rows[] = [
                $code.($localization->isDefault($code) ? ' (default)' : ''),
                $locale->native,
                $locale->direction,
                $row['translated'].' / '.$row['total'],
                $row['missing'],
                $this->bar($row['coverage']),
            ];
        }

        $this->table(['Locale', 'Name', 'Dir', 'Translated', 'Missing', 'Coverage'], $rows);

        return self::SUCCESS;
    }

    private function bar(float $percent): string
    {
        $filled = (int) round($percent / 10);
        $color = $percent >= 100 ? 'green' : ($percent >= 80 ? 'yellow' : 'red');

        return "<fg={$color}>".str_repeat('█', $filled).str_repeat('░', 10 - $filled).'</> '.number_format($percent, 1).'%';
    }
}
