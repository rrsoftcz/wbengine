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
        $sql = sprintf('SELECT %s FROM %s b WHERE (b.site_id = %d AND b.section_id = %d)
                                OR (b.section_id = %4$d AND b.shared = 1) 
                              ORDER BY b.order ASC;'
            , '`id`, `name`, `module`, `method`, `site_id`, `section_id`, `static`, `shared`, `device_min`, `device_strict`, `order`'
            , S_TABLE_BOXES,
            $section->getSite()->getSiteId(),
            $section->getSectionId()
        );
        return Db::fetchAllAssoc($sql);
    }

}
