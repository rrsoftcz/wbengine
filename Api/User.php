<?php

/**
 * User's API module
 */
namespace Wbengine\Api;

use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Api\WbengineRestapiAbstract;
use Wbengine\Application\Env\Http;
use Wbengine\User\Role;
use Wbengine\User\UserException;

class User extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    /**
     * Whether the actor may act on the given user: an administrator on anyone,
     * everybody else only on themselves.
     */
    private function _canActOn($actor, $userId) {
        return Role::atLeast($actor['role'], Role::ADMIN) || (int) $actor['user_id'] === (int) $userId;
    }

    private function _forbidden() {
        $this->Api()->toJson(array("success" => false, "message" => "Forbidden."), Http::FORBIDDEN);
    }

    public function getUsers() {
        $this->isAuthenticated(
            fn() => $this->Api()->toJson($this->getUserModel()->getAllUsers()),
            Role::ADMIN
        );
    }

    public function getUserById($userid) {
        $this->isAuthenticated(function ($payload, $actor) use ($userid) {
            if (!$this->_canActOn($actor, $userid)) {
                $this->_forbidden();
            }
            $this->Api()->toJson($this->getUserModel()->getUserById($userid));
        }, Role::USER);
    }

    public function deleteUserById($userid) {
        $this->isAuthenticated(function ($payload, $actor) use ($userid) {
            if ((int) $actor['user_id'] === (int) $userid) {
                $this->Api()->toJson(array("success" => false, "message" => "You cannot delete your own account."), Http::BAD_REQUEST);
            }
            $this->Api()->toJson($this->getUserModel()->deleteUser($userid, $this->changeActor($actor)));
        }, Role::ADMIN);
    }

    public function updateUser($userId, $user) {
        $this->isAuthenticated(function ($payload, $actor) use ($userId, $user) {
            if (!$this->_canActOn($actor, $userId)) {
                $this->_forbidden();
            }

            $user = is_array($user) ? $user : array();
            // Only an administrator may change a role, nobody can raise their own.
            if (array_key_exists('role', $user) && !Role::atLeast($actor['role'], Role::ADMIN)) {
                $this->_forbidden();
            }

            $this->Api()->toJson($this->getUserModel()->updateUser($userId, $user, $this->changeActor($actor)));
        }, Role::USER);
    }

    public function addUser($user) {
        $this->isAuthenticated(
            function($payload, $actor) use ($user) {
                if (!is_array($user) || empty($user["email"])) {
                    $this->Api()->toJson(array("success" => false, "message" => "Minimum fields error, email and password required."), Http::BAD_REQUEST);
                }

                $this->checkUserExist(
                  fn($e) => $this->api()->toJson($this->getUserModel()->createUser($user, $e, $this->changeActor($actor))), $user["email"]
                );
            }, Role::ADMIN);
    }

    public function checkUserExist($callable, $email){
        if (is_callable($callable)) {
            return $callable(!is_null($this->getUserModel()->getUserByEmail($email)));
        }
        return null;
    }

}