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

namespace Wbengine\Section;

use Wbengine\Db;
use Wbengine\Model\ModelAbstract;
use Wbengine\Section;

class Model extends ModelAbstract {


     /**
     * Return section boxes as assoc array.
     * @param Section $section
     * @return array
     */
    public function getBoxes( Section $section )
    {
        $sql = sprintf('SELECT box.id, box.name, box.module, box.method, sec.key, box.section_id, box.static, box.shared, box.device_min, box.device_strict, box.location
                FROM %s ord LEFT JOIN %s box ON (box.id = ord.box_id)
                            LEFT JOIN %s sec ON (box.section_id = sec.section_id)
                WHERE (ord.site_id = %d OR box.shared = 1)
                AND box.section_id = %d
                AND ((box.device_min <= %4$d AND box.device_strict = 0) OR (box.device_min = %4$d AND box.device_strict = 1))
                GROUP BY box.id, ord.order	ORDER BY ord.order ASC;'
            , S_TABLE_BOX_ORDERS
            , S_TABLE_BOXES
            , S_TABLE_SECTIONS,
            $section->getSite()->getSiteId(),
            $section->getSectionId()
            , (int) 1//$section->getSite()->getParent()->getDeviceType()
        );

        return Db::fetchAllAssoc($sql);
    }

}
