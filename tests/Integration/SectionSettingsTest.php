<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Api\V2\BoxRegistry;
use Wbengine\Api\V2\ContentModel;
use Wbengine\Api\V2\Model;
use Wbengine\Cache;
use Wbengine\Db;
use Wbengine\Section;
use Wbengine\Site;

final class SectionSettingsTest extends TestCase
{
    private array $tables = [];
    private string $directory;

    protected function setUp(): void
    {
        try { Db::query('SELECT 1'); } catch (\Throwable $e) { $this->markTestSkipped('MySQL unavailable.'); }
        $sql = file_get_contents(dirname(__DIR__) . '/schema.sql');
        $sql .= file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/002-api-v2-integrations.sql');
        $sql .= file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/005-api-content.sql');
        $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', preg_replace('/^--.*$/m', '', $sql));
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) === '') { continue; }
            Db::query($statement);
            if (preg_match('/CREATE TEMPORARY TABLE\s+`?([a-z0-9_]+)/i', $statement, $match)) { $this->tables[] = $match[1]; }
        }
        $this->directory = sys_get_temp_dir() . '/section-settings-' . bin2hex(random_bytes(8));
        Cache::setDriver(new \Wbengine\Cache\FileCache($this->directory));
    }

    protected function tearDown(): void
    {
        Cache::setDriver(null);
        if (isset($this->directory)) {
            if (is_dir($this->directory)) {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
            }
            if (is_dir($this->directory)) { rmdir($this->directory); }
        }
        foreach (array_reverse($this->tables) as $table) { Db::query('DROP TEMPORARY TABLE IF EXISTS `' . $table . '`'); }
    }

    private function section(int $id, int $siteId): Section
    {
        $site = new class($siteId) extends Site {
            public function __construct(private int $fixtureId) {}
            public function getSiteId() { return $this->fixtureId; }
            public function getParent() { return new class { public function getParentNameSpace() { return 'Wbengine\\Tests\\SettingsFixture'; } }; }
        };
        return new Section(['section_id' => $id, 'key' => 'central'], $site);
    }

    public function testApiCreateAndPatchReachActualSectionCreatedBoxAndFlushCachedRows(): void
    {
        $registry = new BoxRegistry();
        $registry->register('story', 'Blog', 'story', ['variant' => ['full', 'compact']]);
        $content = new ContentModel($registry);
        Db::insert('cms_users', ['user_id' => 11, 'username' => 'admin', 'role' => 'admin']);
        $keyModel = new Model();
        $key = $keyModel->provision(11, 'Section test', Model::SCOPES)['key'];
        $controller = new \Wbengine\Api\V2(new \Wbengine\Api(), $keyModel, $content, new \Wbengine\RateLimit\Limiter($this->directory . '/rate'));
        $section = $content->create('sections', ['key' => 'central', 'title' => 'Central'], ['integration_id' => 1]);
        $siteId = Db::insert('cms_sites', ['link' => '/settings/']);
        $created = $controller->handle('POST', '/api/v2/boxes', 'Bearer ' . $key, true, null, json_encode(['name' => 'Story', 'type' => 'story', 'section_id' => $section['data']['section_id'], 'sites' => [(string) $siteId], 'settings' => ['variant' => 'full']]));
        self::assertSame(201, $created['status'], json_encode($created));
        $id = $created['body']['data']['id'];
        $before = Db::getQueriesCount();
        $runtime = $this->section($section['data']['section_id'], $siteId);
        self::assertSame($before, Db::getQueriesCount(), 'Constructing a section remains lazy.');
        $rows = $runtime->getModel()->getBoxes($runtime);
        self::assertSame('{"variant":"full"}', $rows[0]['api_settings'] ?? null, 'The model must carry the optional column.');
        self::assertSame($before + 1, Db::getQueriesCount(), 'Loading rows needs one query, with no schema probe.');
        self::assertSame('variant:full', $runtime->getContent());
        self::assertSame($before + 1, Db::getQueriesCount(), 'Rendering reuses cached box rows.');
        $patched = $controller->handle('PATCH', '/api/v2/boxes/' . $id, 'Bearer ' . $key, true, null, '{"settings":{"variant":"compact"}}', [], $created['headers']['ETag']);
        self::assertSame(200, $patched['status'], json_encode($patched));
        self::assertSame('variant:compact', $this->section($section['data']['section_id'], $siteId)->getContent());
    }

    public function testUnmigratedSchemaRendersWithEmptySettingsWithoutProbing(): void
    {
        Db::query('ALTER TABLE cms_boxes DROP COLUMN api_settings');
        $id = Db::insert('cms_sections', ['key' => 'central']);
        Db::insert('cms_boxes', ['section_id' => $id, 'module' => 'Blog', 'method' => 'story', 'sites' => '["1"]']);
        $section = $this->section($id, 1);
        $before = Db::getQueriesCount();
        self::assertSame('variant:default', $section->getContent());
        self::assertSame($before + 1, Db::getQueriesCount());
    }
}

namespace Wbengine\Tests\SettingsFixture\Box\Central;

final class Blog extends \Wbengine\Box\WbengineBoxAbstract
{
    public function Story() { return 'variant:' . $this->getSetting('variant', 'default'); }
}
