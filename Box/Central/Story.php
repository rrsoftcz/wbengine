<?php

/**
 * $Id: Story.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Class Central Story module class.
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */
//require_once 'Class/Site/Box/Abstract.php';

namespace Wbengine\Box\Central;

use Wbengine\Box\ControllerTemplate;

class Story extends ControllerTemplate
{


    /**
     * Return story content from table article
     * @return string
     */
    public function getStoryBox()
    {
        // get article data...
        $row = $this->getModel(__CLASS__)->getArticleRow();

        // assign vars...
        $this->getRenderer()->assign(url, $this->getSite()->getLink());
        $this->getRenderer()->assign(site_id, $this->getSite()->getSiteId());
        $this->getRenderer()->assign(article, $row);
        $this->getRenderer()->assign(story_box_content, $this->getRenderer()->getFormater()->process($row[bodytext]));

        // update given article statistic info...
        $this->getModel(__CLASS__)->updateViews($row->id);

        return $this->getRenderer()->render('story_box').$this->getStoryRecentBox(3);
    }


    public function getStoryRecentBox($count)
    {
        if (!$count) $count = 3;

        $articles = $this->getModel(__CLASS__)->getRecentStories($count);

        return $this->getRenderer()->render('Central/story_recent_box', $articles);
    }

}