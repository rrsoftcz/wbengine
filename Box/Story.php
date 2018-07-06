<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 06/07/2018
 * Time: 12:06
 */

namespace Wbengine\Box;


class Story extends WbengineStaticBox
{

    public $article;

    public function getArticleId(){
        return ($this->article) ? $this->article->id : null;
    }

    /**
     * Return story content from table article
     *
     * @return string
     * @throws \Wbengine\Box\Exception\BoxException
     * @throws \Wbengine\Exception\RuntimeException
     */
    public function getStory()
    {
        $this->article = $this->getStoryModel($this)->getArticleRow();

        $tmplate =  $this->getStaticBoxTemplatePath(self::BOX_STORY);

        $story = $this->getRenderer()->render($tmplate, $this->article);

        $this->getStoryModel($this)->updateViews($this->getArticleId());
        return $story;
    }
}