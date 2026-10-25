<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Facades\Localization;
use Atannex\Localization\Tests\Fixtures\RecordLocaleJob;
use Atannex\Localization\Tests\TestCase;

final class QueueTest extends TestCase
{
    public function test_jobs_run_in_the_locale_they_were_dispatched_in(): void
    {
        config(['queue.default' => 'sync']);
        RecordLocaleJob::$seen = [];

        // A block, so the PendingDispatch is sent before the locale is restored.
        Localization::using('fr', function () {
            RecordLocaleJob::dispatch();
        });
        RecordLocaleJob::dispatch();

        $this->assertSame(['fr|Langue', 'en|Language'], RecordLocaleJob::$seen);
        $this->assertSame('en', app()->getLocale());
    }
}
