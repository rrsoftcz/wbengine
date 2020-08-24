<?php

/**
 * User's API module
 */
namespace Wbengine\Api;

use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Api\WbengineRestapiAbstract;

class User extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    public function getUsers(){
        return $this->Api()->toJson($this->getUserModel()->getAllUsers(true));
    }

    public function getUserById($userid){
        return $this->Api()->toJson($this->getUserModel()->getUserById($userid));
    }

    public function deleteUserById($userid){
        return $this->Api()->toJson($this->getUserModel()->deleteUser($userid));
    }

    public function addUser($user){
        return $this->Api()->toJson($this->getUserModel()->createUser($user));
        if (is_array($user)) {
            $_lastId = $this->getUserModel()->createUser($user);
            if ($_lastId) {
                return $this->Api()->toJson($this->getUserById($_lastId));
            } else {
                throw new ApiModelException("Something went wrong :(", 1);
            }
        } else {
            throw new ApiModelException("No user data found.", 1);
        }

    }

}