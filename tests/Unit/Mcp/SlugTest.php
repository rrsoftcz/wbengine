<?php
namespace Wbengine\Tests\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Wbengine\Mcp\Tools\Slug;

final class SlugTest extends TestCase
{
    public function testAddressesAreAsciiLowercaseHyphenatedAndShort(): void
    {
        $this->assertSame('/blog/muj-prvni-clanek/', Slug::fromTitle('Můj první článek!', '/blog'));
        $this->assertSame('/prilis-zlutoucka-kobyla/', Slug::fromTitle('Příliš žluťoučká Kobyla'));
        $this->assertSame('/article/', Slug::fromTitle('???'));
        $long = Slug::fromTitle('Jak jsme postavili nový web pro naši malou pekárnu na vsi u Brna a proč jsme zvolili WBengine', '/blog/');
        $this->assertLessThanOrEqual(60 + strlen('/blog//'), strlen($long));
        $this->assertDoesNotMatchRegularExpression('~-/$~', $long);
        $this->assertStringNotContainsString('wbengi/', $long, 'a word is not cut in the middle');
        $this->assertTrue(Slug::valid($long));
    }

    public function testSuffixKeepsTheTrailingSlash(): void
    {
        $this->assertSame('/blog/post-2/', Slug::withSuffix('/blog/post/', 2));
    }

    public function testTitleTagCutsAtWordsAndKeepsShortOnesWhole(): void
    {
        $this->assertSame('Krátký titulek', Slug::titleTag('  Krátký   titulek '));
        $tag = Slug::titleTag(str_repeat('slovo ', 20));
        $this->assertLessThanOrEqual(60, mb_strlen($tag));
        $this->assertStringEndsWith('slovo', $tag);
    }

    public function testDescriptionIsPlainTextWithinTheLimit(): void
    {
        $this->assertNull(Slug::description(''));
        $this->assertNull(Slug::description("# \n\n"));
        $this->assertSame('Text s odkazem a obrázkem.', Slug::description('Text s [odkazem](https://x.cz) a ![alt](/m/1) **obrázkem**.'));
        $long = Slug::description('První věta je dost dlouhá, aby se vešla do popisu stránky pro vyhledávače a ještě něco zbylo. ' . str_repeat('Další věta, která už se nevejde. ', 10));
        $this->assertLessThanOrEqual(160, mb_strlen($long));
        $this->assertStringEndsWith('.', $long);
        $this->assertStringStartsWith('První věta', $long);
        $words = Slug::description(str_repeat('slovo ', 60));
        $this->assertLessThanOrEqual(160, mb_strlen($words));
        $this->assertStringEndsWith('slovo', $words);
    }
}
