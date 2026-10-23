<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Db;

/**
 * Base class for tests that need the MySQL test database.
 * They are skipped when the database is not reachable.
 */
abstract class DbTestCase extends TestCase
{
    protected function setUp(): void
    {
        try {
            Db::query('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database is not available: ' . $e->getMessage());
        }

        Db::query('DELETE FROM cms_users');
        Db::query('DELETE FROM cms_sections');
        Db::query('DELETE FROM cms_sessions');
        Db::query('DELETE FROM cms_sites');
    }
}
