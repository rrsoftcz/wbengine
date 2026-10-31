<?php
namespace Wbengine\Tests\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Wbengine\Mcp\Protocol;
use Wbengine\Mcp\Server;
use Wbengine\Mcp\Tool;
use Wbengine\Mcp\ToolError;
use Wbengine\Mcp\ToolRegistry;
use Wbengine\Mcp\ToolResult;

final class ServerTest extends TestCase
{
    private array $logs = [];

    private function server(int $maxResult = Server::MAX_RESULT_BYTES): Server
    {
        $tools = (new ToolRegistry())
            ->add(new Tool('echo', 'Echo', 'Echo a text.', ['type' => 'object', 'properties' => ['text' => ['type' => 'string', 'maxLength' => 20]], 'required' => ['text'], 'additionalProperties' => false], ['readOnly' => true, 'destructive' => false, 'idempotent' => true], static fn(array $a) => ToolResult::ok('said ' . $a['text'], ['text' => $a['text']])))
            ->add(new Tool('fail', 'Fail', 'Always fails.', ['type' => 'object'], ['readOnly' => false, 'destructive' => false, 'idempotent' => false], static function (): ToolResult { throw new ToolError('Choose another page.'); }))
            ->add(new Tool('boom', 'Boom', 'Throws.', ['type' => 'object'], ['readOnly' => false, 'destructive' => true, 'idempotent' => false], static function (): ToolResult { throw new \RuntimeException('secret-internal-detail'); }))
            ->add(new Tool('big', 'Big', 'Large result.', ['type' => 'object'], ['readOnly' => true, 'destructive' => false, 'idempotent' => true], static fn() => ToolResult::ok(str_repeat('x', 500))));
        return new Server($tools, 'wbengine-mcp', '1.2.3', 'Use the tools.', function (string $line): void { $this->logs[] = $line; }, $maxResult);
    }

    private function call(Server $server, array $message): ?array
    {
        $raw = $server->handle(json_encode($message));
        return null === $raw ? null : json_decode($raw, true);
    }

    private function modernMeta(): array
    {
        return ['_meta' => [Protocol::META_VERSION => Protocol::MODERN, Protocol::META_CAPABILITIES => []]];
    }

    public function testLegacyHandshakeNegotiatesAndServesTools(): void
    {
        $server = $this->server();
        $init = $this->call($server, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'c', 'version' => '1']]]);
        $this->assertSame('2025-06-18', $init['result']['protocolVersion']);
        $this->assertSame(['tools' => ['listChanged' => false]], $init['result']['capabilities']);
        $this->assertSame('1.2.3', $init['result']['serverInfo']['version']);
        $this->assertSame('Use the tools.', $init['result']['instructions']);
        $this->assertNull($this->call($server, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
        $list = $this->call($server, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);
        $this->assertSame(['echo', 'fail', 'boom', 'big'], array_column($list['result']['tools'], 'name'));
        $this->assertArrayNotHasKey('resultType', $list['result']);
        $this->assertSame(true, $list['result']['tools'][0]['annotations']['readOnlyHint']);
        $this->assertSame(true, $list['result']['tools'][2]['annotations']['destructiveHint']);
    }

    public function testUnknownLegacyVersionGetsTheNewestSupportedOne(): void
    {
        $init = $this->call($this->server(), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '1999-01-01']]);
        $this->assertSame('2025-11-25', $init['result']['protocolVersion']);
    }

    public function testModernRequestsNeedNoHandshake(): void
    {
        $server = $this->server();
        $discover = $this->call($server, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'server/discover', 'params' => $this->modernMeta()]);
        $this->assertSame('complete', $discover['result']['resultType']);
        $this->assertSame(Protocol::supported(), $discover['result']['supportedVersions']);
        $this->assertArrayHasKey('tools', $discover['result']['capabilities']);
        $this->assertSame('wbengine-mcp', $discover['result']['_meta'][Protocol::META_SERVER]['name']);
        $result = $this->call($server, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => $this->modernMeta() + ['name' => 'echo', 'arguments' => ['text' => 'hi']]]);
        $this->assertSame('complete', $result['result']['resultType']);
        $this->assertSame('said hi', $result['result']['content'][0]['text']);
        $this->assertSame(['message' => 'said hi', 'text' => 'hi'], $result['result']['structuredContent'], 'the text is in the structured result too, for clients that show only that');
        $this->assertFalse($result['result']['isError']);
    }

    public function testModernRequestWithUnknownVersionListsSupportedOnes(): void
    {
        $response = $this->call($this->server(), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => ['_meta' => [Protocol::META_VERSION => '1900-01-01', Protocol::META_CAPABILITIES => []]]]);
        $this->assertSame(Protocol::UNSUPPORTED_VERSION, $response['error']['code']);
        $this->assertSame(Protocol::supported(), $response['error']['data']['supported']);
        $this->assertSame('1900-01-01', $response['error']['data']['requested']);
    }

    public function testModernRequestMissingCapabilitiesIsInvalid(): void
    {
        $response = $this->call($this->server(), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => ['_meta' => [Protocol::META_VERSION => Protocol::MODERN]]]);
        $this->assertSame(Protocol::INVALID_PARAMS, $response['error']['code']);
    }

    public function testToolsNeedAnOpeningWhenNoMetaIsSent(): void
    {
        $response = $this->call($this->server(), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
        $this->assertSame(Protocol::INVALID_PARAMS, $response['error']['code']);
        $this->assertStringContainsString('initialize', $response['error']['message']);
    }

    public function testPingAnswersInBothEras(): void
    {
        $response = $this->server()->handle('{"jsonrpc":"2.0","id":7,"method":"ping"}');
        $this->assertSame('{"jsonrpc":"2.0","id":7,"result":{}}', $response);
    }

    public function testMalformedInputGetsJsonRpcErrorsAndTheServerGoesOn(): void
    {
        $server = $this->server();
        $this->assertSame(Protocol::PARSE_ERROR, json_decode($server->handle('{not json'), true)['error']['code']);
        $this->assertSame(Protocol::INVALID_REQUEST, json_decode($server->handle('[1,2]'), true)['error']['code']);
        $this->assertSame(Protocol::INVALID_REQUEST, json_decode($server->handle('"text"'), true)['error']['code']);
        $this->assertSame(Protocol::INVALID_REQUEST, json_decode($server->handle('{"jsonrpc":"2.0","id":[1],"method":"ping"}'), true)['error']['code']);
        $this->assertSame(Protocol::INVALID_REQUEST, json_decode($server->handle('{"jsonrpc":"1.0","id":1,"method":"ping"}'), true)['error']['code']);
        $this->assertSame(Protocol::INVALID_PARAMS, json_decode($server->handle('{"jsonrpc":"2.0","id":1,"method":"ping","params":"x"}'), true)['error']['code']);
        $this->assertNull($server->handle('{"jsonrpc":"2.0","id":1,"result":{}}'));
        $this->assertNotNull($server->handle('{"jsonrpc":"2.0","id":1,"method":"ping"}'));
    }

    public function testUnknownMethodAndNotificationsOfUnknownKindAreHandledQuietly(): void
    {
        $server = $this->server();
        $this->assertSame(Protocol::METHOD_NOT_FOUND, $this->call($server, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'resources/list'])['error']['code']);
        $this->assertNull($this->call($server, ['jsonrpc' => '2.0', 'method' => 'resources/list']));
        $this->assertNull($this->call($server, ['jsonrpc' => '2.0', 'method' => 'notifications/cancelled', 'params' => ['requestId' => 1]]));
    }

    public function testToolCallProblems(): void
    {
        $server = $this->server();
        $server->handle('{"jsonrpc":"2.0","id":0,"method":"initialize","params":{"protocolVersion":"2025-11-25"}}');
        $unknown = $this->call($server, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'nope']]);
        $this->assertSame(Protocol::INVALID_PARAMS, $unknown['error']['code']);
        $invalid = $this->call($server, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'echo', 'arguments' => ['text' => str_repeat('a', 21)]]]);
        $this->assertTrue($invalid['result']['isError']);
        $this->assertStringContainsString('at most 20', $invalid['result']['content'][0]['text']);
        $missing = $this->call($server, ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'echo']]);
        $this->assertStringContainsString('"text"', $missing['result']['content'][0]['text']);
        $extra = $this->call($server, ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'echo', 'arguments' => ['text' => 'a', 'other' => 1]]]);
        $this->assertStringContainsString('unknown field "other"', $extra['result']['content'][0]['text']);
    }

    public function testToolErrorsAreResultsAndInternalDetailsDoNotLeak(): void
    {
        $server = $this->server();
        $server->handle('{"jsonrpc":"2.0","id":0,"method":"initialize","params":{"protocolVersion":"2025-11-25"}}');
        $fail = $this->call($server, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'fail']]);
        $this->assertTrue($fail['result']['isError']);
        $this->assertSame('Choose another page.', $fail['result']['content'][0]['text']);
        $boom = $this->call($server, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'boom']]);
        $this->assertTrue($boom['result']['isError']);
        $this->assertStringNotContainsString('secret-internal-detail', json_encode($boom));
        $this->assertSame(['tool boom failed: RuntimeException'], $this->logs);
    }

    public function testOversizedResultIsReplacedByAnError(): void
    {
        $server = $this->server(200);
        $server->handle('{"jsonrpc":"2.0","id":0,"method":"initialize","params":{"protocolVersion":"2025-11-25"}}');
        $big = $this->call($server, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'big']]);
        $this->assertTrue($big['result']['isError']);
        $this->assertStringContainsString('larger than 200 bytes', $big['result']['content'][0]['text']);
    }

    public function testResponsesAreSingleLinesEvenWithNewlinesAndBadBytes(): void
    {
        $server = $this->server();
        $server->handle('{"jsonrpc":"2.0","id":0,"method":"initialize","params":{"protocolVersion":"2025-11-25"}}');
        $raw = $server->handle(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'echo', 'arguments' => ['text' => "a\nb\u{00e9}"]]]));
        $this->assertStringNotContainsString("\n", $raw);
        $this->assertSame("said a\nb\u{00e9}", json_decode($raw, true)['result']['content'][0]['text']);
    }

    private function withCatalog(): Server
    {
        return new Server(new ToolRegistry(), 'wbengine-mcp', '1.2.3', 'Use the tools.', null, Server::MAX_RESULT_BYTES, new \Wbengine\Mcp\Catalog());
    }

    public function testPromptsAndResourcesInBothEras(): void
    {
        $server = $this->withCatalog();
        $discover = $this->call($server, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'server/discover', 'params' => $this->modernMeta()])['result'];
        $this->assertArrayHasKey('prompts', $discover['capabilities']);
        $this->assertArrayHasKey('resources', $discover['capabilities']);
        $modern = $this->call($server, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'resources/list', 'params' => $this->modernMeta()])['result'];
        $this->assertSame('complete', $modern['resultType']);
        $this->assertContains('wbengine://guide/overview', array_column($modern['resources'], 'uri'));

        $this->call($server, ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25']]);
        $prompts = $this->call($server, ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'prompts/list'])['result']['prompts'];
        $this->assertSame(['publish_new_article', 'translate_article', 'weekly_report', 'find_problems', 'seo_review'], array_column($prompts, 'name'));
        $prompt = $this->call($server, ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'prompts/get', 'params' => ['name' => 'translate_article', 'arguments' => ['article_id' => 7, 'language' => 'cs']]])['result'];
        $this->assertStringContainsString('article #7 into cs', $prompt['messages'][0]['content']['text']);
        $read = $this->call($server, ['jsonrpc' => '2.0', 'id' => 6, 'method' => 'resources/read', 'params' => ['uri' => 'wbengine://guide/menu']])['result']['contents'][0];
        $this->assertSame(['wbengine://guide/menu', 'text/markdown'], [$read['uri'], $read['mimeType']]);
        $this->assertStringContainsString('Menu', $read['text']);
    }

    public function testPromptAndResourceProblemsAreProtocolErrors(): void
    {
        $server = $this->withCatalog();
        $this->call($server, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25']]);
        $this->assertSame(Protocol::INVALID_PARAMS, $this->call($server, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'prompts/get', 'params' => ['name' => 'nope']])['error']['code']);
        $missing = $this->call($server, ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'prompts/get', 'params' => ['name' => 'seo_review']]);
        $this->assertStringContainsString('page_id', $missing['error']['message']);
        $this->assertSame(Protocol::RESOURCE_NOT_FOUND, $this->call($server, ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'resources/read', 'params' => ['uri' => 'wbengine://guide/../../etc/passwd']])['error']['code']);
        $this->assertSame(Protocol::RESOURCE_NOT_FOUND, $this->call($server, ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'resources/read', 'params' => ['uri' => 'file:///etc/passwd']])['error']['code']);
        $this->assertSame(Protocol::METHOD_NOT_FOUND, $this->call($this->server(), ['jsonrpc' => '2.0', 'id' => 6, 'method' => 'prompts/list', 'params' => $this->modernMeta()])['error']['code'], 'without a catalog the methods do not exist');
    }

    public function testToolsAreOfferedByTheScopesOfTheKey(): void
    {
        $tool = static fn(string $name, array $scopes) => new Tool($name, $name, 'x', ['type' => 'object'], ['readOnly' => true, 'destructive' => false, 'idempotent' => true], static fn() => ToolResult::ok('ok'), $scopes);
        $registry = (new ToolRegistry())->add($tool('open', []))->add($tool('read', ['a:read']))->add($tool('write', ['a:read', 'a:write']));
        $registry->filterByScopes(static fn() => ['a:read']);
        $this->assertSame(['open', 'read'], array_map(static fn($t) => $t->name, $registry->all()));
        $this->assertNull($registry->get('write'));
        $unknown = (new ToolRegistry())->add($tool('read', ['a:read']));
        $unknown->filterByScopes(static fn() => null);
        $this->assertCount(1, $unknown->all(), 'when the scopes cannot be known every tool is offered and the API decides');
        $calls = 0;
        $counted = (new ToolRegistry())->add($tool('read', ['a:read']));
        $counted->filterByScopes(static function () use (&$calls) { ++$calls; return ['a:read']; });
        $counted->all(); $counted->all(); $counted->get('read');
        $this->assertSame(1, $calls, 'the scopes are asked for once');
    }
}
