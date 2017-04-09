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

namespace Wbengine\Error;

class Template {

    /**
     * Error code
     * @var integer
     */
    private $_code = NULL;

    /**
     * Error message
     * @var string
     */
    private $_message = NULL;

    /**
     * Error file name
     * @var string
     */
    private $_file = NULL;

    /**
     * Error line number
     * @var integer
     */
    private $_line = NULL;

    /**
     * Assign Error properties...
     * @param array $array
     */
    public function __construct($array = NULL)
    {
	$this->_code = $array["code"];
	$this->_message = $array["message"];
	$this->_file = $array["file"];
	$this->_line = $array["line"];
    }



    /**
     * Return and Error message
     * @return string
     */
    public function getMessage()
    {
	return $this->_message;
    }



    /**
     * Return and Error Code
     * @return integer
     */
    public function getCode()
    {
	return $this->_code;
    }



    /**
     * Return Error file name
     * @return string
     */
    public function getFile()
    {
	return $this->_file;
    }



    /**
     * Return and Error line
     * @return integer
     */
    public function getLine()
    {
	return $this->_line;
    }



}
