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

    public function testAnApostropheDoesNotSplitAWordAndNoAddressEndsInAFunctionWord(): void
    {
        $title = "Humanity's First Steps into Space: Laika, Gagarin and the Moon";
        $this->assertSame('/blog/humanitys-first-steps-into-space-laika-gagarin/', Slug::fromTitle($title, '/blog/'));
        $this->assertSame('/o-nas-nase-prace/', Slug::fromTitle("O nás, naše práce a"), 'the Czech "a" is a function word too');
        $this->assertSame('/the/', Slug::fromTitle('The and of'), 'a title of function words only keeps one word instead of nothing');
        foreach (["Rock 'n' Roll history", "Don\u{2019}t stop the music", 'Plain title'] as $t) {
            $slug = trim(Slug::fromTitle($t), '/');
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug, $t);
            $this->assertLessThanOrEqual(Slug::MAX_SLUG, strlen($slug));
        }
        $this->assertSame('/dont-stop-the-music/', Slug::fromTitle("Don\u{2019}t stop the music"));
    }

    public function testTitleTagsAndMenuNamesAreCutAtWholeWordsAndNeverEndInAFunctionWord(): void
    {
        $title = "Humanity's First Steps into Space: Laika, Gagarin and the Moon";
        $this->assertSame("Humanity's First Steps into Space: Laika, Gagarin", Slug::titleTag($title));
        $this->assertSame("Humanity's First Steps", Slug::menuName($title));
        $this->assertSame('Jak se peče chléb', Slug::menuName('Jak se peče chléb: recept pro začátečníky'), 'the part before a colon when it is short');
        $this->assertSame('Pekárna', Slug::menuName('Pekárna - naše příběhy ze Šumavy a okolí'));
        $this->assertSame('Home', Slug::menuName('Home'));
        $long = Slug::menuName(str_repeat('slovo ', 20));
        $this->assertLessThanOrEqual(30, mb_strlen($long));
        $this->assertStringEndsWith('slovo', $long);
    }
}
