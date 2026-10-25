<?php

declare(strict_types=1);

namespace Atannex\Localization\Stores;

use Atannex\Localization\Contracts\Store;
use Illuminate\Contracts\Cookie\QueueingFactory;
use Illuminate\Http\Request;

/**
 * Queues the localization cookie (sent by AddQueuedCookiesToResponse, part
 * of the web group). It is excluded from cookie encryption.
 */
final class CookieStore implements Store
{
    /**
     * @param  array{name?: string, minutes?: int, http_only?: bool, same_site?: string|null}  $config
     */
    public function __construct(private readonly QueueingFactory $cookies, private readonly array $config = []) {}

    public function remember(Request $request, string $locale): void
    {
        $this->cookies->queue($this->cookies->make(
            $this->config['name'] ?? 'locale',
            $locale,
            (int) ($this->config['minutes'] ?? 525600),
            httpOnly: (bool) ($this->config['http_only'] ?? false),
            sameSite: $this->config['same_site'] ?? 'lax',
        ));
    }
}
