<?php
namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Api\V2\Model;
use Wbengine\Db;

/**
 * The server as a real process (bin/wbengine-mcp.php, or the phar when WB_MCP_PHAR is set) speaking stdio
 * to a site served by the PHP built-in server on the test database.
 */
final class McpEndToEndTest extends TestCase
{
    private const USER_ID = 90011;

    /** @var resource|null */
    private $site;
    /** @var resource|null */
    private $server;
    /** @var resource[] */
    private array $pipes = [];
    private int $port = 0;
    private string $key = '';
    private array $before = [];
    private bool $addedFormat = false;
    private int $next = 1;

    protected function setUp(): void
    {
        try { Db::query('SELECT 1'); } catch (\Throwable $e) { $this->markTestSkipped('MySQL unavailable.'); }
        if (!function_exists('proc_open')) { $this->markTestSkipped('proc_open is not available.'); }
        // The permanent test schema has no Markdown columns (migration 003): add them for this test only.
        if ([] === Db::fetchAllAssoc("SHOW COLUMNS FROM cms_articles LIKE 'content_format'")) {
            Db::query("ALTER TABLE cms_articles ADD content_format VARCHAR(20) NOT NULL DEFAULT 'legacy_html'");
            Db::query("ALTER TABLE cms_article_translations ADD content_format VARCHAR(20) NOT NULL DEFAULT 'legacy_html'");
            $this->addedFormat = true;
        }
        $this->before = [
            'cms_articles' => ['id', (int) Db::fetchAssoc('SELECT COALESCE(MAX(id), 0) n FROM cms_articles')['n']],
            'cms_sites' => ['site_id', (int) Db::fetchAssoc('SELECT COALESCE(MAX(site_id), 0) n FROM cms_sites')['n']],
            'cms_menu' => ['menu_id', (int) Db::fetchAssoc('SELECT COALESCE(MAX(menu_id), 0) n FROM cms_menu')['n']],
            'cms_api_changes' => ['change_id', (int) Db::fetchAssoc('SELECT COALESCE(MAX(change_id), 0) n FROM cms_api_changes')['n']],
        ];
        Db::insert('cms_users', ['user_id' => self::USER_ID, 'username' => 'mcp-e2e', 'role' => 'admin']);
        $this->key = (new Model())->provision(self::USER_ID, 'MCP end to end', ['discovery:read', 'articles:read', 'articles:write', 'pages:read', 'pages:write', 'menu:read', 'menu:write', 'translations:read', 'translations:write', 'preview:read', 'publish:write', 'audit:read'])['key'];
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->port = (int) parse_url(stream_socket_get_name($socket, false), PHP_URL_PORT);
        fclose($socket);
        $this->site = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $this->port, __DIR__ . '/../fixtures/mcp-site.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $sitePipes, dirname(__DIR__, 2), getenv());
        for ($i = 0; $i < 100 && !@fsockopen('127.0.0.1', $this->port); $i++) { usleep(50000); }
    }

    protected function tearDown(): void
    {
        foreach ($this->pipes as $pipe) { is_resource($pipe) && fclose($pipe); }
        foreach ([$this->server, $this->site] as $process) { if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
        if (!isset($this->before['cms_articles'])) { return; }
        foreach ($this->before as $table => [$column, $max]) { Db::query('DELETE FROM ' . $table . ' WHERE ' . $column . ' > ?', [$max]); }
        Db::query('DELETE FROM cms_article_translations WHERE article_id > ?', [$this->before['cms_articles'][1]]);
        Db::query('DELETE FROM cms_api_drafts WHERE article_id > ?', [$this->before['cms_articles'][1]]);
        Db::query('DELETE FROM cms_api_revisions WHERE article_id > ?', [$this->before['cms_articles'][1]]);
        Db::query('DELETE FROM cms_api_audit WHERE integration_id IN (SELECT integration_id FROM cms_api_integrations WHERE created_by = ?)', [self::USER_ID]);
        Db::query('DELETE FROM cms_api_integrations WHERE created_by = ?', [self::USER_ID]);
        Db::query('DELETE FROM cms_users WHERE user_id = ?', [self::USER_ID]);
        if ($this->addedFormat) {
            Db::query('ALTER TABLE cms_articles DROP COLUMN content_format');
            Db::query('ALTER TABLE cms_article_translations DROP COLUMN content_format');
        }
    }

    private function start(array $env = []): void
    {
        $command = ($phar = getenv('WB_MCP_PHAR')) ? [PHP_BINARY, $phar] : [PHP_BINARY, dirname(__DIR__, 2) . '/bin/wbengine-mcp.php'];
        $environment = $env + ['WBENGINE_MCP_URL' => 'http://127.0.0.1:' . $this->port, 'WBENGINE_MCP_KEY' => $this->key, 'WBENGINE_MCP_ALLOW_INSECURE' => '1', 'PATH' => getenv('PATH')];
        $this->server = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $this->pipes, null, $environment);
        $this->assertIsResource($this->server);
        stream_set_timeout($this->pipes[1], 20);
    }

    private function rpc(string $method, array $params = [], bool $notification = false): ?array
    {
        $message = ['jsonrpc' => '2.0', 'method' => $method] + ($params ? ['params' => $params] : []);
        if (!$notification) { $message['id'] = $this->next++; }
        fwrite($this->pipes[0], json_encode($message) . "\n");
        if ($notification) { return null; }
        $line = fgets($this->pipes[1]);
        $this->assertNotFalse($line, 'the server did not answer ' . $method);
        $this->assertSame($message['id'], json_decode($line, true)['id']);
        return json_decode($line, true);
    }

    private function tool(string $name, array $arguments = []): array
    {
        $response = $this->rpc('tools/call', ['name' => $name, 'arguments' => $arguments]);
        $this->assertArrayHasKey('result', $response, json_encode($response));
        return $response['result'];
    }

    public function testLegacyClientWritesPublishesHidesAndReadsTheChangeFeed(): void
    {
        $this->start();
        $init = $this->rpc('initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'E2E Client', 'version' => '1']]);
        $this->assertSame('2025-11-25', $init['result']['protocolVersion']);
        $this->rpc('notifications/initialized', [], true);
        $names = array_column($this->rpc('tools/list')['result']['tools'], 'name');
        $this->assertCount(18, $names, 'the tools this key may use, none of Structure, Images or statistics');
        $this->assertContains('analyze_activity', $names);
        $this->assertNotContains('get_statistics', $names);
        $this->assertContains('wbengine_guide', $names);
        $this->assertNotContains('add_box', $names);

        $info = $this->tool('site_info');
        $this->assertFalse($info['isError'], json_encode($info));
        $this->assertTrue($info['structuredContent']['can']['publish_and_hide']);

        $saved = $this->tool('save_article_draft', ['title' => 'End to end', 'introtext' => 'Intro', 'bodytext' => 'Body **text**', 'new_page' => ['link' => '/e2e-mcp/'], 'translations' => ['en' => ['title' => 'English title', 'bodytext' => 'English body']]]);
        $this->assertFalse($saved['isError'], json_encode($saved));
        $id = $saved['structuredContent']['id'];

        $preview = $this->tool('preview_article', ['id' => $id, 'locale' => 'en']);
        $this->assertSame('English title', $preview['structuredContent']['title']);
        $this->assertSame('draft', $this->tool('get_article', ['id' => $id])['structuredContent']['state']);

        $this->assertSame('published', $this->tool('publish_article', ['id' => $id])['structuredContent']['state']);
        $this->assertSame('hidden', $this->tool('hide_article', ['id' => $id])['structuredContent']['state']);
        $this->assertSame('hidden', $this->tool('get_article', ['id' => $id])['structuredContent']['state']);
        $this->assertSame('published', $this->tool('unhide_article', ['id' => $id])['structuredContent']['state']);

        $changes = $this->tool('list_changes', ['resource' => 'articles', 'id' => $id]);
        $this->assertSame(['mcp:E2E Client'], array_values(array_unique(array_column($changes['structuredContent']['changes'], 'actor'))));
        $this->assertContains('hide', array_column($changes['structuredContent']['changes'], 'action'));

        $this->assertStringNotContainsString($this->key, json_encode([$info, $saved, $changes]));
        fclose($this->pipes[0]);
        $this->assertSame('', stream_get_contents($this->pipes[1]));
        $stderr = stream_get_contents($this->pipes[2]);
        $this->assertStringContainsString('serving http://127.0.0.1:' . $this->port, $stderr);
        $this->assertStringNotContainsString($this->key, $stderr);
        $this->assertSame(0, proc_close($this->server));
        $this->server = null;
    }

    public function testModernClientNeedsNoHandshake(): void
    {
        $this->start(['WBENGINE_MCP_ACTOR' => 'modern-bot']);
        $meta = ['_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => new \stdClass()]];
        $discover = $this->rpc('server/discover', $meta);
        $this->assertSame('complete', $discover['result']['resultType']);
        $this->assertContains('2026-07-28', $discover['result']['supportedVersions']);
        $call = $this->rpc('tools/call', $meta + ['name' => 'save_article_draft', 'arguments' => ['title' => 'Modern', 'bodytext' => 'x', 'new_page' => ['link' => '/e2e-modern/']]]);
        $this->assertFalse($call['result']['isError'], json_encode($call));
        $this->assertSame('complete', $call['result']['resultType']);
        $id = $call['result']['structuredContent']['id'];
        $changes = $this->rpc('tools/call', $meta + ['name' => 'list_changes', 'arguments' => ['resource' => 'articles', 'id' => $id]]);
        $this->assertSame('modern-bot', $changes['result']['structuredContent']['changes'][0]['actor']);
        $old = $this->rpc('tools/list', ['_meta' => ['io.modelcontextprotocol/protocolVersion' => '1900-01-01', 'io.modelcontextprotocol/clientCapabilities' => new \stdClass()]]);
        $this->assertSame(-32022, $old['error']['code']);
    }

    public function testAKeyTheSiteRejectsGivesAnActionableToolError(): void
    {
        $this->start(['WBENGINE_MCP_KEY' => 'wbv2_' . str_repeat('0', 64)]);
        $this->rpc('initialize', ['protocolVersion' => '2025-11-25']);
        $result = $this->tool('site_info');
        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('integration key was rejected', $result['content'][0]['text']);
    }
}
