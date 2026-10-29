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
use Wbengine\Audit\ChangeLog;
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


    /**
     * What goes into an access token: the user id, name, e-mail and current role.
     * Read straight from the database, so a token can be issued without a PHP session.
     *
     * @param int $userId
     * @return array|null
     */
    public function getTokenIdentity($userId)
    {
        $row = Db::fetchAssoc(
            sprintf("SELECT `user_id`, `username`, `email`, `role` FROM %s WHERE `user_id` = ? AND `user_id` > 0 LIMIT 1;", S_TABLE_USERS),
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


    /**
     * The optional $actor (local_user_id, or the cli actor) and $source (v1 or cli) attribute the change log row.
     */
    public function deleteUser($userId, array $actor = array(), string $source = 'v1')
    {
        $userId = $this->_checkId($userId);
        $this->_assertNotLastAdmin($userId);
        $before = Db::fetchAssoc(sprintf("SELECT %s FROM %s WHERE `user_id` = ? LIMIT 1;", self::PUBLIC_COLUMNS, S_TABLE_USERS), array($userId));

        Db::query(sprintf("DELETE FROM %s WHERE `user_id` = ? LIMIT 1;", S_TABLE_USERS), array($userId));
        // Read the row count first: revoking the tokens runs another statement.
        $deleted = (bool) Db::getAffected();
        $this->_revokeRefreshTokens($userId);
        if ($deleted && is_array($before)) {
            ChangeLog::recordQuietly('user', $userId, 'delete', $before, null, $actor, $source);
        }

        return array(
            "success" => $deleted,
            "user_id" => $userId,
            "message" => $deleted ? "The user successfully deleted." : "The user delete operation failed!"
        );
    }


    public function updateUser($userId, array $user, array $actor = array(), string $source = 'v1')
    {
        $userId = $this->_checkId($userId);

        $data = $this->_checkData($user);
        if (isset($data['role']) && Role::ADMIN !== $data['role']) {
            $this->_assertNotLastAdmin($userId);
        }

        // Changed columns before the write, never the password: only a "password_changed" flag is logged.
        $columns = array_values(array_diff(array_keys($data), array('password')));
        $before = $columns ? Db::fetchAssoc(sprintf("SELECT %s FROM %s WHERE `user_id` = ? LIMIT 1;", implode(', ', array_map(array(Db::class, 'identifier'), $columns)), S_TABLE_USERS), array($userId)) : array();

        $updated = Db::update(S_TABLE_USERS, $data, array('user_id' => $userId));
        if (array_key_exists('password', $data)) {
            // A new password logs the user out of every device.
            $this->_revokeRefreshTokens($userId);
        }
        if ($updated && is_array($before)) {
            $after = array_diff_key($data, array('password' => 1));
            if (array_key_exists('password', $data)) {
                $after['passwordChanged'] = true;
            }
            ChangeLog::recordQuietly('user', $userId, 'update', $before, $after, $actor, $source);
        }
        return array("updated" => $updated);
    }


    public function createUser($user, $exist, array $actor = array(), string $source = 'v1')
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
            $after = array_diff_key($user, array('password' => 1));
            $after['passwordSet'] = true;
            ChangeLog::recordQuietly('user', $id, 'create', null, $after, $actor, $source);
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
