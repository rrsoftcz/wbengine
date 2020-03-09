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
	    return array("deleted" => Db::getAffected());

    }

    public function updateSection($sectionId, $sectionData)
    {
        $i = 0;
    	if(!is_numeric($sectionId)){
        	throw new ApiModelException(sprintf('The section ID must be a number.', $sectionId), 10);
        }

        if(!is_array($sectionData)){
        	throw new ApiModelException(sprintf('The section\'s data can\'t be empty.', $sectionId), 10);
        }

        foreach($sectionData as $key => $value) {
            $statement .= "`".$key."` = '".$value."'";
            if ($i < count($sectionData) - 1) {
                $statement.= " , ";
        }
        $i++;
    }

        $sql = sprintf("UPDATE %s SET %s
			WHERE `section_id` = '%d';"
            , S_TABLE_SECTIONS
            , $statement
            , $sectionId
        );
        
        $res = Db::query($sql);
	    return array("updated" => Db::getAffected());

    }

    public function createUser($user)
    {
        $sql = sprintf("INSERT INTO `%s` (`user_type`, `group_id`, `username`, `firstname`, `lastname`, `password`, `email`, `age`, `sex`, `ac_status`, `ac_key`, `address`, `city`, `postcode`, `country`, `ac_active`, `user_ip`, `locale`)VALUES('%s');"
            , S_TABLE_USERS
            , implode("','", $user)
        );
        
        try {
            Db::query($sql);
            return Db::getInserted();
        }catch(DbException $e){
            throw new ApiException($e->getMessage());
        }

    }


}












