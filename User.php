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
     * Site session.
     * @var Session
     */
    private $_session = NULL;

    /**
     * User's data resource.
     * @var array
     */
    private $_resource = null;

    /**
     * User's data model.
     * @var Model
     */
    private $_model = NULL;

    /**
     * Instance of Site
     * @var Site
     */
    private $_site = NULL;

    /**
     * User name as string
     * @var string
     */
    protected $_login = NULL;

    /**
     * User's MD5 password
     * @var string
     */
    protected $_paswd = NULL;

    private $_parent;

    private $_is_user_logged = false;

    private $_auth =  null;

    protected $_jwt_token = null;
    protected $_refresh_token = null;
    protected $useJwt = false;

    /**
     * We just set default identity here...
     * If real user identity already exist in session
     * whole data resource then will be loaded...
     */
    function __construct($parent)
    {
        $this->_parent = $parent;

        if($this->_is_user_logged = $this->getUserIsLogged()){
            $this->user_id = $this->getSession()->getUserId();
        }else{
            $this->user_id = ANONYMOUS;
        }
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
        if ($this->_resource === null) {
            return array('empty' => true);
        }else{
            if($this->_needReloadResource() === true){
                $this->loadUserDataFromModel((int)$this->_resource['user_id']);
            }
            if(key_exists($name, $this->_resource)){
                return $this->_resource[$name];
            }
        }
    }


    private function getAuth() {
        if(null === $this->_auth) {
            return $this->_auth = new Auth();
        }
        return $this->_auth;
    }

    private function _needReloadResource(){
        if(key_exists('user_id', $this->_resource) && $this->getUserId() > 0) {
            if (false === key_exists('email', $this->_resource)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Return created session instance.
     * @return Session
     */
    public function getSession(){
        return $this->_getParent()->getSession();
    }


    private function _getParent(){
        return $this->_parent;
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
    public function getUserName(){
        return (string)$this->username;
    }


    /**
     * Return user's ID.
     * @return integer
     */
    public function getUserId(){
        return (key_exists('user_id', $this->_resource)) ? (int)$this->_resource['user_id'] : 0;
    }


    /**
     * Return user's password
     * @return string
     */
    public function getUserPassword(){
        return (string)$this->password;
    }


    /**
     * Return user's type.
     * @return integer
     */
    public function getUserType(){
        return (int)$this->user_type;
    }


    /**
     * Return user's group.
     * @return integer
     */
    public function getUserGroup(){
        return (int)$this->group_id;
    }


    /**
     * Return user's first name.
     * @return string
     */
    public function getUserFirstName($space = true)
    {
        if ($this->firstname) {
            $_fullname = ($space) ? $this->firstname . "&nbsp;" : $this->firstname;
        } else {
            return null;
        }
        return (string)$_fullname;
    }


    /**
     * User's last name.
     * @return string
     */
    public function getUserLastName(){
        return (string)$this->lastname;
    }


    /**
     * Return user's email address.
     * @return string
     */
    public function getUserEmail(){
        return (string)$this->email;
    }


    /**
     * Return user's age.
     * @return mixed
     */
    public function getUserAge(){
        return (int)$this->age;
    }


    /**
     * Return user's sex
     * @return integer
     */
    public function getUserSex(){
        return (int)$this->sex;
    }


    /**
     * Return user's home address.
     * @return string
     */
    public function getUserAddress(){
        return (string)$this->address;
    }


    /**
     * Return user's home city or town.
     * @return string
     */
    public function getUserCity(){
        return (string)$this->city;
    }


    /**
     * Return user's home post code.
     * @return mixed
     */
    public function getUserPostCode(){
        return (string)$this->postcode;
    }


    /**
     * Return user's country ID.
     * @return integer
     */
    public function getUserCountry(){
        return (int)$this->country;
    }


    /**
     * Return user's IP.
     * @return string
     */
    public function getUserIp(){
        return (string)$this->getSession()->getUserIp();
    }


    /**
     * returns the user status is active whether or not.
     * @return integer
     */
    public function getUserIsActive(){
        return (int)$this->ac_active;
    }


    /**
     * Return user's locale as ID.
     * @return integer
     */
    public function getUserLocale(){
        return (int)$this->locale;
    }


    /**
     * Return user's last login time
     * @return integer
     */
    public function getUserLastLogin(){
        return (int)$this->getSession()->getSessionLastUpdated();
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
    public function loadUserDataFromModel($userId = null){
        $userId = ($userId) ? $userId : $this->getUserId();
        $this->_resource = $this->getModel()->loadUserDataFromModel($userId);
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

        $_usersData = $this->getModel()->authenticate($this);

        if($_usersData !== null) {
            $this->_resource = $_usersData;
            $this->_setIdentity($this->getUserId());
            if($this->useJwt) {
                try {
//                    $this->_jwt_token = $this->createJwtToken();
//                    $this->_refresh_token = $this->createRefreshToken();
                } catch (UserException $e) {
                    die($e->getMessage());
                }
            }
            return true;
        }else{
            $this->_resetIdentity();
            return false;
        }
    }

    public function createJwtToken($expiration = null){
        if($expiration){
            $this->getAuth()->setExpiredTime($expiration);
        }
        return $this->getAuth()
            ->setPayloadData($this->createPayloadData())
            ->getJwtToken();
    }

    public function createRefreshToken($expiration = null){
        if($expiration){
            $this->getAuth()->setExpiredTime($expiration);
        }
        return $this->getAuth()
            ->setPayloadData($this->createPayloadData())
            ->getRefreshToken();
    }

    public function createPayloadData(){
        return array(
            "user_id" => $this->getUserId(),
            "username" => $this->getUserName(),
            "email" => $this->getUserEmail()
        );
    }

    public function useJwt(bool $val){
        $this->useJwt = $val;
        return $this;
    }

    public function isUsedJwt(){
        return $this->useJwt;
    }

    public function getJwtToken($expiration = null) {
        return $this->_jwt_token = $this->createJwtToken($expiration);
    }

    public function getRefreshToken($expiration = null) {
        return $this->_refresh_token = $this->createRefreshToken($expiration);
    }

    /**
     * Return whatever User is logged in
     * @return bool
     */
    public function getUserIsLogged(){
        return (bool)$this->getSession()->getValue('user_is_logged');
    }

    public function isLogged(){
	    return $this->_is_user_logged;
    }

    /**
     * This method do logout user from the existing
     * session and destroy them.
     * @void
     */
    public function logout()
    {
        $this->getSession()->destroy();
        $this->_resetIdentity();
        return true;
    }

    private function _resetIdentity(){
        $this->getSession()->setValue('user_is_logged', false);
    }


    public function _getLoginName()
    {
        return $this->_login;
    }


    public function _getPassword()
    {
        return $this->_paswd;
    }


    /**
     * Return user's stored identity data as array
     * @return array
     */
    public function getIdentity(){
        if($this->_resource === null) {
            $this->_setIdentity(ANONYMOUS);
        }
        return $this->_resource;
    }


    /**
     * This method tries to retrieve data from session or
     * from the model database and writes them to a local
     * variable for later use by public methods.
     */
    private function _setIdentity(int $userId)
    {
        $this->getSession()->setValue('user_id', $userId);
        $this->getSession()->setValue('user_is_logged', true);
        return $this;
    }

    public function setLoginPassword(string $password) {
        $this->_paswd = md5($password);
    }

    public function setLoginName(string $name) {
        $this->_login = md5($name);
    }

    public function getFullName(){
        return $this->getUserFirstName() . $this->getUserLastName();
    }

    public function getWelcomeName(){
        if($this->getFullName()){
            return $this->getFullName();
        }else{
            if($this->getUserName()){
                return (string)$this->getUserName();
            }else{
                return (string)$this->getUserEmail();
            }
        }
    }


    public function toArray(){
        if($this->getUserIsLogged() === true){
            return $this->_resource;
        }
        return $this->_resource;
    }



}
