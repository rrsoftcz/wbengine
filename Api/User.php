<?php

/**
 * User's API module
 */
namespace Wbengine\Api;

use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Api\WbengineRestapiAbstract;
use Wbengine\Application\Env\Http;
use Wbengine\User\UserException;

class User extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    public function getUsers() {
        $this->isAuthenticated(
            fn() => $this->Api()->toJson($this->getUserModel()->getAllUsers())
        );
    }

    public function getUserById($userid) {
        $this->isAuthenticated(
            fn() => $this->Api()->toJson($this->getUserModel()->getUserById($userid))
        );
    }

    public function deleteUserById($userid) {
        $this->isAuthenticated(
            fn() => $this->Api()->toJson($this->getUserModel()->deleteUser($userid))
        );
    }

    public function updateUser($userId, $user) {
        $this->isAuthenticated(
            fn() => $this->Api()->toJson($this->getUserModel()->updateUser((int) $userId, $user))
        );
    }

    public function addUser($user) {
        $this->isAuthenticated(
            function($payload) use ($user) {
                $this->getUserModel()->createUser($user);
//                $this->Api()->toJson($user);die();
//                $_lastId = $this->Api()->toJson($this->getUserModel()->createUser($user));
                if (is_array($user)) {
                    var_dump($user);
                    $_lastId = $this->getUserModel()->createUser($user);
                    if ($_lastId) {
                        return $this->Api()->toJson($this->getUserById($_lastId));
                    } else {
                        throw new ApiModelException("User not created", 1);
                    }
                } else {
                    throw new ApiModelException("No user data found.", 1);
                }
            });
    }

}