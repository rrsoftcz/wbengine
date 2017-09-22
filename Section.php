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

use Wbengine\Box;
use Wbengine\Model\ModelAbstract;
use Wbengine\Section\Model;
use Wbengine\Site;
use Wbengine\Section\Exception\SectionException;

class Section
{

    /**
     * Model instance
     * @var object
     */
    private $_model = NULL;


    /**
     * Raw model data
     * @var Zend\Db\ResultSet\ResultSet
     */
    private $_section = NULL;


    /**
     * Collection of object all
     * existing sections
     * @var array
     */
    private $_sections = NULL;


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
     * @return ModelAbstract
     */
    private function _setModel()
    {
        $this->_model = new Model();
    }


    /**
     * Return instance of section class
     * @param integer $sectionId
     * @return Section
     */
    private function _getSection($sectionId)
    {
        $newSection = new Section($this->getSite());

        return $newSection->getSection($sectionId);
    }


    /**
     * Return collection of Class_Site_Section object.
     * @return array
     */
    private function _getSections()
    {
        $sections = $this->getModel()->getSections();
        if (sizeof($sections) === 0) {
            return null;
        }
        foreach ($sections as $section) {
            $this->_sections[] = $this->_getSection($section->section_id);
        }

        return $this->_sections;
    }


    /**
     * Return collection of Class_Site_Box objects.
     * @return array
     */
    private function _getBoxes()
    {
        $boxes = $this->getModel()->getBoxes($this);

        if (sizeof($boxes) === 0) {
            return null;
        }

        foreach ($boxes as $box) {
            $clsBox = New Box($this);
            $this->_boxes[] = $clsBox->getBox($box['id']);
        }
        return $this->_boxes;
    }


    /**
     * Assign Site object as aprent object
     * @param \Wbengine\Site $site
     */
    public function __construct(Site $site)
    {
        $this->_site = $site;
    }


    /**
     * Return instance of Site class
     * @return Site
     */
    public function getSite()
    {
        return $this->_site;
    }


    /**
     * Return section id
     * @return integer
     */
    public function getSectionId()
    {
        return $this->_section->section_id;
    }


    /**
     * Return section title
     * @return string
     */
    public function getName()
    {
        return $this->_section['title'];
    }


    /**
     * Return section's description
     * @return string
     */
    public function getDescription()
    {
        return $this->_section['description'];
    }


    /**
     * Return section unique key
     * @return mixed
     */
    public function getKey()
    {
        return $this->_section->key;
    }


    /**
     * Return true/false if section is active
     * @return boolean
     */
    public function isActive()
    {
        return $this->_section['active'];
    }


    /**
     * Return section's error code if defined
     * @return mixed
     */
    public function getErrorCode()
    {
        return $this->_section['return_error_code'];
    }


    /**
     * Reurn collection of sections.
     * The array items are instance of Class_Site_Section
     * @return array
     */
    public function getSections()
    {
        return $this->_getSections();
    }


    /**
     * Return Instance of Site section
     * @param integer $sectionId
     * @return \Wbengine\Section
     */
    public function getSection($sectionId)
    {
        $this->_section = $this->getModel()->getSectionById($sectionId)->current();
        return $this;
    }


    /**
     * Return Box content
     * @return string
     * @throws SectionException
     */
    public function getContent()
    {
        $boxes = $this->getBoxes();

        if ($boxes === null) {
            return;
        }

        if (sizeof($boxes) === 0) {
            return NULL;
        }

        foreach ($boxes as $box) {
            $this->_content .= $box->getContent();
        }

        return $this->_content;
    }

    /**
     * Return Boxes collections
     * @return \Wbengine\Box
     */
    public function getBoxes()
    {
        if (NULL === $this->_boxes) {
            $this->_boxes = $this->_getBoxes();
        }

        /**
         * @todo Create exception in debug mode, when any boxes returned.
         */
        return $this->_boxes;
    }


    public function getBoxById($id)
    {

        $box = New \Wbengine\Box($this);
        $this->_boxes = $box->getBox($id);
        return $box;
    }


    /**
     * Return section model
     * @return Model
     */
    public function getModel()
    {
        if (NULL === $this->_model) {
            $this->_setModel();
        }

        return $this->_model;
    }

}
