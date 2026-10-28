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
    private function box(int $userId, ?string $method = null, array $problems = [], ?UserSession $session = null): array
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
        $box = new class(null, $parent, $recorder, $method, $problems, $limiter) extends Integrations {
            public function __construct($box, $parent, private $recorder, private ?string $method, private array $forcedProblems, private Limiter $limiter)
            {
                parent::__construct($box, $parent);
            }
            protected function requestMethod(): string { return $this->method ?? 'GET'; }
            protected function preflight(): array { return $this->forcedProblems; }
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

    public function testIssueUsesTheCompleteAdminProfileAndRendersTheKeyOnce(): void
    {
        [$box, $out, $session] = $this->box(41, 'POST');
        $this->post(['action' => 'issue', 'name' => 'WBengine Admin', 'password' => 'admin-secret'], $session, Integrations::PURPOSE_ISSUE);
        $html = $box->Integrations();
        $this->assertSame(200, $out->status);
        $this->assertSame(1, preg_match_all('/wbv2_[a-f0-9]{64}/', $html, $m));
        $row = Db::fetchAssoc('SELECT * FROM cms_api_integrations');
        $this->assertSame(hash('sha256', $m[0][0]), $row['key_hash']);
        $this->assertSame(Integrations::PROFILE_SCOPES, json_decode($row['scopes'], true));
        $this->assertCount(17, Integrations::PROFILE_SCOPES);
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
