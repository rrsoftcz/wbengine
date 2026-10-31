<?php
namespace Wbengine\Tests\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Wbengine\Mcp\Api\ApiError;
use Wbengine\Mcp\Api\Client;

/** The real stream transport against local fixture servers. */
final class ClientStreamTest extends TestCase
{
    private const KEY = 'wbv2_supersecretkeyvalue';

    /** @var resource|null */
    private $process;
    private int $port = 0;

    protected function tearDown(): void
    {
        if (is_resource($this->process)) { proc_terminate($this->process); proc_close($this->process); }
    }

    private function startPlainServer(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->port = (int) parse_url(stream_socket_get_name($socket, false), PHP_URL_PORT);
        fclose($socket);
        $this->process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $this->port, __DIR__ . '/../../Support/mcp-http-fixture.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', $this->port)) { return; }
            usleep(100000);
        }
        $this->markTestSkipped('The PHP built-in server did not start.');
    }

    private function client(int $maxBytes = Client::MAX_RESPONSE_BYTES): Client
    {
        return new Client('http://127.0.0.1:' . $this->port, self::KEY, 'mcp:test', null, true, 10, $maxBytes);
    }

    public function testHeadersBodyAndEtagTravelCorrectly(): void
    {
        $this->startPlainServer();
        $response = $this->client()->request('PUT', '/echo', ['q' => 'a b'], ['t' => 'x'], '"e1"');
        $this->assertSame('PUT', $response->data['method']);
        $this->assertSame('Bearer ' . self::KEY, $response->data['authorization']);
        $this->assertSame('mcp:test', $response->data['actor']);
        $this->assertSame('"e1"', $response->data['if_match']);
        $this->assertSame(['q' => 'a b'], $response->data['query']);
        $this->assertSame('{"t":"x"}', $response->data['body']);
        $this->assertSame('"abc"', $response->etag);
    }

    public function testRedirectIsNotFollowed(): void
    {
        $this->startPlainServer();
        try { $this->client()->request('GET', '/redirect'); $this->fail(); } catch (ApiError $e) { $this->assertSame('redirect_refused', $e->errorCode); }
    }

    public function testOversizeAnswerIsCutOff(): void
    {
        $this->startPlainServer();
        try { $this->client(1000000)->request('GET', '/big'); $this->fail(); } catch (ApiError $e) { $this->assertSame('response_too_large', $e->errorCode); }
    }

    public function testErrorStatusesAndForeignBodiesAreHandled(): void
    {
        $this->startPlainServer();
        try { $this->client()->request('GET', '/missing'); $this->fail(); } catch (ApiError $e) { $this->assertSame('not_found', $e->errorCode); $this->assertSame(404, $e->status); }
        try { $this->client()->request('GET', '/html'); $this->fail(); } catch (ApiError $e) { $this->assertSame('bad_response', $e->errorCode); }
    }

    public function testTheKeyEchoedByTheSiteDoesNotReachTheErrorText(): void
    {
        $this->startPlainServer();
        try { $this->client()->request('GET', '/leak'); $this->fail(); } catch (ApiError $e) {
            $this->assertStringNotContainsString(self::KEY, $e->advice());
        }
    }

    public function testSelfSignedCertificateIsRefused(): void
    {
        if (!function_exists('openssl_pkey_new')) { $this->markTestSkipped('OpenSSL is not available.'); }
        $portFile = sys_get_temp_dir() . '/wb-tls-port-' . bin2hex(random_bytes(4));
        $this->process = proc_open([PHP_BINARY, __DIR__ . '/../../Support/mcp-tls-fixture.php', $portFile], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        for ($i = 0; $i < 50 && !is_file($portFile); $i++) { usleep(100000); }
        if (!is_file($portFile) || '' === trim((string) file_get_contents($portFile))) { $this->markTestSkipped('The TLS fixture did not start.'); }
        $port = (int) file_get_contents($portFile);
        @unlink($portFile);
        // https to 127.0.0.1 is not "localhost", so use the host name that resolves to it.
        $client = new Client('https://localhost:' . $port, self::KEY, 'mcp:test', null, false, 5);
        try { $client->request('GET', '/echo'); $this->fail('a certificate that is not trusted must be refused'); } catch (ApiError $e) {
            $this->assertSame('transport', $e->errorCode);
            $this->assertStringNotContainsString(self::KEY, $e->advice());
        }
    }
}
