<?php
namespace Wbengine\Tests\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Wbengine\Mcp\Server;
use Wbengine\Mcp\ToolRegistry;
use Wbengine\Mcp\Transport\Stdio;

final class StdioTest extends TestCase
{
    /** @return array{string, string} stdout and stderr */
    private function session(string $input, int $max = 4096): array
    {
        $in = fopen('php://memory', 'w+'); fwrite($in, $input); rewind($in);
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        $stdio = new Stdio($in, $out, $err, $max);
        $stdio->run(new Server(new ToolRegistry(), 'wbengine-mcp', '1', '', [$stdio, 'log']));
        rewind($out); rewind($err);
        return [stream_get_contents($out), stream_get_contents($err)];
    }

    public function testOneResponsePerLineAndEndOfInputEndsTheLoop(): void
    {
        [$out] = $this->session("{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"ping\"}\n\n{\"jsonrpc\":\"2.0\",\"method\":\"notifications/initialized\"}\n{\"jsonrpc\":\"2.0\",\"id\":2,\"method\":\"ping\"}");
        $this->assertSame("{\"jsonrpc\":\"2.0\",\"id\":1,\"result\":{}}\n{\"jsonrpc\":\"2.0\",\"id\":2,\"result\":{}}\n", $out);
    }

    public function testMalformedLineGivesAnErrorAndTheNextLineIsServed(): void
    {
        [$out] = $this->session("garbage\n{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"ping\"}\n");
        $lines = explode("\n", trim($out));
        $this->assertSame(-32700, json_decode($lines[0], true)['error']['code']);
        $this->assertSame(1, json_decode($lines[1], true)['id']);
    }

    public function testOversizedLineIsDiscardedAndTheNextLineIsServed(): void
    {
        [$out] = $this->session(str_repeat('a', 20000) . "\n{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"ping\"}\n", 4096);
        $lines = explode("\n", trim($out));
        $this->assertCount(2, $lines);
        $this->assertSame(-32600, json_decode($lines[0], true)['error']['code']);
        $this->assertSame(1, json_decode($lines[1], true)['id']);
    }

    public function testLogsGoToStderrOnly(): void
    {
        $in = fopen('php://memory', 'w+'); $out = fopen('php://memory', 'w+'); $err = fopen('php://memory', 'w+');
        (new Stdio($in, $out, $err))->log("multi\nline");
        rewind($out); rewind($err);
        $this->assertSame('', stream_get_contents($out));
        $this->assertSame("[wbengine-mcp] multi line\n", stream_get_contents($err));
    }
}
