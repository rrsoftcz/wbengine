<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 20:59
 */
namespace Wbengine\Api;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
// use http\Header;
use Wbengine\Api;
use Wbengine\Api\Model\ApiSectionModel;
use Wbengine\Api\Model\ApiUserModel;
use Wbengine\Application\Env\Http;
use Wbengine\Auth\Exception\AuthException;
use Wbengine\Session;
use Wbengine\Auth;
// use Wbengine\Box\WbengineBoxAbstract;

class WbengineRestapiAbstract {


    /**
     * @var Api
     */
    private $_api;

    private $_headers = array();
    /**
     * @var Auth
     */
    private $_auth = null;
    private $_session = null;

    public function __construct(Api $api) {
        $this->_api = $api;
        $this->_headers = getallheaders();
    }


    /**
     * Return instance of class Auth
     * The instance is created only onece...
     * @return Auth
     */
    public function wbAuth() {
        if($this->_auth instanceof Auth) {
            return $this->_auth;
        } else {
            return $this->_auth = new Auth();
        }
    }

    /**
     * Return instance of class Api
     * The instance is created only once...
     * @return Api
     */
    public function Api() {
        if($this->_api instanceof Api){
            return $this->_api;
        }else{
            return $this->_api = new Api();
        }
    }

    /**
     * Return instance of class Session
     * The instance is created only once...
     * @return Session
     */
    public function getSession() {
        if($this->_session instanceof Session) {
            return $this->_session;
        }else{
            return $this->_session = new Session();
        }
    }


    /**
     * Check whatever http request include authentication JWT token.
     * Return the Array with decrypted payload or NULL.
     * @param $callable
     * @return Array|null
     */
    public function isAuthenticated($callable) {
        $_token = Http::getBearerToken();

        if(empty($_token)) {
            $this->Api()->toJson(Array("success" => false, "message" => "Empty token."), Http::UNAUTHORIZED);
        }

        try {
            if(is_callable($callable)){
                return $callable($this->wbAuth()->getDecodedData(Http::getBearerToken()));
            }
        }catch (SignatureInvalidException | ExpiredException | BeforeValidException $e){
            $this->Api()->toJson(Array("success" => false, "message" => $e->getMessage()), Http::UNAUTHORIZED);
//        }catch (\Exception $e){
//            $this->Api()->toJson(Array("success" => false, "message" => $e->getMessage()), Http::OK); //@TODO ..or Http::BAD_REQUEST???
        }
        return null;
    }


    /**
     * @return Api\Routes\ApiRoutesInterface
     */
    public function getApiRouteModule($apiModule){
        return $this->getInstanceOfApiRouteModule($apiModule);
    }


    /**
     * Return the Section Model.
     * @return ApiSectionModel
     */
    public function getSectionModel() {
        return new ApiSectionModel();
    }


    /**
     * Return the User Model.
     * @return ApiUserModel
     */
    public function getUserModel() {
        return new ApiUserModel();
    }


    /**
     * Build namespace to instantinate requested api module.
     * @param string $namespace
     * @return string
     * @throws Exception\ApiException
     */
    public function createNameSpace($namespace){
        $name = 'Wbengine\\Api\\'.ucfirst($namespace).'\\Routes';
        if(class_exists($name, true)){
            return $name;
        }else{
            throw new Api\Exception\ApiException('Can not instantinate Api routes module: '.$name.'. Class not found.');
        }
    }


    /**
     * Return the last part from given namespace.
     * @param $namespace
     * @return mixed|string
     */
    public function getLastPartFromNamespace($namespace){
        return end(explode('\\', $namespace));
    }


    /**
     * Create instance of an Route object.
     * @param $apiModule
     * @return mixed
     * @throws Exception\ApiException
     */
    public function getInstanceOfApiRouteModule($apiModule){
        $class = $this->createNameSpace($this->getLastPartFromNamespace(get_class($apiModule)));
        return new $class($this);
    }

}