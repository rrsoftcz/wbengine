<?php


namespace Wbengine\Api\Routes;

// use Wbengine\Api;
// use Wbengine\Api\Routes\RoutesInterface;
use Wbengine\Api\WbengineRestapiAbstract;


class ApiRoutesAbstract
{
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
    public function Api(){
        return $this->_controller;
    }
}