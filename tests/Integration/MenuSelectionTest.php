<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Application\Application;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\Cache\NullCache;
use Wbengine\Db;
use Wbengine\Site;
use Wbengine\Site\SiteModel;

class MenuSelectionTest extends DbTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/wb-menu-selection-' . bin2hex(random_bytes(5));
        Cache::setDriver(new NullCache());
    }

    protected function tearDown(): void
    {
        Cache::setDriver(null);
        foreach (glob($this->dir . '/*') ?: [] as $file) { unlink($file); }
        if (is_dir($this->dir)) { rmdir($this->dir); }
    }

    private function row(string $link, int $visible = 1, int $strict = 0): int
    {
        return Db::insert('cms_sites', ['link' => $link, 'visible' => $visible, 'strict' => $strict]);
    }

    #[DataProvider('cacheModes')]
    public function testManyMenuItemsReuseOneLookupAndPreserveHiddenStrictParents(bool $cached): void
    {
        $parent = $this->row('/parent/', 0);
        $strict = $this->row('/parent/strict/', 1, 1);
        $current = $this->row('/parent/strict/child/');
        $unrelated = $this->row('/other/');
        $site = new MenuSelectionSite(['/parent/', '/parent/strict/', '/parent/strict/child/', '/missing/'], $current);
        if ($cached) {
            Cache::setDriver(new FileCache($this->dir));
            (new SiteModel())->loadSiteData($site);
        }
        $before = Db::getQueriesCount();
        $this->assertTrue($site->isMenuSelected($current));
        $this->assertSame($before, Db::getQueriesCount());
        for ($i = 0; $i < 20; ++$i) {
            $this->assertTrue($site->isMenuSelected($parent));
            $this->assertTrue($site->isMenuSelected((string) $strict));
            $this->assertFalse($site->isMenuSelected($unrelated));
        }
        $pairs = $site->getUrlPairsWithSiteId();
        $this->assertSame([
            ['url' => '/parent/', 'site_id' => $parent],
            ['url' => '/parent/strict/', 'site_id' => $strict],
            ['url' => '/parent/strict/child/', 'site_id' => $current],
            ['url' => '/missing/', 'site_id' => 0],
        ], $pairs);
        $this->assertSame(1, Db::getQueriesCount() - $before, 'one statement for the whole menu, not one per item and prefix');
    }

    public static function cacheModes(): array { return [[false], [true]]; }

    public function testRealUrlParserSelectsSlashParentsButPrefersAnExactBareLink(): void
    {
        $parent = $this->row('/parent/');
        $bare = $this->row('/parent/bare');
        $slash = $this->row('/parent/bare/');
        $current = $this->row('/parent/bare/child/');
        $server = $_SERVER;
        $request = $_REQUEST;
        try {
            $_SERVER['REQUEST_URI'] = '/parent/bare/child/';
            $_SERVER['HTTP_HOST'] = 'example.test';
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            $_REQUEST['link'] = 'parent/bare/child/';
            $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
            $site = new Site($app);
            $site->_resource = ['site_id' => $current];
            $this->assertSame(['/parent', '/parent/bare', '/parent/bare/child'], $site->getUrlPairs());
            $this->assertTrue($site->isMenuSelected($parent));
            $this->assertTrue($site->isMenuSelected($bare));
            $this->assertFalse($site->isMenuSelected($slash));
            $this->assertTrue($site->isMenuSelected($current));
        } finally {
            $_SERVER = $server;
            $_REQUEST = $request;
        }
    }

    public function testBatchPreservesPositionsDuplicatesMissingUrlsAndSqlParameters(): void
    {
        $first = $this->row('/duplicate/', 0);
        $this->row('/duplicate/');
        $quote = $this->row("/O'Brien/");
        $urls = ['/duplicate/', '/nothing/', '/duplicate/', "/O'Brien/", "/' OR 1=1 --/"];
        $before = Db::getQueriesCount();
        $ids = (new SiteModel())->getSiteIdsByUrls($urls);
        $this->assertSame([$first, 0, $first, $quote, 0], $ids);
        $this->assertSame(1, Db::getQueriesCount() - $before);
        $this->assertSame($first, (new SiteModel())->getSiteIdByUrl('/duplicate/'));
    }

    public function testDatabaseCollationStillDecidesCaseAndAccentMatches(): void
    {
        $original = Db::fetchAssoc("SELECT COLLATION_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_sites' AND COLUMN_NAME = 'link'")['name'];
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]+$/D', $original);
        try {
            foreach (['utf8mb4_unicode_ci', 'utf8mb4_bin'] as $collation) {
                Db::query('ALTER TABLE cms_sites MODIFY link varchar(100) CHARACTER SET utf8mb4 COLLATE ' . $collation . ' NOT NULL');
                Db::query('DELETE FROM cms_sites');
                $id = $this->row('/Café/', 0);
                $batch = (new SiteModel())->getSiteIdsByUrls(['/cafe/', '/CAFÉ/', '/Café/']);
                $this->assertSame('utf8mb4_bin' === $collation ? [0, 0, $id] : [$id, $id, $id], $batch);
                $site = new MenuSelectionSite(['/cafe/'], 999999);
                $this->assertSame('utf8mb4_unicode_ci' === $collation, $site->isMenuSelected($id));
            }
        } finally {
            Db::query('ALTER TABLE cms_sites MODIFY link varchar(100) CHARACTER SET utf8mb4 COLLATE ' . $original . ' NOT NULL');
        }
    }

    public function testChangedPrefixesRefreshTheRequestSnapshot(): void
    {
        $first = $this->row('/first/');
        $second = $this->row('/second/');
        $site = new MenuSelectionSite(['/first/'], 999999);
        $this->assertTrue($site->isMenuSelected($first));
        $site->pairs = ['/second/'];
        $this->assertFalse($site->isMenuSelected($first));
        $this->assertTrue($site->isMenuSelected($second));
    }

    public function testEmptyPrefixesAndInvalidMenuIdsDoNotQueryOrWarn(): void
    {
        $before = Db::getQueriesCount();
        foreach ([[], null] as $pairs) {
            $site = new MenuSelectionSite($pairs, 0);
            $this->assertNull($site->getUrlPairsWithSiteId());
            $this->assertFalse($site->isMenuSelected(123));
            $this->assertFalse($site->isMenuSelected(0));
            $this->assertFalse($site->isMenuSelected(null));
            $this->assertFalse($site->isMenuSelected(-1));
        }
        $this->assertSame([], (new SiteModel())->getSiteIdsByUrls([]));
        $this->assertSame($before, Db::getQueriesCount());
    }
}

class MenuSelectionSite extends Site
{
    public function __construct(public ?array $pairs, int $current)
    {
        $this->_resource = ['site_id' => $current];
    }
    public function getUrlPairs() { return $this->pairs; }
    public function getUrl() { return '/parent/strict/child/'; }
    public function isUrlStrict() { return false; }
}
