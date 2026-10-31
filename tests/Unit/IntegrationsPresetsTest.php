<?php
namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Box\Integrations;

/** The permission presets of the issue form and the MCP client configuration of the one-time screen. */
final class IntegrationsPresetsTest extends TestCase
{
    public function testEveryPresetUsesRealGroupsAndLevelsAndOnlyTheEditorAsksForAnalytics(): void
    {
        foreach (Integrations::PRESETS as $id => $preset) {
            $groups = 'editor' === $id ? Integrations::GROUPS : array_diff_key(Integrations::GROUPS, ['analytics' => 1]);
            $this->assertSame(array_keys($groups), array_keys($preset['levels']), $id . ' sets every group' . ('editor' === $id ? '' : ' but analytics'));
            foreach ($preset['levels'] as $group => $level) {
                $this->assertTrue('off' === $level || array_key_exists($level, Integrations::GROUPS[$group]), $id . ': ' . $group . ' has no level ' . $level);
            }
            $this->assertNotSame('', $preset['name']);
            $this->assertLessThanOrEqual(128, strlen($preset['name']));
            $this->assertSame('editor' === $id, in_array('analytics:read', Integrations::scopesForGroups($preset['levels']), true), $id);
        }
    }

    public function testThePresetsGiveTheScopesThePlanPromises(): void
    {
        $scopes = static fn(string $id): array => Integrations::scopesForGroups(Integrations::PRESETS[$id]['levels']);
        $this->assertEqualsCanonicalizing(Integrations::PROFILE_SCOPES, $scopes('full'), 'full is the default profile');
        $editor = $scopes('editor');
        foreach (['articles:write', 'pages:write', 'menu:write', 'publish:write', 'images:write', 'boxes:write', 'boxes:read', 'sections:read', 'audit:read', 'analytics:read'] as $scope) { $this->assertContains($scope, $editor, $scope); }
        foreach (['users:write', 'users:read', 'config:read', 'cache:write'] as $scope) { $this->assertNotContains($scope, $editor, $scope); }
        $agent = $scopes('agent');
        foreach (['articles:write', 'pages:write', 'menu:write', 'translations:write', 'publish:write', 'images:write', 'audit:read', 'preview:read'] as $scope) { $this->assertContains($scope, $agent, $scope); }
        foreach (['sections:write', 'boxes:write', 'users:write', 'users:read', 'config:read', 'cache:write', 'sections:read'] as $scope) { $this->assertNotContains($scope, $agent, $scope); }
        $drafts = $scopes('drafts');
        $this->assertContains('articles:write', $drafts);
        $this->assertNotContains('publish:write', $drafts);
        $this->assertContains('audit:read', $drafts);
        $readonly = $scopes('readonly');
        $this->assertSame([], array_filter($readonly, static fn(string $s) => str_ends_with($s, ':write')));
        $this->assertContains('articles:read', $readonly);
    }

    public function testEveryPresetAndTheMcpBlockAreTranslated(): void
    {
        foreach (['en', 'cs'] as $locale) {
            $messages = require dirname(__DIR__, 2) . '/I18n/messages/' . $locale . '.php';
            foreach (array_keys(Integrations::PRESETS) as $id) {
                $this->assertArrayHasKey('wb.integrations.preset.' . $id, $messages, $locale);
                $this->assertArrayHasKey('wb.integrations.preset_help.' . $id, $messages, $locale);
            }
            foreach (['presets_title', 'presets_help', 'mcp_title', 'mcp_help', 'mcp_json', 'mcp_command', 'mcp_note'] as $key) {
                $this->assertArrayHasKey('wb.integrations.' . $key, $messages, $locale);
            }
            $this->assertStringContainsString($locale === 'en' ? 'should not be bound' : 'neměl být vázán', $messages['wb.integrations.ips_help']);
        }
    }

    public function testTheClientConfigurationNamesTheSiteAndTheMode(): void
    {
        $key = 'wbv2_' . str_repeat('ab', 32);
        $full = Integrations::mcpClientConfig('https://www.example.org/', $key, ['articles:write', 'publish:write']);
        $this->assertSame('wbengine-www-example-org', $full['server']);
        $this->assertSame('full', $full['mode']);
        $config = json_decode($full['json'], true);
        $entry = $config['mcpServers']['wbengine-www-example-org'];
        $this->assertSame('php', $entry['command']);
        $this->assertSame([Integrations::MCP_PHAR_PLACEHOLDER], $entry['args']);
        $this->assertSame(['WBENGINE_MCP_URL' => 'https://www.example.org', 'WBENGINE_MCP_KEY' => $key, 'WBENGINE_MCP_MODE' => 'full'], $entry['env']);
        $this->assertStringContainsString("--env 'WBENGINE_MCP_KEY=" . $key . "'", $full['command']);
        $this->assertStringContainsString("--env 'WBENGINE_MCP_URL=https://www.example.org'", $full['command']);
        $this->assertMatchesRegularExpression('~^claude mcp add( --env \'[A-Z_]+=[^\']+\')+ --transport stdio wbengine-www-example-org -- php /path/to/wbengine-mcp\.phar$~', $full['command']);
        $drafts = Integrations::mcpClientConfig('https://blog.example.org', $key, ['articles:write']);
        $this->assertSame('draft', $drafts['mode']);
        $this->assertStringContainsString("'WBENGINE_MCP_MODE=draft'", $drafts['command']);
        $this->assertSame('wbengine-site', Integrations::mcpClientConfig('', $key, [])['server']);
    }
}
