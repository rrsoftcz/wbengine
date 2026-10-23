<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Box\Article\ArticleModel;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\Cache\NullCache;
use Wbengine\Db;
use Wbengine\I18n;
use Wbengine\I18n\ContentTranslations;
use Wbengine\I18n\Translator;
use Wbengine\Site;
use Wbengine\Site\SiteModel;

/**
 * Translated content through the real models: the site lookup, the menu and the article box.
 */
class ContentReadPathTest extends DbTestCase
{
    private string $dir = '';
    private int $site;
    private int $article;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locale('en');
        $this->site = Db::insert('cms_sites', ['link' => '/blog/', 'title' => 'Blog', 'html_title' => 'The Blog', 'html_keywords' => 'blog', 'visible' => 1, 'strict' => 0]);
        Db::insert('cms_sites', ['link' => '/about/', 'title' => 'About us', 'html_title' => 'Welcome', 'visible' => 1, 'strict' => 0]);
        Db::insert('cms_menu', ['site_id' => $this->site, 'name' => 'Blog', 'description' => 'Read it', 'type' => 1, 'order' => 1, 'visible' => 1]);
        Db::insert('cms_menu', ['site_id' => $this->site, 'name' => 'About', 'type' => 1, 'order' => 2, 'visible' => 1]);
        $this->article = Db::insert('cms_articles', ['site_id' => $this->site, 'title' => 'Hello', 'introtext' => '<p>Intro</p>', 'bodytext' => '<p>Body</p>']);

        $menu = Db::fetchAllAssoc('SELECT menu_id FROM cms_menu ORDER BY `order`');
        ContentTranslations::set('site', $this->site, 'cs', ['title' => 'Blog česky', 'html_title' => 'Blog | titulek']);
        ContentTranslations::set('menu', (int) $menu[0]['menu_id'], 'cs', ['name' => 'Blog česky', 'description' => 'Čtěte to']);
        ContentTranslations::set('menu', (int) $menu[1]['menu_id'], 'cs', ['name' => 'O nás']);
        ContentTranslations::set('article', $this->article, 'cs', ['title' => 'Ahoj', 'bodytext' => '<p>Tělo</p>']);
    }

    protected function tearDown(): void
    {
        I18n::configure(null, null);
        Cache::setDriver(null);
        if ($this->dir !== '') {
            foreach (glob($this->dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->dir);
        }
        parent::tearDown();
    }

    private function locale(string $locale): void
    {
        I18n::configure(
            (new Translator([], 'en'))->setLocale($locale),
            ['enabled' => true, 'default' => 'en', 'locales' => ['en', 'cs'], 'urlPrefix' => true, 'prefixDefault' => false]
        );
    }

    private function cache(bool $on): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-crp-' . bin2hex(random_bytes(4));
        Cache::setDriver($on ? new FileCache($this->dir) : new NullCache(), 60);
    }

    private function siteStub(string $url): Site
    {
        $site = $this->createStub(Site::class);
        $site->method('getUrl')->willReturn($url);
        $site->method('isUrlStrict')->willReturn(false);
        $site->method('getSiteParentId')->willReturn(1);
        return $site;
    }

    // ------------------------------------------------------------ site lookup

    public function testTheSiteLookupIsTranslatedWithAndWithoutTheCache(): void
    {
        foreach ([false, true] as $cached) {
            $this->cache($cached);
            $this->locale('cs');

            $row = (new SiteModel())->loadSiteData($this->siteStub('/blog/'));

            $this->assertSame('Blog česky', $row['title'], $cached ? 'cached path' : 'SQL path');
            $this->assertSame('Blog | titulek', $row['html_title']);
            $this->assertSame('blog', $row['html_keywords'], 'untranslated field: default');
            $this->assertSame('/blog/', $row['link']);
        }
    }

    public function testTheDefaultLanguageSiteLookupIsUntouched(): void
    {
        $this->cache(true);
        $this->locale('en');

        $row = (new SiteModel())->loadSiteData($this->siteStub('/blog/'));

        $this->assertSame('Blog', $row['title']);
        $this->assertSame('The Blog', $row['html_title']);
    }

    public function testASiteWithoutTranslationsShowsTheDefault(): void
    {
        $this->cache(true);
        $this->locale('cs');

        $row = (new SiteModel())->loadSiteData($this->siteStub('/about/'));

        $this->assertSame('About us', $row['title']);
    }

    public function testTheCachedSiteTableDoesNotMixLanguages(): void
    {
        $this->cache(true);
        $this->locale('cs');
        (new SiteModel())->loadSiteData($this->siteStub('/blog/'));
        $this->locale('en');

        $this->assertSame('Blog', (new SiteModel())->loadSiteData($this->siteStub('/blog/'))['title'], 'the Czech request did not poison the English one');
    }

    // ------------------------------------------------------------------- menu

    public function testTheMenuIsTranslatedWithAndWithoutTheCache(): void
    {
        foreach ([false, true] as $cached) {
            $this->cache($cached);
            $this->locale('cs');

            $menu = (new SiteModel())->getSiteMenu($this->siteStub('/blog/'));

            $this->assertSame(['Blog česky', 'O nás'], array_column($menu, 'name'), $cached ? 'cached' : 'SQL');
            $this->assertSame('Čtěte to', $menu[0]['description']);
            $this->assertSame('/blog/', $menu[0]['link'], 'the link is not translated');
        }
    }

    public function testTheMenuKeepsTheDefaultLanguageInTheCacheAndTranslatesPerRequest(): void
    {
        $this->cache(true);
        $this->locale('cs');
        (new SiteModel())->getSiteMenu($this->siteStub('/blog/'));
        $this->locale('en');

        $menu = (new SiteModel())->getSiteMenu($this->siteStub('/blog/'));

        $this->assertSame(['Blog', 'About'], array_column($menu, 'name'));
        $this->assertSame('Read it', $menu[0]['description']);
    }

    // ---------------------------------------------------------------- article

    private function articleModel(): ArticleModel
    {
        $site = new class($this->site) {
            public function __construct(private int $id) {}
            public function getSiteid() { return $this->id; }
        };
        $parent = new class($site) {
            public function __construct(private $site) {}
            public function getSite() { return $this->site; }
        };
        $box = new class($parent) {
            public function __construct(private $parent) {}
            public function getParent() { return $this->parent; }
        };
        return new ArticleModel($box);
    }

    public function testTheArticleIsTranslatedFieldByField(): void
    {
        $this->cache(false);
        $this->locale('cs');

        $article = $this->articleModel()->getArticleRow();

        $this->assertSame('Ahoj', $article->title);
        $this->assertSame('<p>Tělo</p>', $article->bodytext);
        $this->assertSame('<p>Intro</p>', $article->introtext, 'untranslated: default');
        $this->assertSame($this->article, (int) $article->id);
    }

    public function testTheDefaultLanguageArticleIsUntouched(): void
    {
        $this->cache(false);
        $this->locale('en');

        $article = $this->articleModel()->getArticleRow();

        $this->assertSame('Hello', $article->title);
        $this->assertSame('<p>Body</p>', $article->bodytext);
    }

    public function testCountingAViewStillWorksOnATranslatedArticle(): void
    {
        $this->cache(false);
        $this->locale('cs');

        $this->articleModel()->getArticleRow(true);

        $this->assertSame(1, (int) Db::fetchAssoc('SELECT views FROM cms_articles WHERE id = ?', [$this->article])['views']);
    }

    public function testNoExtraQueryInTheDefaultLanguage(): void
    {
        $this->cache(false);
        $this->locale('en');
        $model = $this->articleModel();
        $before = Db::getQueriesCount();

        $model->getArticleRow();

        $this->assertSame(1, Db::getQueriesCount() - $before, 'just the article query');

        $this->locale('cs');
        $before = Db::getQueriesCount();
        $model->getArticleRow();
        $this->assertSame(2, Db::getQueriesCount() - $before, 'a translated request costs one more query');
    }
}
