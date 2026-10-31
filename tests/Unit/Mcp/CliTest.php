<?php
namespace Wbengine\Tests\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Wbengine\Mcp\Api\ApiError;
use Wbengine\Mcp\Cli;
use Wbengine\Mcp\Protocol;

final class CliTest extends TestCase
{
    private const KEY = 'wbv2_topsecretkey123';

    /** @return array{int, string, string, array} exit code, stdout, stderr, requests seen by the transport */
    private function session(array $env, array $messages): array
    {
        $seen = [];
        $transport = function (string $method, string $url, array $headers) use (&$seen): array {
            $seen[] = ['method' => $method, 'url' => $url, 'headers' => $headers];
            $data = str_ends_with($url, '/identity') ? ['name' => 'Agent', 'scopes' => ['discovery:read', 'articles:read']] : ['api_version' => '2.0', 'capabilities' => ['images' => ['write' => false]], 'i18n' => ['default' => 'cs', 'locales' => ['cs', 'en']]];
            return ['status' => 200, 'headers' => [], 'body' => json_encode(['success' => true, 'data' => $data])];
        };
        $in = fopen('php://memory', 'w+'); fwrite($in, implode("\n", array_map('json_encode', $messages)) . "\n"); rewind($in);
        $out = fopen('php://memory', 'w+'); $err = fopen('php://memory', 'w+');
        $code = Cli::run($env, $in, $out, $err, $transport);
        rewind($out); rewind($err);
        return [$code, stream_get_contents($out), stream_get_contents($err), $seen];
    }

    private function env(array $extra = []): array
    {
        return $extra + ['WBENGINE_MCP_URL' => 'https://www.example.org', 'WBENGINE_MCP_KEY' => self::KEY];
    }

    public function testConfigurationDefaultsAndValidation(): void
    {
        $this->assertSame(['url' => 'https://www.example.org', 'key' => self::KEY, 'mode' => 'full', 'insecure' => false, 'actor' => null], Cli::configuration($this->env()));
        $this->assertSame('draft', Cli::configuration($this->env(['WBENGINE_MCP_MODE' => 'draft']))['mode']);
        $this->assertTrue(Cli::configuration($this->env(['WBENGINE_MCP_ALLOW_INSECURE' => '1']))['insecure']);
        $this->assertSame('bot', Cli::configuration($this->env(['WBENGINE_MCP_ACTOR' => ' bot ']))['actor']);
        foreach ([['WBENGINE_MCP_URL' => ''], ['WBENGINE_MCP_KEY' => ''], ['WBENGINE_MCP_MODE' => 'yolo'], ['WBENGINE_MCP_ACTOR' => str_repeat('a', 129)], ['WBENGINE_MCP_ACTOR' => "a\nb"]] as $bad) {
            try { Cli::configuration($bad + $this->env()); $this->fail(json_encode($bad)); } catch (ApiError $e) { $this->assertSame('invalid_configuration', $e->errorCode); }
        }
    }

    public function testBadConfigurationExitsNonZeroWithAMessageOnStderrAndNoKey(): void
    {
        [$code, $out, $err] = $this->session($this->env(['WBENGINE_MCP_URL' => 'http://www.example.org']), []);
        $this->assertSame(2, $code);
        $this->assertSame('', $out);
        $this->assertStringContainsString('https', $err);
        $this->assertStringNotContainsString(self::KEY, $err);
        [$code, , $err] = $this->session(['WBENGINE_MCP_KEY' => self::KEY], []);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('WBENGINE_MCP_URL', $err);
        [$code, , $err] = $this->session($this->env(['WBENGINE_MCP_KEY' => "has space"]), []);
        $this->assertSame(2, $code);
        $this->assertStringNotContainsString('has space', $err);
    }

    public function testASessionServesToolsAndTheActorFollowsTheClient(): void
    {
        [$code, $out, $err, $seen] = $this->session($this->env(), [
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => [], 'clientInfo' => ['name' => 'Claude Desktop', 'version' => '1']]],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'site_info', 'arguments' => []]],
        ]);
        $this->assertSame(0, $code);
        $lines = array_map(static fn($l) => json_decode($l, true), explode("\n", trim($out)));
        $this->assertCount(2, $lines);
        $this->assertSame(Protocol::SERVER_VERSION, $lines[0]['result']['serverInfo']['version']);
        $this->assertStringContainsString('draft', $lines[0]['result']['instructions']);
        $this->assertFalse($lines[1]['result']['isError']);
        $this->assertSame(['cs', 'en'], $lines[1]['result']['structuredContent']['languages']['available']);
        $this->assertSame('mcp:Claude Desktop', $seen[0]['headers']['X-WBengine-Actor']);
        $this->assertSame('Bearer ' . self::KEY, $seen[0]['headers']['Authorization']);
        $this->assertStringNotContainsString(self::KEY, $out);
        $this->assertStringNotContainsString(self::KEY, $err);
    }

    public function testExplicitActorWinsAndModernClientNameIsUsed(): void
    {
        [, , , $seen] = $this->session($this->env(['WBENGINE_MCP_ACTOR' => 'blog-bot']), [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'site_info', 'arguments' => [], '_meta' => [Protocol::META_VERSION => Protocol::MODERN, Protocol::META_CAPABILITIES => [], Protocol::META_CLIENT => ['name' => 'Other', 'version' => '2']]]]]);
        $this->assertSame('blog-bot', $seen[0]['headers']['X-WBengine-Actor']);
        [, , , $seen] = $this->session($this->env(), [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'site_info', 'arguments' => [], '_meta' => [Protocol::META_VERSION => Protocol::MODERN, Protocol::META_CAPABILITIES => [], Protocol::META_CLIENT => ['name' => "Ev\nil<x>", 'version' => '2']]]]]);
        $this->assertSame('mcp:Evilx', $seen[0]['headers']['X-WBengine-Actor']);
    }

    public function testDraftModeOffersNoPublishTools(): void
    {
        [, $out] = $this->session($this->env(['WBENGINE_MCP_MODE' => 'draft']), [
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25']],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
        ]);
        $names = array_column(json_decode(explode("\n", trim($out))[1], true)['result']['tools'], 'name');
        $this->assertNotContains('publish_article', $names);
        $this->assertNotContains('hide_article', $names);
        $this->assertContains('save_article_draft', $names);
    }
}
