<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api\Auth\RefreshTokens;
use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Api\User;
use Wbengine\Db;
use Wbengine\User\Password;
use Wbengine\User\Role;

class ApiUserControllerTest extends ApiTestCase
{
    private User $users;
    private int $admin;
    private int $regular;
    private int $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new User($this->api);
        $this->admin = $this->createUser('admin@example.com', Role::ADMIN);
        $this->createUser('second-admin@example.com', Role::ADMIN);
        $this->regular = $this->createUser('regular@example.com');
        $this->other = $this->createUser('other@example.com');
    }

    // ------------------------------------------------------------ listing

    public function testListIsAdminOnly(): void
    {
        $this->asUser($this->regular);
        $this->assertSame(403, $this->respond(fn () => $this->users->getUsers())['status']);

        $this->asUser($this->admin);
        $r = $this->respond(fn () => $this->users->getUsers());
        $this->assertSame(200, $r['status']);
        $this->assertCount(4, $r['json']);
    }

    public function testListNeverContainsSecrets(): void
    {
        $this->asUser($this->admin);

        $r = $this->respond(fn () => $this->users->getUsers());

        foreach ($r['json'] as $user) {
            $this->assertArrayNotHasKey('password', $user);
            $this->assertArrayNotHasKey('ac_key', $user);
        }
        $this->assertStringNotContainsString('$2y$', $r['body']);
    }

    // ------------------------------------------------------------- reading

    public function testAUserReadsOnlyThemselves(): void
    {
        $this->asUser($this->regular);

        $own = $this->respond(fn () => $this->users->getUserById($this->regular));
        $this->assertSame(200, $own['status']);
        $this->assertSame('regular@example.com', $own['json']['email']);

        $this->assertSame(403, $this->respond(fn () => $this->users->getUserById($this->other))['status']);
    }

    public function testAnAdminReadsAnyone(): void
    {
        $this->asUser($this->admin);

        $this->assertSame('other@example.com', $this->respond(fn () => $this->users->getUserById($this->other))['json']['email']);
    }

    // ------------------------------------------------------------ creating

    public function testCreateIsAdminOnly(): void
    {
        $this->asUser($this->regular);

        $r = $this->respond(fn () => $this->users->addUser(['email' => 'new@example.com', 'password' => 'x']));

        $this->assertSame(403, $r['status']);
        $this->assertSame(0, (int) Db::fetchAssoc("SELECT COUNT(*) c FROM cms_users WHERE email='new@example.com'")['c']);
    }

    public function testAdminCreatesAUserWithAHashedPassword(): void
    {
        $this->asUser($this->admin);

        $r = $this->respond(fn () => $this->users->addUser(['email' => 'new@example.com', 'password' => "p&ss\"w'<rd>"]));

        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['json']['success']);
        $row = Db::fetchAssoc('SELECT * FROM cms_users WHERE user_id = ?', [$r['json']['user_id']]);
        $this->assertTrue(Password::verify("p&ss\"w'<rd>", $row['password']));
        $this->assertSame(Role::USER, $row['role']);
    }

    public function testAdminCanCreateAnEditor(): void
    {
        $this->asUser($this->admin);

        $r = $this->respond(fn () => $this->users->addUser(['email' => 'ed@example.com', 'password' => 'x', 'role' => 'editor']));

        $this->assertSame(Role::EDITOR, Db::fetchAssoc('SELECT role FROM cms_users WHERE user_id = ?', [$r['json']['user_id']])['role']);
    }

    public function testCreateRejectsDuplicatesAndMissingFields(): void
    {
        $this->asUser($this->admin);

        // A duplicate is reported by the model; the route layer turns the exception into a 400.
        try {
            $this->respond(fn () => $this->users->addUser(['email' => 'other@example.com', 'password' => 'x']));
            $this->fail('Expected the duplicate to be refused');
        } catch (\Wbengine\Api\Exception\ApiException $e) {
            $this->assertStringContainsString('already exist', $e->getMessage());
        }

        $missing = $this->respond(fn () => $this->users->addUser(['password' => 'x']));
        $this->assertSame(400, $missing['status']);

        $notAnObject = $this->respond(fn () => $this->users->addUser(null));
        $this->assertSame(400, $notAnObject['status']);
    }

    public function testCreateCannotSetPrivilegedColumns(): void
    {
        $this->asUser($this->admin);

        $this->expectException(ApiModelException::class);
        $this->respond(fn () => $this->users->addUser(['email' => 'x@example.com', 'password' => 'x', 'user_type' => 0, 'group_id' => 1]));
    }

    // ------------------------------------------------------------ updating

    public function testAUserUpdatesTheirOwnProfile(): void
    {
        $this->asUser($this->regular);

        $r = $this->respond(fn () => $this->users->updateUser($this->regular, ['firstname' => 'Me']));

        $this->assertSame(200, $r['status']);
        $this->assertSame('Me', Db::fetchAssoc('SELECT firstname FROM cms_users WHERE user_id = ?', [$this->regular])['firstname']);
    }

    public function testAUserCannotEditSomeoneElse(): void
    {
        $this->asUser($this->regular);

        $this->assertSame(403, $this->respond(fn () => $this->users->updateUser($this->other, ['firstname' => 'Hax']))['status']);
        $this->assertNull(Db::fetchAssoc('SELECT firstname FROM cms_users WHERE user_id = ?', [$this->other])['firstname']);
    }

    public function testNobodyRaisesTheirOwnRole(): void
    {
        foreach ([$this->regular, $this->createUser('ed@example.com', Role::EDITOR)] as $id) {
            $this->asUser($id);
            $this->assertSame(403, $this->respond(fn () => $this->users->updateUser($id, ['role' => 'admin']))['status']);
        }
        $this->assertSame(Role::USER, Db::fetchAssoc('SELECT role FROM cms_users WHERE user_id = ?', [$this->regular])['role']);
    }

    public function testAnAdminChangesRoles(): void
    {
        $this->asUser($this->admin);

        $this->respond(fn () => $this->users->updateUser($this->regular, ['role' => 'editor']));

        $this->assertSame(Role::EDITOR, Db::fetchAssoc('SELECT role FROM cms_users WHERE user_id = ?', [$this->regular])['role']);
    }

    public function testAnInvalidRoleIsRejected(): void
    {
        $this->asUser($this->admin);

        $this->expectException(ApiModelException::class);
        $this->respond(fn () => $this->users->updateUser($this->regular, ['role' => 'root']));
    }

    public function testChangingAPasswordRevokesTheRefreshTokens(): void
    {
        $tokens = new RefreshTokens();
        $token = $tokens->issue($this->regular, 3600);
        $this->asUser($this->regular);

        $this->respond(fn () => $this->users->updateUser($this->regular, ['password' => 'new-password']));

        $this->assertTrue(Password::verify('new-password', Db::fetchAssoc('SELECT password FROM cms_users WHERE user_id = ?', [$this->regular])['password']));
        $this->expectException(\Wbengine\Api\Auth\Exception\InvalidRefreshTokenException::class);
        $tokens->rotate($token, 3600);
    }

    public function testChangingAProfileFieldKeepsTheRefreshTokens(): void
    {
        $tokens = new RefreshTokens();
        $token = $tokens->issue($this->regular, 3600);
        $this->asUser($this->regular);

        $this->respond(fn () => $this->users->updateUser($this->regular, ['firstname' => 'Same']));

        $this->assertSame($this->regular, $tokens->rotate($token, 3600)['user_id']);
    }

    // ------------------------------------------------------------ deleting

    public function testDeleteIsAdminOnly(): void
    {
        $this->asUser($this->regular);

        $this->assertSame(403, $this->respond(fn () => $this->users->deleteUserById($this->other))['status']);
        $this->assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_users WHERE user_id = ?', [$this->other])['c']);
    }

    public function testAdminDeletesAUserAndTheirTokens(): void
    {
        $tokens = new RefreshTokens();
        $token = $tokens->issue($this->other, 3600);
        $this->asUser($this->admin);

        $r = $this->respond(fn () => $this->users->deleteUserById($this->other));

        $this->assertSame(200, $r['status']);
        $this->assertTrue($r['json']['success'], 'a successful delete says so');
        $this->assertSame('The user successfully deleted.', $r['json']['message']);
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_users WHERE user_id = ?', [$this->other])['c']);
        $this->expectException(\Wbengine\Api\Auth\Exception\InvalidRefreshTokenException::class);
        $tokens->rotate($token, 3600);
    }

    public function testNobodyDeletesThemselves(): void
    {
        $this->asUser($this->admin);

        $r = $this->respond(fn () => $this->users->deleteUserById($this->admin));

        $this->assertSame(400, $r['status']);
        $this->assertSame(1, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_users WHERE user_id = ?', [$this->admin])['c']);
    }

    public function testDeletingAMissingUserSaysItFailed(): void
    {
        $this->asUser($this->admin);

        $r = $this->respond(fn () => $this->users->deleteUserById(99999));

        $this->assertFalse($r['json']['success']);
    }
}
