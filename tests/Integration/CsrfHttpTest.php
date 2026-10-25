<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;

class CsrfHttpTest extends DbTestCase
{
    private $server;
    private ?string $cookie = null;
    private string $dir;
    private string $origin;

    protected function setUp(): void
    {
        parent::setUp();
        \Wbengine\Db::insert('cms_users', ['username' => 'csrf@example.test', 'email' => 'csrf@example.test', 'password' => md5('password')]);
        $this->dir = sys_get_temp_dir() . '/wb-pc-http-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->origin = 'http://' . $address;
        $this->server = proc_open([PHP_BINARY, '-S', $address, dirname(__DIR__) . '/fixtures/csrf.php'],
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
        foreach (glob($this->dir . '/cache/*') ?: [] as $file) { unlink($file); }
        if (is_dir($this->dir . '/cache')) { rmdir($this->dir . '/cache'); }
        foreach (glob($this->dir . '/*') ?: [] as $file) { unlink($file); }
        rmdir($this->dir);
    }

    private function request(string $path, array $headers = [], ?array $post = null): array
    {
        if (null !== $this->cookie) { $headers[] = 'Cookie: ' . $this->cookie; }
        if (null !== $post) { $headers[] = 'Content-Type: application/x-www-form-urlencoded'; }
        $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'follow_location' => 0, 'method' => null === $post ? 'GET' : 'POST', 'content' => null === $post ? '' : http_build_query($post),
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
                if ('set-cookie' === strtolower($name) && str_starts_with(trim($value), 'PHPSESSID=')) {
                    $this->cookie = explode(';', trim($value), 2)[0];
                }
            }
        }
        return $result;
    }

    private function token(array $response): string
    {
        $this->assertSame(1, preg_match('/name="token" value="([a-f0-9]{64})"/', $response['body'], $matches), $response['body']);
        return $matches[1];
    }

    public function testTwoLoginTabsKeepTheirTokenAndRejectInvalidAjaxBeforeLogin(): void
    {
        $first = $this->request('/login/');
        $token = $this->token($first);
        $second = $this->request('/login/');
        $this->assertSame($token, $this->token($second));
        $this->assertStringContainsString('no-store', $second['cache-control'] ?? '');
        $this->assertNotSame('HIT', $second['x-wbengine-cache'] ?? null);
        $invalid = $this->request('/login/', ['X-Requested-With: XMLHttpRequest'], ['token' => 'wrong', 'userName' => 'csrf@example.test', 'userPassword' => 'password']);
        $this->assertStringContainsString('403', $invalid['status']);
        $this->assertSame('anonymous', $this->request('/state/')['body']);
        $valid = $this->request('/login/', [], ['token' => $token, 'userName' => 'csrf@example.test', 'userPassword' => 'password']);
        $this->assertStringContainsString('302', $valid['status']);
        $this->assertSame('logged', $this->request('/state/')['body']);
    }

    public function testLogoutRequiresPostAndItsOwnToken(): void
    {
        $login = $this->token($this->request('/login/'));
        $this->request('/login/', [], ['token' => $login, 'userName' => 'csrf@example.test', 'userPassword' => 'password']);
        $this->assertSame('logged', $this->request('/state/')['body']);
        $get = $this->request('/login/?a=logout');
        $this->assertStringContainsString('405', $get['status']);
        $this->assertSame('logged', $this->request('/state/')['body']);
        $currentLoginToken = $this->token($this->request('/login/'));
        $invalid = $this->request('/login/?a=logout', [], ['token' => $currentLoginToken]);
        $this->assertStringContainsString('403', $invalid['status']);
        $this->assertSame('logged', $this->request('/state/')['body']);
        $logout = $this->token($this->request('/logout-form/'));
        $valid = $this->request('/login/?a=logout', [], ['token' => $logout]);
        $this->assertStringContainsString('302', $valid['status']);
        $this->assertSame('anonymous', $this->request('/state/')['body']);
    }

    public function testLoginRejectsAnotherSessionsTokenAndAcceptsAjaxHeader(): void
    {
        $foreign = $this->token($this->request('/login/'));
        $this->cookie = null;
        $own = $this->token($this->request('/login/'));
        $this->assertNotSame($foreign, $own);
        $credentials = ['userName' => 'csrf@example.test', 'userPassword' => 'password'];
        $rejected = $this->request('/login/', [], ['token' => $foreign] + $credentials);
        $this->assertStringContainsString('403', $rejected['status']);
        $this->assertSame('anonymous', $this->request('/state/')['body']);
        $get = $this->request('/login/', ['X-Requested-With: XMLHttpRequest']);
        $this->assertStringContainsString('405', $get['status']);
        $accepted = $this->request('/login/', ['X-Requested-With: XMLHttpRequest', 'X-CSRF-Token: ' . $own], $credentials);
        $this->assertSame('OK', $accepted['body']);
        $this->assertSame('logged', $this->request('/state/')['body']);
    }

    public function testPublicGetIsCachedWithoutSessionWritesOrCookies(): void
    {
        $first = $this->request('/public/');
        $second = $this->request('/public/');
        $this->assertArrayNotHasKey('set-cookie', $first);
        $this->assertSame('HIT', $second['x-wbengine-cache'] ?? null);
        $this->assertSame($first['body'], $second['body']);
        $this->assertSame(0, (int) \Wbengine\Db::fetchAssoc('SELECT COUNT(*) c FROM cms_sessions')['c']);
    }
}
