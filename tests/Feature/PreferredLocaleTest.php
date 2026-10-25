<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Tests\Fixtures\JsonMember;
use Atannex\Localization\Tests\Fixtures\Member;
use Atannex\Localization\Tests\TestCase;

final class PreferredLocaleTest extends TestCase
{
    public function test_a_column_holds_the_preference(): void
    {
        $member = Member::create();
        $this->assertNull($member->preferredLocale());

        $member->setPreferredLocale('pt-br');
        $this->assertSame('pt_BR', $member->fresh()->locale);

        $member->setPreferredLocale('de'); // unsupported: ignored
        $this->assertSame('pt_BR', $member->fresh()->preferredLocale());

        Member::query()->update(['locale' => 'xx']);
        $this->assertNull($member->fresh()->preferredLocale());
    }

    public function test_a_json_path_holds_the_preference(): void
    {
        $member = JsonMember::create(['settings' => ['theme' => 'dark']]);

        $member->setPreferredLocale('fr');

        $fresh = $member->fresh();
        $this->assertSame(['theme' => 'dark', 'preferences' => ['locale' => 'fr']], $fresh->settings);
        $this->assertSame('fr', $fresh->preferredLocale());
    }

    public function test_unsaved_models_are_not_saved(): void
    {
        $member = new Member;
        $member->setPreferredLocale('ar');

        $this->assertFalse($member->exists);
        $this->assertSame('ar', $member->preferredLocale());
    }
}
