<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Events\LocaleSwitched;
use Atannex\Localization\Tests\Fixtures\Member;
use Atannex\Localization\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

final class SwitcherTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('/news', fn () => 'news');
    }

    public function test_it_remembers_the_choice_and_returns_to_the_page(): void
    {
        Event::fake([LocaleSwitched::class]);

        $this->from('/news?page=2')->post('/locale/fr')
            ->assertRedirect('/news?page=2')
            ->assertPlainCookie('locale', 'fr');

        Event::assertDispatched(LocaleSwitched::class, fn ($event) => $event->locale === 'fr' && $event->previous === 'en');

        $this->withUnencryptedCookie('locale', 'fr')->get('/news')->assertHeader('Content-Language', 'fr');
    }

    public function test_the_code_is_case_and_separator_insensitive_and_may_be_posted_as_a_field(): void
    {
        $this->post('/locale/PT-br')->assertPlainCookie('locale', 'pt_BR');
        $this->post('/locale', ['locale' => 'ar'])->assertPlainCookie('locale', 'ar');
    }

    public function test_unsupported_locales_are_refused(): void
    {
        $this->post('/locale/de')->assertNotFound();
        $this->post('/locale', ['locale' => 'fr-CM'])->assertNotFound();
    }

    public function test_it_never_redirects_to_another_site(): void
    {
        $this->from('https://evil.example/phish')->post('/locale/fr')->assertRedirect(url('/'));
        $this->post('/locale/fr', ['redirect' => '//evil.example'])->assertRedirect(url('/'));
        $this->post('/locale/fr', ['redirect' => '/news'])->assertRedirect(url('/news'));
    }

    public function test_it_saves_the_choice_on_the_signed_in_user(): void
    {
        $member = Member::create();

        $this->actingAs($member)->post('/locale/ar');

        $this->assertSame('ar', $member->fresh()->preferredLocale());
    }

    public function test_json_requests_get_the_locale_and_target(): void
    {
        $this->from('/news')->postJson('/locale/fr')->assertOk()->assertJson(['locale' => 'fr', 'redirect' => url('/news')]);
    }

    public function test_a_lang_query_parameter_in_the_previous_url_is_replaced(): void
    {
        $this->from('/news?lang=en&page=3')->post('/locale/fr')->assertRedirect('/news?lang=fr&page=3');
    }
}
