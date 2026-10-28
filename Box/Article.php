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
        foreach (self::sourceView($article->source ?? null) as $key => $value) { $article->$key = $value; }
        $article->byline = '' !== trim((string) ($article->author ?? '')) || null !== $article->source_text;
        return $article;
    }

    /**
     * Describe the free text article source for output: plain text, or a link when it is
     * safe to link (an http or https URL, a local absolute path or a fragment). Any other
     * scheme, such as javascript: or data:, is only ever shown as escaped text.
     *
     * @return array{source_text: ?string, source_href: ?string, source_external: bool}
     */
    public static function sourceView($source): array
    {
        $text = is_string($source) ? trim($source) : '';
        if ('' === $text) { return ['source_text' => null, 'source_href' => null, 'source_external' => false]; }
        $href = null;
        $external = false;
        if (!preg_match('/[\\\\\x00-\x20\x7f]/', $text)) {
            if (in_array(strtolower((string) parse_url($text, PHP_URL_SCHEME)), ['http', 'https'], true) && false !== filter_var($text, FILTER_VALIDATE_URL)) {
                $href = $text;
                $external = true;
            } elseif ((str_starts_with($text, '/') && !str_starts_with($text, '//')) || str_starts_with($text, '#')) {
                $href = $text;
            }
        }
        return ['source_text' => $text, 'source_href' => $href, 'source_external' => $external];
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