<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Fixtures;

use Atannex\Localization\Concerns\HasPreferredLocale;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User;

class Member extends User implements HasLocalePreference
{
    use HasPreferredLocale;

    protected $table = 'members';

    protected $guarded = [];

    protected $casts = ['settings' => 'array'];
}
