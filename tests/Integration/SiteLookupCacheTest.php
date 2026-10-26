<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\Cache\NullCache;
use Wbengine\Db;
use Wbengine\Site;
use Wbengine\Site\SiteModel;

/**
 * Enabling the content cache must preserve database URL selection.
 */
class SiteLookupCacheTest extends DbTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/wb-site-cache-' . bin2hex(random_bytes(4));

        $sites = array(
            array('/', 0, 1), array('/blog/', 0, 1), array('/blog/special/', 1, 1),
            array('/about/', 0, 1), array('/hidden/', 0, 0), array('/shop/', 1, 1),
        );
        foreach ($sites as [$link, $strict, $visible]) {
            Db::insert('cms_sites', array('link' => $link, 'strict' => $strict, 'visible' => $visible, 'title' => $link));
        }
    }

    protected function tearDown(): void
    {
        Cache::setDriver(null);
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function lookup(string $url, bool $strict): ?array
    {
        $site = $this->createStub(Site::class);
        $site->method('getUrl')->willReturn($url);
        $site->method('isUrlStrict')->willReturn($strict);

        return (new SiteModel())->loadSiteData($site);
    }

    #[DataProvider('urls')]
    public function testCachedLookupEqualsSqlLookup(string $url, bool $strict): void
    {
        Cache::setDriver(new NullCache());
        $fromSql = $this->lookup($url, $strict);

        Cache::setDriver(new FileCache($this->dir), 60);
        $fromCache = $this->lookup($url, $strict);
        $fromCacheAgain = $this->lookup($url, $strict);

        $this->assertEquals($fromSql, $fromCache);
        $this->assertEquals($fromSql, $fromCacheAgain);
    }

    public static function urls(): array
    {
        return array(
            'home'                   => array('/', false),
            'blog'                   => array('/blog/', false),
            'blog sub page falls back' => array('/blog/2024/post/', false),
            'strict page'            => array('/blog/special/', false),
            'strict no prefix match' => array('/shop/item/', false),
            'strict mode on'         => array('/blog/', true),
            'hidden'                 => array('/hidden/', false),
            'unknown'                => array('/nothing/here/', false),
            'case differs'           => array('/BLOG/', false),
            'quote'                  => array("/blog/'/", false),
        );
    }

    public function testRandomUrlsDoNotGrowTheCache(): void
    {
        Cache::setDriver(new FileCache($this->dir), 60);

        for ($i = 0; $i < 20; $i++) {
            $this->lookup('/blog/' . bin2hex(random_bytes(4)) . '/', false);
            $this->lookup('/' . bin2hex(random_bytes(4)) . '/', false);
        }

        $this->assertCount(0, glob($this->dir . '/*.cache.php') ?: []);
    }

    public function testUrlSelectionUsesOneQueryAndSeesUpdatesWithoutFlushing(): void
    {
        Cache::setDriver(new FileCache($this->dir), 60);
        $this->lookup('/blog/', false);

        $before = Db::getQueriesCount();
        $this->lookup('/blog/', false);
        $this->assertSame(1, Db::getQueriesCount() - $before, 'URL selection uses the database collation on every lookup');

        Db::update('cms_sites', array('title' => 'Changed'), array('link' => '/blog/'));
        $this->assertSame('Changed', $this->lookup('/blog/', false)['title'], 'URL selection returns current row data');

        Db::update('cms_sites', array('visible' => 0), array('link' => '/blog/'));
        $this->assertNull($this->lookup('/blog/', false), 'visibility changes apply immediately');
        Db::update('cms_sites', array('visible' => 1), array('link' => '/blog/'));

        Cache::flush();
        $this->assertSame('Changed', $this->lookup('/blog/', false)['title']);
    }
}
