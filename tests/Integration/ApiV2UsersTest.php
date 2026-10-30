<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api;
use Wbengine\Api\Model\ApiUserModel;
use Wbengine\Api\V2;
use Wbengine\Api\V2\Model;
use Wbengine\Config;
use Wbengine\Db;
use Wbengine\User;
use Wbengine\User\AccountState;
use Wbengine\User\PasswordSetup;
use Wbengine\User\Role;
use Wbengine\User\UserException;

/** Remote user management (API v2), one-time password links, account state, cache flush and the config view. */
final class ApiV2UsersTest extends ApiTestCase
{
    private V2 $v2;
    private string $key;
    private string $readOnlyKey;
    private int $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['cms_api_changes', 'cms_api_audit', 'cms_api_integrations', 'cms_password_resets'] as $table) { Db::query('DELETE FROM ' . $table); }
        AccountState::reset();
        $this->admin = $this->createUser('users-admin@example.com', Role::ADMIN);
        $model = new Model();
        $this->key = $model->provision($this->admin, 'Central', ['discovery:read', 'users:read', 'users:write', 'cache:write', 'config:read'])['key'];
        $this->readOnlyKey = $model->provision($this->admin, 'Reader', ['discovery:read', 'users:read'])['key'];
        $this->v2 = new V2(new Api(), $model);
    }

    protected function tearDown(): void
    {
        // The detected schema must not leak into tests that run on a different cms_users table.
        AccountState::reset();
        parent::tearDown();
    }

    private function call(string $method, string $path, ?array $body = null, ?string $key = null, array $query = []): array
    {
        $r = $this->v2->handle($method, $path, 'Bearer ' . ($key ?? $this->key), true, 'admin:1', null === $body ? '' : json_encode($body), $query);
        return ['status' => $r['status'], 'json' => $r['body'], 'text' => json_encode($r['body'])];
    }

    private function createUserVia(string $email, string $role = 'user'): array
    {
        $r = $this->call('POST', '/api/v2/users', ['email' => $email, 'role' => $role]);
        $this->assertSame(201, $r['status'], $r['text']);
        return $r['json']['data'];
    }

    public function testScopesGateTheEndpoints(): void
    {
        $this->assertSame(200, $this->call('GET', '/api/v2/users', null, $this->readOnlyKey)['status']);
        $this->assertSame(403, $this->call('POST', '/api/v2/users', ['email' => 'x@example.com'], $this->readOnlyKey)['status']);
        $this->assertSame(403, $this->call('POST', '/api/v2/cache/flush', null, $this->readOnlyKey)['status']);
        $this->assertSame(403, $this->call('GET', '/api/v2/config', null, $this->readOnlyKey)['status']);
    }

    public function testCreatedAccountCannotSignInUntilItsLinkIsUsed(): void
    {
        $user = $this->createUserVia('Fresh@Example.com', 'editor');
        $this->assertSame('fresh@example.com', $user['email']);
        $this->assertSame('editor', $user['role']);
        $this->assertFalse($user['disabled']);
        $token = $user['password_setup']['token'];
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $token);
        $this->assertGreaterThan(time() + 170000, $user['password_setup']['expires_at']);
        $stored = Db::fetchAssoc('SELECT * FROM cms_password_resets');
        $this->assertSame(hash('sha256', $token), $stored['token_hash']);
        $this->assertStringNotContainsString($token, json_encode($stored));

        $this->assertNull($this->authenticate('fresh@example.com', 'anything at all 123'));
        PasswordSetup::consume($token, 'A fine new password');
        $this->assertNotNull($this->authenticate('fresh@example.com', 'A fine new password'));
        $this->expectException(UserException::class);
        PasswordSetup::consume($token, 'Another fine password');
    }

    private function authenticate(string $email, string $password): ?array
    {
        // The web login without a request: a User with only the credentials set.
        $reflection = new \ReflectionClass(User::class);
        $user = $reflection->newInstanceWithoutConstructor();
        foreach (['_login' => $email, '_paswd' => $password] as $property => $value) {
            $p = $reflection->getProperty($property);
            $p->setValue($user, $value);
        }
        return (new User\Model())->authenticate($user);
    }

    public function testLinksExpireAndANewLinkReplacesTheOldOne(): void
    {
        $id = $this->createUser('links@example.com', Role::USER);
        $old = PasswordSetup::issue($id, 1000);
        $new = PasswordSetup::issue($id, 2000);
        $this->assertFalse(PasswordSetup::isValid($old['token'], 2001));
        $this->assertTrue(PasswordSetup::isValid($new['token'], 2001));
        $this->assertFalse(PasswordSetup::isValid($new['token'], 2000 + PasswordSetup::TTL + 1));
        $this->assertFalse(PasswordSetup::isValid('not-a-token'));
        $this->expectException(UserException::class);
        PasswordSetup::consume($new['token'], 'short');
    }

    public function testDuplicateEmailBadRoleAndUnknownFieldsAreRejected(): void
    {
        $this->createUserVia('dup@example.com');
        $this->assertSame(409, $this->call('POST', '/api/v2/users', ['email' => 'dup@example.com'])['status']);
        $this->assertSame(400, $this->call('POST', '/api/v2/users', ['email' => 'bad'])['status']);
        $this->assertSame(400, $this->call('POST', '/api/v2/users', ['email' => 'r@example.com', 'role' => 'root'])['status']);
        $this->assertSame(400, $this->call('POST', '/api/v2/users', ['email' => 'p@example.com', 'password' => 'secret'])['status'], 'a password can never be sent');
        $user = $this->createUserVia('patch@example.com');
        $this->assertSame(400, $this->call('PATCH', '/api/v2/users/' . $user['id'], ['email' => 'other@example.com'])['status']);
        $this->assertSame(400, $this->call('PATCH', '/api/v2/users/' . $user['id'], ['password' => 'x'])['status']);
        $this->assertSame(404, $this->call('GET', '/api/v2/users/9999999')['status']);
    }

    public function testRoleChangeDisableAndEnable(): void
    {
        $user = $this->createUserVia('role@example.com');
        $r = $this->call('PATCH', '/api/v2/users/' . $user['id'], ['role' => 'editor', 'firstname' => 'Eva']);
        $this->assertSame(200, $r['status']);
        $this->assertSame('editor', $r['json']['data']['role']);
        $this->assertSame('Eva', $r['json']['data']['firstname']);

        $this->call('PATCH', '/api/v2/users/' . $user['id'], ['disabled' => true]);
        $this->assertTrue(AccountState::isDisabled((int) $user['id']));
        $this->assertNull((new ApiUserModel())->getAuthorization($user['id']));
        $this->assertNull((new ApiUserModel())->getTokenIdentity($user['id']));
    }

    public function testADisabledAccountCannotSignInAndEnablingRestoresIt(): void
    {
        $user = $this->createUserVia('off@example.com');
        PasswordSetup::consume($user['password_setup']['token'], 'Correct horse battery');
        $this->assertNotNull($this->authenticate('off@example.com', 'Correct horse battery'));
        $this->call('PATCH', '/api/v2/users/' . $user['id'], ['disabled' => true]);
        $this->assertNull($this->authenticate('off@example.com', 'Correct horse battery'));
        $this->assertSame(409, $this->call('POST', '/api/v2/users/' . $user['id'] . '/password-reset')['status']);
        $this->call('PATCH', '/api/v2/users/' . $user['id'], ['disabled' => false]);
        $this->assertNotNull($this->authenticate('off@example.com', 'Correct horse battery'));
        $this->assertNotNull((new ApiUserModel())->getAuthorization($user['id']));
    }

    public function testTheLastActiveAdministratorCannotBeDemotedOrDisabled(): void
    {
        Db::query("UPDATE cms_users SET role = 'user' WHERE user_id <> " . $this->admin);
        $this->assertSame(409, $this->call('PATCH', '/api/v2/users/' . $this->admin, ['role' => 'editor'])['status']);
        $this->assertSame(409, $this->call('PATCH', '/api/v2/users/' . $this->admin, ['disabled' => true])['status']);
        $second = $this->createUserVia('second-admin@example.com', 'admin');
        $this->assertSame(200, $this->call('PATCH', '/api/v2/users/' . $second['id'], ['disabled' => true])['status']);
        // The second one is disabled, so the first is the last active administrator again.
        $this->assertSame(409, $this->call('PATCH', '/api/v2/users/' . $this->admin, ['role' => 'user'])['status']);
        $this->assertSame(200, $this->call('PATCH', '/api/v2/users/' . $second['id'], ['role' => 'user'])['status']);
    }

    public function testNothingSecretReachesTheChangeLogOrTheList(): void
    {
        $user = $this->createUserVia('log@example.com');
        $reset = $this->call('POST', '/api/v2/users/' . $user['id'] . '/password-reset');
        $this->assertSame(200, $reset['status']);
        $token = $reset['json']['data']['password_setup']['token'];
        $this->assertNotSame($user['password_setup']['token'], $token);
        $this->assertFalse(PasswordSetup::isValid($user['password_setup']['token']));
        $dump = json_encode(Db::fetchAllAssoc('SELECT * FROM cms_api_changes')) . json_encode(Db::fetchAllAssoc('SELECT * FROM cms_api_audit'));
        $this->assertStringNotContainsString($token, $dump);
        $this->assertStringNotContainsString($user['password_setup']['token'], $dump);
        $this->assertStringNotContainsString('$2y$', $dump);
        $list = $this->call('GET', '/api/v2/users', null, null, ['search' => 'log@']);
        $this->assertCount(1, $list['json']['data']);
        $this->assertStringNotContainsString('$2y$', $list['text']);
        $this->assertArrayNotHasKey('password', $list['json']['data'][0]);
        $actions = array_column(Db::fetchAllAssoc("SELECT action FROM cms_api_changes WHERE resource = 'user' ORDER BY change_id"), 'action');
        $this->assertSame(['create', 'password_reset'], $actions);
    }

    public function testCacheFlushIsAuditedAndTheConfigViewHidesSecrets(): void
    {
        $this->assertSame(200, $this->call('POST', '/api/v2/cache/flush')['status']);
        $row = Db::fetchAssoc("SELECT * FROM cms_api_changes WHERE resource = 'cache'");
        $this->assertSame('flush', $row['action']);
        $this->assertSame(405, $this->call('GET', '/api/v2/cache/flush')['status']);

        $old = Config::$config;
        Config::$config = (object) ['jwtSecret' => 'TOP-SECRET', 'recaptcha' => (object) ['secret' => 'ALSO-SECRET', 'siteKey' => 'public'], 'dbAdapterDefinition' => (object) ['hostname' => 'db', 'password' => 'DB-PASS'], 'timeZone' => 'Europe/Prague'];
        try {
            $r = $this->call('GET', '/api/v2/config');
        } finally { Config::$config = $old; }
        $this->assertSame(200, $r['status']);
        $this->assertStringNotContainsString('TOP-SECRET', $r['text']);
        $this->assertStringNotContainsString('ALSO-SECRET', $r['text']);
        $this->assertStringNotContainsString('DB-PASS', $r['text']);
        $this->assertSame('set', $r['json']['data']['settings']['jwtSecret']);
        $this->assertSame('Europe/Prague', $r['json']['data']['settings']['timeZone']);
        $this->assertArrayNotHasKey('dbAdapterDefinition', $r['json']['data']['settings']);
    }
}
