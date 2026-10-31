<?php
namespace Wbengine\Tests\Unit\Mcp;

use PHPUnit\Framework\TestCase;

/** The built single-file server: contents, signature and a stdio session through it. */
final class PharTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!class_exists(\Phar::class)) { $this->markTestSkipped('The phar extension is not available.'); }
        $this->dir = sys_get_temp_dir() . '/wb-phar-' . bin2hex(random_bytes(4));
        $root = dirname(__DIR__, 3);
        exec(escapeshellarg(PHP_BINARY) . ' -d phar.readonly=0 ' . escapeshellarg($root . '/bin/build-phar.php') . ' ' . escapeshellarg($this->dir) . ' 2>&1', $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) { @unlink($file); }
        @rmdir($this->dir);
    }

    public function testTheArchiveHoldsOnlyTheServerAndIsSigned(): void
    {
        $file = $this->dir . '/wbengine-mcp.phar';
        $phar = new \Phar($file);
        $this->assertSame('SHA-256', $phar->getSignature()['hash_type']);
        $names = [];
        foreach (new \RecursiveIteratorIterator($phar) as $entry) { $names[] = substr($entry->getPathname(), strlen('phar://' . realpath($file) . '/')); }
        $this->assertContains('bin/wbengine-mcp.php', $names);
        $this->assertContains('Mcp/Server.php', $names);
        $this->assertContains('Mcp/Guide/overview.md', $names, 'the guide texts are packed with the server');
        $this->assertSame([], array_filter($names, static fn(string $n) => !str_starts_with($n, 'Mcp/') && 'bin/wbengine-mcp.php' !== $n), 'nothing else is packed');
        $this->assertSame([], array_filter($names, static fn(string $n) => !preg_match('~\\.(php|md)$~', $n)), 'only classes and guide texts');
        $this->assertSame(hash_file('sha256', $file) . '  wbengine-mcp.phar' . "\n", file_get_contents($file . '.sha256'));
    }

    public function testAStdioSessionThroughThePharKeepsStdoutClean(): void
    {
        $process = proc_open([PHP_BINARY, $this->dir . '/wbengine-mcp.phar'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, ['WBENGINE_MCP_URL' => 'https://site.example', 'WBENGINE_MCP_KEY' => 'wbv2_testkey', 'WBENGINE_MCP_MODE' => 'draft', 'PATH' => getenv('PATH')]);
        fwrite($pipes[0], '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25"}}' . "\n" . '{"jsonrpc":"2.0","id":2,"method":"tools/list"}' . "\n");
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        $this->assertSame(0, proc_close($process));
        $lines = explode("\n", rtrim($out, "\n"));
        $this->assertCount(2, $lines, 'only protocol messages on stdout, no shebang or notices');
        $this->assertSame('2025-11-25', json_decode($lines[0], true)['result']['protocolVersion']);
        $names = array_column(json_decode($lines[1], true)['result']['tools'], 'name');
        $this->assertContains('save_article_draft', $names);
        $this->assertNotContains('publish_article', $names);
        $this->assertStringNotContainsString('wbv2_testkey', $out . $err);
        $this->assertMatchesRegularExpression('/started: pid \d+, server \d+\.\d+\.\d+, PHP /', $err);
        $this->assertStringContainsString('input closed by the client', $err, 'a session that ends says how');
    }

    public function testTheGuideIsReadableFromInsideThePhar(): void
    {
        $process = proc_open([PHP_BINARY, $this->dir . '/wbengine-mcp.phar'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, ['WBENGINE_MCP_URL' => 'https://site.example', 'WBENGINE_MCP_KEY' => 'wbv2_testkey', 'PATH' => getenv('PATH')]);
        fwrite($pipes[0], '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25"}}' . "\n" . '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"wbengine_guide","arguments":{"topic":"overview"}}}' . "\n" . '{"jsonrpc":"2.0","id":3,"method":"resources/read","params":{"uri":"wbengine://guide/boxes"}}' . "\n" . '{"jsonrpc":"2.0","id":4,"method":"prompts/get","params":{"name":"publish_new_article","arguments":{"topic":"bread"}}}' . "\n");
        fclose($pipes[0]);
        $lines = explode("\n", rtrim(stream_get_contents($pipes[1]), "\n"));
        proc_close($process);
        $this->assertStringContainsString('How a WBengine site works', json_decode($lines[1], true)['result']['content'][0]['text']);
        $this->assertStringContainsString('# Boxes', json_decode($lines[2], true)['result']['contents'][0]['text']);
        $this->assertStringContainsString('bread', json_decode($lines[3], true)['result']['messages'][0]['content']['text']);
    }

    public function testMissingConfigurationExitsWithTwo(): void
    {
        $process = proc_open([PHP_BINARY, $this->dir . '/wbengine-mcp.phar'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, ['PATH' => getenv('PATH')]);
        fclose($pipes[0]);
        $this->assertSame('', stream_get_contents($pipes[1]));
        $this->assertStringContainsString('WBENGINE_MCP_URL', stream_get_contents($pipes[2]));
        $this->assertSame(2, proc_close($process));
    }
}
