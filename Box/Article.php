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
    
    /** Render a copy while preserving original source and per-field fallback formats. */
    private function formattedArticle()
    {
        $source = $this->getArticle();
        if (!is_object($source)) { return $source; }
        $article = clone $source;
        $format = $article->content_format ?? 'legacy_html';
        if (!in_array($format, ['legacy_html', 'markdown'], true)) { throw new \UnexpectedValueException('Unknown article content format.'); }
        foreach (['introtext', 'bodytext'] as $field) {
            $fieldFormat = $article->{$field . '_format'} ?? $format;
            if (!in_array($fieldFormat, ['legacy_html', 'markdown'], true)) { throw new \UnexpectedValueException('Unknown article field content format.'); }
            if ($fieldFormat === 'markdown') {
                $article->$field = $this->getParent()->getSite()->getParent()->getMarkdown()->render($article->$field ?? '');
            }
        }
        return $article;
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
        // The labels of the template, in the language of the request.
        $this->getRenderer()->assign('t', array(
            'author' => \Wbengine\I18n::t('wb.article.author'),
            'source' => \Wbengine\I18n::t('wb.article.source'),
        ));
        return $this->getRenderer()->render(
            $this->getStaticBoxTemplatePath(self::BOX_ARTICLE), $this->formattedArticle(), true);
    }
}