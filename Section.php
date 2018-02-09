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

use Wbengine\Application\ApplicationException;
use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Box;
use Wbengine\Components\ComponentParentInterface;
use Wbengine\Model\ModelAbstract;
use Wbengine\Section\Model;
use Wbengine\Site;
use Wbengine\Section\Exception\SectionException;

class Section implements ComponentParentInterface
{

    /**
     * Model instance
     * @var Model
     */
    private $_model;

    /**
     * Raw model data
     * @var Section
     */
    private $_section;

    /**
     * Boxes collection
     * @var array
     */
    private $_boxes;

    /**
     * Object site
     * @var \Wbengine\Site
     */
    private $_site;

    /**
     * Box content as HTML..
     * @var string
     */
    private $_content;

    /**
     * Object parent
     * @var ComponentParentInterface
     */
    private $_parent;

    /**
     * Boxes count
     * @var int
     */
    public $count = 0;



    /**
     * Set Model instance
     * @return Model
     */
    private function _setModel(){
        $this->_model = new Model();
    }



    /**
     * Return stacked content from all found boxes ...
     * @param $callable
     * @return string
     */
    private function _getBoxes($callable)
    {
        try {
            foreach ($this->getModel()->getBoxes($this) as $b) {
                $this->_createMethodName($b['method'], $this->_getModuleBox($b), function ($box, $method) {
                    if ($box instanceof Box\WbengineBoxAbstract) {
                        if (method_exists($box, $method)) {
                            $this->_content .= $box->$method();
                        } else {
                            Throw New SectionException(
                                sprintf('%s->%s : The method name "%s::%s()" not found.'
                                    , __CLASS__
                                    , __FUNCTION__
                                    , $box->getModuleName($box)
                                    , $method)
                            );
                        }
                    }
                    $this->count++;
                });
            }
            return $callable($this->_content);
        }catch(SectionException $e){
            throw new Site\SiteException($e->getMessage());
        }
    }



    /**
     * Create and return method name.
     * @param string $method
     * @param Box\WbengineBoxAbstract $objectBox
     * @param Callable $callable
     * @return string
     */
    private function _createMethodName($method, $objectBox, $callable){
        return $callable($objectBox, ucfirst($method));
    }



    /**
     * Return created instance of box.
     * @param array $box
     * @return Box\WbengineBoxAbstract
     * @throws SectionException
     */
    private function _getModuleBox(array $box)
    {
        $className = $this->_buildNamespace($this->getAppBaseDirName(), $this->getKey(), $box['module']);

        if (class_exists($className, true)) {
            $this->_module = new $className($box, $this);
        }else{
            Throw New SectionException(
                sprintf('%s->%s : Cannot create instance of "%s". Class not found.'
                    , __CLASS__
                    , __FUNCTION__
                    , $className)
            );
        }
        return $this->_module;
    }



    /**
     * Build namespace from given arguments ...
     * @param string $namespace
     * @param string $section
     * @param string $moduleName
     * @return string
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
     * Return Application based directory ...
     * @return string
     */
    public function getAppBaseDirName(){
        return $this->getSite()->getParent()->getAppDir(true);
    }



    /**
     * Section constructor.
     * @param array $section
     */
    public function __construct(array $section, ComponentParentInterface $parent)
    {
        $this->_parent = $parent;
        $this->_section = new \stdClass();

        foreach ($section as $key=>$value){
            $this->_section->$key = $value;
        }
    }



    /**
     * Return instance of Site class
     * @return Site
     */
    public function getSite(){
        if($this->_parent instanceof Site) {
            return $this->_parent;
        }
        return null;
    }



    /**
     * Return instance of object parent
     * @return ComponentParentInterface
     */
    public function getParent(){
        return $this->_parent;
    }



    /**
     * Return section id
     * @return integer
     */
    public function getSectionId(){
        return $this->_section->section_id;
    }



    /**
     * Return section title
     * @return string
     */
    public function getName(){
        return $this->_section->title;
    }



    /**
     * Return section's description
     * @return string
     */
    public function getDescription(){
        return $this->_section->description;
    }



    /**
     * Return section unique key
     * @return mixed
     */
    public function getKey(){
        return $this->_section->key;
    }



    /**
     * Return true/false if section is active
     * @return boolean
     */
    public function isActive(){
        return $this->_section->active;
    }



    /**
     * Return section's error code if defined
     * @return mixed
     */
    public function getErrorCode(){
        return $this->_section->return_error_code;
    }



    /**
     * Return Box content
     * @param $site
     * @return string
     */
    public function getContent(){
        return $this->_getBoxes(function(){
            return ($this->_content);
        });
    }



    /**
     * Return instance of object Renderer
     * @return Renderer
     */
    public function getRenderer(){
        return $this->getParent()->getRenderer();
    }



    /**
     * Return Section boxes sum as integer
     * @return int
     */
    public function getBoxesCount(){
        return (int)$this->count;
    }



    /**
     * Return section model
     * @return Model
     */
    public function getModel(){
        if (NULL === $this->_model) {
            $this->_setModel();
        }
        return $this->_model;
    }


    public function getSession(){
        return $this->getParent()->getSession();
    }


}
