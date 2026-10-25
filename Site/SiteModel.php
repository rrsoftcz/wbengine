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
use Wbengine\Cache;
use Wbengine\I18n\ContentTranslations;
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

        return Cache::remember('sections:active', function () use ($sql) {
            return Db::fetchAllAssoc($sql);
        }, null, true);
    }

    /**
     * Return all url candidates for the given link.
     * If url stricted mode is "TRUE" then only the link itself is returned,
     * otherwise all possible combinations ("/a/", "/a/b/", ...) are returned.
     *
     * @param string $link
     * @param boolean $strict
     * @return array
     */
    public function getUrlCandidates($link, $strict = FALSE)
    {
        if ($strict == TRUE) {
            return array($link);
        }

        $candidates = array();
        $path = "";
        foreach (explode('/', trim($link, '/')) as $piece) {
            $path = ($path === "") ? $piece : $path . "/" . $piece;
            $candidates[] = "/" . $path . "/";
        }
        return $candidates;
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
        $candidates = $this->getUrlCandidates($site->getUrl(), $site->isUrlStrict());

        if (Cache::isEnabled()) {
            return ContentTranslations::overlayRow('site', $this->_findVisibleSite($candidates, (string)$site->getUrl()));
        }

        $sql = sprintf("SELECT * FROM %s AS site
                        WHERE (
                        (link IN (%s) AND strict = 0)
                        OR (link = ? AND strict = 1)
                        ) AND visible = 1
                        ORDER BY site_id DESC LIMIT 1;"
            , S_TABLE_SITES
            , implode(',', array_fill(0, count($candidates), '?'))
        );
        return ContentTranslations::overlayRow('site', Db::fetchAssoc($sql, array_merge($candidates, array((string)$site->getUrl()))));
    }


    /**
     * Same lookup as loadSiteData(), done in PHP on the cached table of visible sites.
     * The whole table is cached once (not one entry per URL), so requests for random
     * URLs cannot grow the cache. Links are compared case-insensitively, as the
     * database collation does.
     *
     * @param array $candidates
     * @param string $url
     * @return array|null
     */
    private function _findVisibleSite(array $candidates, $url)
    {
        $sites = Cache::remember('sites:visible', function () {
            return Db::fetchAllAssoc(sprintf('SELECT * FROM %s WHERE visible = 1;', S_TABLE_SITES));
        });

        $wanted = array_map('mb_strtolower', $candidates);
        $url = mb_strtolower($url);
        $found = null;

        foreach ((array)$sites as $row) {
            if (null === $row['strict']) {
                continue;
            }
            $link = mb_strtolower((string)$row['link']);
            $matches = (0 === (int)$row['strict'])
                ? in_array($link, $wanted, true)
                : $link === $url;

            if ($matches && (null === $found || $row['site_id'] > $found['site_id'])) {
                $found = $row;
            }
        }
        return $found;
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
        $sql = sprintf("SELECT title FROM %s WHERE link = ? LIMIT 1;", S_TABLE_SITES);
        $_res = Db::fetchObject($sql, array(($part) ? "/" . $part . "/" : "/"));
        return (is_object($_res))
            ? $_res->title
            : null;

    }


    public function getSiteTypeKey($site)
    {
        $sql = sprintf("SELECT t.type_id, t.name, t.key FROM %s AS t
			WHERE t.type_id = ? LIMIT 1;"
            , S_TABLE_SITE_TYPES
        );

        $type = Db::fetchObject($sql, array(($site->getSiteParentId()) ? (int)$site->getSiteParentId() : 1));
        return $type ? $type->key : null;
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
                        AND m.type = ?
                        ORDER BY m.order ASC;'
            , S_TABLE_MENU
            , S_TABLE_SITES
        );

        $type = ($site->getSiteParentId()) ? (int)$site->getSiteParentId() : 1;

        $menu = Cache::remember('menu:' . $type, function () use ($sql, $type) {
            return Db::fetchAllAssoc($sql, array($type));
        }, null, true);

        // The cache holds the default language, the names are translated per request.
        return ContentTranslations::overlayRows('menu', (array) $menu);
    }


    /**
     * Check if box for given site ID exists.
     *
     * @param integer $siteId
     * @return boolean
     */
    public function isBoxExist($siteId)
    {
        $sql = sprintf('SELECT COUNT(box_id) AS count FROM %s WHERE site_id = ?;', S_TABLE_BOX_ORDERS);
        $row = Db::fetchAssoc($sql, array((int)$siteId));

        return ($row['count'] > 0) ? TRUE : FALSE;
    }


    /**
     * Resolve exact URLs in one statement, keeping column collation and input order.
     * Includes hidden/strict sites, as the legacy menu lookup does. No URL-keyed cache.
     * @return int[] One ID per input value, or zero when no row matches.
     */
    public function getSiteIdsByUrls(array $urls): array
    {
        if ([] === $urls) {
            return [];
        }
        $queries = $params = [];
        foreach (array_values($urls) as $position => $url) {
            $queries[] = sprintf('SELECT %d AS request_index, (SELECT MIN(site_id) FROM %s WHERE link = ?) AS site_id',
                $position, S_TABLE_SITES);
            $params[] = (string) $url;
        }
        $rows = Db::fetchAllAssoc(implode(' UNION ALL ', $queries), $params);
        $ids = array_fill(0, count($urls), 0);
        foreach ($rows as $row) {
            $ids[(int) $row['request_index']] = (int) $row['site_id'];
        }
        return $ids;
    }

    /**
     * Return site ID from given URL.
     *
     * @param string $url
     * @return integer
     */
    public function getSiteIdByUrl($url)
    {
        return $this->getSiteIdsByUrls([(string) $url])[0];
    }


}
