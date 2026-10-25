<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RecordLocaleJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /** @var list<string> */
    public static array $seen = [];

    public function handle(): void
    {
        self::$seen[] = app()->getLocale().'|'.__('localization::messages.language');
    }
}
