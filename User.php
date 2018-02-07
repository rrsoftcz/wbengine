<?php

/**
 * $Id: User.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * User public class.
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine;

use Wbengine\User\Model;
use Wbengine\User\UserException;

class User
{


    /**
     * Should contain an user ID.
     * (1 => ANONIMOUS or 2 => an real user ID)
     * @var integer
     */
    private $_userId = null;

    /**
     * Site session.
     * @var array
     */
    private $_session = NULL;

    /**
     * User's data resource.
     * @var array
     */
    private $_resource = array();

    /**
     * User's data model.
     * @var Class_User_Model
     */
    private $_model = NULL;

    /**
     * Instance of Class_Site
     * @var Class_Site
     */
    private $_site = NULL;

    private $_logged = NULL;

    protected $_login = NULL;

    protected $_paswd = NULL;


    /**
     * We just set default identity here...
     * If real user identity already exist in session
     * whole data resource then will be loaded...
     */
    function __construct()
    {
        $this->_setIdentity();
    }


    /**
     * This magis method set an value togivenindex.
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value){
        $this->_resource[$key] = $value;
    }

    public function __get($name){
        return $this->_resource[$name];
    }


    /**
     * Return created session instance.
     * @return Session
     */
    public function getSession(){
        if ($this->_session instanceof Session) {
            return $this->_session;
        }
        return $this->_createSession();
    }


    /**
     * Create new session instance object if needed.
     * @see Session
     */
    private function _createSession(){
        return $this->_session = new Session();
    }


    /**
     * Return instance of Class_Site
     * @return Class_Site
     */
    public function getSite()
    {
        return $this->_site;
    }


    /**
     * Return user model
     * @return \Wbengine\User\Model
     */
    public function getModel()
    {
        if (NULL === $this->_model) {
            $this->_model = new Model();
        }

        return $this->_model;
    }

    /**
     * Return username.
     * @return string
     */
    public function getUsername()
    {
        return (string)$this->_resource['username'];
    }


    /**
     * Return user's ID.
     * @return integer
     */
    public function getUserId()
    {
        return (int)$this->_resource['user_id'];
    }


    /**
     * Return user's password
     * @return string
     */
    public function getUserCredintial()
    {
        return (string)$this->_resource['password'];
    }


    /**
     * Return user's type.
     * @return integer
     */
    public function getUserType()
    {
        return (int)$this->_resource['user_type'];
    }


    /**
     * Return user's group.
     * @return integer
     */
    public function getUserGroup()
    {
        return (int)$this->_resource['group_id'];
    }


    /**
     * Return user's first name.
     * @return string
     */
    public function getUserFirstName()
    {
        return (string)$this->_resource['firstname'];
    }


    /**
     * User's last name.
     * @return string
     */
    public function getUserLastName()
    {
        return (string)$this->_resource['lastname'];
    }


    /**
     * Return user's email address.
     * @return string
     */
    public function getUserEmail()
    {
        return (string)$this->_resource['email'];
    }


    /**
     * Return user's age.
     * @return mixed
     */
    public function getUserAge()
    {
        return (int)$this->_resource['age'];
    }


    /**
     * Return user's sex
     * @return integer
     */
    public function getUserSex()
    {
        return (int)$this->_resource['sex'];
    }


    /**
     * Return user's home address.
     * @return string
     */
    public function getUserAddress()
    {
        return (string)$this->_resource['address'];
    }


    /**
     * Return user's home city or town.
     * @return string
     */
    public function getUserCity()
    {
        return (string)$this->_resource['city'];
    }


    /**
     * Return user's home post code.
     * @return mixed
     */
    public function getUserPost()
    {
        return (string)$this->_resource['post'];
    }


    /**
     * Return user's country ID.
     * @return integer
     */
    public function getUserCountry()
    {
        return (int)$this->_resource['country'];
    }


    /**
     * Return user's IP.
     * @return string
     */
    public function getUserIp()
    {
        return (string)$this->_resource['ip'];
    }


    /**
     * returns the user status is active whether or not.
     * @return integer
     */
    public function getUserIsActive()
    {
        return (int)$this->_resource['ac_active'];
    }


    /**
     * Return user's locale as ID.
     * @return integer
     */
    public function getUserLocale()
    {
        return (int)$this->_resource['locale'];
    }


    /**
     * Return user's last login time
     * @return integer
     */
    public function getUserLastLogin()
    {
        return (int)$this->_resource['session_updated'];
    }


    public function getUsersGroup(){
        return (int)$this->group_id;
    }

    /**
     * Load user's data record from database.
     * @param integer $userId
     * @throws User\UserException
     * @return array
     */
    public function loadUserDataFromModel($userId = NULL)
    {
        if (NULL === $userId) {
            throw new UserException(__METHOD__ . ': User ID is null.');
        } else {
            return $this->getModel()->loadUserDataFromModel($userId);
        }
    }


    /**
     * This function try authenticate user and return instance
     * of filled session object, included all usser's data.
     *
     * @param string $login
     * @param string $password
     * @throws User\UserException
     * @return User
     */
    public function login($login = NULL, $password = NULL)
    {

        if (empty($login)) {
            throw new UserException('User name is empty.');
        }

        if (empty($password)) {
            throw new UserException('User password is empty.');
        }

        $this->_login = md5($login);
        $this->_paswd = md5($password);

        $this->_resource = $this->getModel()->authenticate($this);

//        if ($this->user_id)
        if($this->_resource === null){
            return false;
        }else {
            $this->_setIdentity($this->user_id);
        }


//            if ($userId > ANONYMOUS)
//            {
//                $this->_resource = $this->getModel()->getUserData($userId);
//		$this->_logged = TRUE;
//            }else{
//                $this->_resource = $this->getModel()->getUserData(ANONYMOUS);
//		$this->_logged = FALSE;
//	    }

        return $this;
    }


    public function getUserIsLogged(){
        return $this->getSession()->getValue('user_is_logged');
    }


    /**
     * This method do logout user from the existing
     * session and destroy them.
     * @void
     */
    public function logout()
    {
        $this->getSession()->destroy();
    }


    /**
     * Reaturn TRUE if user's IP is banned.
     * @return bollean
     */
    public function isUserBanned()
    {
        $db = $this->_db;
        $sql = sprintf("SELECT ban_id FROM %s
                            WHERE banned_ip = %s LIMIT 1;"
            , S_TABLE_BANS
            , $this->getUserIp()
        );

        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);

        return ($row)
            ? true
            : false;
    }


    /**
     * Return user's stored identity data as array
     * @return array
     */
    public function getIdentity(){
        if($this->_resource === null) {
            $this->_setIdentity();
        }
        return $this->_resource;
    }


    /**
     * This method tries to retrieve data from session or
     * from the model database and writes them to a local
     * variable for later use by public methods.
     */
    private function _setIdentity($userId = null)
    {

        if ((int) $userId > 1) {
//            var_dump('session_id = '.session_id());
//            var_dump('session_data = '.$this->getSession()->getSessionId());
            $this->_userId = $userId;

//            $this->getSession()->destroy(session_id());
//            $this->getSession($this)->setUserId($userId);
            $this->getSession()->setValue('user_id', $userId);
            $this->getSession()->setValue('user_is_logged', true);
        }else{
//            $this->_userId = $this->getSession()->getUserId();
            if ($this->_userId === ANONYMOUS) {
                $this->getSession()->setValue('user_is_logged', false);
                $this->_resource = $this->loadUserDataFromModel($this->_userId);
//                var_dump($this->_resource);
            }
//            var_dump($this->_userId);


        }
        return $this;
    }


    public function _getLoginName()
    {
        return $this->_login;
    }


    public function _getPassword()
    {
        return $this->_paswd;
    }


}
