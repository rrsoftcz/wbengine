<?php
namespace Wbengine\Tests\Integration;

use Wbengine\Db;

/** audit:list, audit:prune and the change log rows of user:create, run as real CLI processes. */
final class ChangeLogCliTest extends DbTestCase
{
    private const PW = 'a long enough passphrase';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['cms_api_changes', 'cms_api_audit', 'cms_api_drafts', 'cms_api_revisions'] as $table) { Db::query('DELETE FROM ' . $table); }
    }

    private function cli(array $arguments, string $stdin = ''): array
    {
        $config = json_decode(file_get_contents(dirname(__DIR__) . '/config/Settings.test.json'), true);
        $config['dbAdapterDefinition']['hostname'] = getenv('WB_TEST_DB_HOST') ?: 'db';
        $file = tempnam(sys_get_temp_dir(), 'wb-audit-') . '.json';
        file_put_contents($file, json_encode($config));
        try {
            $process = proc_open(array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/bin/wbengine.php'], $arguments, ['--config=' . $file]), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            fwrite($pipes[0], $stdin); fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            return [proc_close($process), $out, $err];
        } finally { unlink($file); unlink(substr($file, 0, -5)); }
    }

    private function change(string $resource, string $id, int $at, string $secret = 'S3CRET-BODY'): void
    {
        Db::insert('cms_api_changes', ['resource' => $resource, 'resource_id' => $id, 'action' => 'update', 'before_data' => json_encode(['title' => $secret]), 'after_data' => '{}', 'integration_id' => 1, 'central_actor' => 'editor-9', 'created_at' => $at]);
    }

    public function testHelpListsTheAuditCommands(): void
    {
        [$code, $out] = $this->cli(['help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('audit:prune [--days=N] [--dry-run]', $out);
        self::assertStringContainsString('audit:list [--limit=N] [--resource=NAME] [--actor=ACTOR]', $out);
    }

    public function testListPrintsRecentChangesWithoutBeforeAndAfterData(): void
    {
        $this->change('pages', '1', time() - 20);
        $this->change('sections', '2', time() - 10);
        [$code, $out] = $this->cli(['audit:list', '--limit=5']);
        self::assertSame(0, $code, $out);
        self::assertStringNotContainsString('S3CRET-BODY', $out);
        $changes = json_decode($out, true, 512, JSON_THROW_ON_ERROR)['changes'];
        self::assertSame(['sections', 'pages'], array_column($changes, 'resource'));
        self::assertSame('v2', $changes[0]['source']);
        self::assertSame('editor-9', $changes[0]['actor']);
        [, $filtered] = $this->cli(['audit:list', '--resource=pages', '--actor=editor-9']);
        self::assertSame(['pages'], array_column(json_decode($filtered, true)['changes'], 'resource'));
    }

    public function testListAndPruneRefuseInvalidOptions(): void
    {
        foreach ([['audit:list', '--limit=0'], ['audit:list', '--limit=501'], ['audit:list', '--limit=abc'], ['audit:prune', '--days=29'], ['audit:prune', '--days=3651'], ['audit:prune', '--days=x'], ['audit:prune', '--dry-run=yes'], ['audit:prune', '--limit=5'], ['audit:list', '--days=40']] as $arguments) {
            [$code] = $this->cli($arguments);
            self::assertSame(2, $code, implode(' ', $arguments));
        }
    }

    public function testPruneDryRunCountsAndPruneDeletesThenLogsItself(): void
    {
        $this->change('pages', '1', time() - 500 * 86400);
        $this->change('pages', '1', time() - 450 * 86400);
        $this->change('pages', '2', time() - 5 * 86400);
        Db::insert('cms_api_audit', ['action' => 'old', 'outcome' => 'allowed', 'created_at' => time() - 500 * 86400]);

        [$code, $out] = $this->cli(['audit:prune', '--dry-run']);
        self::assertSame(0, $code, $out);
        $dry = json_decode($out, true);
        self::assertSame([1, 1, true, 365], [$dry['audit'], $dry['changes'], $dry['dry_run'], $dry['audit_days']]);
        self::assertSame(3, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_changes')['n']);

        [$code, $out] = $this->cli(['audit:prune']);
        self::assertSame(0, $code, $out);
        self::assertSame(1, json_decode($out, true)['changes']);
        self::assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_audit WHERE action = ?', ['old'])['n']);
        $rows = Db::fetchAllAssoc('SELECT resource, action, source, central_actor FROM cms_api_changes ORDER BY change_id');
        self::assertSame(['pages:update', 'pages:update', 'audit:prune'], array_map(static fn ($r) => $r['resource'] . ':' . $r['action'], $rows));
        self::assertSame('cli', $rows[2]['source']);
        self::assertStringStartsWith('cli:', $rows[2]['central_actor']);
    }

    public function testUserCreateFromTheCliIsLoggedWithTheOsUserAndNoPassword(): void
    {
        [$code, $out, $err] = $this->cli(['user:create', '--email=boss@corp.cz', '--role=admin'], self::PW . "\n");
        self::assertSame(0, $code, $out . $err);
        $row = Db::fetchAssoc('SELECT * FROM cms_api_changes');
        self::assertSame('user', $row['resource']);
        self::assertSame('create', $row['action']);
        self::assertSame('cli', $row['source']);
        self::assertMatchesRegularExpression('/^cli:[A-Za-z0-9._-]+$/D', $row['central_actor']);
        self::assertNull($row['local_user_id']);
        self::assertSame('boss@corp.cz', json_decode($row['after_data'], true)['email']);
        self::assertStringNotContainsString(self::PW, json_encode($row));
        self::assertStringNotContainsString('$2y$', json_encode($row));
    }
}
