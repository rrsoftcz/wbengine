<?php

/**
 * $Id$ - CLASS
 * --------------------------------------------
 * Section class manage a section content include
 * all boxes.
 *
 * Return self as filled section with content.
 *
 * @package RRsoft-CMS
 * @version $Rev$ $Date$ $Author$
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Box;

use Wbengine\Box\Model;
use Wbengine\Box;

class BoxAbstract {


    /**
     * Model instance
     * @var object
     */
    private $_model = null;

    /**
     * Raw model data
     * @var object
     */
    private $_box = null;

    /**
     * The section raw data from db
     * @var array
     */
    private $_section = null;

    /**
     * Instance of site's renderer
     * @var Class_Renderer
     */
    private $_renderer = null;

    /**
     * The module name
     * @var string
     */
    private $_module = null;

    /**
     * Default box class name prefix
     * @var string
     */
    public $_methodPrefix = 'get';

    /**
     * Default box class name surfix
     * @var string
     */
    public $_methodSurfix = 'Box';

    /**
     * The Box class name
     * @var string
     */
    private $_className = null;

    /**
     * initialy box ID
     * @var integer
     */
    private $_boxId = null;



    /**
     * Just assign parent objects to local variables.
     * @param Class_Site_Section $section
     */
    public function __construct(Box $section)
    {
	$this->_section = $section;
    }



    /**
     * Return Site object
     * @return Class_Site
     */
    public function getSite()
    {
	return $this->getSection()->getSite();
    }



    /**
     * Return and section object
     * @return Class_Site_Section
     */
    public function getSection()
    {
	return $this->_section;
    }



    /**
     * Return and class name
     * @return string
     */
    public function getClassName()
    {
	return $this->_className;
    }



    /**
     * Return section ID
     * @return integer
     */
    public function getSectionId()
    {
	return $this->_box['section_id'];
    }



    /**
     * Return Box name
     * @return string
     */
    public function getBoxName()
    {
	return $this->_box['name'];
    }



    /**
     * Return box module name
     * @return string
     */
    public function getModuleName()
    {
	return $this->_box['module'];
    }



    /**
     * Return method name
     * @return mixed
     */
    public function getMethodName()
    {
	return $this->_box['method'];
    }



    /**
     * Return true/false if section is active
     * @return boolean
     */
    public function getIsStatic()
    {
	return $this->_box['static'];
    }



    /**
     * Return section's error code if defined
     * @return mixed
     */
    public function getIsShared()
    {
	return $this->_box['shared'];
    }



    /**
     * Return section's error code if defined
     * @return mixed
     */
    public function getBoxUrl()
    {
	return $this->_box['link'];
    }



    /**
     * Return box ID
     * @return integer
     */
    public function getBoxId()
    {
	return ((int) $this->_box['id'])
		? $this->_box['id']
		: $this->_boxId;
    }



    /**
     * Return Instance of Site section
     * @param integer $boxId
     * @return Class_Site_Section
     */
    public function getBox($boxId)
    {
	$this->_boxId = $boxId;
	$this->_box = $this->getModel()->getBoxById($this);
	return $this;
    }



    /**
     * Return section model
     * @return Wbengine\Model\ModelAbstract
     */
    public function getModel($namespace)
    {
	if (NULL === $this->_model) {
	    $this->_setModel($namespace);
	}

	return $this->_model;
    }



    /**
     * Return created object renderer
     * @return Class_Renderer
     */
    public function getRenderer()
    {
	If (NULL === $this->_renderer) {
	    $this->_renderer = $this->getSite()->getRenderer();
	}

	return $this->_renderer;
    }



    /**
     * @see Wbengine\Model\ModelAbstract
     */
    private function _setModel($namespace)
    {
	$model = $namespace . "\Model";

	if (class_exists($model, TRUE) === TRUE) {
	    $this->_model = new $model($this);
	} else {
	    throw New Exception\RuntimeException(__METHOD__
	    . ': Can\'t load ' . $model . '!');
	}
    }



    /**
     * Create method name by given box name
     * @param string $method.
     *
     * @return string
     */
    public function _createMethodName($method)
    {
	if (strstr($method, "-")) {
	    $_uparts = explode("-", $method);

	    foreach ($_uparts as $_part) {
		$_tmp .= ucfirst(preg_replace("/[^A-Za-z]/", "", $_part));
	    }

	    return $this->_methodPrefix . ucfirst($_tmp) . $this->_methodSurfix;
	}

	return $this->_methodPrefix . ucfirst($method) . $this->_methodSurfix;
    }



    /**
     * Create and return module box object
     * by given module name.
     *
     * @param string $name
     * @return object
     */
    private function _getModuleBox($section, $name)
    {
	$name = ucfirst($name);

	$this->_className = "Wbengine\Box\\" . ucfirst($section) . "\\" . $name;
//	$_fileName = "Class/Site/Box/" . ucfirst($section) . "/" . $name . ".php";
//	var_dump($this->_className);
//	if ( is_readable($_fileName) ) {
//	    require_once "Class/Site/Box/" . ucfirst($section) . "/" . $name . ".php";
	$this->_module = new $this->_className($this);
	return $this->_module;
//	}
//	return null;
    }



    /**
     * Return parsed (X)HTML content of given box
     * This method call all boxes ssigned to given section
     * and try to get its content.
     *
     * @return string as HTML
     */
    public function getContent()
    {
	$tmp = '';

	$_method = $this->_createMethodName($this->getMethodName());

	$_boxObj = $this->_getModuleBox($this->getSection()->getKey(), $this->getModuleName());
//	var_dump($_boxObj);
	if (!$_boxObj instanceof BoxAbstract) {
	    throw New \Wbengine\Exception\RuntimeException(__METHOD__
	    . ': Given object must be instance of BoxAbstract.');
	}

	if (method_exists($_boxObj, $_method)) {
	    if ((int) $this->getIsStatic() == HTML_STATIC) {
		$tmp .= $_boxObj->$_method($this->getSite());
	    } else {
		$tmp .= $this->getRenderer()->getFormater()->process($_boxObj->$_method($this->getSite()));
	    }
	}

	return $tmp;
    }



}
