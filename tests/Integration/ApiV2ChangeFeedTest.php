<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api;
use Wbengine\Api\V2;
use Wbengine\Api\V2\Model;
use Wbengine\Audit\ChangeLog;
use Wbengine\Db;
use Wbengine\User\Role;

/** GET /api/v2/changes on the real tables: scope, filters, paging, rejected writes, no bodies. */
final class ApiV2ChangeFeedTest extends ApiTestCase
{
    private V2 $v2;
    private string $key;
    private string $keyWithoutScope;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['cms_api_changes', 'cms_api_audit', 'cms_api_integrations'] as $table) { Db::query('DELETE FROM ' . $table); }
        $admin = $this->createUser('feed-admin@example.com', Role::ADMIN);
        $model = new Model();
        $this->key = $model->provision($admin, 'Central', ['discovery:read', 'audit:read'])['key'];
        $this->keyWithoutScope = $model->provision($admin, 'Limited', ['discovery:read', 'articles:read'])['key'];
        $this->v2 = new V2(new Api(), $model);
        $now = time();
        foreach ([
            ['articles', '1', 'create', 'v2', 'u1', $now - 500], ['articles', '1', 'update', 'v2', 'u1', $now - 400],
            ['pages', '2', 'create', 'v2', 'u2', $now - 300], ['user', '9', 'create', 'v1', null, $now - 200],
            ['cache', 'all', 'flush', 'cli', null, $now - 100],
        ] as [$resource, $id, $action, $source, $actor, $at]) {
            Db::insert('cms_api_changes', ['resource' => $resource, 'resource_id' => $id, 'action' => $action, 'before_data' => '{"secret":"BEFORE"}', 'after_data' => '{"secret":"AFTER"}', 'integration_id' => 0, 'central_actor' => $actor, 'source' => $source, 'created_at' => $at]);
        }
        Db::insert('cms_api_audit', ['integration_id' => 1, 'central_actor' => 'u1', 'action' => 'articles.patch', 'outcome' => 'rejected', 'detail' => 'invalid_field', 'created_at' => $now - 50]);
        Db::insert('cms_api_audit', ['integration_id' => 1, 'central_actor' => 'u1', 'action' => 'articles.get', 'outcome' => 'allowed', 'created_at' => $now - 40]);
    }

    private function get(array $query = [], ?string $key = null): array
    {
        $r = $this->v2->handle('GET', '/api/v2/changes', 'Bearer ' . ($key ?? $this->key), true, 'u1', '', $query);
        return ['status' => $r['status'], 'json' => $r['body'], 'body' => json_encode($r['body'])];
    }

    public function testRequiresTheAuditScope(): void
    {
        $this->assertSame(403, $this->get([], $this->keyWithoutScope)['status']);
        $this->assertSame(401, $this->v2->handle('GET', '/api/v2/changes', '', true)['status']);
        $this->assertSame(403, $this->v2->handle('GET', '/api/v2/changes', 'Bearer ' . $this->key, false)['status']);
    }

    public function testFeedIsNewestFirstWithPaginationAndNoBodies(): void
    {
        $r = $this->get(['per_page' => '2']);
        $this->assertSame(200, $r['status']);
        $this->assertSame(['cache', 'user'], array_column($r['json']['data'], 'resource'));
        $this->assertSame(5, $r['json']['meta']['pagination']['total'] ?? $r['json']['pagination']['total']);
        $this->assertStringNotContainsString('BEFORE', $r['body']);
        $this->assertStringNotContainsString('AFTER', $r['body']);
        $this->assertArrayNotHasKey('before_data', $r['json']['data'][0]);
        $second = $this->get(['per_page' => '2', 'page' => '3']);
        $this->assertCount(1, $second['json']['data']);
        $this->assertSame('articles', $second['json']['data'][0]['resource']);
    }

    public function testFilters(): void
    {
        $this->assertCount(2, $this->get(['resource' => 'articles'])['json']['data']);
        $this->assertCount(1, $this->get(['resource' => 'articles', 'action' => 'update'])['json']['data']);
        $this->assertCount(2, $this->get(['actor' => 'u1'])['json']['data']);
        $this->assertCount(1, $this->get(['source' => 'cli'])['json']['data']);
        $this->assertCount(1, $this->get(['resource' => 'articles', 'resource_id' => '1', 'source' => 'v2', 'action' => 'create'])['json']['data']);
        $now = time();
        $this->assertCount(2, $this->get(['since' => (string) ($now - 250)])['json']['data']);
        $this->assertCount(2, $this->get(['until' => (string) ($now - 350)])['json']['data']);
        $this->assertCount(0, $this->get(['resource' => 'nothing'])['json']['data']);
    }

    public function testInvalidFiltersAreRejectedBeforeAnyQuery(): void
    {
        foreach ([['resource' => "x' OR 1=1"], ['source' => 'web'], ['since' => 'yesterday'], ['actor' => str_repeat('a', 129)], ['outcome' => 'all'], ['resource' => ['a']], ['per_page' => '1000']] as $query) {
            $r = $this->get($query);
            $this->assertContains($r['status'], [400, 422], json_encode($query));
        }
    }

    public function testRejectedWritesAreListedSeparatelyWithTheirErrorCode(): void
    {
        $r = $this->get(['outcome' => 'rejected']);
        $this->assertSame(200, $r['status']);
        $this->assertCount(1, $r['json']['data']);
        $this->assertSame('invalid_field', $r['json']['data'][0]['detail']);
        $this->assertSame('rejected', $r['json']['data'][0]['outcome']);
        $this->assertSame('u1', $r['json']['data'][0]['actor']);
    }

    public function testReadingTheFeedIsAudited(): void
    {
        $this->get();
        $row = Db::fetchAssoc("SELECT * FROM cms_api_audit WHERE action = 'changes.list'");
        $this->assertSame('allowed', $row['outcome']);
    }

    public function testCapabilitiesAdvertiseTheFeed(): void
    {
        $r = $this->v2->handle('GET', '/api/v2/capabilities');
        $this->assertTrue($r['body']['data']['capabilities']['audit']['read']);
    }
}
