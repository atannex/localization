<?php

declare(strict_types=1);

namespace Atannex\Localization\Contracts;

use Atannex\Localization\LocaleRegistry;
use Illuminate\Http\Request;

/**
 * One way of telling which locale a request wants. Return any tag (it is
 * matched against the supported locales) or null to let the next one try.
 */
interface Detector
{
    public function detect(Request $request, LocaleRegistry $locales): ?string;
}
