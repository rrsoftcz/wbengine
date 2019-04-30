<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 20:56
 */

namespace Wbengine;
use RouteInterface;
use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\Routes\RoutesInterface;
use Wbengine\Api\Section;
use Wbengine\Api\Auth;
use Wbengine\Api\WbengineRestapiAbstract;
use Wbengine\Application\Env\Http;

class Api
{
    private $_instances = array();


    public function Register(WbengineRestapiAbstract $controller){
        $controller->getRoutes()->init();
    }

    /**
     * Register all API controllers...
     */
    public function Initialize(){
        $this->Register(new Section($this));
        $this->Register(new Auth($this));
    }

    public function toJson($value)
    {
        Http::PrintHeader(Http::HEADER_TYPE_JSON);
        die(json_encode($value));
    }

    public function toString($value)
    {
        Http::PrintHeader(Http::HEADER_TYPE_PLAIN_TEXT);
        die((string)$value);
    }

    public function getApiError($msg)
    {
        Http::PrintHeader(Http::HEADER_TYPE_JSON);
        die(json_encode(array('error' => $msg)));
    }



}