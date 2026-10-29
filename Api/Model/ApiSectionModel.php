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
use Wbengine\Audit\ChangeLog;
use Wbengine\Cache;
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


    /**
     * The optional $actor (local_user_id) attributes the change log row, $source is v1.
     * A section `key` is a namespace, not a secret, so it is exempt from redaction.
     */
    public function deleteSection($sectionId, array $actor = array(), string $source = 'v1')
    {
        $sectionId = $this->_checkId($sectionId);
        $before = Db::fetchAssoc(sprintf("SELECT * FROM %s WHERE `section_id` = ? LIMIT 1;", S_TABLE_SECTIONS), array($sectionId));

        Db::query(sprintf("DELETE FROM %s WHERE `section_id` = ? LIMIT 1;", S_TABLE_SECTIONS), array($sectionId));
        $deleted = Db::getAffected();
        Cache::flush();
        if ($deleted && is_array($before)) {
            ChangeLog::recordQuietly('section', $sectionId, 'delete', $before, null, $actor, $source, array('key'));
        }
        return array("deleted" => $deleted);
    }


    public function updateSection($sectionId, $sectionData, array $actor = array(), string $source = 'v1')
    {
        $sectionId = $this->_checkId($sectionId);
        $sectionData = $this->_checkData($sectionData);
        $before = Db::fetchAssoc(sprintf("SELECT %s FROM %s WHERE `section_id` = ? LIMIT 1;", implode(', ', array_map(array(Db::class, 'identifier'), array_keys($sectionData))), S_TABLE_SECTIONS), array($sectionId));

        $updated = Db::update(S_TABLE_SECTIONS, $sectionData, array('section_id' => $sectionId));
        Cache::flush();
        if ($updated && is_array($before)) {
            ChangeLog::recordQuietly('section', $sectionId, 'update', $before, $sectionData, $actor, $source, array('key'));
        }
        return array("updated" => $updated);
    }


    public function addSection($data, array $actor = array(), string $source = 'v1')
    {
        $data = $this->_checkData($data);
        $id = Db::insert(S_TABLE_SECTIONS, $data);
        Cache::flush();
        if ($id) {
            ChangeLog::recordQuietly('section', $id, 'create', null, $data, $actor, $source, array('key'));
        }
        return $id;
    }


}
