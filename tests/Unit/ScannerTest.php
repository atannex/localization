<?php

declare(strict_types=1);

namespace Atannex\Localization\Tests\Unit;

use Atannex\Localization\Translations\Scanner;
use PHPUnit\Framework\TestCase;

final class ScannerTest extends TestCase
{
    private function keys(string $source): array
    {
        $scanner = new Scanner([], functions: ['__', 'trans', 'trans_choice', '@lang', 'Lang::get', '$t']);

        return array_map(fn (array $match) => [$match[0], $match[2]], $scanner->scanString($source));
    }

    public function test_it_finds_literal_keys_in_php_blade_and_js(): void
    {
        $source = <<<'SRC'
            <?php echo __('Sign in'); trans("nav.home"); trans_choice('posts.count', 3);
            @lang('auth.failed') Lang::get('a.b') {{ $t('js.key') }}
            __( 'It\'s here' ) __("Line\twith tab")
            SRC;

        $this->assertSame([
            ['Sign in', false], ['nav.home', false], ['posts.count', false],
            ['auth.failed', false], ['a.b', false], ['js.key', false],
            ["It's here", false], ["Line\twith tab", false],
        ], $this->keys($source));
    }

    public function test_methods_and_lookalikes_are_ignored(): void
    {
        $this->assertSame([], $this->keys('$this->trans("x.y"); $obj->__("z"); Foo::trans("q"); my__("w"); strans("v")'));
    }

    public function test_runtime_keys_become_dynamic_prefixes(): void
    {
        $this->assertSame([
            ['desks.', true],
            ['menu.', true],
            ['js.', true],
        ], $this->keys('__("desks.{$desk}") __(\'menu.\'.$item) $t(`js.${key}`)'));
    }
}
