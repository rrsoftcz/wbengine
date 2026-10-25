<?php

// Uses connection-local temporary tables shadowing the test tables, never live rows.
require dirname(__DIR__) . '/bootstrap.php';

use Wbengine\Cache;
use Wbengine\Cache\CacheInterface;
use Wbengine\Cache\FileCache;
use Wbengine\Cache\NullCache;
use Wbengine\Db;
use Wbengine\Site;
use Wbengine\Site\SiteModel;

$directory = sys_get_temp_dir() . '/wb-content-bench-' . bin2hex(random_bytes(8));
$memory = new class implements CacheInterface {
    public array $values = [];
    public function get($key, &$found) { $found = array_key_exists($key, $this->values); return $this->values[$key] ?? null; }
    public function set($key, $value, $ttl) { $this->values[$key] = $value; }
    public function flush() { $this->values = []; }
};
$measure = static function (callable $operation, int $runs = 15): float {
    $times = [];
    for ($i = 0; $i < $runs; $i++) {
        $start = hrtime(true);
        $operation();
        $times[] = (hrtime(true) - $start) / 1e6;
    }
    sort($times);
    return round($times[intdiv(count($times), 2)], 4);
};
$seed = static function (string $table, string $columns, int $count, callable $values): void {
    for ($offset = 0; $offset < $count; $offset += 500) {
        $params = $groups = [];
        for ($i = $offset; $i < min($offset + 500, $count); $i++) {
            $row = $values($i);
            $groups[] = '(' . implode(',', array_fill(0, count($row), '?')) . ')';
            array_push($params, ...$row);
        }
        Db::query("INSERT INTO $table ($columns) VALUES " . implode(',', $groups), $params);
    }
};
$site = new class extends Site {
    public string $url = '/';
    public function __construct() {}
    public function getUrl() { return $this->url; }
    public function isUrlStrict() { return false; }
};
$model = new SiteModel();
$result = ['php' => PHP_VERSION, 'database' => Db::fetchAssoc('SELECT VERSION() AS version')['version']];
try {
    foreach (['cms_sites', 'cms_boxes'] as $table) {
        $ddl = Db::fetchAssoc("SHOW CREATE TABLE $table")['Create Table'];
        Db::query(preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $ddl, 1));
    }
    $result['existingBoxIndexes'] = Db::fetchAllAssoc('SHOW INDEX FROM cms_boxes');
    foreach ([100, 1000, 10000] as $count) {
        Db::query('TRUNCATE TABLE cms_sites');
        $seed('cms_sites', 'link,visible,strict,title', $count,
            static fn ($i) => ['/item-' . $i . '/', 1, 0, 'Item ' . $i]);
        $site->url = '/item-' . ($count - 1) . '/extra/';
        Cache::setDriver(new NullCache());
        $expected = $model->loadSiteData($site);
        $entry = ['rows' => $count, 'sqlMs' => $measure(static fn () => $model->loadSiteData($site))];
        foreach (['memory' => $memory, 'file' => new FileCache($directory)] as $name => $driver) {
            $driver->flush();
            Cache::setDriver($driver, 300);
            $start = hrtime(true);
            $actual = $model->loadSiteData($site);
            $entry[$name . 'ColdMs'] = round((hrtime(true) - $start) / 1e6, 4);
            if ($actual !== $expected) { throw new RuntimeException('Site benchmark result differs'); }
            $entry[$name . 'WarmMs'] = $measure(static fn () => $model->loadSiteData($site));
        }
        Cache::setDriver($memory, 300);
        $memory->flush();
        $model->loadSiteData($site);
        $rows = $memory->values['sites:visible'];
        $entry['prototypeMapBuildMs'] = $measure(static function () use ($rows) {
            $map = [];
            foreach ($rows as $row) { $map[mb_strtolower($row['link'])][] = $row; }
        });
        $result['sites'][] = $entry;
    }
    // Characterization only: a faster lowercase map must not hide existing parity gaps.
    Db::query('TRUNCATE TABLE cms_sites');
    foreach (['utf8mb4_unicode_ci', 'utf8mb4_bin'] as $collation) {
        Db::query("ALTER TABLE cms_sites MODIFY link varchar(100) COLLATE $collation NOT NULL");
        Db::query('DELETE FROM cms_sites');
        Db::insert('cms_sites', ['link' => '/café/', 'visible' => 1, 'strict' => 0]);
        foreach (['/cafe/', '/CAFÉ/'] as $url) {
            $site->url = $url;
            Cache::setDriver(new NullCache());
            $sql = $model->loadSiteData($site);
            Cache::setDriver($memory, 300);
            $memory->flush();
            $cached = $model->loadSiteData($site);
            $result['collation'][] = ['collation' => $collation, 'url' => $url,
                'sqlId' => $sql['site_id'] ?? null, 'cacheId' => $cached['site_id'] ?? null];
        }
    }
    Cache::setDriver(new NullCache());
    $seed('cms_boxes', 'section_id,shared,sites,`order`,name', 30000,
        static fn ($i) => [1 + ($i % 100), (int) (intdiv($i, 100) % 5 === 0), json_encode([$i % 3 === 0 ? '42' : '99']), $i, 'Box ' . $i]);
    $select = 'SELECT CONCAT(?) AS site_id, id,name,module,method,section_id,`static`,shared,device_min,device_strict,`order` FROM cms_boxes b WHERE ';
    $queries = [
        'original' => [$select . "(JSON_SEARCH(sites, 'one', ?) IS NOT NULL AND b.section_id = ?) OR (b.section_id = ? AND b.shared = 1) ORDER BY b.order ASC", ['42', '42', 1, 1]],
        'factored' => [$select . "b.section_id = ? AND (JSON_SEARCH(sites, 'one', ?) IS NOT NULL OR b.shared = 1) ORDER BY b.order ASC", ['42', 1, '42']],
    ];
    $expected = Db::fetchAllAssoc(...$queries['original']);
    foreach (['none' => '', 'section_order' => 'section_id,`order`', 'section_shared_order' => 'section_id,shared,`order`'] as $name => $columns) {
        if ($columns !== '') { Db::query("ALTER TABLE cms_boxes ADD INDEX bench_index ($columns)"); }
        foreach ($queries as $query => [$sql, $params]) {
            if (Db::fetchAllAssoc($sql, $params) !== $expected) { throw new RuntimeException('Box benchmark result differs'); }
            $result['boxes'][$name][$query] = [
                'medianMs' => $measure(static fn () => Db::fetchAllAssoc($sql, $params)),
                'explain' => Db::fetchAllAssoc('EXPLAIN ' . $sql, $params),
            ];
        }
        $inserts = [];
        for ($i = 0; $i < 5; $i++) {
            $start = hrtime(true);
            $seed('cms_boxes', 'section_id,shared,sites,`order`', 1000,
                static fn ($i) => [1 + ($i % 100), $i % 2, '["42"]', 30000 + $i]);
            $inserts[] = (hrtime(true) - $start) / 1e6;
            // Cleanup is deliberately outside the insertion measurement.
            Db::query('DELETE FROM cms_boxes WHERE `order` >= 30000');
        }
        sort($inserts);
        $result['boxes'][$name]['insert1000Ms'] = round($inserts[2], 4);
        if ($columns !== '') { Db::query('ALTER TABLE cms_boxes DROP INDEX bench_index'); }
    }
    Db::query('ALTER TABLE cms_boxes ADD INDEX bench_index (section_id, `order`)');
    $boxSite = new class extends Site {
        public function __construct() {}
        public function getSiteId() { return 42; }
    };
    $section = new class($boxSite) extends \Wbengine\Section {
        public function __construct(private Site $site) {}
        public function getSite() { return $this->site; }
        public function getSectionId() { return 1; }
    };
    $boxModel = new \Wbengine\Section\Model();
    foreach (['memory' => $memory, 'file' => new FileCache($directory)] as $name => $driver) {
        $driver->flush();
        Cache::setDriver($driver, 300);
        $start = hrtime(true);
        $actual = $boxModel->getBoxes($section);
        $result['boxCache'][$name . 'ColdMs'] = round((hrtime(true) - $start) / 1e6, 4);
        if ($actual !== $expected) { throw new RuntimeException('Cached boxes differ'); }
        $before = Db::getQueriesCount();
        $result['boxCache'][$name . 'WarmMs'] = $measure(static fn () => $boxModel->getBoxes($section));
        $result['boxCache'][$name . 'WarmQueries'] = Db::getQueriesCount() - $before;
    }
    $result['boxRows'] = 30000;
    $result['matchingBoxRows'] = count($expected);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
} finally {
    Cache::setDriver(null);
    Db::query('DROP TEMPORARY TABLE IF EXISTS cms_sites');
    Db::query('DROP TEMPORARY TABLE IF EXISTS cms_boxes');
    foreach (glob($directory . '/*') ?: [] as $file) { unlink($file); }
    if (is_dir($directory)) { rmdir($directory); }
}
