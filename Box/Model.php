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

namespace Wbengine\Box;

use Wbengine\Box;
use Wbengine\Db;
use Wbengine\Model\ModelAbstract;

class Model extends ModelAbstract {


    /**
     * Return box information data from Db.
     *
     * @param Box $box
     * @return array
     */
    public function getBoxById( Box $box )
    {
        $sql = sprintf("SELECT 
                    b.id,
                    b.name,
                    b.module,
                    b.method,
                    b.section_id,
                    b.static,
                    b.shared,
                    b.device_min,
                    b.device_strict 
                FROM %s b
                WHERE b.id = %d
                LIMIT 1;",
            S_TABLE_BOXES,
            $box->getBoxId()
        );

        return Db::query($sql)->fetch_assoc();
    }

}
