<?php

/**
 * $Id: Site.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Site Vars class.
 *
 * @package RRsoft-CMS * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine;

class Vars
{


    /**
     * Array of all assigned variables
     * @var array
     */
    private $_vars = array();

    /**
     * Instance of Class_Cms
     * @var Class_Cms
     */
    private $_cms = NULL;

    private $_site = NULL;


    /**
     * Create array with all variables needed for render site.
     * @param Class_Cms $wbengine
     */
    public function __construct(\Wbengine\Application\Application $wbengine)
    {
        if ($wbengine instanceof \Wbengine\Application\Application) {
            $this->_cms = $wbengine;
            $this->_site = $wbengine->getSite();
        } else {
//	    require_once 'SessionException.php';
            throw new Vars\VarsException(__METHOD__
                . ': Given object is not the instance of Class_Cms.');
        }
    }


    /**
     * Return an instance of Class_Site
     * @return Class_Site
     */
    private function _getCmsObject()
    {
        return $this->_cms;
    }


    /**
     * Return an instance of Class_Site
     * @return \Wbengine\Site
     */
    private function _getSite()
    {
        return $this->_site;
    }


    /**
     * Update CMS member object in local variable
     * @param Class_Cms_Abstract $cms
     */
    public function envUpdate(Class_Cms_Abstract $cms)
    {
        $this->_cms = $cms;
    }


    /**
     * Array collection as instance of Class_Site_Section
     * @param array $sections
     */
    private function _setSections($sections)
    {
        if (sizeof($sections) === 0) {
            return null;
        }

        foreach ($sections as $section) {
//	    var_dump($section->getKey());
//	    var_dump($section->getContent());

            $this->addValue($section->getKey(), $section->getContent());
        }
    }


    /**
     * Add value to Site variables collection.
     * We store all needed variables here for latest use in
     * site renderer.
     *
     * In order you can specify parent key.
     *
     * @param string $key
     * @param mixed $value
     * @param string $parentKey
     */
    public function addValue($key, $value = NULL, $parentKey = NULL)
    {
//	var_dump($key);
//	var_dump($value);
        if (NULL === $key) {
            $this->_addException('The key can not be NULL.', Class_Site_Exception::ERRROR_VALUE_KEY_IS_EMPTY);
        }

        if (!empty($parentKey)) {
            $this->_vars[$parentKey][$key] = $value;
        } else {
            $this->_vars[$key] = $value;
        }
    }


    /**
     * Return allready assigned value
     * by given key.
     * @param string $key
     * @return mixed
     */
    public function getValue($key)
    {
        return $this->_vars[$key];
    }


    /**
     * Return All assigned variables as
     * associative array.
     *
     * @return array
     */
    public function getValues()
    {
//        var_dump($this->_getSite()->getSections());
//        if (NULL === $this->_getCmsObject()->getException()) {
//            $this->_setSections($this->_getSite()->getSections());
//        }
//var_dump( $this->_getSite()->getNavigation());
//        $this->addValue('url', $this->_getSite()->getLink());
////        $this->addValue('top_navigation', $this->_getSite()->getNavigation());
//        $this->addValue('site_home_url', $this->_getSite()->getHomeUrl());
//        $this->addValue('html_surfix', $this->_getSite()->getTemplateClassSurfix());
//        $this->addValue('breadcrump', $this->_getSite()->getNavigation());
//        $this->addValue('menu', $this->_getSite()->getMenu());
//        $this->addValue('submenu', $this->_getSite()->getSubMenu());
//        $this->addValue('site_id', $this->_getSite()->getSiteId());
//        $this->addValue('title', $this->_getSite()->getHtmlTitle(), 'meta');
//        $this->addValue('description', $this->_getSite()->getHtmlDescription(), 'meta');
//        $this->addValue('keywords', $this->_getSite()->getHtmlKeywords(), 'meta');
//        $this->addValue('defaultCssFile', $this->_getSite()->getParent()->getConfig()->getCssCollection(), 'config');
//        $this->addValue('user_data', $this->_getSite()->getParent()->getIdentity());
//        $this->addValue('locale', $this->_getSite()->getParent()->getLocale()->getAllKeywords());
//        $this->addValue('user', $this->_getSite()->getParent()->getIdentity());
////    $this->addValue('produkt_links', $this->_getCmsObject()->getSectionById(3)->getBoxById(49)->getContent());
//        $this->addValue('full_url', $this->_getSite()->getHomeUrl() . "$_SERVER[REQUEST_URI]");

        return $this->_vars;
    }


}
