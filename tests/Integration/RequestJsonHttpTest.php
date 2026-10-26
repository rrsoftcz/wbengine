<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RequestJsonHttpTest extends TestCase
{
    private $server;
    private string $dir;
    private string $origin;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-json-http-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->origin = 'http://' . $address;
        $this->server = proc_open([PHP_BINARY, '-S', $address, dirname(__DIR__) . '/fixtures/request-json.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->dir . '/server.log', 'a'],
                2 => ['file', $this->dir . '/server.log', 'a']], $pipes);
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

    private function request(string $body, string $mode = 'decoded'): array
    {
        $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true,
            'method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => $body]]);
        $response = file_get_contents($this->origin . '/?mode=' . $mode, false, $context);
        $this->assertIsString($response);
        return json_decode($response, true, 1024, JSON_THROW_ON_ERROR);
    }

    #[DataProvider('payloads')]
    public function testJsonPreservesDecodedAndRawContractsWithOneParse(string $raw, $decoded, int $error): void
    {
        $parsed = $this->request($raw);
        $this->assertSame($decoded, $parsed['value']);
        $this->assertSame($error, $parsed['error']);
        $this->assertSame(1, $parsed['count']);
        $text = $this->request($raw, 'raw');
        $this->assertSame(JSON_ERROR_NONE === $error ? $raw : null, $text['value']);
        $this->assertSame($error, $text['error']);
        $this->assertSame(1, $text['count']);
    }

    public static function payloads(): array
    {
        $deepNulValue = "\0";
        for ($i = 0; $i < 510; ++$i) {
            $deepNulValue = [$deepNulValue];
        }
        return [
            'null' => ['null', null, JSON_ERROR_NONE],
            'true' => ['true', true, JSON_ERROR_NONE],
            'false' => ['false', false, JSON_ERROR_NONE],
            'zero' => ['0', 0, JSON_ERROR_NONE],
            'negative' => ['-42', -42, JSON_ERROR_NONE],
            'float' => ['2.0', 2.0, JSON_ERROR_NONE],
            'string' => ['"hello"', 'hello', JSON_ERROR_NONE],
            'empty string' => ['""', '', JSON_ERROR_NONE],
            'array' => ['[1,"two",null,false]', [1, 'two', null, false], JSON_ERROR_NONE],
            'object' => ['{"name":"žluťoučký","values":[1,2]}', ['name' => 'žluťoučký', 'values' => [1,2]], JSON_ERROR_NONE],
            'empty object' => ['{}', [], JSON_ERROR_NONE],
            'empty array' => ['[]', [], JSON_ERROR_NONE],
            'numeric keys' => ['{"0":"a","1":"b"}', ['a','b'], JSON_ERROR_NONE],
            'whitespace' => [" \n {\"a\":1} \t", ['a' => 1], JSON_ERROR_NONE],
            'empty body' => ['', null, JSON_ERROR_SYNTAX],
            'whitespace only' => [" \t\n", null, JSON_ERROR_SYNTAX],
            'invalid syntax' => ['{"a":}', null, JSON_ERROR_SYNTAX],
            'trailing data' => ['{} false', null, JSON_ERROR_SYNTAX],
            'trailing comma' => ['[1,]', null, JSON_ERROR_SYNTAX],
            'invalid utf8' => ['"' . "\xff" . '"', null, JSON_ERROR_UTF8],
            'NUL key' => ['{"\\u0000key":1}', null, JSON_ERROR_INVALID_PROPERTY_NAME],
            'nested NUL key' => ['{"outer":[{"\\u0000key":1}]}', null, JSON_ERROR_INVALID_PROPERTY_NAME],
            'NUL string value' => ['"\\u0000"', "\0", JSON_ERROR_NONE],
            'NUL value in object' => ['{"0":{"text":"\\u0000"},"list":[{"n":1}]}', [0 => ['text' => "\0"], 'list' => [['n' => 1]]], JSON_ERROR_NONE],
            'escaped NUL-looking text' => ['{"text":"\\\\u0000"}', ['text' => '\\u0000'], JSON_ERROR_NONE],
            'deep NUL value' => [str_repeat('[', 510) . '"\\u0000"' . str_repeat(']', 510), $deepNulValue, JSON_ERROR_NONE],
            'excessive depth' => [str_repeat('[', 513) . '0' . str_repeat(']', 513), null, JSON_ERROR_DEPTH],
        ];
    }

    public function testOnlyBooleanTrueRequestsDecodedDataAndRepeatedReadsAreIndependent(): void
    {
        $raw = ' {"number":1} ';
        $legacy = $this->request($raw, 'legacy-truthy');
        $this->assertSame($raw, $legacy['value']);
        $this->assertSame(JSON_ERROR_NONE, $legacy['error']);
        $repeat = $this->request($raw, 'repeat');
        $this->assertSame([['number' => 1], ['number' => 1]], $repeat['value']);
        $this->assertSame(2, $repeat['count']);
        $next = $this->request('[2]');
        $this->assertSame([2], $next['value']);
    }

    public function testStandaloneValidatorStillRecognizesNullAndErrors(): void
    {
        foreach (['null' => true, '{"a":1}' => true, '' => false, '{' => false] as $body => $valid) {
            $result = $this->request($body, 'validate');
            $this->assertSame($valid, $result['value']);
            $this->assertSame(1, $result['count']);
        }
    }
}
