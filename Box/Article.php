<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 06/07/2018
 * Time: 12:06
 */

namespace Wbengine\Box;


class Article extends WbengineStaticBox
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
    public function getArticleBox()
    {
        $this->article = $this->getArticleModel($this)->getArticleRow();

        $tmplate =  $this->getStaticBoxTemplatePath(self::BOX_ARTICLE);

        $story = $this->getRenderer()->render($tmplate, $this->article, true);

        $this->getArticleModel($this)->updateViews($this->getArticleId());
        return $story;
    }
}