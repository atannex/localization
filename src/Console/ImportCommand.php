<?php

declare(strict_types=1);

namespace Atannex\Localization\Console;

use Atannex\Localization\Localization;
use Atannex\Localization\Translations\Catalogue;
use Illuminate\Console\Command;

final class ImportCommand extends Command
{
    protected $signature = 'localization:import
        {file : A CSV (key, source, translation) or JSON (key => translation) file}
        {locale : The locale the translations are in}
        {--overwrite : Replace existing translations}';

    protected $description = 'Import translations from a translator file into the lang files';

    public function handle(Localization $localization, Catalogue $catalogue): int
    {
        $file = (string) $this->argument('file');
        $locale = (string) $this->argument('locale');

        if (! $localization->supports($locale)) {
            $this->components->error("Locale [{$locale}] is not supported.");

            return self::FAILURE;
        }

        if (! is_file($file)) {
            $this->components->error("File [{$file}] not found.");

            return self::FAILURE;
        }

        $messages = str_ends_with(strtolower($file), '.json') ? $this->json($file) : $this->csv($file);
        $messages = array_filter($messages, fn ($value) => is_string($value) && $value !== '');

        $written = $catalogue->put($locale, $messages, (bool) $this->option('overwrite'));
        $this->components->info("Imported {$written} of ".count($messages)." translation(s) into {$locale}.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function json(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? array_map('strval', array_filter($data, 'is_scalar')) : [];
    }

    /**
     * @return array<string, string>
     */
    private function csv(string $file): array
    {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle, escape: '') ?: [];
        $keyColumn = array_search('key', $header, true);
        $valueColumn = array_search('translation', $header, true);

        if ($keyColumn === false || $valueColumn === false) {
            // No header: key, translation (or key, source, translation).
            rewind($handle);
            $keyColumn = 0;
            $valueColumn = count($header) >= 3 ? 2 : 1;
        }

        $messages = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (isset($row[$keyColumn], $row[$valueColumn]) && $row[$keyColumn] !== '') {
                $messages[(string) $row[$keyColumn]] = (string) $row[$valueColumn];
            }
        }

        fclose($handle);

        return $messages;
    }
}
