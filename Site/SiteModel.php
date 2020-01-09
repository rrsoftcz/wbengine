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

use Wbengine\Application\ApplicationException;
use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Db;
use Wbengine\Session;
use Wbengine\Site;
use Wbengine\Model\ModelAbstract;

//use Wbengine\Model;
//require_once 'Wbengine/Model/Abstract.php';

class SiteModel extends ModelAbstract
{

    /**
     * Return raw DB version as number
     * @return mixed
     */
    public function getDBVersion() {
        $version = Db::getAdapter()->getDbVersion();
        $major = round($version /10000);
        $minor = round(($version/100) - ($major*100));
        $sub = ($version - $major * 10000) - ($minor*100);

        return sprintf("%d.%d.%d", $major, $minor, $sub);
    }

    /**
     * Return site sections as assoc array.
     * @return array
     */
    public function getSections()
    {
        $sql = sprintf("SELECT s.section_id,s.title,s.description,s.active,s.key,s.return_error_code 
                FROM %s AS s
                WHERE active = 1;",
            S_TABLE_SECTIONS
        );

        return Db::fetchAllAssoc($sql);
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
            , $this->getUrlId($site->getUrl(), $site->isUrlStrict())
            , $site->getUrl()
        );
        return Db::fetchAssoc($sql);
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
			WHERE link='%s'
			LIMIT 1;"
            , S_TABLE_SITES
            , ($part)?"/".$part."/":"/"
        );
        $_res = Db::fetchObject($sql);
        return (is_object($_res))
            ? $_res->title
            : null;

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
    public function getSiteMenu(Site $site)
    {
        $sql = sprintf('SELECT m.menu_id, m.site_id, m.name, m.type, m.description, m.visible, m.order, m.parent, s.link FROM %s m
                        LEFT JOIN %s s ON (s.site_id = m.site_id)
                        WHERE m.visible = 1
                        AND m.parent = 0
                        AND m.type = %s
                        ORDER BY m.order ASC;'
            , S_TABLE_MENU
            , S_TABLE_SITES
            , ($site->getSiteParentId())?$site->getSiteParentId():1
        );

        return $data = Db::fetchAllAssoc($sql);
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
//        Utils::dump($sql,true);

        return (int)$this->query($sql)->fetch_field()->site_id;
    }


}
