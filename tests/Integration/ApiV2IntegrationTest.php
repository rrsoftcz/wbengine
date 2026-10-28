<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Api;
use Wbengine\Api\V2;
use Wbengine\Api\V2\Model;
use Wbengine\Api\V2\Problem;
use Wbengine\Db;

/** Uses only connection-local temporary tables; shared fixture rows are untouched. */
class ApiV2IntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            Db::query('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database is not available.');
        }
        Db::query('CREATE TEMPORARY TABLE cms_users (user_id INT PRIMARY KEY, role VARCHAR(16) NOT NULL) ENGINE=InnoDB');
        Db::insert('cms_users', ['user_id' => 11, 'role' => 'admin']);
        Db::insert('cms_users', ['user_id' => 12, 'role' => 'editor']);
        $sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/002-api-v2-integrations.sql');
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $sql);
        foreach (explode(';', $sql) as $statement) {
            if ('' !== trim($statement)) {
                Db::query($statement);
            }
        }
        // migrations/4.0.0/007, applied directly: information_schema does not list temporary tables.
        Db::query('ALTER TABLE cms_api_integrations ADD COLUMN last_used_at BIGINT UNSIGNED NULL DEFAULT NULL');
    }

    protected function tearDown(): void
    {
        if (Db::isConnected()) {
            Db::query('DROP TEMPORARY TABLE IF EXISTS cms_users, cms_api_integrations, cms_api_audit');
        }
    }

    public function testProvisionStoresHashAndDoesNotReturnSecretDuringAuthentication(): void
    {
        $model = new Model();
        $issued = $model->provision(11, "Central's connection", ['discovery:read']);
        self::assertMatchesRegularExpression('/^wbv2_[a-f0-9]{64}$/D', $issued['key']);
        $row = Db::fetchAssoc('SELECT * FROM cms_api_integrations');
        self::assertSame(hash('sha256', $issued['key']), $row['key_hash']);
        self::assertStringNotContainsString($issued['key'], json_encode($row));
        $identity = $model->authenticate($issued['key']);
        self::assertSame("Central's connection", $identity['name']);
        self::assertArrayNotHasKey('key', $identity);
        self::assertArrayNotHasKey('key_hash', $identity);
        self::assertNull($model->authenticate("' OR 1=1 --"));
        self::assertNull($model->authenticate('wbv2_' . str_repeat('0', 64)));
    }

    public function testRevocationIsImmediateAndRecordedWithAdministratorIdentity(): void
    {
        $model = new Model();
        $issued = $model->provision(11, 'Central', ['discovery:read']);
        $api = new V2(new Api(), $model);
        self::assertSame(200, $api->handle('GET', '/api/v2/identity', 'Bearer ' . $issued['key'], true)['status']);
        self::assertTrue($model->revoke(11, $issued['integration_id']));
        self::assertNull($model->authenticate($issued['key']));
        self::assertSame(401, $api->handle('GET', '/api/v2/identity', 'Bearer ' . $issued['key'], true)['status']);
        self::assertFalse($model->revoke(11, $issued['integration_id']));
        $audit = Db::fetchAssoc('SELECT * FROM cms_api_audit WHERE action = ?', ['integration.revoke']);
        self::assertSame(11, $audit['local_user_id']);
        self::assertSame('allowed', $audit['outcome']);
    }

    public function testCurrentDatabaseRoleIsRequiredForProvisionAndRevocation(): void
    {
        $model = new Model();
        $issued = $model->provision(11, 'Central', ['discovery:read']);
        Db::query('UPDATE cms_users SET role = ? WHERE user_id = ?', ['editor', 11]);
        foreach ([fn () => $model->provision(11, 'Denied', ['discovery:read']), fn () => $model->revoke(11, $issued['integration_id']), fn () => $model->provision(12, 'Denied', ['discovery:read'])] as $work) {
            try {
                $work();
                self::fail('Non-administrator operation succeeded');
            } catch (Problem $e) {
                self::assertSame(403, $e->getCode());
            }
        }
        self::assertNotNull($model->authenticate($issued['key']));
        self::assertSame(1, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testWrongScopeAndCentralActorAreAuditedWithoutGrantingRoles(): void
    {
        $model = new Model();
        $issued = $model->provision(11, 'Restricted', ['articles:read']);
        $response = (new V2(new Api(), $model))->handle('GET', '/api/v2/identity', 'Bearer ' . $issued['key'], true, "admin' OR 1=1");
        self::assertSame(403, $response['status']);
        $audit = Db::fetchAssoc('SELECT * FROM cms_api_audit WHERE action = ?', ['identity.read']);
        self::assertSame($issued['integration_id'], $audit['integration_id']);
        self::assertSame("admin' OR 1=1", $audit['central_actor']);
        self::assertNull($audit['local_user_id']);
        self::assertSame('forbidden', $audit['outcome']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nestedOperations')]
    public function testExistingTransactionIsNeverCommittedOrRolledBackByKeyOperations(string $operation, bool $implicit): void
    {
        $model = new Model();
        $issued = $model->provision(11, 'Existing key', ['discovery:read']);
        $connection = Db::getConnection();
        if ($implicit) {
            $connection->autocommit(false);
        } else {
            $connection->begin_transaction();
        }
        $problem = null;
        try {
            Db::insert('cms_users', ['user_id' => 13, 'role' => 'user']);
            try {
                if ('provision' === $operation) {
                    $model->provision(11, 'Must not exist', ['discovery:read']);
                } else {
                    $model->revoke(11, $issued['integration_id']);
                }
            } catch (Problem $e) {
                $problem = $e;
            }
            // Rejection must leave caller-owned pending changes intact.
            self::assertSame(1, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_users WHERE user_id = 13')['n']);
        } finally {
            $connection->rollback();
            $connection->autocommit(true);
        }
        self::assertSame(0, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_users WHERE user_id = 13')['n'], 'The key operation committed caller-owned work.');
        self::assertInstanceOf(Problem::class, $problem);
        self::assertSame('nested_transaction', $problem->errorCode);
        self::assertSame(409, $problem->getCode());
        self::assertSame(1, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
        self::assertNotNull($model->authenticate($issued['key']));
    }

    public static function nestedOperations(): array
    {
        return [
            'provision-explicit' => ['provision', false],
            'revoke-explicit' => ['revoke', false],
            'provision-autocommit-off' => ['provision', true],
            'revoke-autocommit-off' => ['revoke', true],
        ];
    }

    public function testAuditFailureRollsBackRevocationAndLeavesKeyActive(): void
    {
        $model = new Model();
        $issued = $model->provision(11, 'Still active', ['discovery:read']);
        Db::query('DROP TEMPORARY TABLE cms_api_audit');
        Db::query('CREATE TEMPORARY TABLE cms_api_audit (unrelated INT) ENGINE=InnoDB');
        $caught = false;
        $log = ini_set('error_log', '/dev/null');
        try {
            $model->revoke(11, $issued['integration_id']);
        } catch (\Wbengine\Db\Exception\DbException $e) {
            $caught = true;
        } finally {
            ini_set('error_log', $log);
        }
        self::assertTrue($caught);
        self::assertNotNull($model->authenticate($issued['key']));
        self::assertNull(Db::fetchAssoc('SELECT revoked_at FROM cms_api_integrations')['revoked_at']);
    }

    public function testBoundedAuditMaintenanceRequiresAdministratorAndPreservesRecentRecords(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Db::insert('cms_api_audit', ['action' => 'identity.read', 'outcome' => 'unauthorized', 'created_at' => time() - 40 * 86400]);
        }
        Db::insert('cms_api_audit', ['action' => 'identity.read', 'outcome' => 'allowed', 'created_at' => time()]);
        $model = new Model();
        self::assertSame(2, $model->pruneAudit(11, 30, 2));
        self::assertSame(3, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_audit WHERE created_at < ?', [time() - 30 * 86400])['n']);
        self::assertSame(1, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_audit WHERE action = ? AND outcome = ?', ['identity.read', 'allowed'])['n']);
        try { $model->pruneAudit(12, 30, 2); self::fail('Non-admin maintenance accepted'); }
        catch (Problem $e) { self::assertSame(403, $e->getCode()); }
    }

    public function testPermissiveStorageCannotTruncateKeyNameOrAuditActor(): void
    {
        $oldMode = Db::fetchAssoc('SELECT @@SESSION.sql_mode AS mode')['mode'];
        Db::query("SET SESSION sql_mode = ''");
        Db::query('ALTER TABLE cms_api_integrations MODIFY name VARCHAR(3) NOT NULL');
        try {
            try { (new Model())->provision(11, 'Long name', ['discovery:read']); self::fail('Truncated key metadata accepted'); }
            catch (Problem $e) { self::assertSame('storage_mismatch', $e->errorCode); }
            self::assertSame(0, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
        } finally { Db::query('SET SESSION sql_mode = ?', [$oldMode]); }
    }

    public function testAuditFailureRollsBackProvisioning(): void
    {
        Db::query('DROP TEMPORARY TABLE cms_api_audit');
        Db::query('CREATE TEMPORARY TABLE cms_api_audit (unrelated INT) ENGINE=InnoDB');
        $caught = false;
        $log = ini_set('error_log', '/dev/null');
        try {
            (new Model())->provision(11, 'Cannot audit', ['discovery:read']);
        } catch (\Wbengine\Db\Exception\DbException $e) {
            $caught = true;
        } finally {
            ini_set('error_log', $log);
        }
        self::assertTrue($caught);
        self::assertSame(0, Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }
}
