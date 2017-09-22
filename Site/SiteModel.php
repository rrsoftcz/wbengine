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

namespace Wbengine\Site;

use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Site;
use Wbengine\Model\ModelAbstract;

//use Wbengine\Model;
//require_once 'Wbengine/Model/Abstract.php';

class SiteModel extends ModelAbstract
{


    public function __construct(Site $site)
    {
//        $this->db = $site->getParent()->getDbAdapter();
//	parent::__construct($site);
    }


    /**
     * Return cleaned url.
     * If url stricted mode is "TRUE" then all possible
     * combinations with all sections well returned.
     *
     * @param string $link
     * @param boolean $strict
     * @return string
     */
    public function getUrlId($link, $strict = FALSE)
    {
        if ($strict == TRUE) {
            return $link;
        } else {

            $tmp = "";
            $pices = explode('/', trim($link, '/'));

            // Here we create all url combination when trict mode is off
            foreach ($pices as $p) {
                $b = $p;
                $a = (empty($c)) ? $b : $c . "/" . $b;
                $c = $a;

                // just put slashes to ends of each url part ..
                $a = preg_replace('/^(.*)$/', "/$1/", $a);

                $tmp .= (empty($tmp)) ? $a : "','" . $a;
            }
            $urlId = $tmp;
        }

        return $urlId;
    }


    /**
     * This public method return all site data
     * from database by current url.
     *
     * @param \Wbengine\Site|\Wbengine\Site\Class_Site $site
     * @return array
     */
    public function loadSiteData(Site $site)
    {
        $sql = sprintf("SELECT * FROM %s AS site
                        WHERE (
                        (link IN ('%s') AND strict = 0)
                        OR (link = '%s' AND strict = 1)
                        ) AND visible = 1
                        ORDER BY site_id DESC LIMIT 1;"
            , S_TABLE_SITES
//            , S_TABLE_SITE_TYPES
            , $this->getUrlId($site->getUrl(), $site->isUrlStrict())
            , $site->getUrl()
        );
//        var_dump($sql);
//	$sql = 'SELECT * FROM cms_sites WHERE (link IN (?) AND strict = 0)
//                        OR (link = ? AND strict = 1)
//                        AND visible = 1
//                        ORDER BY site_id DESC LIMIT 1;';
//	die(__CLASS__ . __METHOD__);
//	$db = new Zend\Db\Table("cms_sites");
//        var_dump($sql);
//	$xxx = $this->getDbAdapter()->guery($sql);
//	var_dump($this->getDbAdapter()->fetch($sql));
//	$x = $this->getUrlId($site->getUrl(), $site->isUrlStrict());
//	$x = $this->getDbAdapter()->query($sql, array(
//	    $this->getUrlId($site->getUrl(), $site->isUrlStrict()),
//	    $site->getUrl()));


        $statement = $this->getDbAdapter()->query($sql);

//        /** @var $results Zend\Db\ResultSet\ResultSet */
        $results = $statement->execute();

//	$results = $x->execute();
//	var_dump($results->current());
        return $results->current();
//	return $this->getDbAdapter()->query($sql, array(
//		    $this->getUrlId($site->getUrl(), $site->isUrlStrict()),
//		    $site->getUrl()));
//	var_dump($result);
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
//        var_dump($title->title);
        return $title->title;
    }


    public function getSiteTypeKey($site)
    {
        $sql = sprintf("SELECT t.type_id, t.name, t.key FROM %s AS t
			WHERE t.type_id='%d' LIMIT 1;"
            , S_TABLE_SITE_TYPES
            , ($site->getSiteParentId())?$site->getSiteParentId():1
        );

        $title = $this->getDbAdapter()->query($sql, array())->current();
//        var_dump($site->getSiteId());die($sql);
        return $title->key;
    }


    /**
     * Return site submenus as assoc array.
     *
     * @param \Wbengine\Site|\Wbengine\Site\Class_Site $site
     * @return array
     */
    public function getMenu(Site $site)
    {
        $menu = array();

        $sql = sprintf('SELECT m.*, s.link FROM %s m
                        LEFT JOIN %s s ON (s.site_id = m.site_id)
                        WHERE m.visible = 1
                        AND m.type = %s
                        ORDER BY m.order ASC;'
            , S_TABLE_MENU
            , S_TABLE_SITES
            , ($site->getSiteParentId())?$site->getSiteParentId():1
        );
//        var_dump($sql);
//var_dump($site->getSiteParentId());
//        var_dump($site->getSiteParentId());die($site->getSiteParentId());
        $statement = $this->getDbAdapter()->createStatement($sql);
        $result = $statement->execute();

        $con = $site->getParent();
//	$x = $this->getDbAdapter()->prepare($sql);
//	var_dump($site->getParent()->getAppType());
//	$menuItems = $this->getDbAdapter()->query($sql);

        foreach ($result as $row) {

            if ($site->isMenuSelected($row['site_id'])) {
                $row['selected'] = 'selected';
            }
            $menu[$row['menu_id']]['name'] = $row['name'];
            $menu[$row['menu_id']]['url'] = "/" . trim($row['link'], "/") . "/";
//	    $menu[$row['menu_id']]['url'] = "/".$row['link'];
            $menu[$row['menu_id']]['description'] = $row['description'];
            $menu[$row['menu_id']]['selected'] = $row['selected'];

            if ((int)$row['site_id'] === 0){
                if(empty($row['dyna_tag']) == true){
                    $menu[$row['menu_id']]['url'] .= strtolower(Utils::createSeo($row['name']) ."/");
                }else{
                    $menu[$row['menu_id']]['url'] .= strtolower($row['dyna_tag'])."/";
                }
            }
        }

        return $menu;
    }


    /**
     * Return site submenus as assoc array
     * depending up to main menu.
     *
     * @param \Wbengine\Site|\Wbengine\Site\Class_Site $site
     * @return array
     */
    public function getSubMenu(Site $site)
    {
        $submenu = array();
        $part = $site->getUrlParts();
        $where = array($site->getSiteId());
        $sql = sprintf("SELECT s.*, si.link AS url FROM %s s
			LEFT JOIN %s m ON (m.menu_id = s.menu_id)
                        LEFT JOIN %s si ON (s.site_id = si.site_id)
                        WHERE locale = %d
                        AND s.visible = 1
                        AND s.parent = 0
                        AND (m.site_id = %d
                        OR m.site_id = %d
                        OR s.menu_id = (SELECT menu_id FROM cms_submenu
                        WHERE site_id = ?))
                        ORDER BY s.menuorder ASC;"
            , S_TABLE_SUBMENU
            , S_TABLE_MENU
            , S_TABLE_SITES
            , $site->getSessionValue('user_locale')
            , $site->getSiteId()
            , $site->getSiteParentId()
            , $site->getSiteId()
        );
//var_dump($site->getSessionValue());
//        var_dump($site->getSession());
//var_dump($site->getSession()->getValue("user_locale"));
        $res = $this->getDbAdapter()->query($sql, $where);
//var_dump($res->fetchAll());
        foreach ($res as $row) {
            if ((int)$row['site_id'] === $site->getSiteId()) {
                $row['selected'] = 'selected';
            }

            if ($this->getMenuSubitems($site, $row['submenu_id']) && (int)$row['site_id'] !== $site->getSiteId()) {
                $row['selected'] = 'sel_down';
            }

            if ($this->getMenuSubitems($site, $row['submenu_id']) && (int)$row['site_id'] === $site->getSiteId()) {
                $row['selected'] = 'selected noborder';
            }

            $submenu[$row['submenu_id']]['name'] = $row['title'];
            $submenu[$row['submenu_id']]['url'] = $site->getHomeUrl() . $row['url'];
            $submenu[$row['submenu_id']]['selected'] = $row['selected'];
            $submenu[$row['submenu_id']]['site_id'] = (int)$row['site_id'];
//var_dump($this->getMenuSubitems($site, $row['submenu_id']));
            $submenu[$row['submenu_id']]['menuitems'] = $this->getMenuSubitems($site, $row['submenu_id']);
        }

        return $submenu;
    }


    /**
     * Return site submenus as assoc array
     * depending up to main menu.
     *
     * @param \Wbengine\Site|\Wbengine\Site\Class_Site $site
     * @param integer $submenuId
     * @return array
     */
    public function getMenuSubitems(Site $site, $submenuId)
    {
        $submenu = array();

        $where = array($submenuId, $site->getSiteParentId(), $submenuId, $site->getSiteId(), 1);

        $sql = sprintf("SELECT s1.*, si.link AS url FROM %s s1
                        LEFT JOIN %s s2 ON (s1.parent = s2.submenu_id)
                        LEFT JOIN %s si ON (s1.site_id = si.site_id)
                        WHERE (s1.parent = ? AND s2.site_id = ? AND s1.visible = 1)
                        OR (s1.parent = ? AND s2.site_id = ?)
                        AND s1.visible = ?
                        ORDER BY s1.menuorder ASC;"
            , S_TABLE_SUBMENU
            , S_TABLE_SUBMENU
            , S_TABLE_SITES
        );

        $res = $this->getDbAdapter()->query($sql, $where);
//var_dump($res->current());
        return $res->toArray();
//        var_dump($res->current());
        return ($res)
            ? $res->current()
            : null;

//        return $res;
    }


    /**
     * Check if box for given site ID exists.
     *
     * @param integer $siteId
     * @return boolean
     */
    public function isBoxExist($siteId)
    {
        $db = $this->getDbAdapter();

        $sql = sprintf('SELECT COUNT(box_id)AS count
			FROM %s
			WHERE site_id = %d;'
            , S_TABLE_BOX_ORDERS
            , $siteId
        );

        $res = $db->sql_query($sql);
        $row = $db->sql_fetchrow($res);

        return ($row['count'] > 0) ? TRUE : FALSE;
    }


    /**
     * Return site ID from given URL.
     *
     * @param string $url
     * @return integer
     */
    public function getSiteIdByUrl($url)
    {
        $sql = sprintf("SELECT site_id FROM %s
			WHERE link='%s'
			LIMIT 1;"
            , S_TABLE_SITES
            , $url
        );

        return (int)$this->getDbAdapter()->query($sql)->site_id;
    }


    /**
     * Return data from given section.
     *
     * @param \Wbengine\Site|\Wbengine\Site\Class_Site $site
     * @param integer $section_id
     * @return array
     */
    public function getSectionsContent(Site $site, $section_id = NULL)
    {
        $sql = sprintf("SELECT box.module, box.method, sec.key, box.static
			FROM %s ord
			LEFT JOIN %s box ON (box.id = ord.box_id)
                        LEFT JOIN %s sec ON (box.section_id = sec.section_id)
			WHERE (ord.site_id = %s OR box.shared = 1)
                        AND box.section_id = %d
                        GROUP BY box.id
			ORDER BY ord.order ASC;"
            , S_TABLE_BOX_ORDERS
            , S_TABLE_BOXES
            , S_TABLE_SECTIONS
            , $site->getSiteId()
            , (int)$section_id
        );

        $res = $this->getDbAdapter()->query($sql);

        return $res->fetchAll();
    }

}
