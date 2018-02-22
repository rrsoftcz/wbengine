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

use Wbengine\Model\ModelAbstract;
use Wbengine\Db;
use Wbengine\Api\Model\Exception\ApiModelException;

class ApiSectionModel extends ModelAbstract
{

    /**
     * Return site sections as assoc array.
     * @return array
     */
    public function getSections()
    {
        $sql = sprintf("SELECT `section_id`, `title`, `Description`, `active`, `key`, `return_error_code`
                FROM %s
                WHERE active = 1;",
            S_TABLE_SECTIONS
        );

        return Db::fetchAllAssoc($sql);
    }



    public function getSectionById($sectionId)
    {
    	if(!is_numeric($sectionId)){
        	throw new ApiModelException(sprintf('The section ID must be a number.', $sectionId), 10);
        }

        $sql = sprintf("SELECT * FROM %s
			WHERE `section_id` = '%d'
			LIMIT 1;"
            , S_TABLE_SECTIONS
            , $sectionId
        );
        
        $res = Db::fetchAssoc($sql);
        if(!is_array($res)){
        	throw new ApiModelException(sprintf('Section ID:%s not found', $sectionId), 10);
        }else{
	        return $res;
    	}

    }

    public function deleteSection($sectionId)
    {
    	if(!is_numeric($sectionId)){
        	throw new ApiModelException(sprintf('The section ID must be a number.', $sectionId), 10);
        }

        $sql = sprintf("DELETE FROM %s
			WHERE `section_id` = '%d'
			LIMIT 1;"
            , S_TABLE_SECTIONS
            , $sectionId
        );
        
        $res = Db::query($sql);
	    return array("deleted" => Db::getAffected());

    }

    public function updateSection($sectionId, $sectionData)
    {
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

    public function addSection($data)
    {
        $sql = sprintf("INSERT INTO `%s` (`title`, `Description`, `active`, `key`, `return_error_code`)VALUES('%s');"
            , S_TABLE_SECTIONS
            , implode("','", $data)
        );
        
        $res = Db::query($sql);

        if(!$res){
        	throw new ApiModelException(Db::getError(), 1);
        }else{
	        return Db::getInserted();
    	}
    }


}












