<?php

/**
 * $Id: Site.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Site main class.
 *
 * @package RRsoft-CMS * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine;

use Wbengine\Application\Application;
use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Components\ComponentParentInterface;
use Wbengine\Site\SiteException;
use Wbengine\Site\SiteModel;

Class Site implements ComponentParentInterface
{

    /**
     * Include all loaded site data
     * @var array
     */
    public $_resource = array();


    /**
     * Stored  Url object
     * @var Url
     */
    private $_classUrl;


    /**
     * Indicate that given URL is strict or dynamic
     * @var boolean
     */
    private $_urlStrict;


    /**
     * The collection of existing sections
     * @var array
     */
    private $_sections;


    /**
     * Return cite parent
     * @var Application
     */
    public $_parent;


    /**
     * Return site's model
     * @var SiteModel
     */
    private $_model;


    /**
     * Class site exception object
     * @exception SiteException
     */
    public $_exception;


    /**
     * Site menu collection
     * @var array
     */
    public $_menus;


    /**
     * In constructor we parse URL and create all
     * needed variables.
     */
    public function __construct(ComponentParentInterface $parent = null){
        $this->_parent = $parent;
    }


    public function get($name, $default = null)
    {
        //@todo: craete exception when not value and not default set
        if (!$this->_resource) {
            return $default;
        }
        if (array_key_exists($name, $this->_resource)) {
            return $this->_resource[$name];
        }

        return $default;
    }


    public function __get($name)
    {
        return $this->get($name);
    }


    public function __set($name, $value)
    {
        if (null === $name) {
            $this->_resource[] = $value;
        } else {
            $this->_resource[$name] = $value;
        }

        $this->count++;
    }


    public function getSite(){
        return $this;
    }

    /**
     * Return a parent object Application
     * @return Application
     */
    public function getParent(){
        return $this->_parent;
    }

    public function getSession(){
        return $this->_getSession();
    }

    private function _getSession(){
        return $this->getParent()->getSession();
    }

    public function getValueFromSession($name){
        return $this->_getSession()->getValue($name);
    }

    public function setValueToSession($name, $value){
        $this->_getSession()->setValue($name, $value);
    }

    public function getSessionValue($name){
        return $this->_getSession()->getValue($name);
    }


    public function getRenderer()
    {
        return $this->getParent()->getRenderer();
    }

    /**
     * Return instance of Wbengine Class Url
     * @return Url
     */
    public function getClassUrl()
    {
        if ($this->_classUrl instanceof Url) {
            return $this->_classUrl;
        } else {
            return $this->_classUrl = new Url($this->getParent());
        }
    }

    /**
     * Retutn Sites fill URL
     * @return string
     */
    public function getUrl()
    {
        return $this->getClassUrl()->getUrl();
    }

    /**
     * Return exploded url as each parts.
     * @return array
     */
    public function getUrlParts()
    {
        return $this->getClassUrl()->getUrlParts();
    }

    /**
     * Return posted params from site url.
     * @return array
     */
    public function getUrlParams()
    {
        return $this->getClassUrl()->getUrlParams();
    }

    /**
     * Return all url parts as full url path.
     * We need this for site navigation.
     * @return array
     */
    public function getUrlPairs()
    {
        return $this->getClassUrl()->getUrlPairs();
    }

    /**
     * Return relevant site link.
     * @return string
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * Return site's ID
     * @return integer
     */
    public function getSiteId()
    {
        return (int)$this->site_id;
    }

    /**
     * Return site's Parent ID (sitetype DB)
     * @return integer
     */
    public function getSiteParentId()
    {
        return (int)$this->parent_id;
    }

    /**
     * Return site's HTML meta title.
     * @return string
     */
    public function getHtmlTitle()
    {
        return (string)$this->html_title;
    }

    /**
     * Return site's HTML meta description.
     * @return string
     */
    public function getHtmlDescription()
    {
        return (string)$this->html_description;
    }

    /**
     * Return if site URl is stricted or dynamic.
     * @return boolean
     */
    public function getIsUrlStrict()
    {
        return (boolean)$this->strict;
    }

    /**
     * Return site's meta keywords.
     * @return string
     */
    public function getHtmlKeywords()
    {
        return (string)$this->html_keywords;
    }

    /**
     * Return site data model
     * @return \Wbengine\Site\SiteModel
     */
    public function getModel()
    {
        if (NULL === $this->_model) {
            $this->setModel();
        }

        return $this->_model;
    }

    /**
     * Set data model if needed
     * @see Class_Site_Model
     */
    private function setModel()
    {
        $this->_model = new SiteModel();
    }

    /**
     * Return state of loaded site resource
     * @return bool
     */
    public function isLoaded(){
        return (empty($this->_resource) || null === $this->_resource)? false:true;
    }

    /**
     * Load and set site data to local variable
     * for a latest use.
     */
    private function loadSiteResource()
    {
        $this->_resource = $this->getModel()->loadSiteData($this);
        return $this;
    }

    public function setVariable($name, $value = null, $parent = null){
        $this->getParent()->getClassVars()->addValue($name, $value, $parent);
    }

    /**
     * Set HTML title to site object.
     * @param string $htmlTitle
     */
    public function setHtmlTitle($htmlTitle)
    {
        $this->html_title = $htmlTitle;
    }


    /**
     * Set HTML description to site object.
     * @param string $htmlDescription
     */
    public function setHtmlDescription($htmlDescription)
    {
        $this->html_description = $htmlDescription;
    }


    /**
     * Set HTML keywords to site object.
     * @param string $htmlKeywords
     */
    public function setHtmlKeywords($htmlKeywords)
    {
        $this->html_keywords = $htmlKeywords;
    }


    /**
     * Create URL redirection from url FROM to url TO.
     * @param string $from
     * @param string $to
     */
    public function addSiteRedirection($from, $to)
    {
        $this->_redirections[$from] = $to;
    }


    public function getMenuCollection(){
        if($this->_menus && is_array($this->_menus)){
            return $this->_menus;
        }

        $menus = $this->getModel()->getSiteMenu($this);

        if(is_array($menus)){
            foreach ($menus as $menu){
                $_menu = new Menu($menu);
                $_menu->setSite($this);
                $this->_menus[] = $_menu;
            }
        }
        return $this->_menus;
    }


    /**
     * Return associated array with URL parts
     * paired with site ID.
     *
     * @return array
     */
    public function getUrlPairsWithSiteId()
    {
        $_tmp = null;
        $pairs = $this->getUrlPairs();

        if (FALSE == is_array($pairs))
            return null;

        foreach ($pairs as $pair) {
            $_tmp[] = array(
                'url' => $pair,
                'site_id' => $this->getModel()->getSiteIdByUrl($pair));
        }
        return $_tmp;
    }


    /**
     * Returns TRUE or FALSE, unless the given site ID
     * is a parents existing submenu or menuitem.
     *
     * @param integer $siteId
     * @return boolean
     */
    public function isMenuSelected($siteId = NULL)
    {
        if (NULL === $siteId)
            return FALSE;

        if ((int)$siteId == $this->getSiteId())
            return TRUE;

        $_pairs = $this->getUrlPairsWithSiteId();

        foreach ($_pairs as $pair => $value) {
            if ($value['site_id'] == $siteId)
                return TRUE;
        }

        return FALSE;
    }


    /**
     * Return apropirate HTML class name due
     * to given URL
     * @return string
     */
    public function getTemplateClassSurfix()
    {
        return ($this->getUrl() === '/') ? FRONT_SURFIX_CLASS_NAME : "";
    }

    /**
     * Return appropriate HTML title by given URL part
     * @param string $part
     * @return string
     */
    public function getTitleFromLink($part)
    {
        return $this->getModel()->getTitleByUrl($part);
    }

    /**
     * Return boolena value due site url is grouped.
     * @return boolean
     */
    public function isUrlStrict()
    {
        return $this->_urlStrict;
    }

    /**
     * Return site's menu
     * @return array
     */
    public function getMenu()
    {
        return $this->getModel()->getSiteMenu($this);
    }

    /**
     * Return site's menu
     * @return array
     */
    public function getSiteTypeKey()
    {
        return $this->getModel()->getSiteTypeKey($this);
    }


    /**
     * Return Site home URL with default protocol
     * @return string
     */
    public function getHomeUrl(){
        return preg_replace('/[^a-z](.*)/', '://' . $_SERVER['HTTP_HOST']
            , strtolower($_SERVER['SERVER_PROTOCOL']));
    }


    /**
     * Return CMS member object Exception
     * @return SiteException
     */
    public function getException(){
        return $this->_exception;
    }

    private function _setSections($sections)
    {
        if (sizeof($sections) === 0) {
            return null;
        }

        /**
         * @var $section Section
         */
        foreach ($sections as $section) {
            $this->setVariable($section->getKey(), $section->getContent($this));
        }
    }

    public function getSections()
    {
        if($this->_sections && is_array($this->_sections)){
            return $this->_sections;
        }

        $sections = $this->getModel()->getSections();

        if(is_array($sections)) {
            foreach ($sections as $section) {
                $this->_sections[] = new Section($section, $this);
            }
        }
        return $this->_sections;
    }

    public function getUser(){
    	return $this->getParent()->getClassUser();
    }

    public function isUserLogged(){
        return $this->getParent()->isUserLogged();
    }

    /**
     * This method load all needed data from model and assign
     * all needed variables to local array.
     * @param Application $app
     * @return $this
     */
    public function initialize($app)
    {
        $this->_parent = $app;

        // Try to load site properties from db by given url...
        $this->loadSiteResource();

        if((int)$this->acl >= 1){
            if((int)$this->getSessionValue('user_is_logged') === 0){
                header("Location: /login/");exit();
            }
        }

        // Save some essential variables for lates use in templates...
        $this->setVariable('menu', $this->getMenuCollection());
        $this->setVariable('title', $this->getHtmlTitle(), 'meta');
        $this->setVariable('description', $this->getHtmlDescription(), 'meta');
        $this->setVariable('keywords', $this->getHtmlKeywords(), 'meta');
        $this->setVariable('minimizeCss', (Config::minimizeCss())?'.min':'', 'meta');

        $this->_setSections($this->getSections());

        return $this;
    }

}
