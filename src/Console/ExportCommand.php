<?php

declare(strict_types=1);

namespace Atannex\Localization\Console;

use Atannex\Localization\Localization;
use Atannex\Localization\Translations\Inventory;
use Illuminate\Console\Command;

final class ExportCommand extends Command
{
    protected $signature = 'localization:export
        {locale : The locale to export}
        {--format=csv : csv or json}
        {--output= : File to write (default: stdout)}
        {--missing : Only keys without a translation}
        {--no-scan : Use the lang files only}';

    protected $description = 'Export a locale for translators (key, source text, translation)';

    public function handle(Localization $localization, Inventory $inventory): int
    {
        $locale = (string) $this->argument('locale');

        if (! $localization->supports($locale)) {
            $this->components->error("Locale [{$locale}] is not supported.");

            return self::FAILURE;
        }

        $inventory = $this->option('no-scan') ? $inventory->withoutScan() : $inventory;
        $messages = $inventory->catalogue()->all($locale);
        $keys = $this->option('missing') ? $inventory->missing($locale) : array_values(array_unique([...$inventory->reference($locale), ...array_keys($messages)]));
        sort($keys);

        $rows = array_map(fn (string $key) => [
            'key' => $key,
            'source' => $inventory->source($key),
            'translation' => $messages[$key] ?? '',
        ], $keys);

        $output = $this->option('format') === 'json'
            ? (string) json_encode(array_column($rows, 'translation', 'key'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
            : $this->csv($rows);

        if ($path = $this->option('output')) {
            file_put_contents((string) $path, $output);
            $this->components->info(count($rows)." key(s) written to {$path}.");
        } else {
            $this->output->write($output);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{key: string, source: string, translation: string}>  $rows
     */
    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['key', 'source', 'translation'], escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), escape: '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
