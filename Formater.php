<?php

/**
 * $Id$ - CLASS
 * --------------------------------------------
 * Formater class try to get default formater
 * used in CMS.
 * We use TEXY! formater (http://texy.info/)
 *
 * @package RRsoft-CMS
 * @version $Rev$ $Date$ $Author$
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine;

class Formater {


    /**
     * Given formater as object
     * @var object
     */
    private $_formater = null;



    /**
     * Return instance of requested formater object
     * used in CMS
     *
     * @param string $formaterName
     * @return object
     */
    public function getFormater($formaterName, $path)
    {
	if (!$this->_formater) {

	    $_className = 'Wbengine\Formater\Adapter\\' . ucfirst($formaterName);
//	    $_fileName = $path . $formaterName . ".php";
//	    if ( is_readable($_fileName) ) {
//		require_once $_fileName;

	    $_formater = new $_className;

	    $this->_formater = $_formater->get();
//APP_DIR . '/Cache/Renderer/'
	    $this->_formater->imageModule->root = '/Images/';
        $this->_formater->headingModule->top = 2;
	    return $this->_formater;
//	    } else {
//		throw new Exception\RuntimeException('File ' . $_fileName . ' does not exist!');
//	    }
	} else {
	    return $this->_formater;
	}
    }



}
