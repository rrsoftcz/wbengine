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
     * Just a print data...
     * @param array $data
     * @param string | int $code
     */
    public function dispatch($data, $code) {
        $this->getApiModuleController()->Api()->printApiError($data, $code);
    }
}