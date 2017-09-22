<?php

/**
 * $Id: Smarty.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Renderer configuratin class
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Renderer;

//use Exception;
//require_once dirname(__FILE__) . '/RendererInterface.php';

class Adapter implements \Wbengine\Renderer\RendererInterface {


    /**
     * Instance of adapter object
     * @var Class_Renderer_Adapter
     */
    private $_adapter = NULL;

    /**
     * The adapter name
     * @var string
     */
    private $_adapterName = NULL;



    /**
     * Set adapter name
     * @param string $name
     */
    public function setAdapterName($name) {
	$this->_adapterName = $name;

    }



    /**
     * Return adapter name
     * @return string
     */
    public function getAdapterName() {
	return $this->_adapterName;

    }



    /**
     * Return created object instance
     * @return Class_Renderer_Interface
     */
    private function getAdapter() {
	if ($this->_adapter && is_object($this->_adapter))
	    return $this->_adapter;

	$name = '\\'.ucfirst($this->getAdapterName());

//	$adaFile = "/vendor/{$name}/{$name}.class.php";

//	if (is_readable($adaFile)) {
//	    require_once $adaFile;
//var_dump($name);
	    $this->_adapter = new $name();
//var_dump($this->_adapter);
	    return $this->_adapter;
//	} else {
////	    require_once 'RendererException.php';
//	    throw new \RuntimeException('File ' . $adaFile . ' does not exist!');
//	}

    }



    /**
     * Returns the template output
     * @return string
     */
    public function fetch($template, $cache_id = NULL, $compile_id = NULL) {
	return $this->getAdapter()->fetch($template, $cache_id, $compile_id);

    }



    /**
     * Displays the template
     */
    public function display($template, $cache_id = NULL, $compile_id = NULL) {
	$this->getAdapter()->display($template, $cache_id, $compile_id);

    }



    /**
     * Assign values to the templates
     */
    public function assign($varname, $var = NULL, $scope = NULL) {
	$this->getAdapter()->assign($varname, $var, $scope);

    }



    /**
     * Set compiling files directory
     */
    public function setCompileDir($path) {
	$this->getAdapter()->compile_dir = (string) $path;

    }



    /**
     * Set Template files directory
     */
    public function setTemplateDir($path) {
	$this->getAdapter()->template_dir = (string) $path;

    }



    /**
     * set Config directory
     */
    public function setConfigDir($path) {
	$this->getAdapter()->config_dir = (string) $path;

    }



    /**
     * set cache directory
     */
    public function setCacheDir($path) {
	$this->getAdapter()->cache_dir = (string) $path;

    }



    /**
     * set SMARTY left delimiter
     */
    public function setLeftDelimiter($value) {
	$this->getAdapter()->left_delimiter = (string) $value;

    }



    /**
     * set SMARTY right delimiter
     */
    public function setRightDelimiter($value) {
	$this->getAdapter()->right_delimiter = (string) $value;

    }



    /**
     * register object to SMARTY template
     */
    public function registerObject($name, $value) {
	$this->getAdapter()->assignByRef($name, $value);

    }

}
