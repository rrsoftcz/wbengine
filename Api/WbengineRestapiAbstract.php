<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 20:59
 */
namespace Wbengine\Api;

use Wbengine\Api;
use Wbengine\Api\Model\ApiSectionModel;
use Wbengine\Api\Model\ApiUserModel;
use Wbengine\Session;
// use Wbengine\Box\WbengineBoxAbstract;

class WbengineRestapiAbstract
{
    /**
     * @var Api
     */
    private $_api;

    public function __construct(Api $api) {
        $this->_api = $api;
    }

    public function Api() {
        if($this->_api){
            return $this->_api;
        }else{
            return $this->_api = new Api();
        }
    }


    public function getApiError($msg){
        return $this->Api()->getApiError($msg);
    }

    /**
     * @return Api\Routes\ApiRoutesInterface
     */
    public function getApiRoutes($apiModule){
        return $this->getInstanceOfApiRoutes($apiModule);
    }

    public function getSectionModel() {
        return new ApiSectionModel();
    }

    public function getUserModel() {
        return new ApiUserModel();
    }

    public function createNameSpace($namespace){
        $name = 'Wbengine\\Api\\'.ucfirst($namespace).'\\Routes';
        if(class_exists($name, true)){
            return $name;
        }else{
            throw new Api\Exception\ApiException('Can not instantinate Api routes module: '.$name.'. Class not found.');
        }
    }

    public function getLastPartFromNamespace($namespace){
        return end(explode('\\', $namespace));
    }

    public function getSession() {
        return new Session();
    }

    public function getInstanceOfApiRoutes($apiModule){
        $class = $this->createNameSpace($this->getLastPartFromNamespace(get_class($apiModule)));
        return new $class($this);
    }

}