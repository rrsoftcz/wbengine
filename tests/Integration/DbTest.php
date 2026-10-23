<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Db;
use Wbengine\Db\Exception\DbException;

class DbTest extends DbTestCase
{
    private const PAYLOADS = array(
        "O'Brien",
        "x'); DROP TABLE cms_sections; --",
        '" OR 1=1 --',
        "\\' OR '1'='1",
        "emoji \u{1F600} and \u{010D}e\u{0161}tina",
    );

    public function testInsertAndFetchRoundTripWithHostileValues(): void
    {
        foreach (self::PAYLOADS as $i => $payload) {
            $id = Db::insert('cms_sections', array('title' => $payload, 'key' => 'k' . $i));
            $row = Db::fetchAssoc('SELECT title FROM cms_sections WHERE section_id = ?', array($id));

            $this->assertSame($payload, $row['title']);
        }

        $this->assertSame(count(self::PAYLOADS), (int)Db::fetchAssoc('SELECT COUNT(*) AS c FROM cms_sections')['c']);
    }

    public function testBoundValueCannotChangeTheStatement(): void
    {
        Db::insert('cms_sections', array('title' => 'a', 'key' => 'one'));
        Db::insert('cms_sections', array('title' => 'b', 'key' => 'two'));

        $rows = Db::fetchAllAssoc('SELECT * FROM cms_sections WHERE `key` = ?', array("one' OR '1'='1"));

        $this->assertSame(array(), $rows);
    }

    public function testUpdateAffectsOnlyTheWhereRow(): void
    {
        $a = Db::insert('cms_sections', array('title' => 'a', 'key' => 'one'));
        Db::insert('cms_sections', array('title' => 'b', 'key' => 'two'));

        $affected = Db::update('cms_sections', array('title' => "it's"), array('section_id' => $a));

        $this->assertSame(1, $affected);
        $this->assertSame(
            array("it's", 'b'),
            array_column(Db::fetchAllAssoc('SELECT title FROM cms_sections ORDER BY section_id'), 'title')
        );
    }

    public function testUpdateRejectsInjectedColumnName(): void
    {
        $id = Db::insert('cms_sections', array('title' => 'a', 'key' => 'one'));

        $this->expectException(DbException::class);
        Db::update('cms_sections', array('title` = 1 --' => 'x'), array('section_id' => $id));
    }

    public function testUpdateRequiresWhere(): void
    {
        $this->expectException(DbException::class);
        Db::update('cms_sections', array('title' => 'x'), array());
    }

    public function testFailedQueryHidesSqlFromTheMessage(): void
    {
        // The details go to the error log, which is expected here.
        $log = ini_set('error_log', '/dev/null');
        try {
            Db::query('SELECT * FROM table_that_does_not_exist WHERE secret = ?', array('x'));
            $this->fail('Expected DbException');
        } catch (DbException $e) {
            $this->assertStringNotContainsString('table_that_does_not_exist', $e->getMessage());
            $this->assertStringNotContainsString('secret', $e->getMessage());
        } finally {
            ini_set('error_log', (string)$log);
        }
    }

    public function testQueriesAreRecordedForTheDebugBar(): void
    {
        $before = Db::getQueriesCount();
        Db::query('SELECT 1');

        $this->assertSame($before + 1, Db::getQueriesCount());
        $this->assertGreaterThanOrEqual(0.0, Db::getAllQueriesEstimatedTime());
    }
}
