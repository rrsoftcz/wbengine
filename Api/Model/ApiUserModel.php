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

class ApiUserModel extends ModelAbstract
{
    /**
     * Columns returned to API clients. Never the password hash or the activation key.
     */
    const PUBLIC_COLUMNS = '`user_id`, `user_type`, `group_id`, `username`, `firstname`, `lastname`, `email`, `age`, `sex`, `ac_status`, `address`, `city`, `postcode`, `country`, `ac_active`, `user_ip`, `locale`';

    /**
     * Columns a client is allowed to write. Privileged columns (user_type, group_id,
     * ac_status, ac_key, ac_active, user_ip) are intentionally not writable here.
     */
    const WRITABLE_COLUMNS = array('username', 'firstname', 'lastname', 'password', 'email', 'age', 'sex', 'address', 'city', 'postcode', 'country', 'locale');


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

        if (isset($user['email']) && false === filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ApiModelException('Invalid e-mail address.', 10);
        }

        if (array_key_exists('password', $user)) {
            $user['password'] = md5((string)$user['password']);
        }
        return $user;
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

        Db::query(sprintf("DELETE FROM %s WHERE `user_id` = ? LIMIT 1;", S_TABLE_USERS), array($userId));
        $deleted = (bool) Db::getAffected();

        return array(
            "success" => $deleted,
            "user_id" => $userId,
            "message" => $deleted ? "The user successfully deleted." : "The user delete operation failed!"
        );
    }


    public function updateUser($userId, array $user)
    {
        $userId = $this->_checkId($userId);

        return array("updated" => Db::update(S_TABLE_USERS, $this->_checkData($user), array('user_id' => $userId)));
    }


    public function createUser($user, $exist)
    {
        if (true === $exist) {
            throw new ApiException("The user with this email already exist, please try another one.", 400);
        }

        if (!is_array($user) || empty($user['email']) || empty($user['password'])) {
            throw new ApiException("Minimum fields error, email and password required.", 1);
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
