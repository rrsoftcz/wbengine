<?php


namespace Wbengine\Api\Routes;

use Wbengine\Api\WbengineRestapiAbstract;


class ApiRoutesAbstract
{
    public $match = false;
    public function isRouteMatch() {
        return $this->match;
    }

    /**
     * @var WbengineRestapiAbstract
     */
    public $_controller;

    public function __construct(WbengineRestapiAbstract $controller){
        $this->_controller = $controller;
    }

    /**
     * Return instance of Api Controller
     * @return WbengineRestapiAbstract
     */
    public function getApiModuleController(){
        return $this->_controller;
    }

    /**
     * Just dispatch a message to header....
     * @param mixed $data
     */
    public function dispatch($data) {
        $this->getApiModuleController()->Api()->printApiError($data);
    }
}