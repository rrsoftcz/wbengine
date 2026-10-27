<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Analytics\Tracker;
use Wbengine\Analytics\Maintenance;
use Wbengine\Config;
use Wbengine\Db;

class AnalyticsTrackerTest extends TestCase
{
    private mixed $oldConfig;

    protected function setUp(): void
    {
        try { Db::query('SELECT 1'); } catch (\Throwable) { $this->markTestSkipped('MySQL unavailable.'); }
        $this->oldConfig = Config::$config;
        Config::$config = (object) ['analytics' => (object) ['enabled' => true, 'timezone' => 'UTC', 'retentionDays' => 62]];
        Db::query('CREATE TEMPORARY TABLE cms_analytics_daily (day DATE NOT NULL, site_id INT UNSIGNED NOT NULL, entity_type ENUM(\'page\',\'article\',\'box\') NOT NULL, entity_id INT UNSIGNED NOT NULL, views BIGINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY(day,site_id,entity_type,entity_id)) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_analytics_totals (site_id INT UNSIGNED NOT NULL, entity_type ENUM(\'page\',\'box\') NOT NULL, entity_id INT UNSIGNED NOT NULL, views BIGINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY(site_id,entity_type,entity_id)) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_analytics_meta (id TINYINT UNSIGNED PRIMARY KEY, tracking_started_on DATE NOT NULL) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_articles (id INT PRIMARY KEY, site_id INT, views BIGINT NOT NULL) ENGINE=InnoDB');
        Db::insert('cms_articles', ['id' => 41, 'site_id' => 7, 'views' => 20]);
    }

    protected function tearDown(): void
    {
        Db::query('DROP TEMPORARY TABLE IF EXISTS cms_analytics_daily,cms_analytics_totals,cms_analytics_meta,cms_articles');
        Config::$config = $this->oldConfig;
    }

    public function testFlushDeduplicatesRequestEventsAndPersistsDailyAndLifetimeAggregates(): void
    {
        $tracker = new Tracker();
        $tracker->beginPublicPage(7);
        $tracker->record(7, 'page', 7);
        $tracker->record(7, 'page', 7);
        $tracker->record(7, 'article', 41);
        $tracker->record(7, 'box', 82);
        $tracker->record(7, 'box', 82);
        $tracker->record(7, 'box', 83);
        $tracker->flush(new \DateTimeImmutable('2026-10-27 10:00:00', new \DateTimeZone('UTC')));

        self::assertSame(1, (int) Db::fetchAssoc('SELECT SUM(views) AS views FROM cms_analytics_daily WHERE day = ? AND site_id = ? AND entity_type = ? AND entity_id = ?', ['2026-10-27', 7, 'page', 7])['views']);
        self::assertSame(1, (int) Db::fetchAssoc('SELECT SUM(views) AS views FROM cms_analytics_daily WHERE day = ? AND site_id = ? AND entity_type = ? AND entity_id = ?', ['2026-10-27', 7, 'article', 41])['views']);
        self::assertSame(3, (int) Db::fetchAssoc('SELECT SUM(views) AS views FROM cms_analytics_daily WHERE day = ? AND site_id = ? AND entity_type = ?', ['2026-10-27', 7, 'box'])['views']);
        self::assertSame(1, (int) Db::fetchAssoc('SELECT SUM(views) AS views FROM cms_analytics_totals WHERE site_id = ? AND entity_type = ? AND entity_id = ?', [7, 'page', 7])['views']);
        self::assertSame(3, (int) Db::fetchAssoc('SELECT SUM(views) AS views FROM cms_analytics_totals WHERE site_id = ? AND entity_type = ?', [7, 'box'])['views']);
        self::assertSame('2026-10-27', Db::fetchAssoc('SELECT tracking_started_on FROM cms_analytics_meta WHERE id = 1')['tracking_started_on']);
    }

    public function testArticleAndBoxEventsAreIgnoredUntilPublicPageIsConfirmed(): void
    {
        $tracker = new Tracker();
        $tracker->record(7, 'article', 41);
        $tracker->record(7, 'box', 82);
        self::assertSame([], $tracker->metadata());

        $tracker->beginPublicPage(7);
        $tracker->record(7, 'article', 41);
        $tracker->record(7, 'box', 82);
        $tracker->flush(new \DateTimeImmutable('2026-10-27 10:00:00', new \DateTimeZone('UTC')));

        self::assertSame(1, (int) Db::fetchAssoc('SELECT views FROM cms_analytics_daily WHERE day = ? AND entity_type = ?', ['2026-10-27', 'article'])['views']);
        self::assertSame(1, (int) Db::fetchAssoc('SELECT views FROM cms_analytics_daily WHERE day = ? AND entity_type = ?', ['2026-10-27', 'box'])['views']);
    }

    public function testCachedPageHitReplaysArticleAndBoxCountsIncludingLegacyArticleLifetime(): void
    {
        Tracker::flushMetadata([
            ['site_id' => 7, 'entity_type' => 'page', 'entity_id' => 7, 'views' => 1],
            ['site_id' => 7, 'entity_type' => 'article', 'entity_id' => 41, 'views' => 1],
            ['site_id' => 7, 'entity_type' => 'box', 'entity_id' => 82, 'views' => 2],
        ], new \DateTimeImmutable('2026-10-27 10:00:00', new \DateTimeZone('UTC')));

        self::assertSame(21, (int) Db::fetchAssoc('SELECT views FROM cms_articles WHERE id = ?', [41])['views']);
        self::assertSame(1, (int) Db::fetchAssoc('SELECT views FROM cms_analytics_daily WHERE day = ? AND entity_type = ?', ['2026-10-27', 'article'])['views']);
        self::assertSame(2, (int) Db::fetchAssoc('SELECT views FROM cms_analytics_daily WHERE day = ? AND entity_type = ?', ['2026-10-27', 'box'])['views']);
    }

    public function testCachedMetadataWithoutPublicPageEventIsIgnored(): void
    {
        Tracker::flushMetadata([
            ['site_id' => 7, 'entity_type' => 'article', 'entity_id' => 41, 'views' => 1],
            ['site_id' => 7, 'entity_type' => 'box', 'entity_id' => 82, 'views' => 1],
        ], new \DateTimeImmutable('2026-10-27 10:00:00', new \DateTimeZone('UTC')));

        self::assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_analytics_daily')['n']);
    }

    public function testPruneRemovesOnlyDaysOutsideConfiguredRetentionInBoundedBatches(): void
    {
        foreach (['2026-08-26', '2026-08-27', '2026-10-27'] as $day) {
            Db::insert('cms_analytics_daily', ['day' => $day, 'site_id' => 7, 'entity_type' => 'page', 'entity_id' => 7, 'views' => 1]);
        }
        $maintenance = new Maintenance();
        self::assertSame(1, $maintenance->prune(new \DateTimeImmutable('2026-10-27', new \DateTimeZone('UTC')), 1));
        self::assertSame(2, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_analytics_daily')['n']);
    }
}
