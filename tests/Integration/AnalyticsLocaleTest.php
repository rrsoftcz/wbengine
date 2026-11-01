<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Analytics\Overview;
use Wbengine\Analytics\Schema;
use Wbengine\Analytics\Tracker;
use Wbengine\Api\V2\Problem;
use Wbengine\Config;
use Wbengine\Db;

/** Views per language: recorded with the request language, summed in the totals, split on request. */
class AnalyticsLocaleTest extends TestCase
{
    private mixed $oldConfig;
    private const NOW = '2026-10-27 10:00:00';

    private function tables(bool $withLocale): void
    {
        $locale = $withLocale ? ", locale VARCHAR(10) NOT NULL DEFAULT ''" : '';
        $dailyKey = $withLocale ? 'day, site_id, entity_type, entity_id, locale' : 'day, site_id, entity_type, entity_id';
        $totalsKey = $withLocale ? 'site_id, entity_type, entity_id, locale' : 'site_id, entity_type, entity_id';
        Db::query("CREATE TEMPORARY TABLE cms_analytics_daily (day DATE NOT NULL, site_id INT UNSIGNED NOT NULL, entity_type ENUM('page','article','box') NOT NULL, entity_id INT UNSIGNED NOT NULL{$locale}, views BIGINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY ({$dailyKey})) ENGINE=InnoDB");
        Db::query("CREATE TEMPORARY TABLE cms_analytics_totals (site_id INT UNSIGNED NOT NULL, entity_type ENUM('page','box') NOT NULL, entity_id INT UNSIGNED NOT NULL{$locale}, views BIGINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY ({$totalsKey})) ENGINE=InnoDB");
        Schema::reset();
    }

    protected function setUp(): void
    {
        try { Db::query('SELECT 1'); } catch (\Throwable) { $this->markTestSkipped('MySQL unavailable.'); }
        $this->oldConfig = Config::$config;
        Config::$config = (object) ['analytics' => (object) ['enabled' => true, 'timezone' => 'UTC', 'retentionDays' => 62]];
        Db::query('CREATE TEMPORARY TABLE cms_analytics_meta (id TINYINT UNSIGNED PRIMARY KEY, tracking_started_on DATE NOT NULL) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_sites (site_id INT PRIMARY KEY, title VARCHAR(120), visible TINYINT, acl INT) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_articles (id INT PRIMARY KEY, site_id INT, views BIGINT NOT NULL DEFAULT 0, title VARCHAR(255), published INT) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_boxes (id INT PRIMARY KEY, name VARCHAR(100)) ENGINE=InnoDB');
        Db::insert('cms_sites', ['site_id' => 7, 'title' => 'Blog', 'visible' => 1, 'acl' => 0]);
        Db::insert('cms_articles', ['id' => 41, 'site_id' => 7, 'views' => 0, 'title' => 'Story', 'published' => 1]);
    }

    protected function tearDown(): void
    {
        Db::query('DROP TEMPORARY TABLE IF EXISTS cms_analytics_daily,cms_analytics_totals,cms_analytics_meta,cms_sites,cms_articles,cms_boxes');
        Schema::reset();
        Config::$config = $this->oldConfig;
    }

    private function view(string $locale, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $tracker = new Tracker();
            $tracker->beginPublicPage(7, $locale);
            $tracker->record(7, 'article', 41);
            $tracker->flush(new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')));
        }
    }

    private function overview(array $extra = []): array
    {
        return (new Overview())->overview(['from' => '2026-10-27', 'to' => '2026-10-27', 'granularity' => 'day'] + $extra);
    }

    public function testEachLanguageIsRecordedAndTheTotalIsTheSum(): void
    {
        $this->tables(true);
        $this->view('en', 3);
        $this->view('cs', 2);

        $data = $this->overview();
        self::assertSame(5, $data['totals']['page_views'], 'the totals are the sum of all languages');
        self::assertSame(5, $data['totals']['article_views']);
        self::assertTrue($data['per_locale']);
        self::assertSame([
            ['locale' => 'en', 'page_views' => 3, 'article_views' => 3, 'box_appearances' => 0],
            ['locale' => 'cs', 'page_views' => 2, 'article_views' => 2, 'box_appearances' => 0],
        ], $data['by_locale']);
        self::assertSame(3, (int) Db::fetchAssoc("SELECT views FROM cms_analytics_totals WHERE site_id = 7 AND entity_type = 'page' AND locale = 'en'")['views']);
    }

    public function testTheLanguageFilterNarrowsTheFiguresButNotTheValidationOfOtherInput(): void
    {
        $this->tables(true);
        $this->view('en', 3);
        $this->view('cs', 2);

        $cs = $this->overview(['locale' => 'cs']);
        self::assertSame(2, $cs['totals']['page_views']);
        self::assertSame([['id' => 7, 'title' => 'Blog', 'views' => 2]], $cs['top_pages']);
        self::assertSame([['id' => 41, 'title' => 'Story', 'views' => 2]], $cs['top_articles']);
        self::assertCount(1, $cs['by_locale']);
        self::assertSame(2, $this->overview(['locale' => ''])['by_locale'][1]['page_views'], 'an empty filter is no filter');
    }

    public function testRowsCountedBeforeTheSplitAreReportedAsNotRecordedPerLanguage(): void
    {
        $this->tables(true);
        Db::insert('cms_analytics_daily', ['day' => '2026-10-27', 'site_id' => 7, 'entity_type' => 'page', 'entity_id' => 7, 'locale' => '', 'views' => 10]);
        $this->view('cs');

        $data = $this->overview();
        self::assertSame(11, $data['totals']['page_views']);
        self::assertSame([['locale' => null, 'page_views' => 10, 'article_views' => 0, 'box_appearances' => 0], ['locale' => 'cs', 'page_views' => 1, 'article_views' => 1, 'box_appearances' => 0]], $data['by_locale']);
    }

    public function testASiteWithoutLanguagesRecordsAnEmptyLocale(): void
    {
        $this->tables(true);
        $this->view('');

        self::assertSame([['locale' => null, 'page_views' => 1, 'article_views' => 1, 'box_appearances' => 0]], $this->overview()['by_locale']);
    }

    public function testAMalformedLocaleIsNotStoredAndNeverReachesTheSql(): void
    {
        $this->tables(true);
        $this->view("cs'; DROP TABLE x;--");

        self::assertSame([['locale' => null, 'page_views' => 1, 'article_views' => 1, 'box_appearances' => 0]], $this->overview()['by_locale']);
    }

    public function testCountingContinuesOnADatabaseWithoutTheMigration(): void
    {
        $this->tables(false);
        $this->view('cs', 2);

        $data = $this->overview();
        self::assertSame(2, $data['totals']['page_views'], 'an upgraded framework must not stop counting before the migration is applied');
        self::assertFalse($data['per_locale']);
        self::assertSame([], $data['by_locale']);
    }

    public function testAFilterWithoutTheMigrationIsRefusedWithTheFileToApply(): void
    {
        $this->tables(false);
        try { $this->overview(['locale' => 'cs']); self::fail('expected a problem'); }
        catch (Problem $e) { self::assertSame('locale_unavailable', $e->errorCode); self::assertStringContainsString('013-analytics-locale.sql', $e->getMessage()); }
    }

    public function testAnInvalidFilterIsRefused(): void
    {
        $this->tables(true);
        $this->expectException(Problem::class);
        $this->overview(['locale' => 'not a locale']);
    }

    public function testACachedHitReplaysTheLanguageOfTheCachedPage(): void
    {
        $this->tables(true);
        $tracker = new Tracker();
        $tracker->beginPublicPage(7, 'cs');
        $tracker->record(7, 'article', 41);
        $stored = $tracker->metadata();
        self::assertSame('cs', $stored[0]['locale']);

        Tracker::flushMetadata($stored, new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')));
        // A cache entry written before 6.7 has no language: counted, but in no language.
        Tracker::flushMetadata([['site_id' => 7, 'entity_type' => 'page', 'entity_id' => 7, 'views' => 1]], new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')));

        $by = [];
        foreach ($this->overview()['by_locale'] as $row) { $by[(string) $row['locale']] = $row['page_views']; }
        self::assertSame(1, $by['cs']);
        self::assertSame(1, $by['']);
    }
}
