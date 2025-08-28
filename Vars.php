<?php

/**
 * $Id: Site.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Site Vars class.
 *
 * @package RRsoft-CMS * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine;

use Wbengine\Application\Application;
use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Vars\VarsException;

class Vars
{


    /**
     * Array of all assigned variables
     * @var array
     */
    private $_vars = array();

    /**
     * Instance of Class_Cms
     * @var Application
     */
    private $_cms = NULL;

    /**
     * Instance of class Site
     * @var null|Site
     */
    private $_site = NULL;


    /**
     * Create array with all variables needed for render site.
     * @param Application $wbengine
     * @throws Vars\VarsException
     */
    public function __construct(Application $wbengine)
    {
        if ($wbengine instanceof Application) {
            $this->_cms = $wbengine;
            $this->_site = $wbengine->getSite();
        } else {
            throw new Vars\VarsException(__METHOD__
                . ': Given object is not the instance of Class_Cms.');
        }
    }


    /**
     * Add value to Site variables collection.
     * We store all needed variables here for latest use in
     * site renderer.
     *
     * In order you can specify parent key.
     *
     * @param string $key
     * @param mixed $value
     * @param string $parentKey
     * @throws VarsException
     */
    public function addValue($key, $value = NULL, $parentKey = NULL)
    {
        if (NULL === $key) {
            Throw New VarsException(sprintf("%s -> %s: The key value cannot be empty!",
                __CLASS__,
                __FUNCTION__),
                VarsException::ERRROR_VALUE_KEY_IS_EMPTY);
        }

        if (!empty($parentKey)) {
            $this->_vars[$parentKey][$key] = $value;
        } else {
            $this->_vars[$key] = $value;
        }
    }


    /**
     * Return allready assigned value
     * by given key.
     * @param string $key
     * @return mixed
     */
    public function getValue($key)
    {
        if (!array_key_exists($key, $this->_vars)) {
            return NULL;
        }
        return $this->_vars[$key];
    }


    /**
     * Return All assigned variables as
     * associative array.
     *
     * @return array
     */
    public function getValues()
    {
        return $this->_vars;
    }


}
