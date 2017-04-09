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

use Wbengine\Box\BoxTemplate;

class Auth extends BoxTemplate
{


    /**
     * Return story content from table article
     * @return string
     */
    public function getLoginBox()
    {
//        $row = $this->getModel(__CLASS__)->getArticleRow();
//        $this->getRenderer()->assign(locale, $this->getSite()->getParent()->getLocale()->getAllKeywords());

//        $this->getModel(__CLASS__)->updateViews($row->id);
//        $string = rand(1388579140,1420115140);
//        var_dump($string);
//var_dump($this->getSite()->getParent()->getLocale()->getAllKeywords());
        return $this->getRenderer()->render('auth_login', $this->getSite()->getParent()->getLocale()->getAllKeywords());
    }


    public function getStoryRecentBox($count)
    {
        if (!$count) $count = 3;

        $articles = $this->getModel(__CLASS__)->getRecentStories($count);
//        var_dump($articles);
        return $this->getRenderer()->render('Central/story_recent_box', $articles);
    }

}