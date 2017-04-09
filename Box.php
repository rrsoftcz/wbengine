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

namespace Wbengine;

use Wbengine\Box\Exception\BoxException;
use Wbengine\Box\Model;

class Box {

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
    public function __construct( \Wbengine\Section $section )
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
	return $this->_box->module;
    }


    /**
     * Return method name
     * @return mixed
     */
    public function getMethodName()
    {
	return $this->_box->method;
    }


    /**
     * Return true/false if section is active
     * @return boolean
     */
    public function isStatic()
    {
	return $this->_box['static'];
    }


    /**
     * Return section's error code if defined
     * @return mixed
     */
    public function isShared()
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
	return ((int) $this->_box['id']) ? $this->_box['id'] : $this->_boxId;
    }


    /**
     * Return Instance of Site section
     * @param integer $boxId
     * @return Class_Site_Section
     */
    public function getBox( $boxId )
    {
	$this->_boxId = $boxId;
	$this->_box = $this->getModel()->getBoxById($this);
	return $this;
    }


    /**
     * Return section model
     * @return Class_Site_Box_Model
     */
    public function getModel()
    {
	if ( NULL === $this->_model ) {
	    $this->_setModel();
	}

	return $this->_model;
    }


    /**
     * Return Box namespace If set
     * @return string
     */
    public function getNamespace()
    {
	return $this->_box['location'];
    }


    /**
     * Return created object renderer
     * @return Class_Renderer
     */
    public function getRenderer()
    {
	If ( NULL === $this->_renderer ) {
	    $this->_renderer = $this->getSite()->getRenderer();
	}

	return $this->_renderer;
    }


    /**
     * @see Class_Site_Box_Model
     */
    private function _setModel()
    {
	$this->_model = new Model();
    }


    /**
     * Create method name by given box name
     * @param string $method.
     *
     * @return string
     */
    public function _createMethodName( $method )
    {
	if ( strstr($method, "-") ) {
	    $_uparts = explode("-", $method);

	    foreach ( $_uparts as $_part ) {
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
     * @param $section
     * @param string $name
     * @return object
     * @throws AppException
     * @throws Box\Exception\BoxException
     */
    private function _getModuleBox( $section, $name )
    {
//	$name = ucfirst($name);
//	var_dump(__NAMESPACE__);
//	var_dump($this->buildNamespace($this->getNamespace(), $this->getSection()->getKey(), $this->getModuleName()));
//	$fullClassName = $this->buildNamespace($this->getNamespace(), $this->getSection()->getKey(), $this->getBoxName());
	$className = $this->buildNamespace(
		$this->getNamespace(), $this->getSection()->getKey(), $this->getModuleName());
//	$_fileName = "Class/Site/Box/" . ucfirst($section) . "/" . $name . ".php";
//	if ( is_readable($_fileName) ) {
//	    require_once "Class/Site/Box/" . ucfirst($section) . "/" . $name . ".php";
//	$this->count++;
//var_dump($className);
	if ( !class_exists($className, true) ) {
        Throw New BoxException(
            sprintf('%s->%s : Cannot create instance of Box "%s". Class not found'
                , __CLASS__
                , __FUNCTION__
                , $className)
        );
	}

	$this->_module = New $className($this);
//	var_dump($this->_module);
//	if ( $this->_module instanceof \Wbengine\Box\BoxTemplate ) {

	return $this->_module;
//	} else {
//	    throw New \Wbengine\Box\Exception\BoxException(__METHOD__
//	    . ': Box module object must extends \Wbengine\Box\BoxTemplate.');
//	}
//	return null;
    }


    private function buildNamespace( $namespace, $section, $moduleName )
    {
	if ( empty($namespace) ) {
	    $_namespace = __NAMESPACE__;
	} else {
	    $_namespace = ucfirst($namespace);
	}

	if ( empty($section) ) {
	    throw New \Wbengine\Box\Exception\BoxException(__METHOD__
	    . ': expects string; but null given!');
	}

	if ( empty($moduleName) ) {
	    throw New \Wbengine\Box\Exception\BoxException(__METHOD__
	    . ': expects string; but null given!');
	}
	return "\\" . $_namespace . "\Box\\" . ucfirst($section) . "\\" . ucfirst($moduleName);
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

	if ( !$this->_module instanceof \Wbengine\Box\BoxTemplate ) {
	    throw New \Wbengine\Box\Exception\BoxException(__METHOD__
	    . ': Given object must be instance of \Wbengine\BoxTemplate.');
	}

	/**
	 * @todo Given object must implement a box interfce!
	 */
	if ( method_exists($_boxObj, $_method) ) {
	    if ( (int) $this->isStatic() == HTML_STATIC ) {
		$tmp .= $_boxObj->$_method($this->getSite());
	    } else {
		$tmp .= $this->getRenderer()->getFormater()->process($_boxObj->$_method($this->getSite()));
	    }

	} else {
	    throw New \Wbengine\Box\Exception\BoxException(__METHOD__
	    . ': Required method ' . $_method . ' does not exist in class ' . ucfirst($this->getModuleName()));
	}

	return $tmp;
    }

}
