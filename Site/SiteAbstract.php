<?php

/**
 * $Id: Site.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Abstract site class
 *
 * @package RRsoft-CMS * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Site;

abstract class SiteAbstract {


    /**
     * Assigned all site variables for renderer
     * @var array
     */
    public $_vars = ARRAY();

    /**
     * Loaded config values
     * @var array
     */
//    private $_config                    = NULL;

    private $_classUrl = NULL;

    private $_model = NULL;



    public function getClassUrl() {
	if (NULL === $this->_classUrl) {
	    require_once 'Class/Site/Url.php';
	    $this->_classUrl = new Class_Site_Url();
	}

	return $this->_classUrl;

    }



//    /**
//     * Return created object renderer
//     * @return Class_Renderer
//     */
//    public function getRenderer()
//    {
//        If (NULL === $this->_renderer) {
//            $this->_setRenderer();
//        }
//
//        return $this->_renderer;
//    }
//    public function getRenderer()
//    {
//	return $this->getSite()->getRenderer();
//    }

    /**
     * Return site data model
     * @return Class_Site_Model
     */
    public function getModel() {
	if (NULL === $this->_model) {
	    $this->_setModel();
	}

	return $this->_model;

    }



//    /**
//     * Return stored configurations as array.
//     * @return array
//     */
//    public function getConfigs()
//    {
//        if (NULL === $this->_config) {
//            $this->_setConfig();
//        }
//
//        return $this->_config;
//    }


    public function getUrl() {
	return $this->getClassUrl()->getUrl();

    }



    public function getUrlParts() {
	return $this->getClassUrl()->getUrlParts();

    }



    public function getUrlPairs() {
	return $this->getClassUrl()->getUrlPairs();

    }



//    /**
//     * Return all assigned site variables.
//     * @return array
//     */
//    public function getVars()
//    {
//        return $this->_vars;
//    }

    /**
     * Return relevatnt site link.
     * @return string
     */
    public function getLink() {
	return $this->_resource['link'];

    }



    /**
     * Return self object
     * @return Class_Site
     */
    public function getSite() {
	return $this;

    }



    /**
     * Display content of all rendered sections.
     */
    public function display() {
	$this->getRenderer()->dispatch($this);

    }



    /**
     * Return site's ID
     * @return integer
     */
    public function getSiteId() {
	return (int) $this->_resource['site_id'];

    }



    /**
     * Return site's parent ID
     * @return integer
     */
    public function getSiteParentId() {
	return (int) $this->_resource['parent_id'];

    }



    /**
     * Return site's HTML meta title.
     * @return string
     */
    public function getHtmlTitle() {
	return (string) $this->_resource['html_title'];

    }



    /**
     * Return site's HTML meta description.
     * @return string
     */
    public function getHtmlDescription() {
	return (string) $this->_resource['html_description'];

    }



    /**
     * Return if site URl is stricted or dynamic.
     * @return boolean
     */
    public function getIsUrlStrict() {
	return (boolean) $this->_resource['strict'];

    }



    /**
     * Return site's meta keywords.
     * @return string
     */
    public function getHtmlKeywords() {
	return (string) $this->_resource['html_keywords'];

    }



    /**
     * Set data model if needed
     * @see Class_Site_Model
     */
    private function _setModel() {
	require_once 'Class/Site/Model.php';
	$this->_model = new Class_Site_Model();

    }



}
