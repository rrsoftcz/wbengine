<?php

/**
 * $Id$ - CLASS
 * --------------------------------------------
 * Locales abstract class.
 *
 * Return locale by given key or class with existing
 * locale translations.
 *
 * @package RRsoft-CMS
 * @version $Rev$ $Date$ $Author$
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Locale;

use Wbengine\Locale\Exception\LocaleException;

abstract class LocaleAbstract
{

    public function __get($name)
    {
        $name = strtoupper($name);

        if (array_key_exists($name, $this->_locales)) {
            return $this->_locales[$name];
        } else {
            require_once 'Class/Locale/SessionException.php';
            throw new LocaleException ('The locale keyword does not exist.');
        }
    }


    /**
     * Return all keywords as assoc array.
     * @return array
     */
    public function getAllKeywords()
    {
        return $this->_locales;
    }


}
