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

use Wbengine\Box\Model;
use Wbengine\Box\WbengineBoxAbstract;
use Wbengine\Box\Exception\BoxException;
use Wbengine\Components\ComponentParentInterface;
use Wbengine\Model\ModelAbstract;

class Boxxxxxxxxxx implements ComponentParentInterface
{

    /**
     * Model instance
     * @var ModelAbstract
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
     * @var Renderer
     */
    private $_renderer = null;


    /**
     * The module name
     * @var WbengineBoxAbstract
     */
    private $_module = null;


    /**
     * Default box class name prefix
     * @var string
     */
    private $_methodPrefix = 'get';


    /**
     * Default box class name surfix
     * @var string
     */
    private $_methodSurfix = 'Box';


    /**
     * The Box class name
     * @var string
     */
    private $_className = null;



    /**
     * Create and return module box object
     * by given module name.
     *
     * @param $section
     * @param string $name
     * @return WbengineBoxAbstract
     * @throws BoxException
     */
    private function _getModuleBox($section, $name)
    {
        $className = $this->_buildNamespace(
            $this->getAppBaseDirName(), $this->getSection()->getKey(), $this->getModuleName());
        if (!class_exists($className, true)) {
            Throw New BoxException(
                sprintf('%s->%s : Cannot create instance of "%s". Class not found.'
                    , __CLASS__
                    , __FUNCTION__
                    , $className)
            );
        }

        $this->_module = New $className($this);

        return $this->_module;
    }


    /**
     * Build module namespace by given module references...
     * @param $namespace
     * @param $section
     * @param $moduleName
     * @return string
     * @throws BoxException
     */
    private function _buildNamespace($namespace, $section, $moduleName)
    {
        if (empty($namespace)) {
            $_namespace = __NAMESPACE__;
        } else {
            $_namespace = ucfirst($namespace);
        }

        if (empty($section)) {
            throw New BoxException(__METHOD__
                . ': expects string; but null given!');
        }

        if (empty($moduleName)) {
            throw New BoxException(__METHOD__
                . ': expects string; but null given!');
        }
        return "\\" . $_namespace . "\Box\\" . ucfirst($section) . "\\" . ucfirst($moduleName);
    }


    /**
     * Return Box model instance...
     * @return ModelAbstract
     */
    private function _setModel(){
        $this->_model = new Model();
    }


    /**
     * Create method name by given box name
     * @param string $method .
     *
     * @return string
     */
    private function _createMethodName($method)
    {
        $_tmp = '';

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
     * Just assign parent objects to local variables.
     * @param Section $section
     */
    public function __construct(Section $section)
    {
        $this->_section = $section;
    }


    /**
     * Return Site object
     * @return Site
     */
    public function getSite()
    {
        return $this->getSection()->getSite();
    }


    /**
     * Return and section object
     * @return Section
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
        return (int)$this->_box['id'];
    }


    /**
     * Return App basedir path.
     * @return null|string
     */
    public function getAppBaseDirName(){
        return $this->getSite()->getParent()->getAppDir(true);
    }


    /**
     * Return Instance of Site section
     * @return Box
     * @internal param int $boxId
     */
    public function getBox(){
        return $this;
    }


    /**
     * Return section model
     * @return ModelAbstract
     */
    public function getModel(){
        if (NULL === $this->_model) {
            $this->_setModel();
        }
        return $this->_model;
    }


    /**
     * Return Box namespace If set
     * @return string
     */
    public function getNamespace(){die(dddd);
        return $this->_box['location'];
    }


    /**
     * Set box data as array
     * @param array $box
     */
    public function setBox($box){
        $this->_box = $box;
    }


    public function getParent(){
        return $this->_section;
    }

    /**
     * Return created object renderer
     * @return Renderer
     */
    public function getRenderer(){
        If (NULL === $this->_renderer) {
            $this->_renderer = $this->getSite()->getRenderer();
        }
        return $this->_renderer;
    }


    /**
     * Return parsed (X)HTML content of given box
     * This method call all boxes ssigned to given section
     * and try to get its content.
     * @return string as HTML
     * @throws BoxException
     */
    public function getContent()
    {
        $tmp = '';
        $_method = $this->_createMethodName($this->getMethodName());
        $_boxObj = $this->_getModuleBox($this->getSection()->getKey(), $this->getModuleName());

        if (($this->_module instanceof WbengineBoxAbstract) === false) {
            throw New BoxException(__METHOD__
                . ': Given object must be instance of "\Wbengine\WbengineBoxAbstract".');
        }

        //@todo Given object must implement a box interfce!

        try {
            if (method_exists($_boxObj, $_method)) {
                if ((int)$this->isStatic() === HTML_STATIC) {
                    $tmp .= $_boxObj->$_method($this->getSite());
                } else {
                    $tmp .= $this->getRenderer()->getFormater()->process($_boxObj->$_method($this));
                }
            } else {
                throw New BoxException(__METHOD__
                    . ': Required method ' . $_method . ' does not exist in class ' . ucfirst($this->getModuleName()));
            }
        }catch (BoxException $e){
            throw new BoxException($e->getMessage());
        }

        return $tmp;
    }

}
