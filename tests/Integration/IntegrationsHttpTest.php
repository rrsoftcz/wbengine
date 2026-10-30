<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api\V2\Model;
use Wbengine\Box\Integrations;
use Wbengine\Db;

/**
 * The real HTTP surface of Box\Integrations: real session cookie, CSRF, password confirmation,
 * response headers and the one-time key. The fixture treats requests marked by a trusted proxy as HTTPS.
 */
class IntegrationsHttpTest extends DbTestCase
{
    private $server;
    private ?string $cookie = null;
    private string $dir;
    private string $origin;

    protected function setUp(): void
    {
        parent::setUp();
        Db::query('DELETE FROM cms_api_integrations');
        Db::query('DELETE FROM cms_api_audit');
        Db::insert('cms_users', ['user_id' => 21, 'username' => 'admin@example.test', 'email' => 'admin@example.test', 'password' => md5('admin-secret'), 'role' => 'admin']);
        Db::insert('cms_users', ['user_id' => 22, 'username' => 'editor@example.test', 'email' => 'editor@example.test', 'password' => md5('editor-secret'), 'role' => 'editor']);
        $this->dir = sys_get_temp_dir() . '/wb-int-http-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->origin = 'http://' . $address;
        $this->server = proc_open([PHP_BINARY, '-S', $address, dirname(__DIR__) . '/fixtures/integrations.php'],
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
        Db::query('DELETE FROM cms_api_integrations');
        Db::query('DELETE FROM cms_api_audit');
        $this->removeDirectory($this->dir);
    }

    private function removeDirectory(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }
        rmdir($dir);
    }

    private function request(string $path, ?array $post = null, bool $https = true): array
    {
        $headers = [];
        if ($https) { $headers[] = 'X-Forwarded-Proto: https'; }
        if (null !== $this->cookie) { $headers[] = 'Cookie: ' . $this->cookie; }
        if (null !== $post) { $headers[] = 'Content-Type: application/x-www-form-urlencoded'; }
        $context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true, 'follow_location' => 0, 'method' => null === $post ? 'GET' : 'POST', 'content' => null === $post ? '' : http_build_query($post),
            'header' => implode("\r\n", $headers)]]);
        $stream = fopen($this->origin . $path, 'r', false, $context);
        $body = stream_get_contents($stream);
        $metadata = stream_get_meta_data($stream);
        fclose($stream);
        $result = ['status' => (int) explode(' ', $metadata['wrapper_data'][0])[1], 'body' => $body];
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

    private function formToken(array $response, string $action): string
    {
        $this->assertSame(1, preg_match('/name="action" value="' . $action . '">\s*<input type="hidden" name="token" value="([a-f0-9]{64})"/', $response['body'], $matches), $response['body']);
        return $matches[1];
    }

    private function login(string $email, string $password): void
    {
        $this->cookie = null;
        $page = $this->request('/login/');
        $this->assertSame(1, preg_match('/name="token" value="([a-f0-9]{64})"/', $page['body'], $matches));
        $response = $this->request('/login/', ['userName' => $email, 'userPassword' => $password, 'token' => $matches[1]]);
        $this->assertSame(302, $response['status'], $response['body']);
    }

    private function issue(string $token, string $password, string $name = 'WBengine Admin'): array
    {
        return $this->request('/integrations/', ['action' => 'issue', 'token' => $token, 'name' => $name, 'password' => $password]);
    }

    private function assertPrivateHeaders(array $response): void
    {
        $this->assertSame('private, no-store', $response['cache-control']);
        $this->assertSame('same-origin', $response['referrer-policy']);
    }

    public function testAnonymousVisitorGetsNothing(): void
    {
        $response = $this->request('/integrations/');
        $this->assertSame(401, $response['status']);
        $this->assertPrivateHeaders($response);
        $this->assertStringNotContainsString('name="password"', $response['body']);
        $anonymousPost = $this->request('/integrations/', ['action' => 'issue', 'token' => str_repeat('a', 64), 'name' => 'x', 'password' => 'x']);
        $this->assertSame(401, $anonymousPost['status']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testNonAdministratorsAreRefusedEvenWithAValidSessionAndPassword(): void
    {
        $this->login('editor@example.test', 'editor-secret');
        $page = $this->request('/integrations/');
        $this->assertSame(403, $page['status']);
        $this->assertPrivateHeaders($page);
        $this->assertStringNotContainsString('name="action"', $page['body']);
        $post = $this->request('/integrations/', ['action' => 'issue', 'token' => str_repeat('b', 64), 'name' => 'Nope', 'password' => 'editor-secret']);
        $this->assertSame(403, $post['status']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testTheRoleIsReadFromTheDatabaseOnEveryRequest(): void
    {
        $this->login('admin@example.test', 'admin-secret');
        $this->assertSame(200, $this->request('/integrations/')['status']);
        Db::query('UPDATE cms_users SET role = ? WHERE user_id = ?', ['editor', 21]);
        $this->assertSame(403, $this->request('/integrations/')['status']);
    }

    public function testPermissionsAreCollapsedAndTheFormIsAPanelWithANameExplanation(): void
    {
        $this->login('admin@example.test', 'admin-secret');
        $body = $this->request('/integrations/')['body'];
        // The permission list is a collapsed <details> with a count, not an always open list next to the form.
        $this->assertMatchesRegularExpression('~<details class="wb-scopes">\s*<summary>[^<]*\(22\)</summary>~', $body);
        $this->assertStringNotContainsString('<details class="wb-scopes" open', $body);
        $this->assertStringContainsString('Read the structure and capabilities of the website', $body);
        // The form is its own panel and the inline styles survive the template engine unchanged.
        $this->assertStringContainsString('<form class="wb-panel"', $body);
        $this->assertStringContainsString('.wb-integrations .wb-panel{margin:1.5rem 0;', $body);
        // The name field explains what the name is for.
        $this->assertStringContainsString('id="wb-name-help"', $body);
        $this->assertStringContainsString('cannot be changed later', $body);
    }

    private function enableAnalyticsConfig(): void
    {
        file_put_contents($this->dir . '/analytics-enabled', '1');
    }

    /** Real tables of migrations/4.0.0/006: the fixture server uses its own connection. */
    private function createAnalyticsSchema(): void
    {
        $sql = preg_replace('/^--.*$/m', '', (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/4.0.0/006-analytics.sql'));
        foreach (explode(';', $sql) as $statement) {
            if ('' !== trim($statement)) { Db::query($statement); }
        }
    }

    private function dropAnalyticsSchema(): void
    {
        Db::query('DROP TABLE IF EXISTS cms_analytics_daily, cms_analytics_totals, cms_analytics_meta');
    }

    public function testAnalyticsChoiceIsDisabledWithInstructionsWhileAnalyticsIsOffOrHasNoSchema(): void
    {
        $this->dropAnalyticsSchema();
        $this->login('admin@example.test', 'admin-secret');
        // Switched off in the configuration and no migration.
        $response = $this->request('/integrations/');
        $this->assertMatchesRegularExpression('~<input type="checkbox" id="wb-analytics" name="analytics" value="1" disabled~', $response['body']);
        $this->assertStringContainsString('analytics.enabled = true', $response['body']);
        $this->assertStringContainsString('006-analytics.sql', $response['body']);
        $this->assertStringContainsString('href="' . Integrations::ANALYTICS_DOCS . '"', $response['body']);
        // Enabled in the configuration but the migration is missing.
        $this->enableAnalyticsConfig();
        $response = $this->request('/integrations/');
        $this->assertMatchesRegularExpression('~name="analytics" value="1" disabled~', $response['body']);
        $this->assertStringNotContainsString('analytics.enabled = true', $response['body']);
        $this->assertStringContainsString('006-analytics.sql', $response['body']);
        // A key without analytics is still issued.
        $issued = $this->issue($this->formToken($response, 'issue'), 'admin-secret');
        $this->assertSame(200, $issued['status']);
        $this->assertSame(Integrations::PROFILE_SCOPES, json_decode(Db::fetchAssoc('SELECT * FROM cms_api_integrations')['scopes'], true));
    }

    public function testServerRefusesAnalyticsWhenItIsNotUsable(): void
    {
        $this->dropAnalyticsSchema();
        $this->login('admin@example.test', 'admin-secret');
        $token = $this->formToken($this->request('/integrations/'), 'issue');
        $response = $this->request('/integrations/', ['action' => 'issue', 'token' => $token, 'name' => 'WBengine Admin', 'analytics' => '1', 'password' => 'admin-secret']);
        $this->assertSame(400, $response['status']);
        $this->assertPrivateHeaders($response);
        $this->assertStringContainsString(htmlspecialchars(\Wbengine\I18n::t('wb.integrations.error.analytics_unavailable'), ENT_QUOTES), $response['body']);
        $this->assertStringNotContainsString('wbv2_', $response['body']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testAnalyticsKeyIsIssuedWithEighteenScopesWhenAnalyticsIsUsable(): void
    {
        $this->createAnalyticsSchema();
        try {
            $this->enableAnalyticsConfig();
            $this->login('admin@example.test', 'admin-secret');
            $form = $this->request('/integrations/');
            $this->assertMatchesRegularExpression('~<input type="checkbox" id="wb-analytics" name="analytics" value="1" aria-describedby~', $form['body']);
            $this->assertDoesNotMatchRegularExpression('~name="analytics" value="1" disabled~', $form['body']);
            $this->assertMatchesRegularExpression('~<summary>[^<]*\(22\)</summary>~', $form['body']);
            $response = $this->request('/integrations/', ['action' => 'issue', 'token' => $this->formToken($form, 'issue'), 'name' => 'WBengine Admin', 'analytics' => '1', 'password' => 'admin-secret']);
            $this->assertSame(200, $response['status']);
            $this->assertPrivateHeaders($response);
            $this->assertSame(1, preg_match_all('/wbv2_[a-f0-9]{64}/', $response['body']));
            $row = Db::fetchAssoc('SELECT * FROM cms_api_integrations');
            $scopes = json_decode($row['scopes'], true);
            $this->assertCount(23, $scopes);
            $this->assertSame(array_merge(Integrations::PROFILE_SCOPES, ['analytics:read']), $scopes);
            $this->assertMatchesRegularExpression('~<details class="wb-scopes">\s*<summary>[^<]*\(23\)</summary>~', $response['body']);
            $this->assertStringNotContainsString('<details class="wb-scopes" open', $response['body']);
            $this->assertStringContainsString('Read the website traffic overview', $response['body']);
            $this->assertStringContainsString('<span class="wb-badge">Analytics</span>', $response['body']);
            $this->assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_audit WHERE action = ?', ['integration.provision'])['n']);
            // Rotation offers the old key with its analytics flag.
            $next = $this->request('/integrations/');
            $this->assertMatchesRegularExpression('~<option value="\d+" data-analytics="1">WBengine Admin #\d+</option>~', $next['body']);
        } finally {
            $this->dropAnalyticsSchema();
        }
    }

    public function testGetOnlyShowsStateAndNeverChangesIt(): void
    {
        $this->login('admin@example.test', 'admin-secret');
        $page = $this->request('/integrations/');
        $this->assertSame(200, $page['status']);
        $this->assertPrivateHeaders($page);
        $this->assertStringContainsString('name="password"', $page['body']);
        $this->assertStringContainsString('Integrations', $page['body']);
        $this->request('/integrations/?action=issue&name=Evil&password=admin-secret');
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
        $issued = (new Model())->provision(21, 'Existing', ['discovery:read']);
        $this->request('/integrations/?action=revoke&integration_id=' . $issued['integration_id']);
        $this->assertNull(Db::fetchAssoc('SELECT revoked_at FROM cms_api_integrations')['revoked_at']);
    }

    public function testMissingOrWrongCsrfTokenIsRefusedBeforeThePasswordIsEvaluated(): void
    {
        (new Model())->provision(21, 'Existing', ['discovery:read']);
        $this->login('admin@example.test', 'admin-secret');
        $page = $this->request('/integrations/');
        $revokeToken = $this->formToken($page, 'revoke');
        $this->assertNotSame($this->formToken($page, 'issue'), $revokeToken);
        // No token, a malformed one, a well-formed wrong one and the token of another purpose.
        foreach ([null, 'short', str_repeat('0', 64), $revokeToken] as $token) {
            $post = ['action' => 'issue', 'name' => 'No token', 'password' => 'admin-secret'];
            if (null !== $token) { $post['token'] = $token; }
            $response = $this->request('/integrations/', $post);
            $this->assertSame(403, $response['status']);
            $this->assertPrivateHeaders($response);
            $this->assertStringNotContainsString('wbv2_', $response['body']);
        }
        $this->assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
        // Only the audit row of the direct provision above, nothing from the refused requests.
        $this->assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_audit')['n']);
    }

    public function testWrongPasswordIsRefusedAndAuditedWithoutTheSubmittedValue(): void
    {
        $this->login('admin@example.test', 'admin-secret');
        $token = $this->formToken($this->request('/integrations/'), 'issue');
        $response = $this->issue($token, 'definitely-wrong-password');
        $this->assertSame(403, $response['status']);
        $this->assertPrivateHeaders($response);
        $this->assertStringNotContainsString('wbv2_', $response['body']);
        $this->assertStringNotContainsString('definitely-wrong-password', $response['body']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
        $audit = Db::fetchAssoc('SELECT * FROM cms_api_audit');
        $this->assertSame('integration.reauth', $audit['action']);
        $this->assertSame('denied', $audit['outcome']);
        $this->assertSame(21, $audit['local_user_id']);
        $this->assertStringNotContainsString('definitely-wrong-password', json_encode($audit));
        $this->assertStringNotContainsString('definitely-wrong-password', (string) file_get_contents($this->dir . '/server.log'));
    }

    public function testRepeatedWrongPasswordsAreThrottledEvenForTheRightPassword(): void
    {
        $this->login('admin@example.test', 'admin-secret');
        $token = $this->formToken($this->request('/integrations/'), 'issue');
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(403, $this->issue($token, 'wrong-' . $i)['status']);
        }
        $blocked = $this->issue($token, 'admin-secret');
        $this->assertSame(429, $blocked['status']);
        $this->assertGreaterThan(0, (int) $blocked['retry-after']);
        $this->assertStringNotContainsString('wbv2_', $blocked['body']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }

    public function testKeyIsIssuedOnceStoredAsHashAndNeverLeaksAnywhere(): void
    {
        $this->login('admin@example.test', 'admin-secret');
        $token = $this->formToken($this->request('/integrations/'), 'issue');
        $response = $this->issue($token, 'admin-secret', 'WBengine Admin');
        $this->assertSame(200, $response['status']);
        $this->assertPrivateHeaders($response);
        $this->assertSame(1, preg_match_all('/wbv2_[a-f0-9]{64}/', $response['body'], $matches));
        $key = $matches[0][0];
        $this->assertSame(1, substr_count($response['body'], 'value="' . $key . '"'));
        // The central Admin instruction: address, recommended name, key.
        $this->assertStringContainsString('value="https://' . substr($this->origin, 7) . '/"', $response['body']);
        $this->assertStringContainsString('value="WBengine Admin"', $response['body']);
        // Every scope of the Admin profile is described in words.
        foreach (Integrations::PROFILE_SCOPES as $scope) {
            $this->assertStringContainsString(\Wbengine\I18n::t('wb.integrations.scope.' . str_replace(':', '_', $scope)), $response['body']);
        }
        $this->assertCount(22, Integrations::PROFILE_SCOPES);
        $this->assertNotContains('analytics:read', Integrations::PROFILE_SCOPES);

        $row = Db::fetchAssoc('SELECT * FROM cms_api_integrations');
        $this->assertSame(hash('sha256', $key), $row['key_hash']);
        $this->assertSame(Integrations::PROFILE_SCOPES, json_decode($row['scopes'], true));
        $this->assertSame(21, $row['created_by']);
        $dump = json_encode([Db::fetchAllAssoc('SELECT * FROM cms_api_integrations'), Db::fetchAllAssoc('SELECT * FROM cms_api_audit'), Db::fetchAllAssoc('SELECT * FROM cms_sessions')]);
        $this->assertStringNotContainsString($key, $dump);
        $audit = Db::fetchAssoc('SELECT * FROM cms_api_audit WHERE action = ?', ['integration.provision']);
        $this->assertSame('allowed', $audit['outcome']);
        $this->assertSame(21, $audit['local_user_id']);

        // Shown once: neither a reload (GET) nor any cache, log or listing returns it.
        $again = $this->request('/integrations/');
        $this->assertSame(200, $again['status']);
        $this->assertStringNotContainsString($key, $again['body']);
        $this->assertStringContainsString('WBengine Admin', $again['body']);
        $this->assertStringNotContainsString($key, (string) file_get_contents($this->dir . '/server.log'));
        foreach (glob($this->dir . '/cache/*') ?: [] as $file) {
            $this->assertStringNotContainsString($key, (string) file_get_contents($file));
        }
        foreach (glob($this->dir . '/*.php') ?: [] as $file) {
            $this->assertStringNotContainsString($key, (string) file_get_contents($file));
        }
        // The issued key works against the API model.
        $this->assertSame('WBengine Admin', (new Model())->authenticate($key)['name']);
    }

    public function testRevokeIsPostOnlyNeedsThePasswordAndIsImmediate(): void
    {
        $issued = (new Model())->provision(21, 'Old key', ['discovery:read']);
        $this->login('admin@example.test', 'admin-secret');
        $page = $this->request('/integrations/');
        $this->assertStringContainsString('Old key', $page['body']);
        $token = $this->formToken($page, 'revoke');
        $post = ['action' => 'revoke', 'token' => $token, 'integration_id' => (string) $issued['integration_id']];
        $this->assertSame(403, $this->request('/integrations/', $post + ['password' => 'nope'])['status']);
        $this->assertNotNull((new Model())->authenticate($issued['key']));
        $response = $this->request('/integrations/', $post + ['password' => 'admin-secret']);
        $this->assertSame(200, $response['status']);
        $this->assertPrivateHeaders($response);
        $this->assertNull((new Model())->authenticate($issued['key']));
        $audit = Db::fetchAssoc('SELECT * FROM cms_api_audit WHERE action = ?', ['integration.revoke']);
        $this->assertSame(21, $audit['local_user_id']);
    }

    public function testPlainHttpOrMissingConfigurationRefusesToIssueAndExplainsWhy(): void
    {
        $this->login('admin@example.test', 'admin-secret');
        $page = $this->request('/integrations/', null, false);
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('HTTPS', $page['body']);
        $this->assertStringNotContainsString('name="password"', $page['body']);
        $token = $this->formToken($this->request('/integrations/'), 'issue');
        $post = $this->request('/integrations/', ['action' => 'issue', 'token' => $token, 'name' => 'WBengine Admin', 'password' => 'admin-secret'], false);
        $this->assertSame(409, $post['status']);
        $this->assertStringNotContainsString('wbv2_', $post['body']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_integrations')['n']);
    }
}
