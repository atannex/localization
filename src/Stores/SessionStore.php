<?php

declare(strict_types=1);

namespace Atannex\Localization\Stores;

use Atannex\Localization\Contracts\Store;
use Illuminate\Http\Request;

final class SessionStore implements Store
{
    public function __construct(private readonly string $key = 'locale') {}

    public function remember(Request $request, string $locale): void
    {
        if ($request->hasSession()) {
            $request->session()->put($this->key, $locale);
        }
    }
}
