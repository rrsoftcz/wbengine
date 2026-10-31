<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Api;
use Wbengine\Api\V2;
use Wbengine\Api\V2\BoxRegistry;
use Wbengine\Api\V2\ContentModel;
use Wbengine\Api\V2\Model;
use Wbengine\Api\V2\Problem;
use Wbengine\Box\Article\ArticleModel;
use Wbengine\Config;
use Wbengine\Content\ArticleVisibility;
use Wbengine\Db;
use Wbengine\RateLimit\Limiter;
use Wbengine\Site;
use Wbengine\Site\SiteModel;

/** Hiding a published article: API, 404 of its page, menu, readers, drafts and the schema-absent fallback. */
final class ApiV2ArticleHideTest extends TestCase
{
    private array $tables = [];
    private ContentModel $content;
    private array $actor;
    private int $flushes = 0;
    private mixed $previousConfig;
    private string $dir;

    protected function setUp(): void
    {
        try { Db::query('SELECT 1'); } catch (\Throwable $e) { $this->markTestSkipped('MySQL unavailable.'); }
        $this->previousConfig = Config::$config ?? null;
        Config::$config = (object) ['apiV2' => (object) ['enabled' => true]];
        ArticleVisibility::reset();
        $sql = file_get_contents(dirname(__DIR__, 2) . '/tests/schema.sql');
        $sql .= file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/002-api-v2-integrations.sql');
        $sql .= file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/005-api-content.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $sql);
        foreach (explode(';', $sql) as $statement) {
            if ('' === trim($statement)) { continue; }
            if (preg_match('/CREATE TEMPORARY TABLE\s+`?([a-z0-9_]+)/i', $statement, $match)) {
                if (in_array($match[1], $this->tables, true)) { continue; }
                $this->tables[] = $match[1];
            }
            Db::query($statement);
        }
        Db::query("ALTER TABLE cms_articles ADD content_format VARCHAR(20) NOT NULL DEFAULT 'legacy_html'");
        Db::query("ALTER TABLE cms_article_translations ADD content_format VARCHAR(20) NOT NULL DEFAULT 'legacy_html'");
        Db::insert('cms_users', ['user_id' => 11, 'username' => 'admin', 'role' => 'admin']);
        $issued = (new Model())->provision(11, 'Hide test', Model::SCOPES);
        $this->actor = ['integration_id' => $issued['integration_id'], 'central_actor' => 'mcp:test'];
        $this->content = new ContentModel(new BoxRegistry(), function (): void { ++$this->flushes; });
        $this->dir = sys_get_temp_dir() . '/wb-hide-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        Config::$config = $this->previousConfig;
        ArticleVisibility::reset();
        foreach (array_reverse($this->tables) as $table) { Db::query('DROP TEMPORARY TABLE IF EXISTS `' . $table . '`'); }
        foreach (glob($this->dir . '/*') ?: [] as $file) { @unlink($file); }
        @rmdir($this->dir);
    }

    /** A published article on a visible page with a menu item. */
    private function publishedArticle(string $link = '/post/', string $title = 'First post'): array
    {
        $page = $this->content->create('pages', ['link' => $link, 'title' => $title, 'visible' => 1], $this->actor)['data'];
        Db::insert('cms_menu', ['site_id' => $page['site_id'], 'type' => 1, 'name' => $title, 'visible' => 1, 'order' => 1, 'parent' => 0]);
        $draft = $this->content->create('articles', ['title' => $title, 'introtext' => 'Intro', 'bodytext' => "Body\n", 'content_format' => 'markdown', 'site_id' => $page['site_id']], $this->actor);
        $published = $this->content->publish($draft['data']['id'], $draft['etag'], $this->actor);
        return ['id' => $published['data']['id'], 'page' => (int) $page['site_id'], 'etag' => $published['etag']];
    }

    private function site(string $url): Site
    {
        return new class($url) extends Site {
            public function __construct(private string $fixtureUrl) {}
            public function getUrl() { return $this->fixtureUrl; }
            public function isUrlStrict() { return true; }
        };
    }

    private function found(string $url): bool
    {
        return is_array((new SiteModel())->loadSiteData($this->site($url)));
    }

    public function testHidingReturns404ForThePageRemovesTheMenuItemAndShowingRestoresBoth(): void
    {
        $article = $this->publishedArticle();
        self::assertTrue($this->found('/post/'));
        self::assertCount(1, (new SiteModel())->getSiteMenu($this->menuSite()));
        $flushes = $this->flushes;
        $hidden = $this->content->setArticleHidden($article['id'], true, $article['etag'], $this->actor);
        self::assertTrue($hidden['data']['hidden']);
        self::assertSame($flushes + 1, $this->flushes, 'the caches are flushed once');
        self::assertFalse($this->found('/post/'), 'the page is not found, the visitor gets the 404');
        self::assertCount(0, (new SiteModel())->getSiteMenu($this->menuSite()));
        $shown = $this->content->setArticleHidden($article['id'], false, $hidden['etag'], $this->actor);
        self::assertFalse($shown['data']['hidden']);
        self::assertTrue($this->found('/post/'));
        self::assertCount(1, (new SiteModel())->getSiteMenu($this->menuSite()));
        $actions = array_column(Db::fetchAllAssoc("SELECT action, central_actor FROM cms_api_changes WHERE action IN ('hide', 'unhide') ORDER BY change_id"), 'action');
        self::assertSame(['hide', 'unhide'], $actions);
        self::assertSame('mcp:test', Db::fetchAssoc("SELECT central_actor FROM cms_api_changes WHERE action = 'hide'")['central_actor']);
    }

    private function menuSite(): Site
    {
        return new class extends Site {
            public function __construct() {}
            public function getSiteParentId() { return 1; }
        };
    }

    public function testHidingTwiceChangesNothingAndFlushesNothing(): void
    {
        $article = $this->publishedArticle();
        $first = $this->content->setArticleHidden($article['id'], true, $article['etag'], $this->actor);
        $flushes = $this->flushes;
        $again = $this->content->setArticleHidden($article['id'], true, $first['etag'], $this->actor);
        self::assertSame($first['etag'], $again['etag']);
        self::assertSame($flushes, $this->flushes);
        self::assertSame(1, (int) Db::fetchAssoc("SELECT COUNT(*) AS n FROM cms_api_changes WHERE action = 'hide'")['n']);
    }

    public function testAStaleOrMissingEtagIsRefusedAndNothingChanges(): void
    {
        $article = $this->publishedArticle();
        foreach ([[null, 428], ['"' . str_repeat('0', 64) . '"', 412]] as [$etag, $status]) {
            try {
                $this->content->setArticleHidden($article['id'], true, $etag, $this->actor);
                self::fail('The change was accepted without a valid ETag.');
            } catch (Problem $e) {
                self::assertSame($status, $e->getCode());
            }
        }
        self::assertTrue($this->found('/post/'));
    }

    public function testADraftWrittenBeforeHidingCanStillBePublishedAndTheArticleStaysHidden(): void
    {
        $article = $this->publishedArticle();
        $draft = $this->content->update('articles', $article['id'], ['bodytext' => "Edited body\n"], $article['etag'], $this->actor);
        $hidden = $this->content->setArticleHidden($article['id'], true, $draft['etag'], $this->actor);
        $published = $this->content->publish($article['id'], $hidden['etag'], $this->actor);
        self::assertSame("Edited body\n", $published['data']['bodytext']);
        self::assertTrue($published['data']['hidden'], 'publishing does not show a hidden article');
        self::assertFalse($this->found('/post/'));
    }

    public function testHiddenFilterSearchAndDraftFilterOfTheArticleList(): void
    {
        $one = $this->publishedArticle('/one/', 'Spring offer');
        $this->publishedArticle('/two/', 'Autumn news');
        $this->content->setArticleHidden($one['id'], true, $one['etag'], $this->actor);
        $titles = fn (array $query) => array_column($this->content->collection('articles', $query)['data'], 'title');
        self::assertSame(['Spring offer'], $titles(['hidden' => '1']));
        self::assertSame(['Autumn news'], $titles(['hidden' => '0']));
        self::assertSame(['Spring offer'], $titles(['search' => 'spring']));
        self::assertSame([], $titles(['search' => '100%']));
        $this->content->create('articles', ['title' => 'Only a draft', 'introtext' => 'x', 'bodytext' => 'y', 'content_format' => 'markdown'], $this->actor);
        self::assertSame(['Only a draft'], array_column($this->content->collection('articles', ['has_draft' => '1'])['data'], 'draft_title'));
        self::assertCount(2, $titles(['has_draft' => '0']));
        $this->expectException(Problem::class);
        $this->content->collection('articles', ['hidden' => 'yes']);
    }

    public function testBeforeTheMigrationTheArticlesWorkAsBeforeAndHidingIsUnavailable(): void
    {
        $article = $this->publishedArticle();
        Db::query('ALTER TABLE cms_articles DROP COLUMN hidden');
        ArticleVisibility::reset();
        self::assertFalse(ArticleVisibility::isSupported());
        self::assertTrue($this->found('/post/'));
        self::assertCount(1, (new SiteModel())->getSiteMenu($this->menuSite()));
        $row = $this->content->read('articles', $article['id']);
        self::assertArrayNotHasKey('hidden', $row['data']);
        try {
            $this->content->setArticleHidden($article['id'], true, $row['etag'], $this->actor);
            self::fail('Hiding worked without the column.');
        } catch (Problem $e) {
            self::assertSame(['hide_unavailable', 409], [$e->errorCode, $e->getCode()]);
        }
        $this->expectException(Problem::class);
        $this->content->collection('articles', ['hidden' => '1']);
    }

    public function testASiteWithoutApiV2NeverProbesTheColumn(): void
    {
        Config::$config = (object) [];
        ArticleVisibility::reset();
        $before = Db::getQueriesCount();
        self::assertFalse(ArticleVisibility::isSupported());
        self::assertSame($before, Db::getQueriesCount());
        self::assertSame('', ArticleVisibility::pageCondition('s.site_id'));
    }

    public function testTheArticleBoxReaderSkipsAHiddenArticle(): void
    {
        $article = $this->publishedArticle();
        $siteId = $article['page'];
        $reader = function () use ($siteId) {
            $site = new class($siteId) extends Site { public function __construct(private int $fixtureId) {} public function getSiteId() { return $this->fixtureId; } };
            $parent = new class($site) { public function __construct(private $site) {} public function getSite() { return $this->site; } };
            $box = new class($parent) { public function __construct(private $parent) {} public function getParent() { return $this->parent; } };
            return (new ArticleModel($box))->getArticleRow();
        };
        self::assertIsObject($reader());
        $this->content->setArticleHidden($article['id'], true, $article['etag'], $this->actor);
        self::assertFalse((bool) $reader());
    }

    public function testTheRoutesNeedThePublishScopeAndOnlyAcceptPost(): void
    {
        $article = $this->publishedArticle();
        $model = new Model();
        $writer = $model->provision(11, 'No publishing', ['discovery:read', 'articles:read', 'articles:write'])['key'];
        $full = $model->provision(11, 'Full', Model::SCOPES)['key'];
        $controller = new V2(new Api(), $model, $this->content, new Limiter($this->dir . '/limits'));
        $path = '/api/v2/articles/' . $article['id'] . '/hide';
        $denied = $controller->handle('POST', $path, 'Bearer ' . $writer, true, null, '', [], $article['etag'], '203.0.113.1');
        self::assertSame(403, $denied['status']);
        self::assertSame(405, $controller->handle('GET', $path, 'Bearer ' . $full, true, null, '', [], null, '203.0.113.1')['status']);
        $done = $controller->handle('POST', $path, 'Bearer ' . $full, true, 'mcp:test', '', [], $article['etag'], '203.0.113.1');
        self::assertSame(200, $done['status'], json_encode($done['body']));
        self::assertTrue($done['body']['data']['hidden']);
        $unhide = $controller->handle('POST', '/api/v2/articles/' . $article['id'] . '/unhide', 'Bearer ' . $full, true, 'mcp:test', '', [], $done['headers']['ETag'] ?? null, '203.0.113.1');
        self::assertSame(200, $unhide['status']);
        self::assertFalse($unhide['body']['data']['hidden']);
    }
}
