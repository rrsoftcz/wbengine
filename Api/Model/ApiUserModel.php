<?php

/**
 * $Id: Model.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Site's object Class_Site data Model.
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Api\Model;

use Wbengine\Api\Exception\ApiException;
use Wbengine\Model\ModelAbstract;
use Wbengine\Db;
use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Db\Exception\DbException;
use Wbengine\Api\Auth\RefreshTokens;
use Wbengine\User\Password;
use Wbengine\User\Role;

class ApiUserModel extends ModelAbstract
{
    /**
     * Columns returned to API clients. Never the password hash or the activation key.
     */
    const PUBLIC_COLUMNS = '`user_id`, `user_type`, `group_id`, `username`, `firstname`, `lastname`, `email`, `age`, `sex`, `ac_status`, `address`, `city`, `postcode`, `country`, `ac_active`, `user_ip`, `locale`, `role`';

    /**
     * Columns a client is allowed to write. Privileged columns (user_type, group_id,
     * ac_status, ac_key, ac_active, user_ip) are intentionally not writable here.
     * "role" is writable but the controller only lets administrators send it.
     */
    const WRITABLE_COLUMNS = array('username', 'firstname', 'lastname', 'password', 'email', 'age', 'sex', 'address', 'city', 'postcode', 'country', 'locale', 'role');


    /**
     * Return all users as assoc array (without secrets).
     * @return array
     */
    public function getAllUsers()
    {
        $sql = sprintf("SELECT %s FROM %s WHERE NOT user_id = 0;", self::PUBLIC_COLUMNS, S_TABLE_USERS);

        return Db::fetchAllAssoc($sql);
    }


    private function _checkId($userId)
    {
        if (!is_scalar($userId) || !ctype_digit((string)$userId)) {
            throw new ApiModelException('The user\'s ID must be a number.', 10);
        }
        return (int)$userId;
    }


    /**
     * Keep only writable columns, reject anything else. Password is hashed here.
     * @param array $user
     * @return array
     */
    private function _checkData($user)
    {
        if (!is_array($user) || !$user) {
            throw new ApiModelException('Empty user data.', 500);
        }

        $unknown = array_diff(array_keys($user), self::WRITABLE_COLUMNS);
        if ($unknown) {
            throw new ApiModelException(sprintf('Unknown user field(s): %s', implode(', ', array_map('strval', $unknown))), 10);
        }

        foreach ($user as $value) {
            if (!is_scalar($value) && null !== $value) {
                throw new ApiModelException('User field values must be scalar.', 10);
            }
        }

        if (array_key_exists('role', $user) && !Role::isValid($user['role'])) {
            throw new ApiModelException(sprintf('The role must be one of: %s.', implode(', ', Role::all())), 10);
        }

        if (isset($user['email']) && false === filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ApiModelException('Invalid e-mail address.', 10);
        }

        if (array_key_exists('password', $user)) {
            $user['password'] = Password::hash((string)$user['password']);
        }
        return $user;
    }


    /**
     * The facts needed to authorize an API call: the user id and the current role.
     * Returns null for an unknown user and for the anonymous user.
     *
     * @param int $userId
     * @return array|null
     */
    public function getAuthorization($userId)
    {
        $row = Db::fetchAssoc(
            sprintf("SELECT `user_id`, `role` FROM %s WHERE `user_id` = ? AND `user_id` > 0 LIMIT 1;", S_TABLE_USERS),
            array((int) $userId)
        );
        return is_array($row) ? $row : null;
    }


    public function countAdmins()
    {
        return (int) Db::fetchAssoc(sprintf("SELECT COUNT(*) AS c FROM %s WHERE `role` = ?;", S_TABLE_USERS), array(Role::ADMIN))['c'];
    }


    /**
     * The last administrator can neither be demoted nor deleted: nobody could repair it.
     */
    private function _assertNotLastAdmin($userId)
    {
        $current = $this->getAuthorization($userId);
        if ($current && Role::ADMIN === $current['role'] && $this->countAdmins() <= 1) {
            throw new ApiModelException('The last administrator cannot be demoted or deleted.', 10);
        }
    }


    /**
     * Revoke every refresh token of a user. A missing token table must not break the
     * user operation itself.
     */
    private function _revokeRefreshTokens($userId)
    {
        try {
            (new RefreshTokens())->revokeAllForUser($userId);
        } catch (\Throwable $e) {
            error_log(__METHOD__ . ': ' . $e->getMessage());
        }
    }


    public function getUserById($userId)
    {
        $userId = $this->_checkId($userId);

        $res = Db::fetchAssoc(sprintf("SELECT %s FROM %s WHERE `user_id` = ? LIMIT 1;", self::PUBLIC_COLUMNS, S_TABLE_USERS), array($userId));
        if (!is_array($res)) {
            throw new ApiModelException(sprintf('User ID:%s not found', $userId), 10);
        }
        return $res;
    }


    public function getUserByEmail($email) {
        if (!is_string($email) || '' === $email) {
            throw new ApiModelException(sprintf('Invalid E-mail, expected string, but got %s.', gettype($email)), 10);
        }

        return Db::fetchAssoc(sprintf("SELECT `user_id` FROM %s WHERE `email` = ? ORDER BY user_id;", S_TABLE_USERS), array($email));
    }


    public function deleteUser($userId)
    {
        $userId = $this->_checkId($userId);
        $this->_assertNotLastAdmin($userId);

        Db::query(sprintf("DELETE FROM %s WHERE `user_id` = ? LIMIT 1;", S_TABLE_USERS), array($userId));
        // Read the row count first: revoking the tokens runs another statement.
        $deleted = (bool) Db::getAffected();
        $this->_revokeRefreshTokens($userId);

        return array(
            "success" => $deleted,
            "user_id" => $userId,
            "message" => $deleted ? "The user successfully deleted." : "The user delete operation failed!"
        );
    }


    public function updateUser($userId, array $user)
    {
        $userId = $this->_checkId($userId);

        $data = $this->_checkData($user);
        if (isset($data['role']) && Role::ADMIN !== $data['role']) {
            $this->_assertNotLastAdmin($userId);
        }

        $updated = Db::update(S_TABLE_USERS, $data, array('user_id' => $userId));
        if (array_key_exists('password', $data)) {
            // A new password logs the user out of every device.
            $this->_revokeRefreshTokens($userId);
        }
        return array("updated" => $updated);
    }


    public function createUser($user, $exist)
    {
        if (true === $exist) {
            throw new ApiException("The user with this email already exist, please try another one.", 400);
        }

        if (!is_array($user) || empty($user['email']) || empty($user['password'])) {
            throw new ApiException("Minimum fields error, email and password required.", 1);
        }

        if (empty($user['username'])) {
            $user['username'] = $user['email'];
        }

        $user = $this->_checkData($user);

        try {
            $id = Db::insert(S_TABLE_USERS, $user);
            return array(
                "success" => true,
                "user_id" => $id,
                "message" => "The user successfully created."
            );
        } catch (DbException $e) {
            throw new ApiException("The user could not be created.");
        }
    }


}
