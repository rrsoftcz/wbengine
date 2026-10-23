<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api\CacheAdmin;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\User\Role;

class ApiCacheAdminTest extends ApiTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/wb-cadm-' . bin2hex(random_bytes(4));
        Cache::setDriver(new FileCache($this->dir), 60);
        Cache::store('page:/blog/', ['body' => 'x', 'etag' => '"e"'], 60);
        Cache::store('sites:visible', [['link' => '/']], 60);
    }

    protected function tearDown(): void
    {
        Cache::setDriver(null);
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function cached(string $key): bool
    {
        Cache::fetch($key, $found);
        return $found;
    }

    public function testOnlyAnAdministratorCanFlush(): void
    {
        $controller = new CacheAdmin($this->api);

        $this->assertSame(401, $this->respond(fn () => $controller->flush())['status']);

        foreach ([Role::USER, Role::EDITOR] as $role) {
            $this->asUser($this->createUser($role . '@example.com', $role));
            $this->assertSame(403, $this->respond(fn () => $controller->flush())['status']);
        }
        $this->assertTrue($this->cached('page:/blog/'), 'nothing was dropped');
    }

    public function testAnAdministratorFlushesPagesAndData(): void
    {
        $this->asUser($this->createUser('admin@example.com', Role::ADMIN));

        $r = $this->respond(fn () => (new CacheAdmin($this->api))->flush());

        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['json']['success']);
        $this->assertFalse($this->cached('page:/blog/'));
        $this->assertFalse($this->cached('sites:visible'));
    }
}
