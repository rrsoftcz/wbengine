<?php

namespace Wbengine\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\I18n\LocaleResolver;

class LocaleResolverTest extends TestCase
{
    private const SUPPORTED = ['en', 'cs', 'pt_BR'];

    #[DataProvider('paths')]
    public function testFromPath(string $path, ?string $locale, string $rest): void
    {
        $this->assertSame([$locale, $rest], LocaleResolver::fromPath($path, self::SUPPORTED));
    }

    public static function paths(): array
    {
        return [
            'prefix'               => ['/cs/blog/', 'cs', '/blog/'],
            'prefix only'          => ['/cs', 'cs', '/'],
            'prefix and slash'     => ['/cs/', 'cs', '/'],
            'deep'                 => ['/cs/a/b/c/', 'cs', '/a/b/c/'],
            'upper case'           => ['/CS/blog/', 'cs', '/blog/'],
            'region with dash'     => ['/pt-br/x/', 'pt_BR', '/x/'],
            'region with underscore' => ['/pt_BR/x/', 'pt_BR', '/x/'],
            'language of a region' => ['/pt/x/', 'pt_BR', '/x/'],
            'no prefix'            => ['/blog/', null, '/blog/'],
            'root'                 => ['/', null, '/'],
            'empty'                => ['', null, ''],
            'unsupported language' => ['/de/blog/', null, '/de/blog/'],
            'a page that looks like a language' => ['/about/', null, '/about/'],
            'longer word'          => ['/csx/blog/', null, '/csx/blog/'],
            'a page named like a locale elsewhere' => ['/blog/cs/', null, '/blog/cs/'],
            'double slash'         => ['//cs/', null, '//cs/'],
            'no leading slash'     => ['cs/blog/', null, 'cs/blog/'],
        ];
    }

    #[DataProvider('headers')]
    public function testAcceptLanguage($header, ?string $expected): void
    {
        $this->assertSame($expected, LocaleResolver::fromAcceptLanguage($header, self::SUPPORTED));
    }

    public static function headers(): array
    {
        return [
            'simple'                    => ['cs', 'cs'],
            'region matches language'   => ['cs-CZ', 'cs'],
            'typical browser'           => ['cs-CZ,cs;q=0.9,en;q=0.8', 'cs'],
            'quality decides'           => ['en;q=0.5,cs;q=0.9', 'cs'],
            'order breaks ties'         => ['cs,en', 'cs'],
            'order breaks ties 2'       => ['en,cs', 'en'],
            'unsupported first'         => ['de,fr;q=0.9,cs;q=0.8', 'cs'],
            'nothing supported'         => ['de,fr', null],
            'wildcard is ignored'       => ['*', null],
            'wildcard then a language'  => ['*;q=0.1,en;q=0.5', 'en'],
            'q=0 means unacceptable'    => ['cs;q=0,en;q=0.1', 'en'],
            'exact region'              => ['pt-BR', 'pt_BR'],
            'other region same language'=> ['pt-PT', 'pt_BR'],
            'spaces'                    => [' cs ; q=0.8 , en ; q=0.9 ', 'en'],
            'case'                      => ['CS-cz', 'cs'],
            'garbage entries skipped'   => ['??,<script>,cs', 'cs'],
            'only garbage'              => ['??,;;,,', null],
            'empty'                     => ['', null],
            'null'                      => [null, null],
            'array'                     => [['cs'], null],
            'huge header'               => [str_repeat('x,', 1000) . 'cs', null],
            'q out of range ignored'    => ['cs;q=7,en', 'en'],
        ];
    }

    #[DataProvider('values')]
    public function testFromValue($value, ?string $expected): void
    {
        $this->assertSame($expected, LocaleResolver::fromValue($value, self::SUPPORTED));
    }

    public static function values(): array
    {
        return [['cs', 'cs'], ['EN', 'en'], ['de', null], ['', null], [null, null], [['cs'], null], ['cs/../x', null], ["cs\0", null]];
    }

    public function testSupportedLocalesKeepTheirSpelling(): void
    {
        $this->assertSame('pt_BR', LocaleResolver::match('pt-br', ['pt_BR']));
        $this->assertSame('en-GB', LocaleResolver::match('en_GB', ['en-GB']));
        $this->assertNull(LocaleResolver::match('cs', []));
    }
}
