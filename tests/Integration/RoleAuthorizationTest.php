<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api\Model\ApiUserModel;
use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Db;
use Wbengine\User\Role;

class RoleAuthorizationTest extends DbTestCase
{
    private ApiUserModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new ApiUserModel();
    }

    private function user(string $email, string $role = Role::USER): int
    {
        $id = $this->model->createUser(array('email' => $email, 'password' => 'x', 'role' => $role), false)['user_id'];
        return (int)$id;
    }

    public function testNewUsersAreRegularUsersByDefault(): void
    {
        $id = $this->model->createUser(array('email' => 'a@example.com', 'password' => 'x'), false)['user_id'];

        $this->assertSame(Role::USER, $this->model->getAuthorization($id)['role']);
    }

    public function testAuthorizationReflectsTheDatabaseImmediately(): void
    {
        $admin = $this->user('admin@example.com', Role::ADMIN);
        $this->user('second@example.com', Role::ADMIN);
        $this->assertSame(Role::ADMIN, $this->model->getAuthorization($admin)['role']);

        $this->model->updateUser($admin, array('role' => Role::USER));

        $this->assertSame(Role::USER, $this->model->getAuthorization($admin)['role']);
    }

    public function testUnknownAndAnonymousUsersHaveNoAuthorization(): void
    {
        Db::query("INSERT INTO cms_users (user_id, username, role) VALUES (0, 'ANONYMOUS', 'admin')");

        $this->assertNull($this->model->getAuthorization(0));
        $this->assertNull($this->model->getAuthorization(99999));
        $this->assertNull($this->model->getAuthorization(-1));
    }

    public function testLastAdministratorCannotBeDemoted(): void
    {
        $admin = $this->user('only@example.com', Role::ADMIN);

        $this->expectException(ApiModelException::class);
        $this->expectExceptionMessage('last administrator');
        $this->model->updateUser($admin, array('role' => Role::EDITOR));
    }

    public function testLastAdministratorCannotBeDeleted(): void
    {
        $admin = $this->user('only@example.com', Role::ADMIN);

        try {
            $this->model->deleteUser($admin);
            $this->fail('Expected ApiModelException');
        } catch (ApiModelException $e) {
            $this->assertSame(1, $this->model->countAdmins());
        }
    }

    public function testAnAdministratorCanBeDemotedOrDeletedWhenAnotherExists(): void
    {
        $first = $this->user('first@example.com', Role::ADMIN);
        $second = $this->user('second@example.com', Role::ADMIN);

        $this->model->updateUser($first, array('role' => Role::EDITOR));
        $this->assertSame(1, $this->model->countAdmins());

        $this->expectException(ApiModelException::class);
        $this->model->deleteUser($second);
    }

    public function testRegularUsersCanAlwaysBeDeleted(): void
    {
        $this->user('admin@example.com', Role::ADMIN);
        $regular = $this->user('regular@example.com');

        $this->assertTrue($this->model->deleteUser($regular)['success']);
    }

    public function testRoleNeverLeaksPrivilegedColumnsOrPasswords(): void
    {
        $id = $this->user('a@example.com', Role::EDITOR);

        $row = $this->model->getUserById($id);

        $this->assertSame(Role::EDITOR, $row['role']);
        $this->assertArrayNotHasKey('password', $row);
    }
}
