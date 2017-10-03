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

use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Box;
use Wbengine\Model\ModelAbstract;
use Wbengine\Section\Model;
use Wbengine\Site;
use Wbengine\Section\Exception\SectionException;

class Section
{

    /**
     * Model instance
     * @var Model
     */
    private $_model = NULL;

    /**
     * Raw model data
     * @var Section
     */
    private $_section = NULL;

    /**
     * Boxes collection
     * @var array
     */
    private $_boxes = NULL;

    /**
     * Object site
     * @var \Wbengine\Site
     */
    private $_site = NULL;

    /**
     * Box content as HTML..
     * @var string
     */
    private $_content = NULL;

    /**
     * Set Model instance
     * @return Model
     */
    private function _setModel(){
        $this->_model = new Model();
    }



    /**
     * Return collection of object Box.
     * @return array
     */
    private function _getBoxes()
    {
        if (is_array($this->_boxes)) {
            return $this->_boxes;
        }

        $boxes = $this->getModel()->getBoxes($this);

        if(!sizeof($boxes)) return null;

        foreach ($boxes as $box) {
            $Box = New Box($this);
            $Box->setBox($box);
            $this->_boxes[] = $Box->getBox();
        }
        return $this->_boxes;
    }


    /**
     * Section constructor.
     * @param array $section
     */
    public function __construct($section)
    {
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
        return $this->_site;
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
        return $this->_section['return_error_code'];
    }


    /**
     * Return Box content
     * @param $site
     * @return string
     */
    public function getContent($site)
    {
        $this->_site = $site;

        if(!$boxes = $this->getBoxes()) return '';

        /**
         * @var $box \Wbengine\Box
         */
        foreach ($boxes as $box) {
            $this->_content .= $box->getContent();
        }

        return $this->_content;
    }


    /**
     * Return Section boxes sum as integer
     * @return int
     */
    public function getBoxesCount(){
        return sizeof($this->getBoxes());
    }


    /**
     * Return Boxes collections
     * @return array
     */
    public function getBoxes(){
        return $this->_getBoxes();
    }


    /**
     * Return new instance of object Box
     * @param $id
     * @return \Wbengine\Box
     */
    public function getBoxById($id)
    {
        $box = New Box($this);
        $this->_boxes = $box->getBox($id);
        return $box;
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


}
