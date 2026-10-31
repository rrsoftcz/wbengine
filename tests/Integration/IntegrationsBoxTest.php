<?php

namespace Wbengine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Wbengine\Api\V2\Model;
use Wbengine\Box\Integrations;
use Wbengine\Db;
use Wbengine\RateLimit\Limiter;
use Wbengine\Template\Engine;
use Wbengine\Tests\Support\UserSession;
use Wbengine\User\PasswordConfirmation;

/** Box logic in one process with connection-local tables, so audit failures can be forced. */
class IntegrationsBoxTest extends TestCase
{
    private array $post;
    private array $server;
    private string $dir;

    protected function setUp(): void
    {
        \Wbengine\User\AccountState::reset();
        try {
            Db::query('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL test database is not available.');
        }
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->dir = sys_get_temp_dir() . '/wb-box-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        Db::query('CREATE TEMPORARY TABLE cms_users (user_id INT PRIMARY KEY, role VARCHAR(16) NOT NULL, password VARCHAR(255) NOT NULL) ENGINE=InnoDB');
        Db::insert('cms_users', ['user_id' => 41, 'role' => 'admin', 'password' => md5('admin-secret')]);
        Db::insert('cms_users', ['user_id' => 42, 'role' => 'editor', 'password' => md5('editor-secret')]);
        $sql = preg_replace('/^--.*$/m', '', file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/002-api-v2-integrations.sql'));
        $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $sql);
        foreach (explode(';', $sql) as $statement) {
            if ('' !== trim($statement)) { Db::query($statement); }
        }
        Db::query('ALTER TABLE cms_api_integrations ADD COLUMN last_used_at BIGINT UNSIGNED NULL DEFAULT NULL');
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        $_SERVER = $this->server;
        (new \ReflectionProperty(\Wbengine\PageCache::class, '_bypassed'))->setValue(null, false);
        (new \ReflectionProperty(\Wbengine\Template\FragmentCache::class, '_bypassed'))->setValue(null, false);
        if (Db::isConnected()) {
            Db::query('DROP TEMPORARY TABLE IF EXISTS cms_users, cms_api_integrations, cms_api_audit');
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) { is_dir($file) ? null : @unlink($file); }
        @rmdir($this->dir);
    }

    /** @return array{0: Integrations, 1: object} the box and a recorder of status and headers */
    private function box(int $userId, ?string $method = null, array $problems = [], ?UserSession $session = null, array $analyticsProblems = []): array
    {
        $session ??= new UserSession($userId);
        $renderer = new class($this->dir) {
            private Engine $engine;
            public function __construct(string $dir) { $this->engine = (new Engine())->setTemplateDir(dirname(__DIR__, 2) . '/Box')->setCompileDir($dir); }
            public function assign($name, $value, $nocache = false) { $this->engine->assign($name, $value); }
            public function render($template, $vars = null, $static = false) { return $this->engine->fetch($template . '.tpl'); }
        };
        $parent = new class($session, $renderer) {
            public function __construct(private $session, private $renderer) {}
            public function getSession() { return $this->session; }
            public function getRenderer() { return $this->renderer; }
        };
        $recorder = new \stdClass();
        $recorder->headers = [];
        $recorder->status = null;
        $limiter = new Limiter($this->dir . '/limits', fn () => 3_000_000);
        $box = new class(null, $parent, $recorder, $method, $problems, $limiter, $analyticsProblems) extends Integrations {
            public function __construct($box, $parent, private $recorder, private ?string $method, private array $forcedProblems, private Limiter $limiter, private array $forcedAnalytics = [])
            {
                parent::__construct($box, $parent);
            }
            protected function requestMethod(): string { return $this->method ?? 'GET'; }
            protected function preflight(): array { return $this->forcedProblems; }
            protected function analyticsProblems(): array { return $this->forcedAnalytics; }
            protected function passwordConfirmation(): PasswordConfirmation { return new PasswordConfirmation($this->limiter, 900); }
            protected function sendHeader(string $line): void { $this->recorder->headers[] = $line; }
            protected function sendStatus(int $status): void { $this->recorder->status = $status; }
        };
        $_SERVER['REQUEST_URI'] = '/integrations/';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        return [$box, $recorder, $session];
    }

    private function post(array $fields, UserSession $session, string $purpose): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        $_POST = $fields + ['token' => $session->getCsrf()->token($purpose)];
    }

    public function testThePresetButtonsFillTheFormAndTheClientBlockAppearsOnlyWhenTheKeyIsIssued(): void
    {
        [$box] = $this->box(41, 'GET');
        $form = $box->Integrations();
        $this->assertSame(1, substr_count($form, 'data-wb-presets hidden'), 'hidden until the script shows it, so the form works without JavaScript');
        foreach (['full', 'agent', 'drafts', 'readonly'] as $id) { $this->assertStringContainsString('data-wb-preset="' . $id . '"', $form); }
        $this->assertStringContainsString('data-name="MCP agent (drafts)" data-levels="content=write;publish=off;structure=off;images=write;site=off;history=read"', $form);
        $this->assertStringContainsString('data-name="WBengine Admin" data-levels="content=write;publish=write;structure=write;images=write;site=write;history=read"', $form);
        $this->assertStringNotContainsString('WBENGINE_MCP', $form);
        $this->assertStringNotContainsString('claude mcp add', $form);

        [$box, , $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'MCP agent', 'password' => 'admin-secret', 'group_content' => 'write', 'group_publish' => 'write', 'group_images' => 'write', 'group_history' => 'read'], $session, Integrations::PURPOSE_ISSUE);
        $issued = $box->Integrations();
        $this->assertSame(1, substr_count($issued, 'id="wb-mcp-json"'));
        $this->assertSame(1, substr_count($issued, 'id="wb-mcp-command"'));
        $this->assertSame(1, preg_match('~<textarea[^>]*id="wb-mcp-json">(.*?)</textarea>~s', $issued, $json));
        $config = json_decode(html_entity_decode($json[1]), true);
        $entry = array_values($config['mcpServers'])[0];
        $this->assertSame('full', $entry['env']['WBENGINE_MCP_MODE']);
        $this->assertSame(Db::fetchAssoc('SELECT key_hash FROM cms_api_integrations')['key_hash'], hash('sha256', $entry['env']['WBENGINE_MCP_KEY']));
        $this->assertStringContainsString('claude mcp add', $issued);

        // Neither the block nor the key is shown again.
        [$box] = $this->box(41, 'GET');
        $again = $box->Integrations();
        $this->assertStringNotContainsString('WBENGINE_MCP', $again);
        $this->assertStringNotContainsString('wb-mcp-json', $again);
        $this->assertStringNotContainsString($entry['env']['WBENGINE_MCP_KEY'], $again);
    }

    public function testADraftsOnlyKeyGetsTheDraftModeInItsClientBlock(): void
    {
        [$box, , $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'MCP agent (drafts)', 'password' => 'admin-secret', 'group_content' => 'write', 'group_publish' => 'off', 'group_history' => 'read'], $session, Integrations::PURPOSE_ISSUE);
        $html = $box->Integrations();
        $this->assertSame(1, preg_match('~<textarea[^>]*id="wb-mcp-json">(.*?)</textarea>~s', $html, $json));
        $this->assertSame('draft', array_values(json_decode(html_entity_decode($json[1]), true)['mcpServers'])[0]['env']['WBENGINE_MCP_MODE']);
    }

    public function testIssueUsesTheCompleteAdminProfileAndRendersTheKeyOnce(): void
    {
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'WBengine Admin', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
        $html = $box->Integrations();
        $this->assertSame(200, $out->status);
        // The key is in its field, in the client JSON and in the Claude Code command of the one-time screen, and nowhere else.
        $this->assertSame(3, preg_match_all('/wbv2_[a-f0-9]{64}/', $html, $m));
        $this->assertCount(1, array_unique($m[0]));
        $row = Db::fetchAssoc('SELECT * FROM cms_api_integrations');
        $this->assertSame(hash('sha256', $m[0][0]), $row['key_hash']);
        $issued = json_decode($row['scopes'], true);
        $this->assertEqualsCanonicalizing(Integrations::PROFILE_SCOPES, $issued);
        $this->assertCount(22, Integrations::PROFILE_SCOPES);
        $this->assertNotContains('analytics:read', Integrations::PROFILE_SCOPES);
        // Every scope of the profile is a real framework scope, even when the framework knows more.
        $this->assertSame([], array_diff(Integrations::PROFILE_SCOPES, Model::SCOPES));
        $this->assertContains('Cache-Control: private, no-store', $out->headers);
        $this->assertContains('Referrer-Policy: same-origin', $out->headers);
        $this->assertNotContains('Referrer-Policy: no-referrer', $out->headers);
        // The key is in no session value.
        $this->assertStringNotContainsString($m[0][0], json_encode($session->values));
        // The follow-up GET lists metadata only.
        [$box2, $out2] = $this->box(41, 'GET', [], $session);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $this->assertStringNotContainsString($m[0][0], $box2->Integrations());
    }

    private function lastRow(): array
    {
        return Db::fetchAssoc('SELECT * FROM cms_api_integrations ORDER BY integration_id DESC LIMIT 1');
    }

    public function testIssueWithTheAnalyticsChoiceAddsExactlyOneScope(): void
    {
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'WBengine Admin', 'analytics' => '1', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
        $html = $box->Integrations();
        $this->assertSame(200, $out->status);
        $scopes = json_decode($this->lastRow()['scopes'], true);
        $this->assertCount(23, $scopes);
        $this->assertEqualsCanonicalizing(array_merge(Integrations::PROFILE_SCOPES, ['analytics:read']), $scopes);
        $this->assertSame([], array_diff($scopes, Model::SCOPES));
        $this->assertMatchesRegularExpression('~<details class="wb-scopes">\s*<summary>[^<]*\(23\)</summary>~', $html);
        $this->assertStringContainsString('Read the website traffic overview', $html);
        $this->assertStringNotContainsString('<details class="wb-scopes" open', $html);
        // The list of keys shows that the key has analytics.
        [$box2] = $this->box(41, 'GET', [], $session);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $this->assertStringContainsString('<span class="wb-badge">Analytics</span>', $box2->Integrations());
    }

    public function testIssueWithoutTheChoiceOrWithItUncheckedStaysTheFixedProfile(): void
    {
        foreach ([[], ['analytics' => ''], ['analytics' => '0']] as $extra) {
            [$box, $out, $session] = $this->box(41, 'POST');
            $this->post(['action' => 'issue', 'name' => 'WBengine Admin', 'password' => 'admin-secret'] + $extra, $session, Integrations::PURPOSE_ISSUE);
            $html = $box->Integrations();
            $this->assertSame(200, $out->status);
            $this->assertEqualsCanonicalizing(Integrations::PROFILE_SCOPES, json_decode($this->lastRow()['scopes'], true));
            $this->assertMatchesRegularExpression('~<summary>[^<]*\(22\)</summary>~', $html);
            $this->assertStringNotContainsString('<span class="wb-badge">Analytics</span>', $html);
        }
    }

    public function testAnalyticsChoiceIsDisabledWithAnExplanationWhenAnalyticsIsNotUsable(): void
    {
        $cases = ['analytics_disabled' => 'analytics.enabled = true', 'analytics_schema' => '006-analytics.sql', 'analytics_database' => 'doctor'];
        foreach ($cases as $code => $needle) {
            [$box, $out] = $this->box(41, 'GET', [], null, [$code]);
            $html = $box->Integrations();
            $this->assertSame(200, $out->status);
            $this->assertStringContainsString('<input type="radio" name="group_analytics" value="read" disabled>', $html, $code);
            $this->assertStringContainsString(htmlspecialchars(\Wbengine\I18n::t('wb.integrations.analytics_problem.' . $code), ENT_QUOTES), $html);
            $this->assertStringContainsString($needle, \Wbengine\I18n::t('wb.integrations.analytics_problem.' . $code));
            $this->assertStringContainsString(Integrations::ANALYTICS_DOCS, $html);
            // The form still issues a key without analytics.
            $this->assertStringContainsString('name="password"', $html);
        }
        [$box, $out] = $this->box(41, 'GET');
        $html = $box->Integrations();
        $this->assertStringContainsString('<input type="radio" name="group_analytics" value="read">', $html);
        $this->assertStringNotContainsString('name="group_analytics" value="read" disabled', $html);
        $this->assertStringNotContainsString('wb-off', explode('</style>', $html)[1]);
    }

    public function testServerRefusesTheAnalyticsChoiceWhenAnalyticsIsNotUsableAndIssuesNothing(): void
    {
        foreach (['analytics_disabled', 'analytics_schema'] as $code) {
            [$box, $out, $session] = $this->box(41, 'POST', [], null, [$code]);
            $this->post(['action' => 'issue', 'name' => 'WBengine Admin', 'analytics' => '1', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
            $html = $box->Integrations();
            $this->assertSame(400, $out->status);
            $this->assertStringContainsString(htmlspecialchars(\Wbengine\I18n::t('wb.integrations.error.analytics_unavailable'), ENT_QUOTES), $html);
            $this->assertStringNotContainsString('wbv2_', $html);
        }
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_audit')['n']);
        // Without the choice the same configuration still issues.
        [$box, $out, $session] = $this->box(41, 'POST', [], null, ['analytics_disabled']);
        $this->post(['action' => 'issue', 'name' => 'WBengine Admin', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
        $box->Integrations();
        $this->assertSame(200, $out->status);
        $this->assertEqualsCanonicalizing(Integrations::PROFILE_SCOPES, json_decode($this->lastRow()['scopes'], true));
    }

    public function testOnlyABooleanIsReadAndNeverAScopeListFromTheClient(): void
    {
        foreach ([['analytics' => 'analytics:read'], ['analytics' => 'yes'], ['analytics' => ['analytics:read']], ['scopes' => ['publish:write', 'analytics:read']], ['scopes' => 'analytics:read']] as $extra) {
            [$box, $out, $session] = $this->box(41, 'POST');
            $this->post(['action' => 'issue', 'name' => 'WBengine Admin', 'password' => 'admin-secret'] + $extra, $session, Integrations::PURPOSE_ISSUE);
            $box->Integrations();
            if (isset($extra['analytics']) && is_string($extra['analytics'])) {
                $this->assertSame(400, $out->status, json_encode($extra));
            } else {
                $this->assertSame(200, $out->status, json_encode($extra));
                $this->assertEqualsCanonicalizing(Integrations::PROFILE_SCOPES, json_decode($this->lastRow()['scopes'], true));
            }
        }
        $this->assertSame(3, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testListShowsActiveKeysFirstAndFoldsRevokedOnesAway(): void
    {
        $old = (new Model())->provision(41, 'Old key', Integrations::scopesForGroups(['content' => 'read']));
        (new Model())->provision(41, 'Current key', Integrations::PROFILE_SCOPES);
        Db::query('UPDATE cms_api_integrations SET revoked_at = ' . time() . ' WHERE integration_id = ' . (int) $old['integration_id']);
        (new Model())->provision(41, 'Reader key', Integrations::scopesForGroups(['content' => 'read', 'structure' => 'read']));
        [$box] = $this->box(41, 'GET');
        $html = $box->Integrations();
        $this->assertSame(2, substr_count($html, 'class="wb-key"'));
        $this->assertStringContainsString('<span class="wb-badge wb-lv-read">Content: Read</span>', $html);
        $this->assertStringContainsString('<span class="wb-badge wb-lv-write">Content: Read and write</span>', $html);
        $this->assertStringContainsString('Site administration: Read and write</span>', $html);
        $this->assertSame(2, substr_count($html, 'name="action" value="revoke"'), 'a revoke form exists for active keys only');
        $this->assertMatchesRegularExpression('~<summary>Revoked keys \(1\)</summary>~', $html);
        $this->assertLessThan(strpos($html, 'Revoked keys (1)'), strpos($html, 'Current key'));
    }

    public function testIssueWithChosenGroupsGrantsExactlyThoseScopes(): void
    {
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'Editor key', 'password' => 'admin-secret', 'group_content' => 'write', 'group_publish' => 'off', 'group_structure' => 'read', 'group_images' => 'read', 'group_site' => 'off', 'group_history' => 'read'], $session, Integrations::PURPOSE_ISSUE);
        $box->Integrations();
        $this->assertSame(200, $out->status);
        $scopes = json_decode($this->lastRow()['scopes'], true);
        $this->assertEqualsCanonicalizing(['discovery:read', 'articles:read', 'pages:read', 'menu:read', 'translations:read', 'preview:read', 'articles:write', 'pages:write', 'menu:write', 'translations:write', 'sections:read', 'boxes:read', 'images:read', 'audit:read'], $scopes);
        $this->assertNotContains('publish:write', $scopes);
        $this->assertNotContains('users:write', $scopes);
        $this->assertSame([], array_diff($scopes, Model::SCOPES));
    }

    public function testWriteLevelAlwaysIncludesTheReadLevelAndAnalyticsStaysItsOwnChoice(): void
    {
        $this->assertSame(['discovery:read', 'images:read', 'images:write'], Integrations::scopesForGroups(['images' => 'write']));
        $this->assertSame(['discovery:read', 'analytics:read'], Integrations::scopesForGroups(['analytics' => 'read']));
        $this->assertSame(['content' => 'write', 'publish' => 'write', 'structure' => 'write', 'images' => 'write', 'site' => 'write', 'history' => 'read'], Integrations::levelsOf(Integrations::PROFILE_SCOPES));
        $this->assertSame(['content' => 'read', 'images' => 'read'], Integrations::levelsOf(['discovery:read', 'articles:read', 'pages:read', 'menu:read', 'translations:read', 'preview:read', 'images:read', 'users:write']));
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'Analytics only', 'password' => 'admin-secret', 'group_content' => 'off', 'group_publish' => 'off', 'group_structure' => 'off', 'group_images' => 'off', 'group_site' => 'off', 'group_history' => 'off', 'analytics' => '1'], $session, Integrations::PURPOSE_ISSUE);
        $box->Integrations();
        $this->assertSame(200, $out->status);
        $this->assertSame(['discovery:read', 'analytics:read'], json_decode($this->lastRow()['scopes'], true));
    }

    public function testAnalyticsCanBeChosenInTheMatrixAndOnlyAsOffOrRead(): void
    {
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'Matrix analytics', 'password' => 'admin-secret', 'group_content' => 'read', 'group_analytics' => 'read'], $session, Integrations::PURPOSE_ISSUE);
        $box->Integrations();
        $this->assertSame(200, $out->status);
        $this->assertContains('analytics:read', json_decode($this->lastRow()['scopes'], true));
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'Bad analytics', 'password' => 'admin-secret', 'group_content' => 'read', 'group_analytics' => 'write'], $session, Integrations::PURPOSE_ISSUE);
        $box->Integrations();
        $this->assertSame(400, $out->status);
    }

    public function testUnknownGroupLevelsAndEmptyKeysAreRefusedAndNothingIsIssued(): void
    {
        foreach ([['group_content' => 'admin'], ['group_site' => 'on'], ['group_publish' => 'read'], ['group_history' => 'on'], ['group_content' => 'off', 'group_publish' => 'off', 'group_structure' => 'off', 'group_images' => 'off', 'group_site' => 'off', 'group_history' => 'off']] as $groups) {
            [$box, $out, $session] = $this->box(41, 'POST');
            $this->post(['action' => 'issue', 'name' => 'Bad', 'password' => 'admin-secret'] + $groups, $session, Integrations::PURPOSE_ISSUE);
            $html = $box->Integrations();
            $this->assertSame(400, $out->status, json_encode($groups));
            $this->assertSame(0, preg_match('/wbv2_[a-f0-9]{64}/', $html));
        }
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testTheFormOffersEveryGroupWithTheFullProfilePreselectedAndRotationPresetsTheLevels(): void
    {
        (new Model())->provision(41, 'Reader', Integrations::scopesForGroups(['content' => 'read', 'site' => 'read']));
        [$box] = $this->box(41, 'GET');
        $html = $box->Integrations();
        foreach (['content', 'publish', 'structure', 'images', 'site', 'history'] as $group) {
            $this->assertStringContainsString('name="group_' . $group . '"', $html);
        }
        $this->assertStringContainsString('<input type="radio" name="group_content" value="write" checked>', $html);
        $this->assertStringNotContainsString('<input type="radio" name="group_content" value="off" checked>', $html);
        $this->assertSame(7, substr_count($html, 'class="wb-mrow"'), 'six groups and the analytics choice are rows of the matrix');
        $this->assertStringContainsString('<input type="radio" name="group_analytics" value="off" checked>', $html);
        $this->assertStringContainsString('data-groups="content=read;publish=off;structure=off;images=off;site=read;history=off;analytics=off"', $html);
        $this->assertStringContainsString("getElementsByName('group_'", $html);
    }

    private function withIpColumns(): void
    {
        Db::query('ALTER TABLE cms_api_integrations ADD COLUMN allowed_ips TEXT NULL DEFAULT NULL');
        Db::query('ALTER TABLE cms_api_integrations ADD COLUMN last_used_ip VARCHAR(45) NULL DEFAULT NULL');
    }

    public function testAddressControlsAreHiddenUntilTheMigrationIsApplied(): void
    {
        [$box] = $this->box(41, 'GET');
        $html = $box->Integrations();
        $this->assertStringNotContainsString('name="allowed_ips"', $html);
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'Needs migration', 'password' => 'admin-secret', 'allowed_ips' => '203.0.113.5'], $session, Integrations::PURPOSE_ISSUE);
        $box->Integrations();
        $this->assertSame(400, $out->status);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testKeyIsIssuedWithAllowedAddressesAndTheCardShowsThemWithTheLastUsedAddress(): void
    {
        $this->withIpColumns();
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'Admin server', 'password' => 'admin-secret', 'allowed_ips' => '203.0.113.5, 198.51.100.0/24'], $session, Integrations::PURPOSE_ISSUE);
        $box->Integrations();
        $this->assertSame(200, $out->status);
        $row = $this->lastRow();
        $this->assertSame(['203.0.113.5', '198.51.100.0/24'], json_decode($row['allowed_ips'], true));
        Db::query('UPDATE cms_api_integrations SET last_used_ip = ? WHERE integration_id = ?', ['203.0.113.5', $row['integration_id']]);
        [$box2] = $this->box(41, 'GET');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $html = $box2->Integrations();
        $this->assertStringContainsString('<code>203.0.113.5, 198.51.100.0/24</code>', $html);
        $this->assertStringContainsString('<code>203.0.113.5</code>', $html);
        $this->assertStringContainsString('name="allowed_ips"', $html);
        $this->assertMatchesRegularExpression('~data-ips="203\.0\.113\.5, 198\.51\.100\.0/24"~', $html);
    }

    public function testInvalidAddressesAreRefusedWhenIssuingAndNothingIsIssued(): void
    {
        $this->withIpColumns();
        foreach (['example.com', '10.0.0.0/40', '1.2.3.4; DROP TABLE x'] as $input) {
            [$box, $out, $session] = $this->box(41, 'POST');
            $this->post(['action' => 'issue', 'name' => 'Bad', 'password' => 'admin-secret', 'allowed_ips' => $input], $session, Integrations::PURPOSE_ISSUE);
            $html = $box->Integrations();
            $this->assertSame(400, $out->status, $input);
            $this->assertSame(0, preg_match('/wbv2_[a-f0-9]{64}/', $html));
        }
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testRestrictNeedsThePasswordAndTheTokenAndChangesOnlyTheChosenKey(): void
    {
        $this->withIpColumns();
        $first = (new Model())->provision(41, 'One', Integrations::PROFILE_SCOPES);
        $second = (new Model())->provision(41, 'Two', Integrations::PROFILE_SCOPES);
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'restrict', 'integration_id' => (string) $first['integration_id'], 'allowed_ips' => '203.0.113.9', 'password' => 'wrong'], $session, Integrations::PURPOSE_RESTRICT);
        $box->Integrations();
        $this->assertSame(403, $out->status);
        $this->assertNull(Db::fetchAssoc('SELECT allowed_ips FROM cms_api_integrations WHERE integration_id = ' . (int) $first['integration_id'])['allowed_ips']);
        [$box, $out, $session] = $this->box(41, 'POST');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => 'restrict', 'token' => 'forged', 'integration_id' => (string) $first['integration_id'], 'allowed_ips' => '203.0.113.9', 'password' => 'admin-secret'];
        $box->Integrations();
        $this->assertSame(403, $out->status);
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'restrict', 'integration_id' => (string) $first['integration_id'], 'allowed_ips' => '203.0.113.9', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_RESTRICT);
        $box->Integrations();
        $this->assertSame(200, $out->status);
        $this->assertSame(['203.0.113.9'], json_decode(Db::fetchAssoc('SELECT allowed_ips FROM cms_api_integrations WHERE integration_id = ' . (int) $first['integration_id'])['allowed_ips'], true));
        $this->assertNull(Db::fetchAssoc('SELECT allowed_ips FROM cms_api_integrations WHERE integration_id = ' . (int) $second['integration_id'])['allowed_ips']);
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'restrict', 'integration_id' => (string) $first['integration_id'], 'allowed_ips' => '', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_RESTRICT);
        $box->Integrations();
        $this->assertNull(Db::fetchAssoc('SELECT allowed_ips FROM cms_api_integrations WHERE integration_id = ' . (int) $first['integration_id'])['allowed_ips']);
    }

    public function testRotationPrefillsTheChoiceFromTheOldKey(): void
    {
        (new Model())->provision(41, 'Plain key', Integrations::PROFILE_SCOPES);
        (new Model())->provision(41, 'Analytics key', Integrations::scopesFor(true));
        [$box] = $this->box(41, 'GET');
        $html = $box->Integrations();
        $this->assertMatchesRegularExpression('~<option value="\d+" data-analytics="1" data-groups="[^"]*" data-ips="[^"]*">Analytics key #\d+</option>~', $html);
        $this->assertMatchesRegularExpression('~<option value="\d+" data-analytics="0" data-groups="[^"]*" data-ips="[^"]*">Plain key #\d+</option>~', $html);
        $this->assertStringContainsString("getAttribute('data-analytics')", $html);
    }

    public function testCachesAreBypassedBeforeAnythingIsDecided(): void
    {
        foreach ([0, 41] as $user) {
            (new \ReflectionProperty(\Wbengine\PageCache::class, '_bypassed'))->setValue(null, false);
            (new \ReflectionProperty(\Wbengine\Template\FragmentCache::class, '_bypassed'))->setValue(null, false);
            [$box, $out] = $this->box($user);
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $box->Integrations();
            $this->assertTrue((new \ReflectionProperty(\Wbengine\PageCache::class, '_bypassed'))->getValue());
            $this->assertTrue((new \ReflectionProperty(\Wbengine\Template\FragmentCache::class, '_bypassed'))->getValue());
            $this->assertContains('Cache-Control: private, no-store', $out->headers);
        }
    }

    public function testAuditFailureRollsTheIssueBackAndNoKeyIsShown(): void
    {
        Db::query('DROP TEMPORARY TABLE cms_api_audit');
        Db::query('CREATE TEMPORARY TABLE cms_api_audit (unrelated INT) ENGINE=InnoDB');
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'Cannot audit', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
        $log = ini_set('error_log', '/dev/null');
        try {
            $html = $box->Integrations();
        } finally {
            ini_set('error_log', $log);
        }
        $this->assertSame(500, $out->status);
        $this->assertStringNotContainsString('wbv2_', $html);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testAuditFailureKeepsTheKeyActiveOnRevoke(): void
    {
        $issued = (new Model())->provision(41, 'To revoke', ['discovery:read']);
        Db::query('DROP TEMPORARY TABLE cms_api_audit');
        Db::query('CREATE TEMPORARY TABLE cms_api_audit (unrelated INT) ENGINE=InnoDB');
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'revoke', 'integration_id' => (string) $issued['integration_id'], 'password' => 'admin-secret'], $session, Integrations::PURPOSE_REVOKE);
        $log = ini_set('error_log', '/dev/null');
        try { $box->Integrations(); } finally { ini_set('error_log', $log); }
        $this->assertSame(500, $out->status);
        $this->assertNotNull((new Model())->authenticate($issued['key']));
    }

    public function testNonAdministratorAndLoggedOutNeverReachTheModel(): void
    {
        foreach ([[42, 403], [0, 401], [999, 403]] as [$user, $status]) {
            [$box, $out, $session] = $this->box($user, 'POST');
            $this->post(['action' => 'issue', 'name' => 'Nope', 'password' => 'editor-secret'], $session, Integrations::PURPOSE_ISSUE);
            $html = $box->Integrations();
            $this->assertSame($status, $out->status, (string) $user);
            $this->assertStringNotContainsString('wbv2_', $html);
            $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
        }
    }

    public function testSessionRoleClaimIsIgnored(): void
    {
        $session = new UserSession(42);
        $session->values['role'] = 'admin';
        $session->values['user_role'] = 'admin';
        [$box, $out] = $this->box(42, 'POST', [], $session);
        $this->post(['action' => 'issue', 'name' => 'Nope', 'password' => 'editor-secret', 'role' => 'admin'], $session, Integrations::PURPOSE_ISSUE);
        $box->Integrations();
        $this->assertSame(403, $out->status);
    }

    public function testPreflightProblemsBlockIssuingAndShowInstructions(): void
    {
        foreach (['api_disabled', 'not_https', 'schema_integrations', 'schema_last_used', 'database'] as $problem) {
            [$box, $out, $session] = $this->box(41, 'POST', [$problem]);
            $this->post(['action' => 'issue', 'name' => 'Blocked', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
            $html = $box->Integrations();
            $this->assertSame(409, $out->status, $problem);
            $this->assertStringNotContainsString('wbv2_', $html);
            $this->assertStringNotContainsString('name="password"', $html);
            $this->assertStringContainsString(htmlspecialchars(\Wbengine\I18n::t('wb.integrations.problem.' . $problem), ENT_QUOTES), $html);
            $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
        }
    }

    public function testInvalidNameIsRejectedBeforeThePasswordIsEvaluated(): void
    {
        foreach (['', '   ', str_repeat('x', 129), "bad\nname"] as $name) {
            [$box, $out, $session] = $this->box(41, 'POST');
            $this->post(['action' => 'issue', 'name' => $name, 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
            $box->Integrations();
            $this->assertSame(400, $out->status);
        }
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_audit')['n']);
    }

    public function testRotationShowsTheOrderAndNamesTheKeyToRevokeAfterTheCentralAdminAcceptedTheNewOne(): void
    {
        $old = (new Model())->provision(41, 'Old key', ['discovery:read']);
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'WBengine Admin', 'replaces' => (string) $old['integration_id'], 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
        $html = $box->Integrations();
        $this->assertSame(200, $out->status);
        $this->assertStringContainsString(\Wbengine\I18n::t('wb.integrations.instr_replace', ['id' => $old['integration_id'], 'name' => 'Old key']), $html);
        $this->assertStringContainsString(\Wbengine\I18n::t('wb.integrations.rotation_3'), $html);
        // Nothing is revoked automatically: the old key still works until the operator revokes it.
        $this->assertNotNull((new Model())->authenticate($old['key']));
    }

    public function testRotationOfAnUnknownOrRevokedIntegrationIsRefused(): void
    {
        $old = (new Model())->provision(41, 'Old key', ['discovery:read']);
        (new Model())->revoke(41, $old['integration_id']);
        foreach ([(string) $old['integration_id'], '999', 'abc'] as $replaces) {
            [$box, $out, $session] = $this->box(41, 'POST');
            $this->post(['action' => 'issue', 'name' => 'WBengine Admin', 'replaces' => $replaces, 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
            $box->Integrations();
            $this->assertSame(400, $out->status);
        }
        $this->assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testUnsupportedMethodsAndUnknownActionsChangeNothing(): void
    {
        foreach (['PUT', 'DELETE', 'PATCH'] as $method) {
            [$box, $out] = $this->box(41, $method);
            $box->Integrations();
            $this->assertSame(405, $out->status);
            $this->assertContains('Allow: GET, HEAD, POST', $out->headers);
        }
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'rename', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
        $box->Integrations();
        $this->assertSame(400, $out->status);
    }

    public function testThrottledConfirmationAnswers429WithRetryAfter(): void
    {
        for ($i = 0; $i < PasswordConfirmation::USER_ATTEMPTS; $i++) {
            [$box, $out, $session] = $this->box(41, 'POST');
            $this->post(['action' => 'issue', 'name' => 'x', 'password' => 'wrong'], $session, Integrations::PURPOSE_ISSUE);
            $box->Integrations();
            $this->assertSame(403, $out->status);
        }
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'x', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
        $html = $box->Integrations();
        $this->assertSame(429, $out->status);
        $this->assertSame(1, count(array_filter($out->headers, fn ($h) => str_starts_with($h, 'Retry-After: '))));
        $this->assertStringNotContainsString('wbv2_', $html);
        $this->assertSame(5, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_audit WHERE action = ? AND outcome = ?', ['integration.reauth', 'denied'])['n']);
    }
}
