<?php

/**
 * $Id$ - CLASS
 * --------------------------------------------
 * Session abstract class.
 *
 * This class manage session variables by
 * username and locale.
 *
 * @package RRsoft-CMS
 * @version $Rev$ $Date$ $Author$
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Session;

use Wbengine\Locale;
use Wbengine\Session\Exception\SessionException;

abstract class SessionAbstract
{


    /**
     * Local data cache.
     * @var array
     */
    private $_cache = null;

    /**
     * Locale class instance.
     * @var Class_Locale
     */
    private $_locale = NULL;

    /**
     * Eexpiration time in seconds.
     * @var integer
     */
    private $_expirationTime = 3600;

    /**
     * Cookie enabled state.
     * @var boolean
     */
    private $_isCookieEnabled = FALSE;

    /**
     * Session's data model.
     * @var Class_Session_Model
     */
    private $_model = NULL;

    private $_session = array();


    /**
     * Set cookie state to local variable.
     */
    function __construct()
    {
        $this->_isCookieEnabled = $this->_getIsCookieEnabled();
    }

    /**
     * Return what's cookie is enabled
     * @return boolean
     */
    private function _getIsCookieEnabled()
    {
        return (($_COOKIE["PHPSESSID"] == session_id()));
    }

    /**
     * Return created default or created expiration time due to
     * first argument.
     *
     * @param boolean $create
     * @return integer
     */
    public function getExpirationTime($create = TRUE)
    {
        if ($create === TRUE) {
            return time() + (int)$this->_expirationTime;
        } else {
            return (int)$this->_expirationTime;
        }
    }


    /**
     * Return local session cache
     * @return array
     */
    public function getCache()
    {
        return $this->_cache;
    }

    /**
     * Method create a new session and save values to
     * database.
     *
     * @return boolean
     */
    public function create()
    {
        if (!$this->_cache['user_id']) {
            $this->_cache['user_id'] = ANONYMOUS;
            $this->_cache['user_is_logged'] = FALSE;
            $this->_cache['user_locale'] = DEFAULT_LOCALE;
        }
//        die(ddd);
//        die(var_dump($this->_cache));
        $this->getModel()->insertSessionData($this);
//        die(var_dump($this->_cache));
        $this->init(TRUE);
    }

    /**
     * Get or create session class instance.
     * @return \Wbengine\Session\Model
     */
    public function getModel()
    {
        if (NULL === $this->_model) {
            $this->_setModel();
        }

        return $this->_model;
    }

    /**
     * Create new instance of Class_Session_Model.
     * @void
     */
    private function _setModel()
    {
        $this->_model = new Model();
    }


    /**
     * Initialisation method create new session instance
     * if needed.
     * @param boolean $clean
     */
    public function init($clean = TRUE)
    {
        if (NULL === $this->_session) {
            $this->open();
        }

        If ($clean === TRUE) {
            $this->clean();
        }
    }


    /**
     * We try to load current session data from the Database.
     * New session record will be created, when session data
     * does not exist.
     *
     * @return \Wbengine\Session
     */
    public function open()
    {
        $this->_session = $this->getModel()->getSessionData();

        if (NULL === $this->_session) {
            $this->create();
        }

        if (array_key_exists('session_data', $this->_session)) {
            $this->_cache = unserialize($this->_session[session_data]);
        }

    }

    /**
     * Return session locale
     * @return integer
     */
    public function getLocale()
    {
        return $this->_getClassLocale(
            $this->getValue('user_locale', DEFAULT_LOCALE));
    }

    /**
     * Return locale class related to given locale's id.
     * @param integer $locale
     * @return Class_Locale
     */
    private function _getClassLocale($locale)
    {
        if ($this->_locale instanceof Class_Locale) {
            return $this->_locale;
        } else {
            $this->_setClassLocale();
        }

        return $this->_locale->getLocale($locale);
    }

    /**
     * Create and set instance object of Class_Locale
     * @void
     */
    public function _setClassLocale()
    {
        $this->_locale = new Locale($this);
    }

    /**
     * Function return the requested value
     * by given value name.
     * Default value will be returnd when
     * value does not exist or is null.
     *
     * @param string $sName
     * @param mixed $sDefault
     * @throws Exception\SessionException
     * @return mixed
     */
    public function getValue($sName, $sDefault = "")
    {

        if (empty($sName)) {
            throw New SessionException(__METHOD__ . ': Variable name cannot be empty.');
        }

        if (NULL === $this->_cache) {
            $this->open();
        }

        if (array_key_exists($sName, $this->_cache)) {
            return $this->_cache[$sName];
        } else {
            return $sDefault;
        }
    }

    /**
     * This method do logout user from the existing
     * session and destroy them.
     * @void
     */
    public function destroy()
    {
        $this->setValue("user_name", "");
        $this->setValue("user_pass", "");

        $this->getModel()->deleteSession();

        session_unset();
        session_destroy();
    }

    /**
     * Store value to session under given variable name.
     *
     * @param string $sName
     * @param mixed $sValue
     * @throws Class_Session_Exception
     * @return boolean
     */
    public function setValue($sName, $sValue)
    {//$sName=null;
//        $x = $this[0];
        $sName = (string)$sName;

        if (empty($sName)) {
            throw new SessionException(__CLASS__.'->'.__FUNCTION__ . '() with a message: The value name is empty.', 3008);
        }

        // As first we put them in to cache..
        $this->_cache[$sName] = $sValue;

        // Then store it to db..
        $this->getModel()->updateSession($this);
    }


    /**
     * This method remove all expired sessions
     * from database.
     * @void
     */
    public function clean()
    {
        $this->getModel()->cleanSessions($this->_expirationTime);
    }


    public function getSessionUpdated()
    {
        if ($this->_session == NULL)
            $this->_loadResource();

        return $this->_session["session_updated"];
    }


    private function _loadResource()
    {
        $this->_session = $this->getModel()->getSessionResource();
    }


}
