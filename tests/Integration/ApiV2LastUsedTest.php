<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Api\V2\Model;
use Wbengine\Console\Doctor;
use Wbengine\Console\MigrationStatus;
use Wbengine\Db;

/** Connection-local tables, except the migration test which needs information_schema. */
class ApiV2LastUsedTest extends TestCase
{
    protected function setUp(): void
    {
        \Wbengine\User\AccountState::reset();
        try {
            Db::query('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database is not available.');
        }
    }

    protected function tearDown(): void
    {
        if (Db::isConnected()) {
            Db::query('DROP TEMPORARY TABLE IF EXISTS cms_users, cms_api_integrations, cms_api_audit');
        }
    }

    private function tables(bool $lastUsed = true): void
    {
        Db::query('CREATE TEMPORARY TABLE cms_users (user_id INT PRIMARY KEY, role VARCHAR(16) NOT NULL) ENGINE=InnoDB');
        Db::insert('cms_users', ['user_id' => 51, 'role' => 'admin']);
        $sql = preg_replace('/^--.*$/m', '', file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/002-api-v2-integrations.sql'));
        $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $sql);
        foreach (explode(';', $sql) as $statement) {
            if ('' !== trim($statement)) { Db::query($statement); }
        }
        if ($lastUsed) {
            Db::query('ALTER TABLE cms_api_integrations ADD COLUMN last_used_at BIGINT UNSIGNED NULL DEFAULT NULL');
        }
    }

    private function clockModel(int $now): Model
    {
        return new class($now) extends Model {
            public function __construct(public int $now) {}
            protected function now(): int { return $this->now; }
        };
    }

    public function testListReturnsOnlyNonSecretMetadataNewestFirst(): void
    {
        $this->tables();
        $model = new Model();
        $first = $model->provision(51, 'First', ['discovery:read']);
        $second = $model->provision(51, 'Second', Model::SCOPES);
        $model->revoke(51, $first['integration_id']);
        $list = $model->list();
        $this->assertSame([$second['integration_id'], $first['integration_id']], array_column($list, 'integration_id'));
        $this->assertSame(['integration_id', 'name', 'created_by', 'created_at', 'revoked_at', 'scopes', 'last_used_at'], array_keys($list[0]));
        $this->assertSame(Model::SCOPES, $list[0]['scopes']);
        $this->assertNull($list[0]['revoked_at']);
        $this->assertIsInt($list[1]['revoked_at']);
        $this->assertNull($list[0]['last_used_at']);
        $this->assertSame(51, $list[0]['created_by']);
        $dump = json_encode($list);
        $this->assertStringNotContainsString($first['key'], $dump);
        $this->assertStringNotContainsString($second['key'], $dump);
        $this->assertStringNotContainsString(hash('sha256', $second['key']), $dump);
        $this->assertStringNotContainsString('key_hash', $dump);
    }

    public function testLastUsedIsWrittenAtMostOncePerSixtySecondsPerKey(): void
    {
        $this->tables();
        $issued = (new Model())->provision(51, 'Used', ['discovery:read']);
        $other = (new Model())->provision(51, 'Other', ['discovery:read']);
        $id = $issued['integration_id'];
        $used = fn () => Db::fetchAssoc('SELECT last_used_at FROM cms_api_integrations WHERE integration_id = ?', [$id])['last_used_at'];
        $this->assertNull($used());
        $this->assertNotNull($this->clockModel(1000)->authenticate($issued['key']));
        $this->assertSame(1000, $used());
        // Within the interval nothing is written, however many requests arrive.
        foreach ([1001, 1030, 1059] as $now) {
            $this->assertNotNull($this->clockModel($now)->authenticate($issued['key']));
            $this->assertSame(1000, $used());
        }
        $this->assertNotNull($this->clockModel(1060)->authenticate($issued['key']));
        $this->assertSame(1060, $used());
        $this->assertNotNull($this->clockModel(1061)->authenticate($issued['key']));
        $this->assertSame(1060, $used());
        // Another key has its own interval, and a rejected key writes nothing.
        $this->assertNull(Db::fetchAssoc('SELECT last_used_at FROM cms_api_integrations WHERE integration_id = ?', [$other['integration_id']])['last_used_at']);
        $this->assertNull($this->clockModel(2000)->authenticate('wbv2_' . str_repeat('0', 64)));
        (new Model())->revoke(51, $id);
        $this->assertNull($this->clockModel(3000)->authenticate($issued['key']));
        $this->assertSame(1060, $used());
    }

    public function testFailedLastUsedWriteNeverFailsTheRequest(): void
    {
        // Before migration 4.0.0/007 the column does not exist: authentication still succeeds.
        $this->tables(false);
        $issued = (new Model())->provision(51, 'Unmigrated', ['discovery:read']);
        $log = ini_set('error_log', '/dev/null');
        try {
            $identity = $this->clockModel(1000)->authenticate($issued['key']);
        } finally {
            ini_set('error_log', $log);
        }
        $this->assertSame('Unmigrated', $identity['name']);
        $this->assertSame(['discovery:read'], $identity['scopes']);
    }

    public function testMigrationIsIdempotentAndKeepsExistingRows(): void
    {
        $this->assertTrue(Db::fetchAssoc('SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', ['cms_api_integrations', 'last_used_at'])['n'] > 0, 'tests/schema.sql must contain the column');
        Db::query('DELETE FROM cms_api_integrations');
        Db::insert('cms_api_integrations', ['name' => 'Kept', 'key_hash' => str_repeat('a', 64), 'scopes' => '["discovery:read"]', 'created_by' => 1, 'created_at' => 1]);
        try {
            Db::query('ALTER TABLE cms_api_integrations DROP COLUMN last_used_at');
            $this->assertFalse((new MigrationStatus())->inspect(Doctor::SCHEMA_INTEGRATIONS_LAST_USED));
            $sql = preg_replace('/^--.*$/m', '', file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/007-api-integration-last-used.sql'));
            for ($run = 0; $run < 2; $run++) {
                foreach (explode(';', $sql) as $statement) {
                    if ('' !== trim($statement)) {
                        // Plain statements: PREPARE is not allowed inside a prepared statement.
                        Db::getConnection()->query($statement);
                    }
                }
                $this->assertTrue((new MigrationStatus())->inspect(Doctor::SCHEMA_INTEGRATIONS_LAST_USED), 'run ' . $run);
            }
            $column = Db::fetchAssoc('SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', ['cms_api_integrations', 'last_used_at']);
            $this->assertMatchesRegularExpression('/^bigint(\(20\))? unsigned$/', strtolower($column['COLUMN_TYPE']));
            $this->assertSame('YES', $column['IS_NULLABLE']);
            $row = Db::fetchAssoc('SELECT name, last_used_at FROM cms_api_integrations');
            $this->assertSame('Kept', $row['name']);
            $this->assertNull($row['last_used_at']);
        } finally {
            Db::query('DELETE FROM cms_api_integrations');
            if (!(new MigrationStatus())->inspect(Doctor::SCHEMA_INTEGRATIONS_LAST_USED)) {
                Db::query('ALTER TABLE cms_api_integrations ADD COLUMN last_used_at BIGINT UNSIGNED NULL DEFAULT NULL AFTER revoked_at');
            }
        }
    }

    public function testDoctorReportsTheMissingColumnWithTheMigrationPath(): void
    {
        $config = ['apiV2' => ['enabled' => true]];
        $report = (new Doctor())->check($config, sys_get_temp_dir(), true);
        $this->assertArrayHasKey('migration.007', $report);
        $this->assertSame('ok', $report['migration.007']['status']);
        $this->assertSame('ok', $report['migration.002']['status']);
        $skipped = (new Doctor())->check([], sys_get_temp_dir(), false);
        $this->assertSame('skip', $skipped['migration.007']['status']);
        Db::query('ALTER TABLE cms_api_integrations DROP COLUMN last_used_at');
        try {
            $report = (new Doctor())->check($config, sys_get_temp_dir(), true);
            $this->assertSame('fail', $report['migration.007']['status']);
            $this->assertStringContainsString('migrations/4.0.0/007', $report['migration.007']['action']);
        } finally {
            Db::query('ALTER TABLE cms_api_integrations ADD COLUMN last_used_at BIGINT UNSIGNED NULL DEFAULT NULL AFTER revoked_at');
        }
    }
}
