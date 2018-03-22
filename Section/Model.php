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

use Wbengine\Application\Env\Stac\Utils;
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
        $sql = sprintf("SELECT CONCAT(%d) AS site_id, %s FROM %s b
              WHERE (JSON_SEARCH(sites, 'one', '%d') IS NOT NULL AND b.section_id = %d)
                                OR (b.section_id = %d AND b.shared = 1)
                              ORDER BY b.order ASC;"
            , $section->getSite()->getSiteId()
            , '`id`, `name`, `module`, `method`, `section_id`, `static`, `shared`, `device_min`, `device_strict`, `order`'
            , S_TABLE_BOXES
            , $section->getSite()->getSiteId()
            , $section->getSectionId()
            , $section->getSite()->getSiteId()
        );

        return Db::fetchAllAssoc($sql);
    }

}
