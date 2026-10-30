<?php
namespace Wbengine\Tests\Integration;

use Wbengine\Audit\ChangeLog;
use Wbengine\Audit\Retention;
use Wbengine\Config;
use Wbengine\Db;
use Wbengine\User\Role;

/** Real tables (tests/schema.sql): v1 and CLI write paths, redaction in storage, retention. */
final class ChangeLogTest extends ApiTestCase
{
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['cms_api_changes', 'cms_api_audit', 'cms_api_drafts', 'cms_api_revisions'] as $table) { Db::query('DELETE FROM ' . $table); }
        $this->admin = $this->createUser('admin@example.com', Role::ADMIN);
        $this->createUser('second-admin@example.com', Role::ADMIN);
    }

    private function changes(): array
    {
        return Db::fetchAllAssoc('SELECT * FROM cms_api_changes ORDER BY change_id') ?: [];
    }

    public function testRedactionAppliesToStoredRowsOfCliAndHistoricV1ButNotToV2(): void
    {
        $actor = ['local_user_id' => $this->admin];
        ChangeLog::record('user', 1, 'update', ['token' => 'T1'], ['password' => 'P1', 'ac_key' => 'K1', 'title' => 'x'], $actor, 'v1');
        ChangeLog::record('user', 2, 'update', null, ['secret' => 'S2'], ChangeLog::cliActor(), 'cli');
        $rows = $this->changes();
        $dump = json_encode($rows);
        foreach (['T1', 'P1', 'K1', 'S2'] as $secret) { $this->assertStringNotContainsString($secret, $dump); }
        $this->assertSame('x', json_decode($rows[0]['after_data'], true)['title']);
        $this->assertMatchesRegularExpression('/^cli:/', $rows[1]['central_actor']);
        $this->assertSame('cli', $rows[1]['source']);
        $this->assertNull($rows[1]['local_user_id']);
        // v2 snapshots feed structure recovery and are stored as given.
        ChangeLog::record('sections', 3, 'create', null, ['key' => 'central'], ['integration_id' => 5], 'v2');
        $this->assertSame('central', json_decode($this->changes()[2]['after_data'], true)['key']);
    }

    public function testRecentListsNewestFirstFiltersAndCarriesNoBodies(): void
    {
        ChangeLog::record('user', 1, 'create', null, ['email' => 'a@example.com'], ['local_user_id' => 7], 'v1');
        ChangeLog::record('section', 2, 'update', ['title' => 'a'], ['title' => 'b'], ['local_user_id' => 7], 'v1');
        ChangeLog::record('pages', 3, 'update', ['title' => 'a'], ['title' => 'b'], ['integration_id' => 4, 'central_actor' => 'editor-9'], 'v2');
        $all = ChangeLog::recent(10);
        $this->assertSame(['pages', 'section', 'user'], array_column($all, 'resource'));
        $this->assertArrayNotHasKey('before_data', $all[0]);
        $this->assertArrayNotHasKey('after_data', $all[0]);
        $this->assertSame('editor-9', $all[0]['actor']);
        $this->assertSame(4, $all[0]['integration_id']);
        $this->assertSame(['section', 'user'], array_column(ChangeLog::recent(10, null, '7'), 'resource'));
        $this->assertSame(['pages'], array_column(ChangeLog::recent(10, null, 'editor-9'), 'resource'));
        $this->assertSame(['user'], array_column(ChangeLog::recent(10, 'user'), 'resource'));
        $this->assertCount(1, ChangeLog::recent(1));
    }

    public function testPruneRemovesOldRowsInBatchesKeepsRecentOnesTheLatestChangePerResourceAndDraftRevisions(): void
    {
        $old = time() - 400 * 86400; $recent = time() - 10 * 86400;
        foreach ([[$old, 'a'], [$old, 'b'], [$recent, 'c']] as [$at, $action]) {
            Db::insert('cms_api_audit', ['action' => $action, 'outcome' => 'allowed', 'created_at' => $at]);
        }
        // Resource 1: two old changes, the newest of them must stay. Resource 2: one old, one recent.
        foreach ([[1, $old], [1, $old], [2, $old], [2, $recent]] as [$id, $at]) {
            Db::insert('cms_api_changes', ['resource' => 'pages', 'resource_id' => (string) $id, 'action' => 'update', 'integration_id' => 1, 'created_at' => $at]);
        }
        $kept = Db::insert('cms_api_revisions', ['article_id' => 5, 'payload' => '{}', 'action' => 'save', 'integration_id' => 1, 'created_at' => $old]);
        Db::insert('cms_api_revisions', ['article_id' => 5, 'payload' => '{}', 'action' => 'save', 'integration_id' => 1, 'created_at' => $old]);
        Db::insert('cms_api_revisions', ['article_id' => 5, 'payload' => '{}', 'action' => 'save', 'integration_id' => 1, 'created_at' => $recent]);
        Db::insert('cms_api_drafts', ['article_id' => 5, 'source_etag' => str_repeat('a', 66), 'payload' => '{}', 'revision_id' => $kept]);

        $dry = (new Retention())->prune(365, 365, true);
        $this->assertSame(['audit' => 2, 'changes' => 2, 'revisions' => 1, 'dry_run' => true, 'audit_days' => 365, 'revision_days' => 365], $dry);
        $this->assertCount(4, $this->changes());

        $result = (new Retention())->prune(365, 365);
        $this->assertSame([2, 2, 1], [$result['audit'], $result['changes'], $result['revisions']]);
        $this->assertSame(['c'], array_column(Db::fetchAllAssoc('SELECT action FROM cms_api_audit'), 'action'));
        $this->assertCount(2, $this->changes());
        $this->assertSame(2, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_revisions')['n']);
        $this->assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_revisions WHERE revision_id = ?', [$kept])['n']);
        // Idempotent.
        $this->assertSame([0, 0, 0], array_values(array_intersect_key((new Retention())->prune(365, 365), array_flip(['audit', 'changes', 'revisions']))));
    }

    public function testPruneUsesConfiguredRetentionAndRefusesValuesOutsideTheBounds(): void
    {
        $previous = Config::$config;
        try {
            Config::$config = (object) ['audit' => (object) ['retentionDays' => 60, 'revisionRetentionDays' => 90]];
            Db::insert('cms_api_audit', ['action' => 'old', 'outcome' => 'allowed', 'created_at' => time() - 70 * 86400]);
            Db::insert('cms_api_audit', ['action' => 'new', 'outcome' => 'allowed', 'created_at' => time() - 50 * 86400]);
            $result = (new Retention())->prune();
            $this->assertSame([60, 90, 1], [$result['audit_days'], $result['revision_days'], $result['audit']]);
            foreach ([29, 3651] as $days) {
                try { (new Retention())->prune($days); $this->fail('Out of range retention accepted'); }
                catch (\InvalidArgumentException $e) { $this->assertStringContainsString('30 to 3650', $e->getMessage()); }
            }
        } finally { Config::$config = $previous; }
    }
}
