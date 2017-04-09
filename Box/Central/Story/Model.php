<?php

/**
 * $Id: Model.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Data model of Central Story Class.
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Box\Central\Story;

use Wbengine\Model\ModelAbstract;
use Zend\Db\Sql\Expression;
use Zend\Db\TableGateway\TableGateway;


class Model extends ModelAbstract
{

    /**
     * Parent site object
     * @var Class_site
     */
    private $site = NULL;
    private $table = null;
    /**
     * @var
     */
    private $boxStory;


    /**
     * We do nothink in constructor.
     * @param $boxStory
     */
    public function __construct($boxStory)
    {
        $this->table = new TableGateway(S_TABLE_ARTICLES, $this->getDbAdapter());
        $this->site = $boxStory->getSite();
        $this->boxStory = $boxStory;
    }


    /**
     * Return story data from db
     * @return array
     */
    public function getArticleRow()
    {
        $sql = sprintf("SELECT CONCAT('%s', bodytext) AS bodytext, a.id, a.title, a.published, a.author, a.views, a.source  FROM %s a
                        WHERE  site_id = %d LIMIT 1;"
            ,PHP_EOL
            ,S_TABLE_ARTICLES
            ,$this->site->getSiteId()
        );

        $rowset = $this->getDbAdapter()->query($sql, array());
//        $rowset = $this->table->select($where);
//        $rec = $rowset->current();
//        $rec[introtext] ="hovno";
//        var_dump($rowset->current());
        return $rowset->current();

//        $where = array(
//            'site_id' => $this->site->getSiteId());
//        $rowset = $this->table->select($where);
//
//        return $rowset->current();


    }

    Public function updateViews($id)
    {

        $where = array(
            'id' => $id);

        $this->table->update(array('views' => new Expression('views + 1')),$where);
    }


    public function getRecentStories($count)
    {
        $sql = sprintf("SELECT a.title, a.introtext, s.link FROM %s a
                        LEFT JOIN %s s ON (s.site_id = a.site_id)
                        WHERE a.id NOT IN(SELECT id FROM cms_articles WHERE site_id = %d)
                        ORDER BY a.views DESC LIMIT %d;"
            , S_TABLE_ARTICLES
            , S_TABLE_SITES
            , $this->boxStory->getSite()->getSiteId()
            , $count
        );
//        var_dump($this->boxStory->getBox()->getBoxId());
//        $where = array('views'=>1);
        $where = array();
        $rowset = $this->getDbAdapter()->query($sql, array());
//        $rowset = $this->table->select($where);

//        var_dump($rowset->toArray());
         return $rowset->toArray();

//        foreach ($rowset as $projectRow) {
//            var_dump($projectRow['autor'] . PHP_EOL);
//        }

//        $rows = $this->table->
//        $db = $this->getDbAdapter();
//        $stmt = $db->query('SELECT * FROM cms_articles', $where);
//
//        $rows = $stmt->execute();
//
//        var_dump($rows);
    }
}
