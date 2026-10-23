<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Api\Model\ApiUserModel;
use Wbengine\Db;
use Wbengine\User\Password;

class PasswordColumnTest extends DbTestCase
{
    public function testWideColumnAllowsModernHashes(): void
    {
        $this->assertTrue(Password::canStoreModernHash());
        $this->assertStringStartsWith('$2y$', Password::hash('secret'));
    }

    public function testApiCreatesHashedUserAndWhitelistsFields(): void
    {
        $model = new ApiUserModel();

        $result = $model->createUser(array('email' => "o'brien+t@example.com", 'password' => "p&ss\"w'<rd>"), false);

        $this->assertTrue($result['success']);
        $row = Db::fetchAssoc('SELECT * FROM cms_users WHERE user_id = ?', array($result['user_id']));
        $this->assertSame("o'brien+t@example.com", $row['username']);
        $this->assertTrue(Password::verify("p&ss\"w'<rd>", $row['password']));
        $this->assertSame('2', (string)$row['user_type']);
    }

    public function testApiNeverReturnsSecrets(): void
    {
        $model = new ApiUserModel();
        $id = $model->createUser(array('email' => 'a@example.com', 'password' => 'x'), false)['user_id'];

        $this->assertArrayNotHasKey('password', $model->getUserById($id));
        $this->assertArrayNotHasKey('ac_key', $model->getUserById($id));
        foreach ($model->getAllUsers() as $user) {
            $this->assertArrayNotHasKey('password', $user);
        }
    }

    public function testApiUpdateRehashesPassword(): void
    {
        $model = new ApiUserModel();
        $id = $model->createUser(array('email' => 'a@example.com', 'password' => 'first'), false)['user_id'];

        $model->updateUser($id, array('password' => 'second'));

        $hash = Db::fetchAssoc('SELECT password FROM cms_users WHERE user_id = ?', array($id))['password'];
        $this->assertTrue(Password::verify('second', $hash));
        $this->assertFalse(Password::verify('first', $hash));
    }
}
