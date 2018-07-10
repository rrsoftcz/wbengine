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
namespace Wbengine\Box\Article;

use Wbengine\Box;
use Wbengine\Db;
use Wbengine\Model\ModelAbstract;

class ArticleModel extends ModelAbstract {

    /**
     * Parent site object
     * @var Site
     */
    public $site = NULL;

    /**
     * @var
     */
    public $boxStory;

    /**
     * We do nothink in constructor.
     * @param Story $boxStory
     */
    public function __construct($boxStory)
    {
        $this->site = $boxStory->getParent()->getSite();
        $this->boxStory = $boxStory;
    }


    /**
     * Return story data from db
     * @return \stdClass
     */
    public function getArticleRow()
    {
        $sql = sprintf("SELECT 
                          a.introtext, 
                          a.bodytext, 
                          a.id, 
                          a.title, 
                          a.published, 
                          a.author, 
                          a.views, 
                          a.source  
                        FROM %s a
                        WHERE  site_id = %d LIMIT 1;"
            ,S_TABLE_ARTICLES
            ,$this->getSiteId($this)
        );
        return Db::fetchObject($sql);
    }

    /**
     * Update blog view in article table...
     * @param BoxTemplate $box
     * @return \mysqli_result
     * @throws Db\Exception\DbException
     */
    Public function updateViews($id)
    {
        $sql = sprintf("UPDATE %s SET views = views + 1 WHERE id = %d;",
            S_TABLE_ARTICLES,
            $id
        );
        return Db::query($sql);
    }
}