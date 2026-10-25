<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Unit;

use Atannex\Localization\Formatting\Formatter;
use Atannex\Localization\LocaleRegistry;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class FormatterTest extends TestCase
{
    private function format(string $code): Formatter
    {
        return new Formatter(LocaleRegistry::describe($code), ['currency' => 'USD', 'dates' => ['long' => 'LL', 'medium' => 'll']]);
    }

    /** Normalises the spaces intl uses (NBSP, narrow NBSP) for comparison. */
    private function plain(string $value): string
    {
        return str_replace(["\u{00A0}", "\u{202F}"], ' ', $value);
    }

    public function test_numbers(): void
    {
        $this->assertSame('1,234.5', $this->format('en')->number(1234.5));
        $this->assertSame('1 234,5', $this->plain($this->format('fr')->number(1234.5)));
        $this->assertSame('1.234,50', $this->format('de')->number(1234.5, 2));
        $this->assertSame('3', $this->format('en')->number(3.0));
    }

    public function test_money_and_percentages(): void
    {
        $this->assertSame('$1,234.50', $this->format('en_US')->currency(1234.5, 'USD'));
        $this->assertSame('1 234,50 €', $this->plain($this->format('fr')->currency(1234.5)));
        $this->assertStringContainsString('FCFA', $this->format('fr_CM')->currency(5000));
        $this->assertSame('25.6%', $this->format('en')->percent(0.256));
        $this->assertSame('12 %', $this->plain($this->format('fr')->percent(12, isFraction: false)));
    }

    public function test_compact_sizes_and_ordinals(): void
    {
        $this->assertSame('1.2K', $this->format('en')->compact(1234));
        $this->assertSame('999', $this->format('en')->compact(999));
        $this->assertSame('3.4M', $this->format('en')->compact(3_400_000));
        $this->assertSame('1.5 KB', $this->plain($this->format('en')->fileSize(1536)));
        $this->assertSame('0 B', $this->plain($this->format('en')->fileSize(0)));
        $this->assertSame('22nd', $this->format('en')->ordinal(22));
        $this->assertSame('11th', $this->format('en')->ordinal(11));
    }

    public function test_lists_use_the_locale_conjunction(): void
    {
        // PHP 8.5's IntlListFormatter adds the serial comma; the built-in rules don't.
        $this->assertContains($this->format('en_US')->list(['A', 'B', 'C']), ['A, B, and C', 'A, B and C']);
        $this->assertSame('A, B et C', $this->format('fr')->list(['A', 'B', 'C']));
        $this->assertSame('A und B', $this->format('de')->list(['A', 'B']));
        $this->assertSame('A', $this->format('de')->list(['A']));
        $this->assertSame('', $this->format('de')->list([]));
    }

    public function test_dates_and_relative_times(): void
    {
        Carbon::setTestNow('2026-10-24 12:00:00');

        $this->assertSame('24 octobre 2026', $this->format('fr')->date('2026-10-24', 'long'));
        $this->assertSame('October 24, 2026', $this->format('en')->date('2026-10-24', 'long'));
        $this->assertSame('', $this->format('en')->date(null));
        $this->assertSame('il y a 3 heures', $this->format('fr')->relative('2026-10-24 09:00:00'));
        $this->assertSame('1 hour and 2 minutes', $this->format('en')->duration(3725));
        $this->assertSame('janvier', $this->format('fr')->months()[1]);
        $this->assertSame('lundi', $this->format('fr')->weekdays()[1]);

        Carbon::setTestNow();
    }
}
