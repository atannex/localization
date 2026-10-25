<?php

declare(strict_types=1);

namespace Atannex\Localization\Queue;

use Atannex\Localization\Localization;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;

/**
 * Queued jobs run in the locale they were dispatched in: the locale goes
 * into the job payload and is applied around the job, then restored.
 */
final class LocalePropagation
{
    public const PAYLOAD_KEY = 'localization:locale';

    /** @var list<string> locales to restore, innermost job last */
    private array $previous = [];

    public function __construct(private readonly Localization $localization) {}

    public function register(Dispatcher $events): void
    {
        // Resolved per call: the callback is static and outlives this container in tests.
        Queue::createPayloadUsing(fn () => [self::PAYLOAD_KEY => app(Localization::class)->code()]);

        $events->listen(JobProcessing::class, function (JobProcessing $event): void {
            $locale = $event->job->payload()[self::PAYLOAD_KEY] ?? null;

            if (is_string($locale) && $this->localization->supports($locale)) {
                $this->previous[] = $this->localization->code();
                $this->localization->set($locale);
            }
        });

        $restore = function (JobProcessed|JobExceptionOccurred $event): void {
            $locale = $event->job->payload()[self::PAYLOAD_KEY] ?? null;

            if (is_string($locale) && $this->localization->supports($locale) && $this->previous !== []) {
                $this->localization->set(array_pop($this->previous));
            }
        };

        $events->listen(JobProcessed::class, $restore);
        $events->listen(JobExceptionOccurred::class, $restore);
    }
}
