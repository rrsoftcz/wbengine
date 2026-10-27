<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Api;
use Wbengine\Api\V2;
use Wbengine\Api\V2\Model;
use Wbengine\Config;
use Wbengine\Db;
use Wbengine\RateLimit\Limiter;

class ApiV2AnalyticsTest extends TestCase
{
    private array $tables = [];
    private mixed $oldConfig;

    protected function setUp(): void
    {
        try { Db::query('SELECT 1'); } catch (\Throwable $e) { $this->markTestSkipped('MySQL unavailable.'); }
        $this->oldConfig = Config::$config;
        Config::$config = (object) ['analytics' => (object) ['enabled' => true, 'timezone' => 'UTC', 'retentionDays' => 62]];
        Db::query('CREATE TEMPORARY TABLE cms_users (user_id INT PRIMARY KEY, role VARCHAR(16) NOT NULL) ENGINE=InnoDB');
        Db::insert('cms_users', ['user_id' => 11, 'role' => 'admin']);
        $sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/002-api-v2-integrations.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $sql);
        foreach (explode(';', $sql) as $statement) {
            if ('' === trim($statement)) { continue; }
            Db::query($statement);
            if (preg_match('/CREATE TEMPORARY TABLE\s+`?([a-z0-9_]+)/i', $statement, $match)) { $this->tables[] = $match[1]; }
        }
        Db::query('CREATE TEMPORARY TABLE cms_sites (site_id INT PRIMARY KEY, title VARCHAR(120), visible TINYINT, acl INT) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_articles (id INT PRIMARY KEY, site_id INT, views BIGINT, title VARCHAR(255), published INT) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_boxes (id INT PRIMARY KEY, name VARCHAR(100)) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_analytics_daily (day DATE NOT NULL, site_id INT UNSIGNED NOT NULL, entity_type VARCHAR(16) NOT NULL, entity_id INT UNSIGNED NOT NULL, views BIGINT UNSIGNED NOT NULL, PRIMARY KEY(day,site_id,entity_type,entity_id), KEY site_day(site_id,day)) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_analytics_totals (site_id INT UNSIGNED NOT NULL, entity_type VARCHAR(16) NOT NULL, entity_id INT UNSIGNED NOT NULL, views BIGINT UNSIGNED NOT NULL, PRIMARY KEY(site_id,entity_type,entity_id)) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_analytics_meta (id TINYINT UNSIGNED PRIMARY KEY, tracking_started_on DATE NOT NULL) ENGINE=InnoDB');
        Db::insert('cms_analytics_meta', ['id' => 1, 'tracking_started_on' => '2026-10-25']);
        Db::insert('cms_sites', ['site_id' => 1, 'title' => 'Home', 'visible' => 1, 'acl' => 0]);
        Db::insert('cms_articles', ['id' => 1, 'site_id' => 1, 'views' => 123, 'title' => 'Story', 'published' => 1]);
        Db::insert('cms_boxes', ['id' => 1, 'name' => 'Header']);
        foreach ([
            ['day' => '2026-10-25', 'site_id' => 1, 'entity_type' => 'page', 'entity_id' => 1, 'views' => 2],
            ['day' => '2026-10-25', 'site_id' => 1, 'entity_type' => 'article', 'entity_id' => 1, 'views' => 1],
            ['day' => '2026-10-25', 'site_id' => 1, 'entity_type' => 'box', 'entity_id' => 1, 'views' => 3],
            ['day' => '2026-10-26', 'site_id' => 1, 'entity_type' => 'page', 'entity_id' => 1, 'views' => 3],
            ['day' => '2026-10-26', 'site_id' => 1, 'entity_type' => 'article', 'entity_id' => 1, 'views' => 2],
            ['day' => '2026-10-26', 'site_id' => 1, 'entity_type' => 'box', 'entity_id' => 1, 'views' => 1],
        ] as $row) { Db::insert('cms_analytics_daily', $row); }
        Db::insert('cms_analytics_totals', ['site_id' => 1, 'entity_type' => 'page', 'entity_id' => 1, 'views' => 90]);
        Db::insert('cms_analytics_totals', ['site_id' => 1, 'entity_type' => 'box', 'entity_id' => 1, 'views' => 40]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tables) as $table) { Db::query('DROP TEMPORARY TABLE IF EXISTS `' . $table . '`'); }
        Db::query('DROP TEMPORARY TABLE IF EXISTS cms_users,cms_sites,cms_articles,cms_boxes,cms_analytics_daily,cms_analytics_totals,cms_analytics_meta');
        Config::$config = $this->oldConfig;
    }

    public function testAnalyticsReadCanBeGrantedWithoutContentWriteScopes(): void
    {
        $model = new Model();
        $issued = $model->provision(11, 'Analytics only', ['discovery:read', 'analytics:read']);

        self::assertSame(['discovery:read', 'analytics:read'], $model->authenticate($issued['key'])['scopes']);
    }

    public function testOverviewRequiresTheAnalyticsReadScope(): void
    {
        $model = new Model();
        $issued = $model->provision(11, 'Discovery only', ['discovery:read']);
        $response = $this->api($model)->handle('GET', '/api/v2/analytics/overview', 'Bearer ' . $issued['key'], true, null, '', [
            'from' => '2026-10-25', 'to' => '2026-10-26', 'granularity' => 'day',
        ]);

        self::assertSame(403, $response['status']);
        self::assertSame('forbidden', $response['body']['error']['code']);
        self::assertSame(1, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_audit WHERE action = ?', ['analytics.overview'])['n']);
    }

    public function testOverviewReturnsPeriodSeriesTopItemsAndLifetimeTotals(): void
    {
        $model = new Model();
        $issued = $model->provision(11, 'Analytics reader', ['discovery:read', 'analytics:read']);
        $response = $this->api($model)->handle('GET', '/api/v2/analytics/overview', 'Bearer ' . $issued['key'], true, null, '', [
            'from' => '2026-10-25', 'to' => '2026-10-27', 'granularity' => 'day',
        ]);

        self::assertSame(200, $response['status']);
        self::assertSame([
            'timezone' => 'UTC',
            'period' => ['from' => '2026-10-25', 'to' => '2026-10-27', 'granularity' => 'day', 'complete' => true],
            'totals' => ['page_views' => 5, 'article_views' => 3, 'box_appearances' => 4],
            'lifetime_totals' => ['page_views' => 90, 'article_views' => 123, 'box_appearances' => 40],
            'series' => [
                ['period_start' => '2026-10-25', 'page_views' => 2, 'article_views' => 1, 'box_appearances' => 3],
                ['period_start' => '2026-10-26', 'page_views' => 3, 'article_views' => 2, 'box_appearances' => 1],
                ['period_start' => '2026-10-27', 'page_views' => 0, 'article_views' => 0, 'box_appearances' => 0],
            ],
            'top_pages' => [['id' => 1, 'title' => 'Home', 'views' => 5]],
            'top_articles' => [['id' => 1, 'title' => 'Story', 'views' => 3]],
            'top_boxes' => [['id' => 1, 'name' => 'Header', 'appearances' => 4]],
        ], $response['body']['data']);
    }

    public function testOverviewRejectsMalformedAndOverRetentionRanges(): void
    {
        $model = new Model();
        $issued = $model->provision(11, 'Analytics reader', ['discovery:read', 'analytics:read']);
        foreach ([
            [['from' => '2026-02-30', 'to' => '2026-03-01', 'granularity' => 'day'], 'invalid_date'],
            [['from' => '2026-10-26', 'to' => '2026-10-25', 'granularity' => 'day'], 'invalid_period'],
            [['from' => '2026-10-25', 'to' => '2026-10-26', 'granularity' => 'year'], 'invalid_granularity'],
            [['from' => '2026-08-01', 'to' => '2026-10-26', 'granularity' => 'day'], 'invalid_period'],
        ] as [$query, $code]) {
            $response = $this->api($model)->handle('GET', '/api/v2/analytics/overview', 'Bearer ' . $issued['key'], true, null, '', $query);
            self::assertSame(400, $response['status']);
            self::assertSame($code, $response['body']['error']['code']);
        }
    }

    public function testOverviewMarksDatesBeforeFirstMeasurementIncomplete(): void
    {
        Db::query('UPDATE cms_analytics_meta SET tracking_started_on = ? WHERE id = 1', ['2026-10-26']);
        $model = new Model();
        $issued = $model->provision(11, 'Analytics reader', ['discovery:read', 'analytics:read']);
        $response = $this->api($model)->handle('GET', '/api/v2/analytics/overview', 'Bearer ' . $issued['key'], true, null, '', [
            'from' => '2026-10-25', 'to' => '2026-10-27', 'granularity' => 'day',
        ]);

        self::assertSame(200, $response['status']);
        self::assertFalse($response['body']['data']['period']['complete']);
    }

    private function api(Model $model): V2
    {
        return new V2(new Api(), $model, null, new Limiter(sys_get_temp_dir() . '/analytics-v2-' . bin2hex(random_bytes(8))));
    }
}
