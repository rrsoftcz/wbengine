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
    private $_autoclean = true;
    private $_created = false;

    /**
     * Probability (1 in N) that loading a session also purges expired sessions.
     */
    const GC_DIVISOR = 100;

    /**
     * The database row was read (or the lookup was done) during this request.
     */
    private $_loaded = false;

    /**
     * A row for this session exists in the database.
     */
    private $_persisted = false;

    /**
     * Data changed and has to be written at the end of the request.
     */
    private $_dirty = false;

    private $_shutdownRegistered = false;

    private $_destroyed = false;

    function __construct()
    {
//        var_dump(debug_backtrace()[0]);
        $this->_data = new \stdClass();
        $this->_setSelfValue(self::USER_AGENT, Utils::getUserAgent());
        $this->_setSelfValue(self::USER_IP, Utils::getUserIp());
        $this->_setSelfValue(self::USER_ID,ANONYMOUS);
        $this->_setSelfValue(self::USER_SALT, $this->generateUserSalt());
        $this->_setSelfValue(self::SESSION_ID, $this->sessionStart());
        $this->_setSelfValue(self::SESSION_DATA, new \stdClass());
        $this->_setSelfValue(self::SESSION_EXPIRE, $this->createExpirationTime());
        $this->_setSelfValue(self::SESSION_UPDATED, time());
    }


    public function isAutoCleanOn()
    {
        return $this->_autoclean;
    }


    /**
     * Mark the session as changed. The database is written once, at the end of the
     * request (see flush()), no matter how many values were set.
     */
    public function save(){
        $this->_dirty = true;

        if (false === $this->_shutdownRegistered) {
            $this->_shutdownRegistered = true;
            register_shutdown_function(array($this, 'flush'));
        }
    }

    /**
     * Called when the session is destroyed (logout). It stays dead for the rest of
     * the request: values set afterwards live in memory only and are never stored.
     */
    protected function discardPendingWrites(){
        $this->_dirty = false;
        $this->_persisted = false;
        $this->_destroyed = true;
    }

    /**
     * Write pending changes. The row is created on the first write, so visitors
     * that never store anything do not cost an INSERT.
     */
    public function flush(){
        if (false === $this->_dirty || true === $this->_destroyed) {
            return;
        }
        $this->_dirty = false;

        try {
            if ($this->_persisted) {
                $this->getModel()->updateSession($this);
            } else {
                $this->getModel()->insertSessionData($this);
                $this->_persisted = true;
            }
        } catch (\Throwable $e) {
            error_log(__METHOD__ . ': ' . $e->getMessage());
        }
    }

    public function setAutoClean(bool $state)
    {
        $this->_autoclean = $state;
    }

    /**
     * Read the session row once per request. A missing or expired row is not
     * created here, it is inserted by flush() when something is stored.
     */
    public function _load()
    {
        if ($this->_loaded) {
            return $this->_data;
        }
        $this->_loaded = true;

        if ($this->isAutoCleanOn() && random_int(1, self::GC_DIVISOR) === 1) {
            $this->getModel()->cleanSessions($this->getExpirationTime());
        }

        // A request without a session cookie cannot belong to a stored session.
        $_data = $this->_cookieReceived ? $this->getModel()->getSessionData($this) : null;
        if (is_object($_data)) {
            if ($this->isValid($_data->session_id, $_data->session_expire)) {
                $this->_data = $_data;
                $this->_persisted = true;
                $sessionData = unserialize($_data->session_data, array('allowed_classes' => array('stdClass')));
                $this->_data->session_data = ($sessionData instanceof \stdClass) ? $sessionData : new \stdClass();
            } else {
                $this->getModel()->deleteSession($_data->session_id);
            }
        }
        return $this->_data;
    }


    public function __set($name, $value)
    {
        // Load first: writing to a session that was not read would overwrite the stored data.
        $this->_load();
        $this->getSessionData()->$name = $value;
        $this->save();
    }

    public function __get($name)
    {
        $this->_load();
        if (isset($this->getSessionData()->$name)) {
            return $this->getSessionData()->$name;
        }
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
//	    var_dump(debug_backtrace()[0]);
//	    var_dump($name);

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
        if(self::USER_ID === $name){
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
        return $this->_data->user_id;
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

    /**
     * Issue a new session id (call after a privilege change, e.g. login) to prevent
     * session fixation. The stored database row follows the new id.
     */
    public function regenerateId()
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }
        $this->_load();
        $old = $this->_data->session_id;
        session_regenerate_id(true);
        $new = session_id();
        $this->getModel()->renameSession($old, $new);
        $this->_data->session_id = $new;
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

    public function unsetKey($name){
        unset($this->_data->session_data->$name);
        $this->save();
    }

}
