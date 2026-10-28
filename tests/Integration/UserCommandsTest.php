<?php
namespace Wbengine\Tests\Integration;

use Wbengine\Api\Auth\RefreshTokens;
use Wbengine\Console\CommandException;
use Wbengine\Console\PasswordPrompt;
use Wbengine\Console\UserCommands;
use Wbengine\Db;
use Wbengine\User\Password;

final class UserCommandsTest extends DbTestCase
{
    private const PW = 'a long enough passphrase';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['cms_api_audit', 'cms_api_integrations'] as $table) { Db::query('DELETE FROM ' . $table); }
    }

    private function commands(string $password = self::PW): UserCommands
    {
        $in = fopen('php://memory', 'w+'); fwrite($in, $password . "\n"); rewind($in);
        return new UserCommands(new PasswordPrompt($in, fopen('php://memory', 'w+'), false));
    }
    private function row(string $email): ?array
    {
        $row = Db::fetchAssoc('SELECT * FROM cms_users WHERE email = ?', [$email]);
        return is_array($row) ? $row : null;
    }
    private function refuses(callable $call, string $contains): void
    {
        try { $call(); self::fail('Expected a refusal'); }
        catch (CommandException $e) { self::assertStringContainsString($contains, $e->getMessage()); self::assertStringNotContainsString(self::PW, $e->getMessage()); }
    }
    private function cli(array $arguments, string $stdin = ''): array
    {
        $config = json_decode(file_get_contents(dirname(__DIR__) . '/config/Settings.test.json'), true);
        $config['dbAdapterDefinition']['hostname'] = getenv('WB_TEST_DB_HOST') ?: 'db';
        $file = tempnam(sys_get_temp_dir(), 'wb-users-') . '.json';
        file_put_contents($file, json_encode($config));
        try {
            $process = proc_open(array_merge([PHP_BINARY, dirname(__DIR__, 2) . '/bin/wbengine.php'], $arguments, ['--config=' . $file]), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            fwrite($pipes[0], $stdin); fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            return [proc_close($process), $out, $err];
        } finally { unlink($file); unlink(substr($file, 0, -5)); }
    }
    private function admin(string $email = 'root@corp.cz'): int
    {
        return $this->commands()->create(['email' => $email, 'role' => 'admin'])['id'];
    }

    public function testCreatesAdminWithPasswordHashAndNoSecretInResult(): void
    {
        $result = $this->commands()->create(['email' => 'boss@corp.cz', 'role' => 'admin']);
        self::assertSame(['created' => true, 'id' => $result['id'], 'email' => 'boss@corp.cz', 'username' => 'boss', 'role' => 'admin'], $result);
        $row = $this->row('boss@corp.cz');
        self::assertSame('admin', $row['role']);
        self::assertStringStartsWith('$2y$', $row['password']);
        self::assertTrue(password_verify(self::PW, $row['password']));
        self::assertStringNotContainsString(self::PW, json_encode($result) . json_encode($row));
        self::assertSame('2', (string) $row['user_type']);
    }
    public function testRoleDefaultsToUserAndUsernameIsDerivedSafelyAndWithoutCollisions(): void
    {
        self::assertSame('user', $this->commands()->create(['email' => 'jan@a.cz'])['role']);
        self::assertSame('jan-2', $this->commands()->create(['email' => 'jan@b.cz'])['username']);
        self::assertSame('jan-3', $this->commands()->create(['email' => 'jan@c.cz'])['username']);
        self::assertSame('o_brien', $this->commands()->create(['email' => "o'brien@d.cz"])['username']);
    }
    public function testDuplicateEmailUsernameInvalidRoleAndEmailAreRefusedBeforeAnyPrompt(): void
    {
        $this->commands()->create(['email' => 'one@corp.cz', 'username' => 'one']);
        $none = new UserCommands(new PasswordPrompt(fopen('php://memory', 'r'), fopen('php://memory', 'w'), false));
        $this->refuses(fn () => $none->create(['email' => 'ONE@corp.cz']), 'e-mail already exists');
        $this->refuses(fn () => $none->create(['email' => 'two@corp.cz', 'username' => 'one']), 'username already exists');
        $this->refuses(fn () => $none->create(['email' => 'two@corp.cz', 'role' => 'root']), 'role must be one of');
        $this->refuses(fn () => $none->create(['email' => 'not-an-email']), 'Invalid e-mail');
        $this->refuses(fn () => $none->create(['email' => 'two@corp.cz', 'username' => 'bad name;']), 'username may contain');
        self::assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_users')['c']);
    }
    public function testShortAndOverlongPasswordsAreRefusedAndNothingIsCreated(): void
    {
        $this->refuses(fn () => $this->commands('short')->create(['email' => 'x@corp.cz']), 'at least 12');
        $this->refuses(fn () => $this->commands(str_repeat('x', 73))->create(['email' => 'x@corp.cz']), 'at most 72');
        self::assertNull($this->row('x@corp.cz'));
    }
    public function testCreatedUserCanLogInThroughTheExistingLoginLogic(): void
    {
        $id = $this->commands()->create(['email' => 'login@corp.cz'])['id'];
        $user = $this->createStub(\Wbengine\User::class);
        $user->method('_getLoginName')->willReturn('login@corp.cz');
        $user->method('_getPassword')->willReturn(self::PW);
        $identity = (new \Wbengine\User\Model())->authenticate($user);
        self::assertSame($id, (int) $identity['user_id']);
        self::assertArrayNotHasKey('password', $identity);
        $wrong = $this->createStub(\Wbengine\User::class);
        $wrong->method('_getLoginName')->willReturn('login@corp.cz');
        $wrong->method('_getPassword')->willReturn('wrong password here');
        self::assertNull((new \Wbengine\User\Model())->authenticate($wrong));
    }
    public function testPasswordCommandChangesHashRevokesTokensAndEndsSessions(): void
    {
        $id = $this->commands()->create(['email' => 'pw@corp.cz'])['id'];
        $old = $this->row('pw@corp.cz')['password'];
        $token = (new RefreshTokens())->issue($id, 3600);
        Db::insert('cms_sessions', ['session_id' => 'sess-1', 'user_id' => $id]);
        Db::insert('cms_sessions', ['session_id' => 'sess-2', 'user_id' => $id + 100]);

        $result = $this->commands('another long passphrase')->password(['email' => 'pw@corp.cz']);
        self::assertSame(['updated' => true, 'id' => $id, 'email' => 'pw@corp.cz'], $result);
        $new = $this->row('pw@corp.cz')['password'];
        self::assertNotSame($old, $new);
        self::assertTrue(password_verify('another long passphrase', $new));
        self::assertFalse(password_verify(self::PW, $new));
        self::assertNotNull(Db::fetchAssoc('SELECT revoked_at FROM cms_refresh_tokens WHERE user_id = ?', [$id])['revoked_at']);
        self::assertSame(['sess-2'], array_column(Db::fetchAllAssoc('SELECT session_id FROM cms_sessions'), 'session_id'));
        self::assertNotSame('', $token);
    }
    public function testPasswordCommandRefusesUnknownUserAndShortPassword(): void
    {
        $this->commands()->create(['email' => 'pw@corp.cz']);
        $before = $this->row('pw@corp.cz')['password'];
        $this->refuses(fn () => $this->commands()->password(['email' => 'none@corp.cz']), 'No user');
        $this->refuses(fn () => $this->commands('tiny')->password(['email' => 'pw@corp.cz']), 'at least 12');
        self::assertSame($before, $this->row('pw@corp.cz')['password']);
    }
    public function testListHasNoHashNoAnonymousUserAndNoSecrets(): void
    {
        Db::insert('cms_users', ['user_id' => 0, 'username' => 'ANONYMOUS', 'password' => '0']);
        Db::query('UPDATE cms_users SET user_id = 0 WHERE username = ?', ['ANONYMOUS']);
        $this->commands()->create(['email' => 'a@corp.cz', 'role' => 'editor']);
        $json = json_encode($this->commands()->list());
        $list = json_decode($json, true)['users'];
        self::assertCount(1, $list);
        self::assertSame(['id', 'username', 'email', 'role', 'active'], array_keys($list[0]));
        self::assertSame(['editor', true], [$list[0]['role'], $list[0]['active']]);
        self::assertStringNotContainsString('$2y$', $json);
        self::assertStringNotContainsString('ANONYMOUS', $json);
    }
    public function testDemoAdminIsDeletedOnlyAfterAnotherAdminExists(): void
    {
        Db::insert('cms_users', ['username' => 'admin', 'email' => 'admin@example.com', 'role' => 'admin', 'password' => password_hash('demo', PASSWORD_DEFAULT)]);
        $this->refuses(fn () => $this->commands()->delete(['example' => true]), 'no active administrator');
        self::assertNotNull($this->row('admin@example.com'));
        $this->refuses(fn () => $this->commands()->delete(['email' => 'admin@example.com']), 'no active administrator');

        $this->admin();
        $result = $this->commands()->delete(['example' => true]);
        self::assertSame([['id' => $result['deleted'][0]['id'], 'email' => 'admin@example.com', 'role' => 'admin']], $result['deleted']);
        self::assertFalse($result['dryRun']);
        self::assertNull($this->row('admin@example.com'));
        self::assertSame(['root@corp.cz'], array_column($this->commands()->list()['users'], 'email'));
    }
    public function testLastAdminCannotBeDeletedByEmail(): void
    {
        $this->admin();
        $this->refuses(fn () => $this->commands()->delete(['email' => 'root@corp.cz']), 'no active administrator');
        self::assertNotNull($this->row('root@corp.cz'));
    }
    public function testLookalikeDomainsAreNotDeletedAndExampleUsersOfAnyRoleAre(): void
    {
        $this->admin();
        foreach (['u1@notexample.com', 'u2@example.com.cz', 'u3@corp.cz'] as $email) { $this->commands()->create(['email' => $email]); }
        foreach (['d1@example.org', 'd2@sub.example.net', 'd3@host.test', 'd4@x.localhost'] as $email) { $this->commands()->create(['email' => $email]); }
        $result = $this->commands()->delete(['example' => true]);
        self::assertSame(['d1@example.org', 'd2@sub.example.net', 'd3@host.test', 'd4@x.localhost'], array_column($result['deleted'], 'email'));
        self::assertSame(['root@corp.cz', 'u1@notexample.com', 'u2@example.com.cz', 'u3@corp.cz'], array_column($this->commands()->list()['users'], 'email'));
    }
    public function testDryRunReportsButChangesNothing(): void
    {
        $this->admin();
        $id = $this->commands()->create(['email' => 'demo@example.com'])['id'];
        (new RefreshTokens())->issue($id, 3600);
        $result = $this->commands()->delete(['example' => true, 'dry-run' => true]);
        self::assertSame([['id' => $id, 'email' => 'demo@example.com', 'role' => 'user']], $result['deleted']);
        self::assertTrue($result['dryRun']);
        self::assertNotNull($this->row('demo@example.com'));
        self::assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_refresh_tokens')['c']);
    }
    public function testAnonymousUserSurvivesAndRelatedRowsAreRemovedInTheSameDeletion(): void
    {
        Db::insert('cms_users', ['username' => 'ANONYMOUS', 'password' => '0']);
        Db::query('UPDATE cms_users SET user_id = 0 WHERE username = ?', ['ANONYMOUS']);
        $this->admin();
        $demo = $this->commands()->create(['email' => 'demo@example.com'])['id'];
        $keep = $this->commands()->create(['email' => 'keep@corp.cz'])['id'];
        $tokens = new RefreshTokens();
        $tokens->issue($demo, 3600); $tokens->issue($keep, 3600);
        Db::insert('cms_sessions', ['session_id' => 's-demo', 'user_id' => $demo]);
        Db::insert('cms_sessions', ['session_id' => 's-keep', 'user_id' => $keep]);
        Db::insert('cms_api_audit', ['local_user_id' => $demo, 'action' => 'x', 'outcome' => 'ok', 'created_at' => 1]);

        $this->commands()->delete(['email' => 'demo@example.com']);

        self::assertNotNull(Db::fetchAssoc('SELECT 1 FROM cms_users WHERE user_id = 0'));
        self::assertSame([$keep], array_map('intval', array_column(Db::fetchAllAssoc('SELECT user_id FROM cms_refresh_tokens'), 'user_id')));
        self::assertSame(['s-keep'], array_column(Db::fetchAllAssoc('SELECT session_id FROM cms_sessions'), 'session_id'));
        self::assertNull(Db::fetchAssoc('SELECT local_user_id FROM cms_api_audit')['local_user_id']);
    }
    public function testCommandLineReadsPasswordFromStdinAndNeverPrintsIt(): void
    {
        [$code, $out, $err] = $this->cli(['user:create', '--email=cli@corp.cz', '--role=admin'], self::PW . "\n");
        self::assertSame(0, $code, $err);
        $json = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['created' => true, 'id' => $json['id'], 'email' => 'cli@corp.cz', 'username' => 'cli', 'role' => 'admin'], $json);
        self::assertStringNotContainsString(self::PW, $out . $err);
        self::assertTrue(password_verify(self::PW, $this->row('cli@corp.cz')['password']));

        [$code, $out] = $this->cli(['user:list']);
        self::assertSame(0, $code);
        self::assertStringNotContainsString('$2y$', $out);
        [$code, , $err] = $this->cli(['user:create', '--email=cli2@corp.cz'], "short\n");
        self::assertSame(2, $code);
        self::assertStringContainsString('at least 12', $err);
    }
    public function testPasswordInAnArgumentIsRejectedWithExplanation(): void
    {
        foreach (['--password=' . self::PW, '--pass=' . self::PW, '--password'] as $argument) {
            [$code, $out, $err] = $this->cli(['user:create', '--email=arg@corp.cz', $argument]);
            self::assertSame(2, $code);
            self::assertStringContainsString('never accepted as arguments', $err);
            self::assertStringNotContainsString(self::PW, $out . $err);
        }
        self::assertNull($this->row('arg@corp.cz'));
    }
    public function testCommandLineRefusesMissingConfigurationAndUnknownOptions(): void
    {
        [$code, , $err] = $this->cli(['user:list', '--dry-run']);
        self::assertSame(2, $code);
        [$code, , $err] = $this->cli(['user:delete', '--email=a@example.com', '--example']);
        self::assertSame(2, $code);
        self::assertStringContainsString('exactly one', $err);
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/wbengine.php', 'user:list', '--config=/nonexistent/Settings.json'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
        self::assertSame(2, proc_close($process));
    }
}
