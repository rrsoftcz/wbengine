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
     * Return site sections as assoc array.
     * @return array
     */
    public function getAllUsers()
    {
        $sql = sprintf("
            SELECT `user_id`, `user_type`, `group_id`, `username`, `firstname`, `lastname`, `password`, `email`, `age`, `sex`, `ac_status`, `ac_key`, `address`, `city`, `postcode`, `country`, `ac_active`, `user_ip`, `locale`
            FROM %s WHERE NOT user_id = 0;",
            S_TABLE_USERS);

        return Db::fetchAllAssoc($sql);
    }



    public function getUserById($userId)
    {
    	if(!is_numeric($userId)){
        	throw new ApiModelException(sprintf('The user\'s ID must be a number.', $userId), 10);
        }

        $sql = sprintf("SELECT `user_id`, `user_type`, `group_id`, `username`, `firstname`, `lastname`, `password`, `email`, `age`, `sex`, `ac_status`, `ac_key`, `address`, `city`, `postcode`, `country`, `ac_active`, `user_ip`, `locale` 
            FROM %s
			WHERE `user_id` = %d
            LIMIT 1;",
            S_TABLE_USERS,
            $userId
        );
        
        $res = Db::fetchAssoc($sql);
        if(!is_array($res)){
        	throw new ApiModelException(sprintf('User ID:%s not found', $userId), 10);
        }else{
	        return $res;
    	}

    }


    public function getUserByEmail($email) {
    	if(empty($email)){
        	throw new ApiModelException(sprintf('Invalid E-mail, expected string, but got %s.', gettype($email)), 10);
        }

        $sql = sprintf("SELECT `user_id` FROM %s WHERE `email` = '%s' ORDER BY user_id;",
            S_TABLE_USERS,
            $email
        );

        $res = Db::fetchAssoc($sql);
        return $res;
    }


    public function deleteUser($userId)
    {
    	if(!is_numeric($userId)){
        	throw new ApiModelException(sprintf('The user ID must be a number.', $userId), 10);
        }

        $sql = sprintf("DELETE FROM %s
			WHERE `user_id` = '%d'
			LIMIT 1;"
            , S_TABLE_USERS
            , $userId
        );
        
        $res = Db::query($sql);
        return array(
            "success" => (bool) Db::getAffected(),
            "user_id" => $userId,
            "message" =>  Db::getAffected() ? "The user successfully deleted." : "The user delete operation failed!"
        );
	    // return array("deleted" => Db::getAffected());

    }
    
    public function updateUser(int $userId, array $user)
    {
        $i = 0;
        $statement = '';

    	if(!is_numeric($userId)){
        	throw new ApiModelException('The user ID must be a number.', 500);
        }

        if(!is_array($user) || sizeof($user) === 0){
        	throw new ApiModelException('Empty user data.', 500);
        }

        foreach($user as $key => $value) {
            $fn1 = fn($v, $k) => ($k === 'password') ? md5($v) : $value;
            $fn2 = fn($i, $c) => ($i < ($c - 1));
            $fn3 = fn($i, $c, $s) => ($fn2($i, $c)) ? $s . ', ' : $s;

            $s = "`" . $key . "` = '" . $fn1($value, $key) . "'" ;
            $statement .= $fn3($i, count($user), $s);
            $i++;
        }

        $sql = sprintf("UPDATE %s SET %s WHERE `user_id` = %d;"
            , S_TABLE_USERS
            , $statement
            , $userId
        );
        die(json_encode(array("success" => true, "sql" => $sql), JSON_PRETTY_PRINT));

        $res = Db::query($sql);
	    return array("updated" => Db::getAffected());

    }

    public function createUser($user, $exist)
    {
        $names = null;
        $email = $user['email'];
        $paswd = $user['password'];

        if(true === $exist){
            throw new ApiException(sprintf("The user with email '%s' already exist, please try another one.", $user["email"]), 400);
        }

        if(true === empty($email) || true === empty($paswd)){
            throw new ApiException("Minimum fields error, email and password required.", 1);
        }

        if(!empty($paswd)){
            $user['password'] = md5($paswd);
        }

        foreach ($user as $key => $value) {
            if(is_null($names)){
                $names .= "`".$key."`";
            }else{
                $names .= ",`".$key."`";
            }
        }

        $sql = sprintf("INSERT INTO `%s` (%s)VALUES('%s');"
            , S_TABLE_USERS
            , $names
            , implode("','", $user)
        );
        
//        die(json_encode(array("success" => true, "sql" => $sql), JSON_PRETTY_PRINT));
        try {
            Db::query($sql);
            return array(
                "success" => true,
                "user_id" => Db::getInserted(),
                "message" => "The user successfully created."
            );
//            return Db::getInserted();
        }catch(DbException $e){
            throw new ApiException($e->getMessage());
        }

    }


}












