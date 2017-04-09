<?php

/**
 * $Id: Texy.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * XHTML Formater class
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Formater\Adapter;

class Texy {

    /**
     * Texy formater object
     * @var Texy
     */
    private $_formater = null;



    /**
     *  We create new instance of specific formater
     *  @see Texy
     */
    function __construct()
    {
//	die(dirname(APP_DIR));
	$formaterFile = dirname(APP_DIR) . '/Vendor/Texy/texy.php';

//	if ( is_readable($formaterFile) ) {
//	    require_once $formaterFile;

	    $this->_formater = new \Texy;

//            $this->_formater->utf = TRUE;
	    $this->_formater->imageModule->root = '/Images/';
//            $this->_formater->smiliesModule->allowed = TRUE;
	    $this->_formater->obfuscateEmail = FALSE;
//	} else {
//	    throw new Exception\RuntimeException('File ' . $formaterFile . ' does not exist!');
//	}
    }


    /**
     * Return texy formater class
     * @return Texy
     */
    public function get()
    {
	return $this->_formater;
    }

}
