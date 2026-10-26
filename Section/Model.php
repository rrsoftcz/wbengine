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
use Wbengine\Cache;
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
        $siteId = (string)(int)$section->getSite()->getSiteId();
        $sectionId = (int)$section->getSectionId();

        // Select the box row so optional api_settings survives when migration 005
        // is present. Naming that column would break older schemas; probing would
        // add a query on every cold load. This also carries future box columns in
        // the internal cache, but never changes the public API's explicit fields.
        $sql = sprintf("SELECT b.*, CONCAT(?) AS site_id FROM %s b
              WHERE b.section_id = ?
                AND (JSON_SEARCH(sites, 'one', ?) IS NOT NULL OR b.shared = 1)
                              ORDER BY b.order ASC;"
            , S_TABLE_BOXES
        );

        return Cache::remember('boxes:' . $siteId . ':' . $sectionId, function () use ($sql, $siteId, $sectionId) {
            return Db::fetchAllAssoc($sql, array($siteId, $sectionId, $siteId));
        }, null, true);
    }

}
