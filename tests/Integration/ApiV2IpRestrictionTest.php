<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Api;
use Wbengine\Api\V2;
use Wbengine\Api\V2\Model;
use Wbengine\Api\V2\Problem;
use Wbengine\Db;
use Wbengine\RateLimit\Limiter;

/** Per-key address restriction. Connection-local tables, the new columns are added like migration 6.1.0/011 does. */
final class ApiV2IpRestrictionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        \Wbengine\User\AccountState::reset();
        try {
            Db::query('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database is not available.');
        }
        $this->dir = sys_get_temp_dir() . '/wb-ip-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (Db::isConnected()) {
            Db::query('DROP TEMPORARY TABLE IF EXISTS cms_users, cms_api_integrations, cms_api_audit');
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) { @unlink($file); }
        @rmdir($this->dir);
    }

    private function tables(bool $ips = true): void
    {
        Db::query('CREATE TEMPORARY TABLE cms_users (user_id INT PRIMARY KEY, role VARCHAR(16) NOT NULL) ENGINE=InnoDB');
        Db::insert('cms_users', ['user_id' => 61, 'role' => 'admin']);
        $sql = preg_replace('/^--.*$/m', '', file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/002-api-v2-integrations.sql'));
        $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $sql);
        foreach (explode(';', $sql) as $statement) {
            if ('' !== trim($statement)) { Db::query($statement); }
        }
        Db::query('ALTER TABLE cms_api_integrations ADD COLUMN last_used_at BIGINT UNSIGNED NULL DEFAULT NULL');
        Db::query('ALTER TABLE cms_api_audit ADD COLUMN detail VARCHAR(128) NULL DEFAULT NULL');
        if ($ips) {
            Db::query('ALTER TABLE cms_api_integrations ADD COLUMN allowed_ips TEXT NULL DEFAULT NULL');
            Db::query('ALTER TABLE cms_api_integrations ADD COLUMN last_used_ip VARCHAR(45) NULL DEFAULT NULL');
        }
    }

    public function testProvisionStoresNormalizedAddressesAndListShowsThem(): void
    {
        $this->tables();
        $model = new Model();
        $key = $model->provision(61, 'Restricted', ['discovery:read'], ['203.0.113.5', '198.51.100.0/24', '2001:0db8::1']);
        $other = $model->provision(61, 'Open', ['discovery:read']);
        $list = $model->list();
        $byId = array_column($list, null, 'integration_id');
        $this->assertSame(['203.0.113.5', '198.51.100.0/24', '2001:db8::1'], $byId[$key['integration_id']]['allowed_ips']);
        $this->assertSame([], $byId[$other['integration_id']]['allowed_ips']);
        $this->assertNull($byId[$key['integration_id']]['last_used_ip']);
        $this->expectException(Problem::class);
        $model->provision(61, 'Bad', ['discovery:read'], ['example.com']);
    }

    public function testAnAddressOutsideTheListIsDeniedAndNotRecordedAsUse(): void
    {
        $this->tables();
        $model = new Model();
        $key = $model->provision(61, 'Restricted', ['discovery:read'], ['198.51.100.0/24']);
        $denied = $model->authenticate($key['key'], '203.0.113.9');
        $this->assertTrue($denied['ip_denied']);
        $row = Db::fetchAssoc('SELECT last_used_at, last_used_ip FROM cms_api_integrations');
        $this->assertNull($row['last_used_at']);
        $this->assertNull($row['last_used_ip']);
        $allowed = $model->authenticate($key['key'], '198.51.100.77');
        $this->assertArrayNotHasKey('ip_denied', $allowed);
        $this->assertSame('198.51.100.77', Db::fetchAssoc('SELECT last_used_ip FROM cms_api_integrations')['last_used_ip']);
    }

    public function testWithoutAClientAddressNothingIsCheckedAndAnOpenKeyRecordsWhereItWasUsedFrom(): void
    {
        $this->tables();
        $model = new Model();
        $restricted = $model->provision(61, 'Restricted', ['discovery:read'], ['198.51.100.1']);
        $this->assertArrayNotHasKey('ip_denied', $model->authenticate($restricted['key']));
        $open = $model->provision(61, 'Open', ['discovery:read']);
        $this->assertArrayNotHasKey('ip_denied', $model->authenticate($open['key'], '2001:db8::7'));
        $this->assertSame('2001:db8::7', Db::fetchAssoc('SELECT last_used_ip FROM cms_api_integrations WHERE integration_id = ?', [$open['integration_id']])['last_used_ip']);
    }

    public function testTheLastUsedAddressIsUpdatedAtOnceWhenItChanges(): void
    {
        $this->tables();
        $clock = new class extends Model { public int $t = 1000; protected function now(): int { return $this->t; } };
        $key = $clock->provision(61, 'Moving', ['discovery:read']);
        $clock->authenticate($key['key'], '203.0.113.1');
        $clock->t = 1010;
        $clock->authenticate($key['key'], '203.0.113.1');
        $this->assertSame(1000, (int) Db::fetchAssoc('SELECT last_used_at FROM cms_api_integrations')['last_used_at']);
        $clock->authenticate($key['key'], '203.0.113.2');
        $row = Db::fetchAssoc('SELECT last_used_at, last_used_ip FROM cms_api_integrations');
        $this->assertSame([1010, '203.0.113.2'], [(int) $row['last_used_at'], $row['last_used_ip']]);
    }

    public function testRestrictChangesAndClearsTheListOfAnActiveKeyOnly(): void
    {
        $this->tables();
        $model = new Model();
        $key = $model->provision(61, 'Key', ['discovery:read']);
        $this->assertTrue($model->restrict(61, $key['integration_id'], ['203.0.113.0/24']));
        $this->assertTrue($model->authenticate($key['key'], '203.0.113.200') !== null);
        $this->assertTrue($model->authenticate($key['key'], '198.51.100.1')['ip_denied']);
        $this->assertTrue($model->restrict(61, $key['integration_id'], []));
        $this->assertArrayNotHasKey('ip_denied', $model->authenticate($key['key'], '198.51.100.1'));
        $this->assertSame(2, (int) Db::fetchAssoc("SELECT COUNT(*) AS n FROM cms_api_audit WHERE action = 'integration.restrict'")['n']);
        $this->assertStringNotContainsString('203.0.113', json_encode(Db::fetchAllAssoc('SELECT * FROM cms_api_audit')), 'the audit stores no address');
        $model->revoke(61, $key['integration_id']);
        $this->assertFalse($model->restrict(61, $key['integration_id'], ['203.0.113.1']));
        $this->expectException(Problem::class);
        $model->restrict(61, $key['integration_id'], ['nope']);
    }

    public function testTheControllerAnswers403ForAWrongAddressAndAuditsOnlyTheReason(): void
    {
        $this->tables();
        $model = new Model();
        $key = $model->provision(61, 'Restricted', ['discovery:read'], ['198.51.100.0/24']);
        $controller = new V2(new Api(), $model, null, new Limiter($this->dir . '/limits'));
        $denied = $controller->handle('GET', '/api/v2/identity', 'Bearer ' . $key['key'], true, null, '', [], null, '203.0.113.9');
        $this->assertSame(403, $denied['status']);
        $this->assertSame('ip_not_allowed', $denied['body']['error']['code']);
        $audit = Db::fetchAssoc("SELECT outcome, detail FROM cms_api_audit WHERE action = 'identity.read' ORDER BY audit_id DESC LIMIT 1");
        $this->assertSame(['forbidden', 'ip_not_allowed'], [$audit['outcome'], $audit['detail']]);
        $this->assertStringNotContainsString('203.0.113.9', json_encode(Db::fetchAllAssoc('SELECT * FROM cms_api_audit')));
        $ok = $controller->handle('GET', '/api/v2/identity', 'Bearer ' . $key['key'], true, null, '', [], null, '198.51.100.20');
        $this->assertSame(200, $ok['status']);
    }

    public function testBeforeTheMigrationEverythingKeepsWorkingWithoutRestrictions(): void
    {
        $this->tables(false);
        $model = new Model();
        $this->assertFalse($model->supportsIpRestriction());
        $key = $model->provision(61, 'Old schema', ['discovery:read']);
        $this->assertArrayNotHasKey('ip_denied', $model->authenticate($key['key'], '203.0.113.9'));
        $this->assertSame(['integration_id', 'name', 'created_by', 'created_at', 'revoked_at', 'scopes', 'last_used_at'], array_keys($model->list()[0]));
        $this->assertNotNull(Db::fetchAssoc('SELECT last_used_at FROM cms_api_integrations')['last_used_at']);
    }
}
