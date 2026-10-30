<?php
namespace Wbengine\Api;

use Wbengine\Api;
use Wbengine\Session;

/**
 * Base of the API v2 controller and of the application boxes. It carries the Api instance and the session,
 * and resolves the routes class of an API module.
 */
class WbengineRestapiAbstract {

    /** @var Api */
    private $_api;

    /** @var Session|null */
    private $_session = null;

    public function __construct(Api $api) {
        $this->_api = $api;
    }

    /** @return Api */
    public function Api() {
        if($this->_api instanceof Api){
            return $this->_api;
        }else{
            return $this->_api = new Api();
        }
    }

    /** @return Session */
    public function getSession() {
        if($this->_session instanceof Session) {
            return $this->_session;
        }else{
            return $this->_session = new Session();
        }
    }

    public function getApiRouteModule($apiModule){
        return $this->getInstanceOfApiRouteModule($apiModule);
    }

    /**
     * Build namespace to instantiate the routes of an API module.
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

    public function getLastPartFromNamespace($namespace){
        $parts = explode('\\', $namespace);
        return end($parts);
    }

    /** @throws Exception\ApiException */
    public function getInstanceOfApiRouteModule($apiModule){
        $class = $this->createNameSpace($this->getLastPartFromNamespace(get_class($apiModule)));
        return new $class($this);
    }
}
