<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Cache;
use Wbengine\Cache\NullCache;
use Wbengine\Db;
use Wbengine\Section;
use Wbengine\Section\Model;
use Wbengine\Site;

class BoxIndexTest extends TestCase
{
    public function testMigrationPreservesBoxesAndLetsTheIndexSupplyOrdering(): void
    {
        try {
            Db::query('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Test database unavailable: ' . $e->getMessage());
        }
        $ddl = Db::fetchAssoc('SHOW CREATE TABLE cms_boxes')['Create Table'];
        Db::query(preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $ddl, 1));
        Cache::setDriver(new NullCache());
        try {
            for ($offset = 0; $offset < 3000; $offset += 500) {
                $groups = $params = [];
                for ($i = $offset; $i < $offset + 500; $i++) {
                    $groups[] = '(?, ?, ?, ?)';
                    array_push($params, 1 + ($i % 100), (int) (intdiv($i, 100) % 5 === 0), $i % 3 === 0 ? '["42"]' : '["99"]', $i);
                }
                Db::query('INSERT INTO cms_boxes (section_id, shared, sites, `order`) VALUES ' . implode(',', $groups), $params);
            }
            $site = $this->createStub(Site::class);
            $site->method('getSiteId')->willReturn(42);
            $section = $this->createStub(Section::class);
            $section->method('getSite')->willReturn($site);
            $section->method('getSectionId')->willReturn(1);
            $model = new Model();
            $before = $model->getBoxes($section);
            $this->assertNotEmpty($before);
            $migration = dirname(__DIR__, 2) . '/migrations/4.0.0/001-boxes-section-order.sql';
            $this->assertFileExists($migration);
            Db::query(file_get_contents($migration));
            $this->assertSame($before, $model->getBoxes($section));
            $queries = Db::getAllQueries();
            $sql = end($queries)['query'];
            // Constants do not change section selectivity or the ordering requirement.
            $plan = Db::fetchAllAssoc('EXPLAIN ' . $sql, array_fill(0, substr_count($sql, '?'), 1));
            $this->assertSame('wb_boxes_section_order', $plan[0]['key']);
            $this->assertStringNotContainsString('filesort', strtolower($plan[0]['Extra']));
            Db::query('ALTER TABLE cms_boxes DROP INDEX wb_boxes_section_order');
            $this->assertSame($before, $model->getBoxes($section), 'Rollback preserves results too');
        } finally {
            Db::query('DROP TEMPORARY TABLE cms_boxes');
            Cache::setDriver(null);
        }
    }
}
