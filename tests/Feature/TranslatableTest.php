<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Feature;

use Atannex\Localization\Events\TranslationSet;
use Atannex\Localization\Exceptions\AttributeIsNotTranslatable;
use Atannex\Localization\Facades\Localization;
use Atannex\Localization\LocaleRegistry;
use Atannex\Localization\Negotiator;
use Atannex\Localization\Tests\Fixtures\Article;
use Atannex\Localization\Tests\Fixtures\AttributedArticle;
use Atannex\Localization\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class TranslatableTest extends TestCase
{
    public function test_assigning_a_string_sets_the_current_locale(): void
    {
        $article = Article::create(['title' => 'Hello']);
        Localization::using('fr', fn () => $article->update(['title' => 'Bonjour']));

        $this->assertSame(['en' => 'Hello', 'fr' => 'Bonjour'], $article->fresh()->getTranslations('title'));
        $this->assertSame('{"en":"Hello","fr":"Bonjour"}', DB::table('articles')->value('title'));
    }

    public function test_assigning_a_locale_map_sets_every_translation(): void
    {
        $article = Article::create(['title' => ['en' => 'Hi', 'fr' => 'Salut']]);

        $this->assertSame('Hi', $article->title);
        $this->assertSame('Salut', Localization::using('fr', fn () => $article->title));
    }

    public function test_lists_and_value_arrays_are_values_not_translations(): void
    {
        $article = Article::create(['body' => [['type' => 'p', 'text' => 'One']]]);
        $article->body = ['type' => 'quote'];

        $this->assertSame(['en' => ['type' => 'quote']], $article->getTranslations('body'));

        Localization::using('fr', fn () => $article->body = []);
        $this->assertSame(['en' => ['type' => 'quote'], 'fr' => []], $article->getTranslations('body'));
        $this->assertSame([], $article->in('fr')->body);
    }

    public function test_reading_falls_back_along_the_chain(): void
    {
        $article = Article::create(['title' => ['en' => 'Hello', 'fr' => 'Bonjour']]);

        $this->assertSame('Hello', $article->getTranslation('title', 'ar'));
        $this->assertSame('', $article->getTranslation('title', 'ar', false));
        $this->assertSame('Bonjour', $article->in('fr')->title);
        $this->assertSame('Hello', $article->title, 'in() returns a copy');

        config(['localization.fallback_chains' => ['pt_BR' => ['fr']]]);
        $this->refresh();
        $this->assertSame('Bonjour', $article->getTranslation('title', 'pt_BR'));
    }

    public function test_fallback_can_be_switched_off_for_a_block_or_a_context(): void
    {
        $article = Article::create(['title' => ['en' => 'Hello']]);
        Localization::set('fr');

        $this->assertSame('Hello', $article->title);
        $this->assertSame('', Localization::withoutFallback(fn () => $article->title));
        $this->assertSame('Hello', $article->title);

        $strict = false;
        Localization::strictWhen(function () use (&$strict) {
            return $strict;
        });
        $this->assertSame('Hello', $article->toArray()['title']);

        // By default a strict context empties serialisation (forms) only.
        $strict = true;
        $this->assertSame('Hello', $article->title);
        $this->assertSame('', $article->toArray()['title']);

        Localization::strictWhen(fn () => $strict, reads: true);
        $this->assertSame('', $article->title);
    }

    public function test_null_columns_stay_null_and_empty_values_are_missing(): void
    {
        $article = Article::create(['title' => ['en' => 'Hello', 'fr' => '']]);

        $this->assertNull($article->body);
        $this->assertSame(['en' => 'Hello'], $article->getTranslations('title'));
        $this->assertFalse($article->hasTranslation('title', 'fr'));
    }

    public function test_values_from_before_the_column_was_translatable_belong_to_the_default_locale(): void
    {
        DB::table('articles')->insert(['title' => 'Plain old title', 'body' => '[{"type":"p"}]']);
        DB::table('articles')->insert(['title' => '{}', 'body' => '[]']);
        [$article, $empty] = Article::orderBy('id')->get()->all();

        $this->assertSame([], $empty->getTranslations('title'));
        $this->assertSame([], $empty->body, 'a pre-translation empty list is the default locale\'s value');

        $this->assertSame(['en' => 'Plain old title'], $article->getTranslations('title'));
        $this->assertSame([['type' => 'p']], $article->body);
        $this->assertSame('Plain old title', Localization::using('fr', fn () => $article->title));

        Localization::using('fr', fn () => $article->update(['title' => 'Un titre']));
        $this->assertSame(['en' => 'Plain old title', 'fr' => 'Un titre'], $article->fresh()->getTranslations('title'));
    }

    public function test_forgetting_and_replacing(): void
    {
        $article = Article::create(['title' => ['en' => 'A', 'fr' => 'B', 'ar' => 'C']]);

        $article->forgetTranslation('title', 'ar');
        $this->assertSame(['en', 'fr'], $article->getTranslatedLocales('title'));

        $article->replaceTranslations('title', ['fr' => 'Seul']);
        $this->assertSame(['fr' => 'Seul'], $article->getTranslations('title'));

        $article->forgetAllTranslations('fr');
        $this->assertSame([], $article->getTranslations('title'));

        $article->setTranslations('title', ['en' => 'X'])->forgetTranslations('title', asNull: true);
        $this->assertNull($article->getAttributes()['title']);
    }

    public function test_serialisation_gives_the_current_locale_or_everything(): void
    {
        $article = Article::create(['title' => ['en' => 'Hello', 'fr' => 'Bonjour']]);

        $this->assertSame('Bonjour', Localization::using('fr', fn () => $article->toArray()['title']));

        config(['localization.translatable.serialize' => 'all']);
        $this->refresh();
        $this->assertSame(['en' => 'Hello', 'fr' => 'Bonjour'], $article->toArray()['title']);
    }

    public function test_completeness_helpers(): void
    {
        $article = Article::create(['title' => ['en' => 'Hello', 'fr' => 'Bonjour'], 'body' => ['en' => 'Text']]);

        $this->assertSame(['body'], $article->missingTranslations('fr'));
        $this->assertFalse($article->isFullyTranslated('fr'));
        $this->assertTrue($article->isFullyTranslated('en'));
        $this->assertSame(['en' => 1.0, 'fr' => 0.5, 'pt_BR' => 0.0, 'ar' => 0.0], $article->translationProgress());
        $this->assertSame(['en', 'fr'], $article->locales());
    }

    public function test_translation_events(): void
    {
        Event::fake([TranslationSet::class]);

        $article = new Article;
        $article->setTranslation('title', 'fr', 'Bonjour');
        $article->setTranslation('title', 'fr', 'Bonjour');

        Event::assertDispatchedTimes(TranslationSet::class, 1);
        Event::assertDispatched(TranslationSet::class, fn ($e) => $e->key === 'title' && $e->locale === 'fr' && $e->old === null && $e->new === 'Bonjour');
    }

    public function test_non_translatable_attributes_are_guarded(): void
    {
        $this->expectException(AttributeIsNotTranslatable::class);

        (new Article)->getTranslation('slug', 'en');
    }

    public function test_the_php_attribute_declares_columns_and_accessors_still_apply(): void
    {
        $article = AttributedArticle::create(['title' => ['en' => 'loud', 'fr' => 'fort']]);

        $this->assertTrue($article->isTranslatableAttribute('body'));
        $this->assertSame('LOUD', $article->title);
        $this->assertSame('FORT', $article->getTranslation('title', 'fr'));
    }

    public function test_where_translation_matches_any_or_one_locale(): void
    {
        Article::create(['title' => ['en' => 'Music', 'fr' => 'Musique']]);
        Article::create(['title' => ['en' => 'Sport']]);

        $this->assertSame(1, Article::whereTranslation('title', 'Musique')->count());
        $this->assertSame(0, Article::whereTranslation('title', 'Musique', 'en')->count());
        $this->assertSame(1, Article::whereTranslation('title', 'Sport', 'en')->count());
        $this->assertSame(1, Article::whereJsonContainsLocale('title', 'fr', 'Musique')->count());
        $this->assertSame(2, Article::whereJsonContainsLocales('title', ['en', 'fr'], 'Musique')->orWhereTranslation('title', 'Sport')->count());
    }

    public function test_like_search_is_case_insensitive_and_escapes_wildcards(): void
    {
        Article::create(['title' => ['en' => 'Breaking News', 'fr' => 'Dernière minute']]);
        Article::create(['title' => ['en' => '100% local']]);

        $this->assertSame(1, Article::whereTranslationLike('title', 'news')->count());
        $this->assertSame(1, Article::whereTranslationLike('title', 'MINUTE', ['fr'])->count());
        $this->assertSame(0, Article::whereTranslationLike('title', 'minute', ['en'])->count());
        $this->assertSame(1, Article::whereTranslationLike('title', '0% l')->count());
        $this->assertSame(0, Article::whereTranslationLike('title', '_%')->count());
        $this->assertSame(2, Article::whereTranslationLike(['title', 'body'], '')->count());
    }

    public function test_ordering_uses_the_current_locale_with_fallback(): void
    {
        Article::create(['title' => ['en' => 'Banana', 'fr' => 'Zèbre']]);
        Article::create(['title' => ['en' => 'Apple']]);
        Article::create(['title' => ['en' => 'Cherry', 'fr' => '']]);

        $this->assertSame(['Apple', 'Banana', 'Cherry'], Article::orderByTranslation('title')->get()->pluck('title')->all());

        Localization::set('fr');
        // Apple and Cherry (empty French) fall back to English.
        $this->assertSame(['Apple', 'Cherry', 'Zèbre'], Article::orderByTranslation('title')->get()->pluck('title')->all());
        $this->assertSame(['Zèbre', 'Cherry', 'Apple'], Article::orderByTranslation('title', 'desc')->get()->pluck('title')->all());
    }

    public function test_translation_queues_and_selects(): void
    {
        Article::create(['title' => ['en' => 'Done', 'fr' => 'Fait']]);
        Article::create(['title' => ['en' => 'Todo']]);
        Article::create(['title' => ['fr' => 'Seulement français']]);

        $this->assertSame(1, Article::whereMissingTranslation('title', 'fr')->count());
        $this->assertSame(2, Article::whereHasTranslation('title', 'fr')->count());
        $this->assertSame(2, Article::whereLocales('title', ['en'])->count());
        $this->assertSame(1, Article::whereLocale('title', 'en')->whereLocale('title', 'fr')->count());

        $titles = Localization::using('fr', fn () => Article::query()->selectTranslation('title', as: 'label')->orderBy('id')->get()->pluck('label')->all());
        $this->assertSame(['Fait', 'Todo', 'Seulement français'], $titles);
    }

    private function refresh(): void
    {
        foreach ([LocaleRegistry::class, Negotiator::class, \Atannex\Localization\Localization::class] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        Localization::clearResolvedInstances();
    }
}
