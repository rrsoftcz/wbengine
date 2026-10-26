<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\Cache\NullCache;
use Wbengine\Db;
use Wbengine\Site;
use Wbengine\Site\SiteModel;

class SiteLookupCollationTest extends DbTestCase
{
    #[DataProvider('collations')]
    public function testCacheSettingPreservesDatabaseUrlSelection(string $collation): void
    {
        $original = Db::fetchAssoc("SELECT COLLATION_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cms_sites' AND COLUMN_NAME = 'link'")['name'];
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]+$/D', $original);
        $directory = sys_get_temp_dir() . '/wb-site-collation-' . bin2hex(random_bytes(4));
        try {
            Db::query('ALTER TABLE cms_sites MODIFY link varchar(100) CHARACTER SET utf8mb4 COLLATE ' . $collation . ' NOT NULL');
            foreach ([
                ['/café/', 0, 1, 'accent'],
                ['/padding/ ', 1, 1, 'padded'],
                ['/prefix/', 0, 1, 'prefix'],
                ['/prefix/exact/', 1, 1, 'exact'],
                ['/duplicate/', 0, 1, 'older duplicate'],
                ['/duplicate/', 0, 1, 'newer duplicate'],
                ['/duplicate/', 0, 0, 'hidden duplicate'],
                ['/priority/deep/', 0, 1, 'older deep'],
                ['/priority/', 0, 1, 'newer prefix'],
                ['/hidden/', 0, 0, 'hidden'],
                ['/null/', null, 1, 'null strict'],
                ['/two/', 2, 1, 'unsupported strict'],
                ['/negative/', -1, 1, 'negative strict'],
                ["/O'Brien/", 1, 1, 'quote'],
            ] as [$link, $strict, $visible, $title]) {
                Db::insert('cms_sites', compact('link', 'strict', 'visible', 'title'));
            }
            $accentMatch = 'utf8mb4_unicode_ci' === $collation ? 'accent' : null;
            $cases = [
                ['/café/', false, 'accent'],
                ['/cafe/', false, $accentMatch],
                ['/CAFÉ/', false, $accentMatch],
                ["/cafe\u{0301}/", false, $accentMatch],
                ['/padding/', true, 'padded'],
                ['/padding/   ', true, 'padded'],
                ['/prefix/child/', false, 'prefix'],
                ['/prefix/child/', true, null],
                ['/prefix/exact/', false, 'exact'],
                ['/prefix/exact/child/', false, 'prefix'],
                ['/duplicate/', false, 'newer duplicate'],
                ['/priority/deep/child/', false, 'newer prefix'],
                ['/hidden/', false, null],
                ['/null/', false, null],
                ['/two/', false, null],
                ['/negative/', false, null],
                ["/O'Brien/", true, 'quote'],
                ["/' OR 1=1 --/", false, null],
                ['/missing/', false, null],
            ];
            foreach ([new NullCache(), new FileCache($directory)] as $driver) {
                Cache::setDriver($driver, 60);
                foreach ($cases as [$url, $strict, $title]) {
                    $site = $this->createStub(Site::class);
                    $site->method('getUrl')->willReturn($url);
                    $site->method('isUrlStrict')->willReturn($strict);
                    $before = Db::getQueriesCount();
                    $row = (new SiteModel())->loadSiteData($site);
                    $this->assertSame(1, Db::getQueriesCount() - $before);
                    $this->assertSame($title, $row['title'] ?? null, $collation . ': ' . $url . ' with ' . get_class($driver));
                }
            }
        } finally {
            Cache::setDriver(null);
            Db::query('ALTER TABLE cms_sites MODIFY link varchar(100) CHARACTER SET utf8mb4 COLLATE ' . $original . ' NOT NULL');
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public static function collations(): array
    {
        return [['utf8mb4_unicode_ci'], ['utf8mb4_bin']];
    }
}
