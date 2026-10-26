<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;

class PageCacheHttpTest extends TestCase
{
    private $server;
    private string $dir;
    private string $origin;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-pc-http-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->origin = 'http://' . $address;
        $this->server = proc_open([PHP_BINARY, '-S', $address, dirname(__DIR__) . '/fixtures/page-cache.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->dir . '/server.log', 'a'],
                2 => ['file', $this->dir . '/server.log', 'a']], $pipes, null,
            array_merge(getenv(), ['WB_PAGE_CACHE_HTTP_DIR' => $this->dir]));
        for ($i = 0; $i < 100; $i++) {
            $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
            if (is_resource($probe)) {
                fclose($probe);
                return;
            }
            usleep(20000);
        }
        $this->fail('HTTP fixture did not start: ' . file_get_contents($this->dir . '/server.log'));
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function request(string $path, array $headers = []): array
    {
        $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true,
            'header' => implode("\r\n", $headers)]]);
        $stream = fopen($this->origin . $path, 'r', false, $context);
        $body = stream_get_contents($stream);
        $metadata = stream_get_meta_data($stream);
        fclose($stream);
        $result = ['status' => $metadata['wrapper_data'][0], 'body' => $body];
        foreach ($metadata['wrapper_data'] as $header) {
            if (str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $result[strtolower($name)] = trim($value);
            }
        }
        return $result;
    }

    public function testHitAnd304KeepSecurityPolicy(): void
    {
        $miss = $this->request('/cached/');
        $this->assertSame('MISS-STORED', $miss['x-wbengine-cache'] ?? null);
        $hit = $this->request('/cached/');
        $this->assertSame('HIT', $hit['x-wbengine-cache'] ?? null);
        $this->assertSame($miss['body'], $hit['body']);
        foreach (['x-frame-options', 'content-security-policy', 'referrer-policy',
            'cross-origin-opener-policy', 'cross-origin-embedder-policy',
            'cross-origin-resource-policy', 'origin-agent-cluster'] as $name) {
            $this->assertSame($miss[$name], $hit[$name]);
        }
        $conditional = $this->request('/cached/', ['If-None-Match: ' . $hit['etag']]);
        $this->assertStringContainsString('304', $conditional['status']);
        $this->assertSame('', $conditional['body']);
        $this->assertSame('DENY', $conditional['x-frame-options']);
        $this->assertSame('same-origin', $conditional['cross-origin-opener-policy']);
        $this->assertSame('require-corp', $conditional['cross-origin-embedder-policy']);
        $this->assertSame('same-origin', $conditional['cross-origin-resource-policy']);
    }

    public function testCurrentPolicyOverridesCachedPolicy(): void
    {
        $this->request('/cached/');
        $hit = $this->request('/cached/', ['X-Current-Policy: yes']);
        $this->assertSame('HIT', $hit['x-wbengine-cache'] ?? null);
        $this->assertSame('SAMEORIGIN', $hit['x-frame-options']);
    }

    public function testExcludedAndBypassedPagesNeverReuseBodies(): void
    {
        foreach (['/excluded/', '/bypass/', '/late-bypass/', '/private/'] as $path) {
            $first = $this->request($path);
            $second = $this->request($path);
            $this->assertNotSame($first['body'], $second['body'], $path);
            $this->assertNotSame('HIT', $second['x-wbengine-cache'] ?? null, $path);
        }
    }

    public function testPreUpgradeRecordsAreIgnored(): void
    {
        $response = $this->request('/old/');
        $this->assertSame('MISS-STORED', $response['x-wbengine-cache'] ?? null);
        $this->assertNotSame('old private body', $response['body']);
    }

    public function testCustomCookieCannotReadOrPopulateSharedCache(): void
    {
        $public = $this->request('/cached/');
        $personal = $this->request('/cached/', ['Cookie: audience=vip']);
        $this->assertNotSame('HIT', $personal['x-wbengine-cache'] ?? null);
        $this->assertStringContainsString('vip', $personal['body']);
        $this->assertSame($public['body'], $this->request('/cached/')['body']);
    }

    public function testPersonalFragmentPreventsPageStorageBeforeAnotherUserVisits(): void
    {
        $first = $this->request('/scoped/', ['X-Fixture-User: 7']);
        $this->assertSame('user-7', $first['body']);
        $this->assertSame('BYPASS', $first['x-wbengine-cache'] ?? null);
        $second = $this->request('/scoped/', ['X-Fixture-User: 8']);
        $this->assertSame('user-8', $second['body']);
        $this->assertSame('BYPASS', $second['x-wbengine-cache'] ?? null);
    }
}
