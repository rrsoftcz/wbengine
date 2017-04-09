<?php

/**
 * $Id$ - CLASS
 * --------------------------------------------
 * Locales class manage all avaliable locales in
 * CMS.
 * Return locale by given id or class with existing
 * locale translations.
 *
 * @package RRsoft-CMS
 * @version $Rev$ $Date$ $Author$
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine;

use Wbengine\Locale\Exception\LocaleException;
use Wbengine\Locale\Model;

class Locale
{


    /**
     * Created locale class
     * @var object
     */
    private $_locale = null;

    /**
     * Created locale class
     * @var object
     */
    private $_model = null;


    /**
     *
     * @param integer $locale_id
     * @throws Locale\Exception\LocaleException
     * @throws Exception\RuntimeException
     * @return string
     */
    private function _getClassName($locale_id)
    {
        // Get locale name by given locale id...
        $locale = $this->_getModel()->getLocaledataRow((int)$locale_id);

        // Returned locale name from the model can't be empty..
        if (empty($locale)) {
            throw new Exception\RuntimeException(__METHOD__ .
                ": Locale Cannot be null.");
        }

        $s_className = 'Wbengine\Locale\\' . ucfirst($locale);

        // test if class exist by the created class name..
        if (class_exists($s_className)) {
            return $s_className;
        } else {
            throw new LocaleException(__METHOD__ . ': Class name ' . $s_className . ' does\'t exist.');
        }
    }


    /**
     * Return fuull locale path by given locale name.
     *
     * @param string $locale_name
     * @throws Locale\Exception\LocaleException
     * @return string
     */
    private function _getClassPath($locale_name)
    {
        // create path we should know it...
        $f_name = __DIR__ . '/Locale/' . ucfirst($locale_name) . '.php';

        // ..try read the class file...
        if (is_readable($f_name)) {
            return (string)$f_name;
        } else {
            throw new LocaleException(__METHOD__ . ': Locale file ' . $f_name . ' not found.');
        }

    }


    /**
     * This method return locale class by given existing
     * locale ID stored in databse.
     *
     * @param integer $locale_id
     * @throws Locale\Exception\LocaleException
     * @return object
     */
    public function getLocale($locale_id)
    {
        if (!(int)$locale_id) {
            throw new LocaleException(__METHOD__ . ': The locale id cannot be null.');
        }

        if (is_object($this->_locale)) {
            return $this->_locale;
        }

        $this->_locale = $this->_getLocale($locale_id);

        if ($this->_locale instanceof Locale\LocaleAbstract) {
            return $this->_locale;
        } else {
            throw new LocaleException(__METHOD__ . ': The given locale object is not an instance of locale class.');
        }

        return null;
    }


    /**
     * Return local instance of locale class.
     *
     * @param integer $locale_id
     * @return Class_Locale_Abstract
     */
    private function _getLocale($locale_id)
    {
        if (NULL === $this->_locale) {
            $this->_setLocale($locale_id);
        }

        return $this->_locale;
    }


    /**
     * Create and set if not exist instance of
     * Class_Locale_Abstract to local variable.
     *
     * @param integer $locale_id
     * @throws Locale\Exception\LocaleException
     */
    private function _setLocale($locale_id)
    {
        $className = $this->_getClassName($locale_id);

        $tmpLocale = new $className();

        if ($tmpLocale instanceof Locale\LocaleAbstract) {
            $this->_locale = $tmpLocale;
        } else {
            throw new LocaleException(__METHOD__ . ': Cannot create locale object.');
        }
    }


    /**
     * Return Locale Model object
     * @return \Wbengine\Locale\Model
     */
    private function _getModel()
    {
        if (NULL === $this->_model) {
            $this->_model = new Model();
        }

        return $this->_model;
    }


}
