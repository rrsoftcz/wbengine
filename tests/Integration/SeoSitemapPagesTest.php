<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Config;
use Wbengine\Content\ArticleVisibility;
use Wbengine\Db;
use Wbengine\Seo\Sitemap;

/** Which pages the sitemap lists: the ones a visitor can open by their link. */
class SeoSitemapPagesTest extends DbTestCase
{
    private mixed $previous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous = Config::$config;
        ArticleVisibility::reset();
    }

    protected function tearDown(): void
    {
        Config::$config = $this->previous;
        ArticleVisibility::reset();
    }

    private function page(string $link, int $visible = 1, int $acl = 0, int $strict = 0): int
    {
        return Db::insert('cms_sites', ['link' => $link, 'visible' => $visible, 'acl' => $acl, 'strict' => $strict, 'title' => $link]);
    }

    private function links(array $exclude = [], array $locales = ['en', 'cs']): array
    {
        return array_column(Sitemap::pages($exclude, $locales), null, 'link');
    }

    public function testOnlyVisiblePublicPagesAreListed(): void
    {
        $this->page('/public/');
        $this->page('/hidden/', 0);
        $this->page('/members/', 1, 1);

        $this->assertSame(['/public/'], array_keys($this->links()));
    }

    public function testAPageWithAHiddenArticleIsNotListedBecauseItAnswersWithA404(): void
    {
        // Hiding an article exists with API v2 only, and so does the 404 it causes.
        Config::$config = (object) ['apiV2' => (object) ['enabled' => true]];
        ArticleVisibility::reset();
        $shown = $this->page('/shown/');
        $hidden = $this->page('/hidden-article/');
        Db::insert('cms_articles', ['site_id' => $shown, 'title' => 'a', 'published' => 1730000000, 'hidden' => 0]);
        Db::insert('cms_articles', ['site_id' => $hidden, 'title' => 'b', 'published' => 1730000000, 'hidden' => 1]);

        $this->assertSame(['/shown/'], array_keys($this->links()));
    }

    public function testAStrictAndADynamicPageAreBothListedByTheirLinkOnly(): void
    {
        $this->page('/exact/', 1, 0, 1);
        $this->page('/addons/', 1, 0, 0);

        $this->assertSame(['/addons/', '/exact/'], array_keys($this->links()));
    }

    public function testExcludedLinksAndDuplicatesAreLeftOut(): void
    {
        $this->page('/login/');
        $this->page('/a/');
        $this->page('/a/');

        $this->assertSame(['/a/'], array_keys($this->links(['/login/'])));
    }

    public function testLastmodIsTheNewestArticleAndMissingWithoutOne(): void
    {
        $withArticle = $this->page('/blog/');
        $this->page('/plain/');
        Db::insert('cms_articles', ['site_id' => $withArticle, 'title' => 'old', 'published' => 1700000000, 'hidden' => 0]);
        Db::insert('cms_articles', ['site_id' => $withArticle, 'title' => 'draft', 'published' => 0, 'hidden' => 0]);

        $pages = $this->links();
        $this->assertSame(1700000000, $pages['/blog/']['lastmod']);
        $this->assertNull($pages['/plain/']['lastmod'], 'a page without an article has no date');
    }

    public function testALanguageIsListedWhenThePageIsTranslatedAndTheDefaultAlways(): void
    {
        $translated = $this->page('/translated/');
        $this->page('/untranslated/');
        $empty = $this->page('/empty-translation/');
        Db::insert('cms_site_translations', ['site_id' => $translated, 'locale' => 'cs', 'title' => 'Přeloženo']);
        Db::insert('cms_site_translations', ['site_id' => $empty, 'locale' => 'cs', 'title' => '']);

        $pages = $this->links();
        $this->assertSame(['en', 'cs'], $pages['/translated/']['locales']);
        $this->assertSame(['en'], $pages['/untranslated/']['locales']);
        $this->assertSame(['en'], $pages['/empty-translation/']['locales'], 'an empty translation does not make a language version');
    }
}
