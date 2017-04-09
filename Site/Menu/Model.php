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

namespace Wbengine\Site\Menu;

use Wbengine\Site;
use Wbengine\Model\ModelAbstract;

class Model extends ModelAbstract
{
    private static $db = null;

    public function __construct(Site\Menu\MenuitemInterface $menu){
        self::$db = $this->getDbAdapter();
    }

    /**
     * Return current site title by given
     * url.
     *
     * @param string $part
     * @return string
     */
    public function getTitleByUrl($part)
    {
        $sql = sprintf("SELECT title FROM %s
			WHERE link='/%s/'
			LIMIT 1;"
            , S_TABLE_SITES
            , $part
        );

        $title = $this->getDbAdapter()->query($sql, array())->current();

        return $title->title;
    }

    /**
     * Return result as db statement
     * @param MenuitemInterface $menu
     * @return \Zend\Db\Adapter\Driver\ResultInterface
     */
    public function getMenuItems(Site\Menu\MenuitemInterface $menu)
    {
        $sql = sprintf("SELECT (SELECT COUNT(mm.menu_id) FROM cms_menu mm WHERE mm.parent = m.menu_id) AS subitems, s.site_id,s.link, s.title, m.*
            FROM %s m
            LEFT JOIN %s s ON (m.site_id = s.site_id)
			WHERE parent = %d AND m.type = %d;"
            , S_TABLE_MENU
            , S_TABLE_SITES
            , $menu->menu_id
            , ($menu->getSite()->getSiteParentId())?$menu->getSite()->getSiteParentId():1
        );
//var_dump($sql);
        $statement = self::$db->createStatement($sql);
        return $statement->execute();
    }

    public function getMenu($menuId = null){
        $sql = sprintf("SELECT (SELECT COUNT(mm.menu_id) FROM cms_menu mm WHERE mm.parent = m.menu_id) AS subitems, m.* FROM %s m
			WHERE m.menu_id = %d
			LIMIT 1;"
            , S_TABLE_MENU
            , (int)$menuId
        );
//        var_dump($sql);
        $stmt = self::$db->createStatement($sql);
        return $stmt->execute();
    }


}
