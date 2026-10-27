<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Db;
use Wbengine\Privacy\Erasure;

class PrivacyErasureTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            Db::query('SELECT 1');
        } catch (\Throwable) {
            $this->markTestSkipped('MySQL test database is not available.');
        }

        Db::query('CREATE TEMPORARY TABLE cms_users (
            user_id INT UNSIGNED PRIMARY KEY,
            username VARCHAR(150) NOT NULL,
            email VARCHAR(100) DEFAULT NULL,
            firstname VARCHAR(45) DEFAULT NULL,
            lastname VARCHAR(150) DEFAULT NULL,
            user_ip VARCHAR(45) DEFAULT NULL,
            role VARCHAR(20) NOT NULL
        ) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_sessions (
            session_id VARCHAR(128) PRIMARY KEY,
            user_id INT NOT NULL,
            user_ip VARCHAR(45) DEFAULT NULL
        ) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_refresh_tokens (
            id BIGINT UNSIGNED PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            ip VARCHAR(45) DEFAULT NULL
        ) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_api_integrations (
            integration_id INT UNSIGNED PRIMARY KEY,
            created_by INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_api_audit (
            audit_id BIGINT UNSIGNED PRIMARY KEY,
            central_actor VARCHAR(128) DEFAULT NULL,
            local_user_id INT UNSIGNED DEFAULT NULL
        ) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_api_changes (
            change_id BIGINT UNSIGNED PRIMARY KEY,
            central_actor VARCHAR(128) DEFAULT NULL
        ) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_api_revisions (
            revision_id BIGINT UNSIGNED PRIMARY KEY,
            central_actor VARCHAR(128) DEFAULT NULL
        ) ENGINE=InnoDB');
        Db::query('CREATE TEMPORARY TABLE cms_analytics_daily (
            metric_date DATE PRIMARY KEY,
            page_views BIGINT UNSIGNED NOT NULL
        ) ENGINE=InnoDB');

        Db::insert('cms_users', ['user_id' => 101, 'username' => 'private-user', 'email' => 'private@example.test', 'firstname' => 'Sensitive', 'lastname' => 'Person', 'user_ip' => '192.0.2.101', 'role' => 'editor']);
        Db::insert('cms_users', ['user_id' => 202, 'username' => 'unrelated-user', 'email' => 'other@example.test', 'firstname' => 'Other', 'lastname' => 'Person', 'user_ip' => '192.0.2.202', 'role' => 'admin']);
        Db::insert('cms_sessions', ['session_id' => 'session-private', 'user_id' => 101, 'user_ip' => '192.0.2.102']);
        Db::insert('cms_sessions', ['session_id' => 'session-other', 'user_id' => 202, 'user_ip' => '192.0.2.203']);
        Db::insert('cms_refresh_tokens', ['id' => 1, 'user_id' => 101, 'ip' => '192.0.2.103']);
        Db::insert('cms_refresh_tokens', ['id' => 2, 'user_id' => 202, 'ip' => '192.0.2.204']);
        Db::insert('cms_api_integrations', ['integration_id' => 1, 'created_by' => 101]);
        Db::insert('cms_api_integrations', ['integration_id' => 2, 'created_by' => 202]);
        Db::insert('cms_api_audit', ['audit_id' => 1, 'central_actor' => 'private@example.test', 'local_user_id' => 101]);
        Db::insert('cms_api_audit', ['audit_id' => 2, 'central_actor' => 'other@example.test', 'local_user_id' => 202]);
        Db::insert('cms_api_changes', ['change_id' => 1, 'central_actor' => 'private-user']);
        Db::insert('cms_api_revisions', ['revision_id' => 1, 'central_actor' => 'private@example.test']);
        Db::insert('cms_analytics_daily', ['metric_date' => '2026-10-27', 'page_views' => 42]);
    }

    protected function tearDown(): void
    {
        if (Db::isConnected()) {
            Db::query('DROP TEMPORARY TABLE IF EXISTS cms_users, cms_sessions, cms_refresh_tokens, cms_api_integrations, cms_api_audit, cms_api_changes, cms_api_revisions, cms_analytics_daily');
        }
    }

    public function testInspectionReturnsRedactedActionsAndStableConfirmationFingerprint(): void
    {
        $report = (new Erasure())->inspect(101);

        self::assertSame(101, $report['user_id']);
        self::assertFalse($report['blocked']);
        self::assertSame(1, $report['actions']['account']['records']);
        self::assertSame(1, $report['actions']['sessions']['records']);
        self::assertSame(1, $report['actions']['refresh_tokens']['records']);
        self::assertSame(1, $report['actions']['api_audit']['records']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $report['confirmation']);

        $json = json_encode($report, JSON_THROW_ON_ERROR);
        foreach (['private-user', 'private@example.test', 'Sensitive', '192.0.2.101', 'session-private'] as $value) {
            self::assertStringNotContainsString($value, $json);
        }

        self::assertSame($report['confirmation'], (new Erasure())->inspect(101)['confirmation']);
        Db::query('UPDATE cms_users SET email = ? WHERE user_id = ?', ['changed@example.test', 101]);
        self::assertNotSame($report['confirmation'], (new Erasure())->inspect(101)['confirmation']);
    }

    public function testEraseRequiresCurrentConfirmationAndLeavesNoTargetRecords(): void
    {
        $erasure = new Erasure();
        $report = $erasure->inspect(101);
        self::assertSame('erased', $erasure->erase(101, $report['confirmation']));
        self::assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS count FROM cms_users WHERE user_id = 101')['count']);
        self::assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS count FROM cms_users WHERE user_id = 202')['count']);
        self::assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS count FROM cms_sessions WHERE user_id = 101')['count']);
        self::assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS count FROM cms_refresh_tokens WHERE user_id = 101')['count']);
        self::assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS count FROM cms_api_integrations WHERE created_by = 101')['count']);
        self::assertNull(Db::fetchAssoc('SELECT local_user_id FROM cms_api_audit WHERE audit_id = 1')['local_user_id']);
        self::assertSame('[erased]', Db::fetchAssoc('SELECT central_actor FROM cms_api_audit WHERE audit_id = 1')['central_actor']);
        self::assertSame('[erased]', Db::fetchAssoc('SELECT central_actor FROM cms_api_changes WHERE change_id = 1')['central_actor']);
        self::assertSame('[erased]', Db::fetchAssoc('SELECT central_actor FROM cms_api_revisions WHERE revision_id = 1')['central_actor']);
        self::assertSame(42, (int) Db::fetchAssoc('SELECT page_views FROM cms_analytics_daily WHERE metric_date = ?', ['2026-10-27'])['page_views']);
        self::assertSame('already_erased', $erasure->erase(101, str_repeat('0', 64)));
    }

    public function testEraseRejectsStaleOrMalformedConfirmationWithoutWriting(): void
    {
        $erasure = new Erasure();
        $confirmation = $erasure->inspect(101)['confirmation'];
        Db::query('UPDATE cms_sessions SET user_ip = ? WHERE user_id = ?', ['192.0.2.111', 101]);
        try {
            $erasure->erase(101, $confirmation);
            self::fail('Expected stale confirmation to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The inspection has changed. Run privacy:inspect again.', $exception->getMessage());
        }
        self::assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS count FROM cms_users WHERE user_id = 101')['count']);
        try {
            $erasure->erase(101, 'invalid');
            self::fail('Expected malformed confirmation to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('A valid 64-character confirmation hash is required.', $exception->getMessage());
        }
    }

    public function testEraseProtectsLastAdministrator(): void
    {
        Db::query('DELETE FROM cms_users WHERE user_id = 101');
        Db::query('DELETE FROM cms_users WHERE user_id = 202');
        Db::insert('cms_users', ['user_id' => 303, 'username' => 'only-admin', 'email' => null, 'firstname' => null, 'lastname' => null, 'user_ip' => null, 'role' => 'admin']);
        $erasure = new Erasure();
        self::assertTrue($erasure->inspect(303)['blocked']);
        try {
            $erasure->erase(303, $erasure->inspect(303)['confirmation']);
            self::fail('Expected the last administrator to be protected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The last administrator cannot be erased.', $exception->getMessage());
        }
        self::assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS count FROM cms_users WHERE user_id = 303')['count']);
    }

    public function testDatabaseFailureRollsBackAllDatabaseMutations(): void
    {
        Db::query('ALTER TABLE cms_api_integrations ADD CONSTRAINT positive_creator CHECK (created_by > 0)');
        $erasure = new Erasure();
        try {
            $erasure->erase(101, $erasure->inspect(101)['confirmation']);
            self::fail('Expected the constraint violation to fail the transaction.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Privacy erasure failed and all database changes were rolled back.', $exception->getMessage());
        }
        self::assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS count FROM cms_users WHERE user_id = 101')['count']);
        self::assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS count FROM cms_sessions WHERE user_id = 101')['count']);
        self::assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS count FROM cms_api_audit WHERE local_user_id = 101')['count']);
    }
}
