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
use Wbengine\Config;
use Wbengine\Counter\BatchCounter;
use Wbengine\Db;
use Wbengine\I18n\ContentTranslations;
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
    public function getArticleRow($updateView = false)
    {
        $sql = sprintf("SELECT a.*
                        FROM %s a
                        WHERE  site_id = ?%s LIMIT 1;"
            ,S_TABLE_ARTICLES
            ,\Wbengine\Content\ArticleVisibility::isSupported() ? ' AND hidden = 0' : ''
        );

	    $article = ContentTranslations::overlayRow('article', Db::fetchObject($sql, array((int)$this->getSiteId($this))));

	    if($updateView === true && is_object($article)){
	        $this->updateViews($article->id);
        }

        return $article;
    }

    /**
     * Update blog view in article table...
     * @param BoxTemplate $box
     * @return \mysqli_result
     * @throws Db\Exception\DbException
     */
    Public function updateViews($id)
    {
        $id = (int)$id;
        $application = method_exists($this->site, 'getParent') ? $this->site->getParent() : null;
        if (is_object($application) && method_exists($application, 'getAnalyticsTracker')) {
            $application->getAnalyticsTracker()->record($this->site->getSiteId(), 'article', $id);
        }
        $settings = Config::getViewCounterSettings();
        $write = function ($count) use ($id) {
            Db::query(sprintf("UPDATE %s SET views = views + ? WHERE id = ?;", S_TABLE_ARTICLES), array((int)$count, $id));
        };

        if (!$settings['enabled']) {
            $write(1);
            return true;
        }

        (new BatchCounter($settings['dir']))->increment('article:' . $id, $settings['hits'], $settings['seconds'], $write);
        return true;
    }
}
