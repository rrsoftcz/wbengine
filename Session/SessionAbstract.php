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

    CONST USER_AGENT                = 'user_agent';
    CONST USER_IP                   = 'user_ip';
    CONST USER_ID                   = 'user_id';
    CONST USER_SALT                 = 'user_salt';
    CONST USER_LOCALE               = 'user_locale';
    CONST USER_LOGGED               = 'user_is_logged';
    CONST SESSION_ID                = 'session_id';
    CONST SESSION_DATA              = 'session_data';
    CONST SESSION_UPDATED           = 'session_updated';
    CONST SESSION_EXPIRE            = 'session_expire';
    CONST SESSION_DEFALT_LOCALE     = DEFAULT_LOCALE;

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
     * @var Model
     */
    private $_model = NULL;

    /**
     * Stored class session instance.
     * @var Session
     */
    private static $_session = null;



    /**
     * Validate session by given vars
     * @param $session_id
     * @param $expiration
     * @return bool
     */
    public function isValid($session_id, $expiration){
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }else{
            if(session_id() === $session_id){
                if(
                    $expiration < time()){
                    return false;
                }
                return true;
            }else{
                return false;
            }
        }
    }


    /**
     * Start the session ad return session ID
     * @return string
     */
    public function sessionStart(){
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
            return session_id();
        }else{
//            session_regenerate_id(true);
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


    /**
     * Create object Session
     * @return Session
     */
    private function _createSession(){
        $this->_session = new Session();
        return self::$_session;
    }


    /**
     * Return state of cookies enabled
     * @return boolean
     */
    public function isCookieEnabled(){
        return $this->_getSession()->_isCookieEnabled();
    }


    /**
     * Return users salt as string
     * @return string
     */
    public function generateUserSalt(){
        return substr(md5(Utils::getUserAgent()), 0, 10);
    }


    /**
     * Return created default or created expiration time due to
     * first argument.
     * @return int
     * @internal param bool $create
     */
    public function getExpirationTime(){
        return time() + (int)$this->_expirationTime;
    }


    /**
     * Get or create session class instance.
     * @return \Wbengine\Session\Model
     */
    public function getModel(){
        if (NULL === $this->_model) {
            $this->_setModel();
        }
        return $this->_model;
    }

    /**
     * Create new instance of Class_Session_Model.
     * @void
     */
    private function _setModel(){
        $this->_model = new Model();
    }


    /**
     * Return session locale
     * @param $local_id
     * @return Locale
     */
    public function getLocale($local_id){
        return $this->_getClassLocale($local_id);
    }


    /**
     * Return locale class related to given locale's id.
     * @param integer $locale
     * @return Locale
     */
    private function _getClassLocale($locale){
        if ($this->_locale instanceof Locale) {
            return $this->_locale;
        } else {
            $this->_setClassLocale($locale);
        }
        return $this->_locale->getLocale($locale);
    }


    /**
     * Create and set instance object of Class_Locale
     * @void
     */
    public function _setClassLocale($locale_id){
        $locale = (is_int($locale_id))?$locale_id:self::SESSION_DEFALT_LOCALE;
        $this->_locale = new Locale($locale);
    }


    /**
     * This method do logout user from the existing
     * session and destroy them.
     * @void
     */
    public function destroy($session_id = null){
        $session_id = ($session_id)
            ? $session_id
            : session_id();

//        $this->setValue("user_name", "");
//        $this->setValue("user_pass", "");

        $this->getModel()->deleteSession($session_id);

        session_unset();
        session_destroy();
    }

}
