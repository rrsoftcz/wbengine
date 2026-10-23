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
     * Columns a client is allowed to write.
     */
    const WRITABLE_COLUMNS = array('title', 'description', 'active', 'key', 'return_error_code');


    /**
     * Return site sections as assoc array.
     * @param bool $active
     * @return array
     */
    public function getSections($active = false)
    {
        $sql = sprintf("
            SELECT `section_id`, `title`, `description`, `active`, `key`, `return_error_code`
            FROM %s %s;",
            S_TABLE_SECTIONS,
            ($active) ? " WHERE active = 1" : null
        );

        return Db::fetchAllAssoc($sql);
    }


    private function _checkId($sectionId)
    {
        if (!is_scalar($sectionId) || !ctype_digit((string)$sectionId)) {
            throw new ApiModelException('The section ID must be a number.', 10);
        }
        return (int)$sectionId;
    }


    /**
     * Keep only writable columns, reject anything else.
     * @param mixed $data
     * @return array
     */
    private function _checkData($data)
    {
        if (!is_array($data) || !$data) {
            throw new ApiModelException('The section\'s data can\'t be empty.', 10);
        }

        $unknown = array_diff(array_keys($data), self::WRITABLE_COLUMNS);
        if ($unknown) {
            throw new ApiModelException(sprintf('Unknown section field(s): %s', implode(', ', array_map('strval', $unknown))), 10);
        }

        foreach ($data as $value) {
            if (!is_scalar($value) && null !== $value) {
                throw new ApiModelException('Section field values must be scalar.', 10);
            }
        }
        return $data;
    }


    public function getSectionById($sectionId)
    {
        $sectionId = $this->_checkId($sectionId);

        $res = Db::fetchAssoc(sprintf("SELECT * FROM %s WHERE `section_id` = ? LIMIT 1;", S_TABLE_SECTIONS), array($sectionId));
        if (!is_array($res)) {
            throw new ApiModelException(sprintf('Section ID:%s not found', $sectionId), 10);
        }
        return $res;
    }


    public function deleteSection($sectionId)
    {
        $sectionId = $this->_checkId($sectionId);

        Db::query(sprintf("DELETE FROM %s WHERE `section_id` = ? LIMIT 1;", S_TABLE_SECTIONS), array($sectionId));
        return array("deleted" => Db::getAffected());
    }


    public function updateSection($sectionId, $sectionData)
    {
        $sectionId = $this->_checkId($sectionId);
        $sectionData = $this->_checkData($sectionData);

        return array("updated" => Db::update(S_TABLE_SECTIONS, $sectionData, array('section_id' => $sectionId)));
    }


    public function addSection($data)
    {
        return Db::insert(S_TABLE_SECTIONS, $this->_checkData($data));
    }


}
