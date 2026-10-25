<?php

declare(strict_types=1);

namespace Atannex\Localization\Formatting;

use Atannex\Localization\Locale;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use NumberFormatter;

/**
 * Numbers, money, dates, durations and lists in one locale. Uses ext-intl
 * when it is loaded and falls back to sensible built-in rules otherwise,
 * so every method always returns something readable.
 *
 *     Localization::format('fr')->currency(1234.5, 'EUR')   // 1 234,50 €
 *     Localization::format()->date($post->published_at, 'long')
 *     Localization::format('de')->list(['A', 'B', 'C'])     // A, B und C
 */
final class Formatter
{
    /** Decimal and grouping separators for locales when intl is missing. */
    private const SEPARATORS = [
        'fr' => [',', "\u{202F}"], 'de' => [',', '.'], 'es' => [',', '.'], 'it' => [',', '.'], 'pt' => [',', '.'],
        'nl' => [',', '.'], 'ru' => [',', "\u{00A0}"], 'pl' => [',', "\u{00A0}"], 'tr' => [',', '.'], 'sv' => [',', "\u{00A0}"],
        'da' => [',', '.'], 'nb' => [',', "\u{00A0}"], 'fi' => [',', "\u{00A0}"], 'cs' => [',', "\u{00A0}"], 'uk' => [',', "\u{00A0}"],
        'id' => [',', '.'], 'vi' => [',', '.'], 'ro' => [',', '.'], 'el' => [',', '.'], 'hu' => [',', "\u{00A0}"],
    ];

    /** "and" / "or" for list() when intl's list formatter is missing. */
    private const CONJUNCTIONS = [
        'en' => ['and', 'or'], 'fr' => ['et', 'ou'], 'de' => ['und', 'oder'], 'es' => ['y', 'o'], 'it' => ['e', 'o'],
        'pt' => ['e', 'ou'], 'nl' => ['en', 'of'], 'ru' => ['и', 'или'], 'pl' => ['i', 'lub'], 'tr' => ['ve', 'veya'],
        'sv' => ['och', 'eller'], 'da' => ['og', 'eller'], 'nb' => ['og', 'eller'], 'fi' => ['ja', 'tai'], 'ar' => ['و', 'أو'],
        'sw' => ['na', 'au'], 'id' => ['dan', 'atau'], 'ro' => ['și', 'sau'], 'uk' => ['і', 'або'], 'cs' => ['a', 'nebo'],
    ];

    /**
     * @param  array<string, mixed>  $config  localization.formatting
     */
    public function __construct(
        private readonly Locale $locale,
        private readonly array $config = [],
    ) {}

    public function locale(): Locale
    {
        return $this->locale;
    }

    public static function hasIntl(): bool
    {
        return class_exists(NumberFormatter::class);
    }

    // ---------------------------------------------------------------- numbers

    public function number(int|float|string|null $value, ?int $decimals = null, ?int $maxDecimals = null): string
    {
        $value = (float) $value;

        if (self::hasIntl()) {
            $formatter = $this->numberFormatter(NumberFormatter::DECIMAL);

            if ($decimals !== null) {
                $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxDecimals ?? $decimals);
            } elseif ($maxDecimals !== null) {
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxDecimals);
            }

            return $this->clean($formatter->format($value));
        }

        [$point, $group] = $this->separators();

        if ($decimals !== null) {
            return number_format($value, $decimals, $point, $group);
        }

        return $this->trimZeros(number_format($value, $maxDecimals ?? 3, $point, $group), $point);
    }

    /** 0.256 → 25.6 % (pass 25.6 with $isFraction = false). */
    public function percent(int|float|string|null $value, int $maxDecimals = 1, bool $isFraction = true): string
    {
        $value = (float) $value;
        $fraction = $isFraction ? $value : $value / 100;

        if (self::hasIntl()) {
            $formatter = $this->numberFormatter(NumberFormatter::PERCENT);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxDecimals);

            return $this->clean($formatter->format($fraction));
        }

        $number = $this->number($fraction * 100, null, $maxDecimals);

        return in_array($this->locale->language(), ['fr', 'de', 'sv', 'nb', 'fi', 'cs', 'ru', 'es'], true) ? "{$number}\u{00A0}%" : "{$number}%";
    }

    public function currency(int|float|string|null $amount, ?string $currency = null, ?int $decimals = null): string
    {
        $currency = strtoupper($currency ?? $this->locale->currency ?? (string) ($this->config['currency'] ?? 'USD'));
        $amount = (float) $amount;

        if (self::hasIntl()) {
            $formatter = $this->numberFormatter(NumberFormatter::CURRENCY);

            if ($decimals !== null) {
                $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            }

            $formatted = $formatter->formatCurrency($amount, $currency);

            if ($formatted !== false) {
                return $this->clean($formatted);
            }
        }

        $decimals ??= in_array($currency, ['XAF', 'XOF', 'JPY', 'KRW', 'VND', 'CLP', 'ISK', 'UGX', 'RWF'], true) ? 0 : 2;
        [$point, $group] = $this->separators();
        $number = number_format($amount, $decimals, $point, $group);

        return $point === ',' ? "{$number}\u{00A0}{$currency}" : "{$currency}\u{00A0}{$number}";
    }

    /** 1234 → 1.2K (1,2K in French), 1234567 → 1.2M. */
    public function compact(int|float|string|null $value, int $maxDecimals = 1): string
    {
        $value = (float) $value;

        $units = ['', 'K', 'M', 'B', 'T'];
        $power = abs($value) < 1000 ? 0 : min(count($units) - 1, (int) floor(log10(abs($value)) / 3));

        return $this->number($value / (1000 ** $power), null, $maxDecimals).$units[$power];
    }

    /** 1536 → 1.5 KB (base 1024, unit names in English abbreviations). */
    public function fileSize(int|float|string|null $bytes, int $maxDecimals = 1): string
    {
        $bytes = max(0.0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $power = $bytes >= 1024 ? min(count($units) - 1, (int) floor(log($bytes, 1024))) : 0;

        return $this->number($bytes / (1024 ** $power), null, $power === 0 ? 0 : $maxDecimals)."\u{00A0}".$units[$power];
    }

    /** 1 → 1st / 1er / 1., 22 → 22nd. */
    public function ordinal(int $value): string
    {
        if (self::hasIntl()) {
            $formatted = (new NumberFormatter($this->intlLocale(), NumberFormatter::ORDINAL))->format($value);

            if (is_string($formatted) && $formatted !== '') {
                return $this->clean($formatted);
            }
        }

        return match ($this->locale->language()) {
            'en' => $value.(in_array($value % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$value % 10] ?? 'th')),
            'fr' => $value.($value === 1 ? 'er' : 'e'),
            'es', 'pt', 'it' => $value.'º',
            default => $value.'.',
        };
    }

    /** 42 → "forty-two" / "quarante-deux" (intl only; digits otherwise). */
    public function spell(int|float $value): string
    {
        if (self::hasIntl()) {
            $formatted = (new NumberFormatter($this->intlLocale(), NumberFormatter::SPELLOUT))->format($value);

            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return $this->number($value);
    }

    // ---------------------------------------------------------------- dates

    /**
     * A date in a preset (short, medium, long, full, time, datetime, from
     * localization.formatting.dates) or any Carbon isoFormat pattern.
     */
    public function date(DateTimeInterface|string|int|null $value, string $format = 'medium', ?string $timezone = null): string
    {
        $date = $this->carbon($value, $timezone);

        if ($date === null) {
            return '';
        }

        $pattern = (string) ($this->config['dates'][$format] ?? $format);

        return $date->locale($this->locale->code)->isoFormat($pattern);
    }

    public function time(DateTimeInterface|string|int|null $value, ?string $timezone = null): string
    {
        return $this->date($value, 'time', $timezone);
    }

    public function dateTime(DateTimeInterface|string|int|null $value, ?string $timezone = null): string
    {
        return $this->date($value, 'datetime', $timezone);
    }

    /** "3 hours ago" / "il y a 3 heures" / "in 2 days". */
    public function relative(DateTimeInterface|string|int|null $value, DateTimeInterface|string|null $from = null, bool $short = false): string
    {
        $date = $this->carbon($value);

        if ($date === null) {
            return '';
        }

        $other = $from === null ? null : $this->carbon($from);

        return $date->locale($this->locale->code)->diffForHumans($other, ['short' => $short]);
    }

    /** 3725 seconds → "1 hour 2 minutes" / "1 heure 2 minutes". */
    public function duration(int|float|CarbonInterval $value, int $parts = 2, bool $short = false): string
    {
        $interval = $value instanceof CarbonInterval ? $value : CarbonInterval::seconds((int) round($value))->cascade();

        return $interval->locale($this->locale->code)->forHumans(['parts' => $parts, 'short' => $short, 'join' => true]);
    }

    /** Month names in this locale, 1 => January… */
    public function months(bool $short = false): array
    {
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = Carbon::create(2000, $month, 1)->locale($this->locale->code)->isoFormat($short ? 'MMM' : 'MMMM');
        }

        return $months;
    }

    /** Weekday names in this locale, starting Monday (1 => Monday… 7 => Sunday). */
    public function weekdays(bool $short = false): array
    {
        $days = [];

        for ($day = 1; $day <= 7; $day++) {
            $days[$day] = Carbon::create(2024, 1, $day)->locale($this->locale->code)->isoFormat($short ? 'ddd' : 'dddd');
        }

        return $days;
    }

    // ---------------------------------------------------------------- text

    /**
     * ['A', 'B', 'C'] → "A, B and C" (conjunction) or "A, B or C" (disjunction).
     *
     * @param  iterable<string|\Stringable>  $items
     */
    public function list(iterable $items, string $type = 'and'): string
    {
        $items = array_values(array_map('strval', is_array($items) ? $items : iterator_to_array($items, false)));

        if (count($items) < 2) {
            return $items[0] ?? '';
        }

        if (class_exists(\IntlListFormatter::class)) {
            $formatter = new \IntlListFormatter(
                $this->intlLocale(),
                $type === 'or' ? constant(\IntlListFormatter::class.'::TYPE_OR') : constant(\IntlListFormatter::class.'::TYPE_AND'),
            );

            return $formatter->format($items);
        }

        $word = (self::CONJUNCTIONS[$this->locale->language()] ?? self::CONJUNCTIONS['en'])[$type === 'or' ? 1 : 0];
        $last = array_pop($items);
        $comma = $this->locale->language() === 'ar' ? '، ' : ', ';

        return implode($comma, $items).' '.$word.' '.$last;
    }

    // ---------------------------------------------------------------- internals

    private function intlLocale(): string
    {
        return str_replace('_', '-', $this->locale->code);
    }

    private function numberFormatter(int $style): NumberFormatter
    {
        return new NumberFormatter($this->intlLocale(), $style);
    }

    /** @return array{0: string, 1: string} */
    private function separators(): array
    {
        return self::SEPARATORS[$this->locale->language()] ?? ['.', ','];
    }

    private function trimZeros(string $formatted, string $point): string
    {
        if (! str_contains($formatted, $point)) {
            return $formatted;
        }

        [$whole, $fraction] = explode($point, $formatted, 2);
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole.$point.$fraction;
    }

    private function clean(string|false $formatted): string
    {
        return $formatted === false ? '' : $formatted;
    }

    private function carbon(DateTimeInterface|string|int|null $value, ?string $timezone = null): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = match (true) {
            $value instanceof DateTimeInterface => Carbon::instance($value),
            is_int($value) => Carbon::createFromTimestamp($value),
            default => Carbon::parse($value),
        };

        return $timezone ? $date->setTimezone($timezone) : $date;
    }
}
