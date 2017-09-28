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

use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Locale;
use Wbengine\Section;
use Wbengine\Session;
use Wbengine\Session\Exception\SessionException;

abstract class SessionAbstract
{

    CONST USER_AGENT        = 'user_agent';
    CONST USER_IP           = 'user_ip';
    CONST USER_ID           = 'user_id';
    CONST USER_SALT         = 'user_salt';
    CONST USER_LOCALE       = 'user_locale';
    CONST USER_LOGGED       = 'user_is_logged';
    CONST SESSION_ID        = 'session_id';
    CONST SESSION_DATA      = 'session_data';
    CONST SESSION_UPDATED   = 'session_updated';
    CONST SESSION_EXPIRE    = 'session_expire';

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
     * Session's data model.
     * @var Class_Session_Model
     */
    private $_model = NULL;

    /**
     * Stored class session instance.
     * @var SessionAbstract
     */
    private static $_session = null;

    /**
     * Set cookie state to local variable.
     */
//    function __construct()
//    {
//        if (session_status() === PHP_SESSION_NONE) {
//            session_start();
//        }
//
//        $this->_isCookieEnabled = $this->_getIsCookieEnabled();
//    }

//    public function setSessionAutoClean($state){
//        $this->_getSession()->setAutoClean((boolean)$state);
//    }
//
//    public function isAutoCleanOn(){
//        return self::_getSession()->isAutoCleanOn();
//    }

//    public function isOpen(){
//        if($this->_getSession()->getSessionId()){
//            return true;
//        }else{
//            return false;
//        }
////        if(is_array($this->_session_data)) {
////            if (array_key_exists('session_id', $this->_session_data)) {
////                return true;
//////                return $this->_session_data[self::SESSION_ITEM_SESSION_ID];
////            }else{
////                return false;
////            }
////        }
//    }

    public function isValid($session_id){
        if (session_status() === PHP_SESSION_NONE) {
//            session_start();
            return false;
        }else{
            if(session_id() === $session_id){
                return true;
            }else{
//                $this->destroy($session_id);
                return false;
            }
        }
    }

    public function sessionStart(){
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
            return session_id();
        }else{
            session_regenerate_id(true);
            return session_id();
        }
    }

    /**
     * Return Instance of class Session
     * @return Session
     */
    private function _getSession(){
        if(self::$_session instanceof Section) {
            return self::$_session;
        }else{
            return self::_createSession();
        }
    }

    private function _createSession(){
        $this->_session = new Session();
        return self::$_session;
    }

    private function _getSessionData(){
        return self::_getSession()->getSessionData();
    }

    public function isCookieEnabled(){
        return $this->_getSession()->_isCookieEnabled();
    }

    public function generateUserSalt(){
        return substr(md5(Utils::getUserAgent()), 0, 10);
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
     * Method create a new session and save values to
     * database.
     *
     * @return boolean
     */
//    public function create()
//    {
//        if (!$this->_cache['user_id']) {
//            $this->_cache['user_id'] = ANONYMOUS;
//            $this->_cache['user_is_logged'] = FALSE;
//            $this->_cache['user_locale'] = DEFAULT_LOCALE;
//        }
////        die(ddd);
////        die(var_dump($this->_cache));
////        var_dump($this->getModel()->insertSessionData($this));
////        die(var_dump($this->_cache));
//        $this->init(TRUE);
//    }

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


//    /**
//     * Initialisation method create new session instance
//     * if needed.
//     * @param boolean $clean
//     */
//    public function init()
//    {
////        if(!self::isOpen()) {
////            self::open();
////        }
////        die(init);
////        If (self::isAutoCleanOn()) {die(autoclean);
////            $this->clean();
////        }
//    }
//

    /**
     * We try to load current session data from the Database.
     * New session record will be created, when session data
     * does not exist.
     *
     * @return \Wbengine\Session
     */
//    public function open()
//    {
//        $this->_session = $this->getModel()->getSessionData();
////var_dump($this->_session);die();
//        if ($this->_session === null) {
//            $this->create();
//        }
////        Utils::dump($this->_session);
//        if (array_key_exists('session_data', $this->_session)) {
//            $this->_cache = unserialize($this->_session['session_data']);
//        }
//
//    }

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
        if ($this->_locale instanceof Locale) {
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
//    public function getValue($sName, $defaultValue = null)
//    {
//
//        if (empty($sName)) {
//            return $defaultValue;
//        }

//        self::init();
//        if (self::$_session instanceof Session) {
//            if(self::isOpen()){
//        $this->setValue('user_ip','172.16.24.0/24');
//        $this->setValue('user_xx','172.16.24.0/24');
//        Utils::dump($this);
//        var_dump($this->getUserIp());
//        var_dump($this->getUserIp());
//            }
//        }
//        self::$_session = new Session();
//        $this->open();
//var_dump($this->_cache);
//        if (array_key_exists($sName, $this->_cache)) {
//            return $this->_cache[$sName];
//        } else {
//            return $sDefault;
//        }
//    }

    /**
     * This method do logout user from the existing
     * session and destroy them.
     * @void
     */
    public function destroy($session_id = null)
    {
        $session_id = ($session_id)
            ? $session_id
            : session_id();

        $this->setValue("user_name", "");
        $this->setValue("user_pass", "");

        $this->getModel()->deleteSession($session_id);

        session_unset();
        session_destroy();
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
