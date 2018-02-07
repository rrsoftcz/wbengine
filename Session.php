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
    private $_data;
    private $_autoclean = false;
    private $_created = false;

    function __construct()
    {
        $this->_data = new \stdClass();
        $this->_setSelfValue(self::USER_AGENT, Utils::getUserAgent());
        $this->_setSelfValue(self::USER_IP, Utils::getUserIp());
        $this->_setSelfValue(self::USER_ID,ANONYMOUS);
        $this->_setSelfValue(self::USER_SALT, $this->generateUserSalt());
        $this->_setSelfValue(self::SESSION_ID, $this->sessionStart());
        $this->_setSelfValue(self::SESSION_DATA, new \stdClass());
        $this->_setSelfValue(self::SESSION_EXPIRE, $this->getExpirationTime());
        $this->_setSelfValue(self::SESSION_UPDATED, time());
    }


    public function isAutoCleanOn()
    {
        return $this->_autoclean;
    }

    public function setAutoClean(boolean $state)
    {
        $this->_autoclean = $state;
    }

    private function _create()
    {
        $this->getModel()->insertSessionData($this);
    }

    public function _load()
    {
        $_data = $this->getModel()->getSessionData($this);
        if (is_object($_data)) {
            if ($this->isValid($_data->session_id, $_data->session_expire)) {
                $this->_data = $_data;
                $this->_data->session_data = unserialize($_data->session_data);
            } else {
                $this->getModel()->deleteSession($_data->session_id);
                $this->_create();
            }
        } else {
            $this->_create();
        }

    }

    public function __set($name, $value)
    {
        //@TODO Check if we need to preload data when updateing...!
        //$this->_load();
        $this->getSessionData()->$name = $value;
        $this->getModel()->updateSession($this);
    }

    public function __get($name)
    {
        if (!$this->getId()) {
            $this->_load();
        }
        return $this->getSessionData()->$name;
    }

    private function _setValueToSession($name, $value)
    {
        $this->$name = $value;
    }

    public function getSessionData()
    {
        return $this->_data->session_data;
    }

    public function getValue($name)
    {
        return $this->$name;
    }

    private function _setSelfValue($name, $value)
    {
        $this->_data->$name = $value;
    }

    private function _getSelfValue($name)
    {
        return $this->_data->$name;
    }

    public function setValue($name, $value)
    {
        if($name === self::USER_ID){
            $this->_setSelfValue(self::USER_ID, $value);
        }
        $this->$name = $value;
    }

    public function getUserId()
    {
        return $this->_data->user_id;
    }

    public function getId()
    {
        return $this->_data->id;
    }

    public function getUserIp()
    {
        return $this->_data->user_ip;
    }

    public function getUserAgent()
    {
        return $this->_data->user_agent;
    }

    public function getUserSalt()
    {
        return $this->_data->user_salt;
    }

    public function getSessionId()
    {
        return $this->_data->session_id;
    }

    public function setUserId($userId){
        $this->setValue(self::USER_ID, $userId);
        $this->_data->user_id = $userId;
    }

    public function geSessionStoredValues($name = null)
    {
        if ($name) {
            if ($this->session_data) {
                return $this->session_data->$name;
            }
        } else {
            return $this->session_data;
        }
        return null;
    }

    public function getSessionLastUpdated()
    {
        return $this->_data->session_updated;
    }

    public function getSessionExpireTime()
    {
        return $this->_data->session_expire;
    }

}
