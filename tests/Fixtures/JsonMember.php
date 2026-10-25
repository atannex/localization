<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Fixtures;

class JsonMember extends Member
{
    protected string $preferredLocaleAttribute = 'settings->preferences->locale';
}
