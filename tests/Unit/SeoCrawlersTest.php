<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wbengine\Config;
use Wbengine\Seo\Robots;
use Wbengine\Seo\Sitemap;

final class SeoCrawlersTest extends TestCase
{
    private mixed $previous;

    protected function setUp(): void { $this->previous = Config::$config; }
    protected function tearDown(): void { Config::$config = $this->previous; }

    private static function url(string $link, string $locale): string
    {
        return 'en' === $locale ? $link : '/' . str_replace('_', '-', $locale) . ('/' === $link ? '/' : $link);
    }

    public function testSettingsDefaultToIndexingWithoutRestrictions(): void
    {
        Config::$config = (object) [];
        self::assertSame(['allowIndexing' => true, 'disallow' => [], 'sitemapExclude' => []], Config::getSeoSettings());
        Config::$config = (object) ['seo' => (object) ['allowIndexing' => false, 'disallow' => ['/login/'], 'sitemapExclude' => ['/login/', '/x/']]];
        self::assertSame(['allowIndexing' => false, 'disallow' => ['/login/'], 'sitemapExclude' => ['/login/', '/x/']], Config::getSeoSettings());
    }

    #[DataProvider('invalid')]
    public function testInvalidSettingsAreRefusedWithTheKeyName(array $seo, string $key): void
    {
        Config::$config = (object) ['seo' => $seo];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($key);
        Config::getSeoSettings();
    }

    public static function invalid(): array
    {
        return [
            'flag not boolean' => [['allowIndexing' => 'no'], 'seo.allowIndexing'],
            'disallow not a list' => [['disallow' => '/a/'], 'seo.disallow'],
            'disallow without slash' => [['disallow' => ['login']], 'seo.disallow'],
            'exclude with a space' => [['sitemapExclude' => ['/a b/']], 'seo.sitemapExclude'],
            'exclude not a string' => [['sitemapExclude' => [5]], 'seo.sitemapExclude'],
        ];
    }

    public function testRobotsNamesTheSitemapAndListsTheDisallowedPaths(): void
    {
        self::assertSame("User-agent: *\nDisallow:\n\nSitemap: https://example.com/sitemap.xml\n", Robots::text(['allowIndexing' => true, 'disallow' => []], 'https://example.com'));
        self::assertSame("User-agent: *\nDisallow: /login/\nDisallow: /a/\n\nSitemap: https://example.com/sitemap.xml\n", Robots::text(['allowIndexing' => true, 'disallow' => ['/login/', '/a/']], 'https://example.com'));
    }

    public function testRobotsWithoutAnAddressHasNoSitemapLineAndClosedIndexingBlocksEverything(): void
    {
        self::assertSame("User-agent: *\nDisallow:\n", Robots::text(['allowIndexing' => true, 'disallow' => []], ''));
        self::assertSame("User-agent: *\nDisallow: /\n", Robots::text(['allowIndexing' => false, 'disallow' => ['/a/']], 'https://example.com'));
    }

    public function testSitemapListsEveryLanguageWithAlternatesAndXDefault(): void
    {
        $xml = Sitemap::xml([['link' => '/blog/', 'lastmod' => 1730000000, 'locales' => ['en', 'cs']]], 'https://example.com', self::url(...));
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($xml), 'well-formed XML');
        self::assertSame(2, $doc->getElementsByTagName('url')->length);
        self::assertSame(['https://example.com/blog/', 'https://example.com/cs/blog/'], array_map(static fn($n) => $n->textContent, iterator_to_array($doc->getElementsByTagName('loc'))));
        self::assertStringContainsString('<lastmod>2024-10-27</lastmod>', $xml);
        self::assertStringContainsString('hreflang="cs" href="https://example.com/cs/blog/"', $xml);
        self::assertStringContainsString('hreflang="x-default" href="https://example.com/blog/"', $xml);
    }

    public function testASingleLanguagePageHasNoAlternatesAndNoInventedDate(): void
    {
        $xml = Sitemap::xml([['link' => '/', 'lastmod' => null, 'locales' => ['en']]], 'https://example.com', self::url(...));
        self::assertStringNotContainsString('xhtml:link', $xml);
        self::assertStringNotContainsString('lastmod', $xml);
        self::assertStringNotContainsString('changefreq', $xml);
        self::assertStringContainsString('<loc>https://example.com/</loc>', $xml);
    }

    public function testSpecialCharactersAreEscaped(): void
    {
        $xml = Sitemap::xml([['link' => '/a&b/<x>/', 'lastmod' => null, 'locales' => ['en']]], 'https://example.com', self::url(...));
        self::assertStringContainsString('/a&amp;b/&lt;x&gt;/', $xml);
        self::assertTrue((new \DOMDocument())->loadXML($xml));
    }

    public function testTheListStopsAtTheProtocolLimit(): void
    {
        $pages = [];
        for ($i = 0; $i < Sitemap::MAX_URLS + 5; $i++) { $pages[] = ['link' => '/p' . $i . '/', 'lastmod' => null, 'locales' => ['en']]; }
        self::assertSame(Sitemap::MAX_URLS, substr_count(Sitemap::xml($pages, 'https://e.com', self::url(...)), '<url>'));
    }
}
