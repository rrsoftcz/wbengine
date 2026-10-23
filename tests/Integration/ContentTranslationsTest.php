<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\Db;
use Wbengine\I18n;
use Wbengine\I18n\ContentTranslations;
use Wbengine\I18n\Translator;
use Wbengine\I18n\TranslationException;

class ContentTranslationsTest extends DbTestCase
{
    private string $cacheDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->locale('cs');
        $this->site = Db::insert('cms_sites', ['link' => '/blog/', 'title' => 'Blog', 'html_title' => 'The Blog', 'html_description' => 'About blogs', 'html_keywords' => 'blog,news', 'visible' => 1]);
        $this->menu = Db::insert('cms_menu', ['site_id' => $this->site, 'name' => 'Blog', 'description' => 'Read the blog', 'type' => 1]);
        $this->article = Db::insert('cms_articles', ['site_id' => $this->site, 'title' => 'Hello', 'introtext' => '<p>Intro</p>', 'bodytext' => '<p>Body</p>']);
    }

    protected function tearDown(): void
    {
        I18n::configure(null, null);
        Cache::setDriver(null);
        if ($this->cacheDir !== '') {
            foreach (glob($this->cacheDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->cacheDir);
        }
        parent::tearDown();
    }

    private int $site;
    private int $menu;
    private int $article;

    private function locale(string $locale, array $locales = ['en', 'cs', 'pt_BR', 'pt']): void
    {
        I18n::configure(
            (new Translator([], 'en'))->setLocale($locale),
            ['enabled' => true, 'default' => 'en', 'locales' => $locales, 'urlPrefix' => true, 'prefixDefault' => false]
        );
    }

    private function translate(string $entity, int $id, string $locale, array $fields): void
    {
        ContentTranslations::set($entity, $id, $locale, $fields);
    }

    private function siteRow(): array
    {
        return Db::fetchAssoc('SELECT * FROM cms_sites WHERE site_id = ?', [$this->site]);
    }

    // -------------------------------------------------------------- overlay

    public function testTheDefaultLanguageAndADisabledI18nCostNothing(): void
    {
        $this->translate('site', $this->site, 'cs', ['title' => 'Blog cs']);
        $row = $this->siteRow();

        $this->locale('en');
        $before = Db::getQueriesCount();
        $this->assertSame($row, ContentTranslations::overlayRow('site', $row));
        $this->assertSame($before, Db::getQueriesCount(), 'the default language needs no query');

        I18n::configure(null, null);
        $this->assertSame($row, ContentTranslations::overlayRow('site', $row), 'i18n off: untouched');
    }

    public function testASiteRowIsTranslatedFieldByField(): void
    {
        $this->translate('site', $this->site, 'cs', ['title' => 'Blog česky', 'html_title' => 'Blog | Titulek', 'html_keywords' => null, 'html_description' => '']);

        $row = ContentTranslations::overlayRow('site', $this->siteRow());

        $this->assertSame('Blog česky', $row['title']);
        $this->assertSame('Blog | Titulek', $row['html_title']);
        $this->assertSame('blog,news', $row['html_keywords'], 'NULL falls back to the default language');
        $this->assertSame('About blogs', $row['html_description'], 'an empty text falls back too');
        $this->assertSame('/blog/', $row['link'], 'the URL is not translated');
        $this->assertSame($this->site, (int) $row['site_id']);
    }

    public function testAnEmptyTextEnteredDirectlyInSqlAlsoFallsBack(): void
    {
        // set() stores "" as NULL, but somebody editing the table by hand can leave ""
        Db::query("INSERT INTO cms_site_translations (site_id, locale, title, html_title) VALUES (?, 'cs', 'Blog česky', '')", [$this->site]);

        $row = ContentTranslations::overlayRow('site', $this->siteRow());

        $this->assertSame('Blog česky', $row['title']);
        $this->assertSame('The Blog', $row['html_title'], 'an empty text is not a translation');
    }

    public function testAnItemWithoutATranslationIsUnchanged(): void
    {
        $other = Db::insert('cms_sites', ['link' => '/about/', 'title' => 'About', 'visible' => 1]);
        $this->translate('site', $this->site, 'cs', ['title' => 'Blog česky']);

        $row = ContentTranslations::overlayRow('site', Db::fetchAssoc('SELECT * FROM cms_sites WHERE site_id = ?', [$other]));

        $this->assertSame('About', $row['title']);
    }

    public function testObjectRowsKeepTheirTypeAndRows(): void
    {
        $this->translate('article', $this->article, 'cs', ['title' => 'Ahoj', 'bodytext' => '<p>Tělo</p>']);
        $row = Db::fetchObject('SELECT * FROM cms_articles WHERE id = ?', [$this->article]);

        $translated = ContentTranslations::overlayRow('article', $row);

        $this->assertIsObject($translated);
        $this->assertSame('Ahoj', $translated->title);
        $this->assertSame('<p>Tělo</p>', $translated->bodytext);
        $this->assertSame('<p>Intro</p>', $translated->introtext, 'untranslated field: default');
        $this->assertSame($this->article, (int) $translated->id);
    }

    public function testAListIsTranslatedInPlaceKeepingItsOrder(): void
    {
        $second = Db::insert('cms_menu', ['site_id' => $this->site, 'name' => 'About', 'type' => 1]);
        $this->translate('menu', $this->menu, 'cs', ['name' => 'Blog česky']);
        $this->translate('menu', $second, 'cs', ['name' => 'O nás', 'description' => 'Kdo jsme']);
        $rows = Db::fetchAllAssoc('SELECT * FROM cms_menu ORDER BY menu_id');

        $out = ContentTranslations::overlayRows('menu', $rows);

        $this->assertSame(['Blog česky', 'O nás'], array_column($out, 'name'));
        $this->assertSame(['Read the blog', 'Kdo jsme'], array_column($out, 'description'));
    }

    public function testTheLocaleChainPrefersTheExactLocaleThenTheLanguage(): void
    {
        $this->translate('site', $this->site, 'pt', ['title' => 'Blog pt', 'html_title' => 'Titulo pt']);
        $this->translate('site', $this->site, 'pt_BR', ['title' => 'Blog pt-BR']);

        $this->locale('pt_BR');
        $row = ContentTranslations::overlayRow('site', $this->siteRow());

        $this->assertSame('Blog pt-BR', $row['title'], 'the exact locale first');
        $this->assertSame('Titulo pt', $row['html_title'], 'a field it lacks comes from the language');
        $this->assertSame('blog,news', $row['html_keywords'], 'then the default');
    }

    public function testAnotherLocalesTranslationIsNeverUsed(): void
    {
        $this->translate('site', $this->site, 'cs', ['title' => 'Blog česky']);

        $this->locale('pt');
        $this->assertSame('Blog', ContentTranslations::overlayRow('site', $this->siteRow())['title']);
    }

    public function testOddInputIsLeftAlone(): void
    {
        $this->assertNull(ContentTranslations::overlayRow('site', null));
        $this->assertFalse(ContentTranslations::overlayRow('site', false));
        $this->assertSame([], ContentTranslations::overlayRows('site', []));
        $this->assertSame([['x' => 1]], ContentTranslations::overlayRows('site', [['x' => 1]]), 'a row without the id');
        $this->assertSame([['site_id' => 'abc']], ContentTranslations::overlayRows('site', [['site_id' => 'abc']]));
        $this->assertSame([['site_id' => 1]], ContentTranslations::overlayRows('nonsense', [['site_id' => 1]]));
    }

    public function testHostileTextRoundTripsUnchanged(): void
    {
        $text = "O'Brien \"q\" <script>alert(1)</script> \\ ; DROP TABLE x; -- \u{1F600} čeština";
        $this->translate('article', $this->article, 'cs', ['title' => $text]);

        $row = ContentTranslations::overlayRow('article', Db::fetchObject('SELECT * FROM cms_articles WHERE id = ?', [$this->article]));

        $this->assertSame($text, $row->title, 'stored and returned as is: escaping is the template engine\'s job');
        $this->assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_articles')['c']);
    }

    public function testMissingTablesDegradeToTheDefaultLanguage(): void
    {
        $row = $this->siteRow();
        Db::query('RENAME TABLE cms_site_translations TO cms_site_translations_off');
        $log = ini_set('error_log', '/dev/null');
        try {
            $this->assertSame($row, ContentTranslations::overlayRow('site', $row), 'the site keeps working');
        } finally {
            ini_set('error_log', (string) $log);
            Db::query('RENAME TABLE cms_site_translations_off TO cms_site_translations');
        }
    }

    // ---------------------------------------------------------------- cache

    private function useCache(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/wb-ct-' . bin2hex(random_bytes(4));
        Cache::setDriver(new FileCache($this->cacheDir), 60);
    }

    public function testSmallTablesAreCachedPerLocaleAndWritesFlushThem(): void
    {
        $this->translate('menu', $this->menu, 'cs', ['name' => 'Blog česky']);
        $this->useCache();
        $rows = Db::fetchAllAssoc('SELECT * FROM cms_menu');

        $this->assertSame('Blog česky', ContentTranslations::overlayRows('menu', $rows)[0]['name']);
        $before = Db::getQueriesCount();
        $this->assertSame('Blog česky', ContentTranslations::overlayRows('menu', $rows)[0]['name']);
        $this->assertSame($before, Db::getQueriesCount(), 'the second call is served from the cache');

        // A direct edit is not seen until the cache is flushed, a write through the class flushes.
        Db::query("UPDATE cms_menu_translations SET name = 'Změněno'");
        $this->assertSame('Blog česky', ContentTranslations::overlayRows('menu', $rows)[0]['name']);
        $this->translate('menu', $this->menu, 'cs', ['description' => 'x']);
        $this->assertSame('Změněno', ContentTranslations::overlayRows('menu', $rows)[0]['name']);
    }

    public function testEachLocaleHasItsOwnCacheEntry(): void
    {
        $this->translate('menu', $this->menu, 'cs', ['name' => 'Blog česky']);
        $this->translate('menu', $this->menu, 'pt', ['name' => 'Blog pt']);
        $this->useCache();
        $rows = Db::fetchAllAssoc('SELECT * FROM cms_menu');

        $this->assertSame('Blog česky', ContentTranslations::overlayRows('menu', $rows)[0]['name']);
        $this->locale('pt');
        $this->assertSame('Blog pt', ContentTranslations::overlayRows('menu', $rows)[0]['name'], 'not the Czech copy');
    }

    public function testArticlesAreNotCachedWhole(): void
    {
        $this->translate('article', $this->article, 'cs', ['title' => 'Ahoj']);
        $this->useCache();

        ContentTranslations::overlayRow('article', Db::fetchObject('SELECT * FROM cms_articles'));

        $this->assertSame([], glob($this->cacheDir . '/*.cache.php'), 'bodies can be large, the page cache covers articles');
    }

    // ---------------------------------------------------------------- writing

    public function testSetCreatesThenUpdatesOnlyTheGivenFields(): void
    {
        $first = ContentTranslations::set('article', $this->article, 'cs', ['title' => 'Ahoj', 'introtext' => 'Úvod']);
        $this->assertSame(['title' => 'Ahoj', 'introtext' => 'Úvod', 'bodytext' => null], $first);

        $second = ContentTranslations::set('article', $this->article, 'cs', ['bodytext' => 'Tělo']);
        $this->assertSame(['title' => 'Ahoj', 'introtext' => 'Úvod', 'bodytext' => 'Tělo'], $second, 'other fields kept');

        $this->assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_article_translations')['c'], 'one row, not two');
    }

    public function testNullOrEmptyClearsAFieldBackToTheDefault(): void
    {
        ContentTranslations::set('article', $this->article, 'cs', ['title' => 'Ahoj']);

        ContentTranslations::set('article', $this->article, 'cs', ['title' => '']);

        $this->assertNull(ContentTranslations::all('article', $this->article)['cs']['title']);
        $this->assertSame('Hello', ContentTranslations::overlayRow('article', Db::fetchObject('SELECT * FROM cms_articles'))->title);
    }

    public function testSetNormalizesTheLocaleToTheSupportedSpelling(): void
    {
        ContentTranslations::set('site', $this->site, 'CS-cz', ['title' => 'Blog česky']);

        $this->assertSame(['cs'], array_keys(ContentTranslations::all('site', $this->site)));
    }

    public function testAllListsEveryLocale(): void
    {
        ContentTranslations::set('menu', $this->menu, 'pt', ['name' => 'a']);
        ContentTranslations::set('menu', $this->menu, 'cs', ['name' => 'b']);

        $this->assertSame(['cs', 'pt'], array_keys(ContentTranslations::all('menu', $this->menu)));
        $this->assertSame([], ContentTranslations::all('site', $this->site));
    }

    public function testDelete(): void
    {
        ContentTranslations::set('site', $this->site, 'cs', ['title' => 'x']);

        $this->assertTrue(ContentTranslations::delete('site', $this->site, 'cs'));
        $this->assertFalse(ContentTranslations::delete('site', $this->site, 'cs'), 'nothing left to delete');
        $this->assertSame([], ContentTranslations::all('site', $this->site));
    }

    // ------------------------------------------------------------- validation

    #[DataProvider('badWrites')]
    public function testBadWritesAreRefusedAndStoreNothing(string $entity, $id, $locale, array $fields, int $code): void
    {
        $id = (is_string($id) && str_starts_with($id, '@')) ? $this->{substr($id, 1)} : $id;
        try {
            ContentTranslations::set($entity, $id, $locale, $fields);
            $this->fail('Expected a TranslationException');
        } catch (TranslationException $e) {
            $this->assertSame($code, $e->getCode(), $e->getMessage());
        }
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_article_translations')['c']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_site_translations')['c']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_menu_translations')['c']);
    }

    public static function badWrites(): array
    {
        $ok = ['title' => 'x'];
        return [
            'unknown entity'        => ['users', '@article', 'cs', $ok, 404],
            'entity injection'      => ["article`; DROP TABLE cms_users; --", '@article', 'cs', $ok, 404],
            'unknown item'          => ['article', 99999, 'cs', $ok, 404],
            'id zero'               => ['article', 0, 'cs', $ok, 400],
            'id negative'           => ['article', -1, 'cs', $ok, 400],
            'id text'               => ['article', 'abc', 'cs', $ok, 400],
            'id injection'          => ['article', '1 OR 1=1', 'cs', $ok, 400],
            'id float'              => ['article', '1.5', 'cs', $ok, 400],
            'unsupported locale'    => ['article', '@article', 'de', $ok, 400],
            'default locale'        => ['article', '@article', 'en', $ok, 400],
            'locale traversal'      => ['article', '@article', '../../x', $ok, 400],
            'locale injection'      => ['article', '@article', "cs'; DROP TABLE x; --", $ok, 400],
            'locale null byte'      => ['article', '@article', "cs\0", $ok, 400],
            'locale array'          => ['article', '@article', ['cs'], $ok, 400],
            'no fields'             => ['article', '@article', 'cs', [], 400],
            'unknown field'         => ['article', '@article', 'cs', ['views' => 'x'], 400],
            'privileged column'     => ['article', '@article', 'cs', ['site_id' => '5'], 400],
            'column injection'      => ['article', '@article', 'cs', ['title` = 1 --' => 'x'], 400],
            'numeric key'           => ['article', '@article', 'cs', [0 => 'x'], 400],
            'array value'           => ['article', '@article', 'cs', ['title' => ['x']], 400],
            'number value'          => ['article', '@article', 'cs', ['title' => 5], 400],
            'too long'              => ['article', '@article', 'cs', ['title' => str_repeat('x', 256)], 400],
            'site field on menu'    => ['menu', '@menu', 'cs', ['title' => 'x'], 400],
        ];
    }

    public function testTheLengthLimitIsInCharactersNotBytes(): void
    {
        $title = str_repeat("\u{010D}", 255);   // 255 characters, 510 bytes

        $stored = ContentTranslations::set('article', $this->article, 'cs', ['title' => $title]);

        $this->assertSame($title, $stored['title']);
    }

    public function testReadingAMissingItemIs404(): void
    {
        $this->expectException(TranslationException::class);
        $this->expectExceptionCode(404);
        ContentTranslations::all('article', 99999);
    }

    public function testEntitiesAreListed(): void
    {
        $this->assertSame(['site', 'menu', 'article'], ContentTranslations::entities());
    }
}
