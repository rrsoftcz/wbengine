<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 06/07/2018
 * Time: 12:06
 */

namespace Wbengine\Box;


use Wbengine\Box\Article\ArticleModel;

class Article extends WbengineStaticBox
{

    public $article;
	private $_model;
	private $_updateView = true;

	private Function _loadBySiteId(){
		return $this->article = $this->getModel()->getArticleRow($this->_updateView);
	}

	private function _setModel(){
		return $this->_model = new ArticleModel($this);
	}

	public function getArticleId(){
        return ($this->article) ? $this->article->id : $this->_loadBySiteId()->id;
    }

	public function getArticleSiteId(){
        return ($this->article) ? $this->article->site_id : $this->_loadBySiteId()->site_id;
    }

	public function getArticle(){
    	return ($this->article) ? $this->article : $this->_loadBySiteId();
    }

    public function getModel(){
    	return ($this->_model && $this->_model instanceof ArticleModel) ? $this->_model : $this->_setModel();
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
        return $this->getRenderer()->render(
        	$this->getStaticBoxTemplatePath(self::BOX_ARTICLE), $this->getArticle(), true);
    }
}