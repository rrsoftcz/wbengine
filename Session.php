<?php

/**
 * $Id: Session.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Session initial class
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine;

use MongoDB\BSON\Unserializable;
use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Session\Exception\SessionException;
use Wbengine\Session\SessionAbstract;
use Wbengine\Session\SessionData;
use Wbengine\Session\Value;


class Session extends SessionAbstract
{
    private $_cookieEnabled;
    private $_test;
//    private $_data_data;
    private $_data;
    private $_autoclean = false;
    private $_loaded = false;
    private $_created = false;

    function __construct()
    {
        $this->_data = new \stdClass();
        $this->_setSelfValue(self::USER_AGENT, Utils::getUserAgent());
        $this->_setSelfValue(self::USER_IP, Utils::getUserIp());
        $this->_setSelfValue(self::USER_ID, ANONYMOUS);
        $this->_setSelfValue(self::USER_SALT, $this->generateUserSalt());
        $this->_setSelfValue(self::SESSION_ID, $this->sessionStart());
        $this->_setSelfValue(self::SESSION_DATA, new \stdClass());
        $this->_setSelfValue(self::SESSION_EXPIRE, $this->getExpirationTime());
        $this->_setSelfValue(self::SESSION_UPDATED, time());
//        $this->_load();
//        $this->_cookieEnabled = ($_COOKIE['session_id'] === session_id());
    }


    public function isAutoCleanOn(){
        return $this->_autoclean;
    }

    public function setAutoClean(boolean $state){
        $this->_autoclean = $state;
    }

    private function _isLoaded(){
        return $this->_loaded;
    }

    private function _inicialized(){
//        var_dump(!(is_null($this->_data->id)));
        return !(is_null($this->_data->id));
    }
    private function _create(){
//        $this->_created = true;
//        $this->init();

//die(ddd);
        $this->getModel()->insertSessionData($this);
//            $this->user_ip = '192.';
//        die(creating);
    }

    public function _load(){
//        $this->init();
        Utils::dump('_load');
        $_data = $this->getModel()->getSessionData($this);
//        var_dump($_data);die();
        $this->_loaded = true;
        if(is_object($_data)){
//            Utils::dump($_data);
            $this->_data = $_data;
//            Utils::dump($this->_data);
            $this->_data->session_data = unserialize($_data->session_data);
        }else{
            $this->_create();
        }

    }

    public function __set($name, $value)
    {//var_dump($value);die();
//        if(!is_object($this->getSessionData())){
//            $this->_data->session_data = new Value();
//        }
//        if(!$this->getId()){
            $this->_load();
//        }
        $this->getSessionData()->$name = $value;
        $this->getModel()->updateSession($this);
//        if(!$name){
//            Throw new SessionException(
//                sprintf("%s -> %s: The magis quote name for SET cannot be empty.",
//                __CLASS__,
//                __FUNCTION__),
//                SessionException::SESSION_MAGIC_QUOTE_IS_NULL);
//        }
//        if(!is_object($this->_data)){
//            $this->_data = new \stdClass();
//        }
//        $this->_data->$name = $value;
    }

    public function __get($name)
    {//var_dump($this->getSessionData());die();
        if(!$this->getId()) {
            $this->_load();
        }
        var_dump($name);
        return $this->getSessionData()->$name;
//        if($this->_isLoaded() === false) {
//            $this->_load();
//        }
//        die(__get);
//        if(is_object($this->_data)){
////            Utils::dump($this->_data);
//            if($this->_inicialized() === false){
//                $this->_load();
//            }
////            var_dump($this->id);
////            return $this->_data->$name;
//            return $this->_data->$name;
////            }else{
////                return null;
////                Throw new SessionException(sprintf("%s -> %s: The session properties '%s' does not exist.",
////                    __CLASS__,
////                    __FUNCTION__,
////                    $name),
////                    SessionException::SESSION_ERROR_DATA_NOT_LOADED);
//        }else{
//                Throw new SessionException(sprintf("%s -> %s: Load Session data error.",
//                    __CLASS__,
//                    __FUNCTION__),
//                    SessionException::SESSION_ERROR_DATA_NOT_LOADED);
//
//        }

    }

    private function _setValueToSession($name, $value){
        $this->$name = $value;
//        if($this->session_data instanceof Value){
//            $this->session_data->$name = $value;
//        }else{
//            $this->session_data =  new Value($name, $value);
//        }
//        $this->getModel()->updateSession($this);
    }
//
//    private function _getValueFromSession($name){
//        if($this->session_data instanceof Value) {
//            return $this->session_data->$name;
//        }else{
//            return null;
//        }
//    }

    public function getSessionData(){
        return $this->_data->session_data;
    }

    public function getValue($name){
        return $this->$name;
    }

    private function _setSelfValue($name, $value){
        $this->_data->$name = $value;
    }

    private function _getSelfValue($name){
        return $this->_data->$name;
    }

    public function setValue($name, $value){
        $this->$name = $value;
    }

    public function getUserId(){
        return $this->_data->user_id;
    }

    public function getId(){
        return $this->_data->id;
    }

    public function getUserIp(){
        return $this->_data->user_ip;
    }

    public function getUserAgent(){
        return $this->_data->user_agent;
    }

    public function getUserSalt(){
        return $this->_data->user_salt;
    }

    public function getSessionId(){
        return $this->_data->session_id;
    }

    public function geSessionStoredValues($name = null){
        if($name){
            if($this->session_data){
            return $this->session_data->$name;
            }
        }else{
            return $this->session_data;
        }
        return null;
    }

    public function getSessionLastUpdated(){
        return $this->_data->session_updated;
    }

    public function getSessionExpireTime(){
        return $this->_data->session_expire;
    }

}
