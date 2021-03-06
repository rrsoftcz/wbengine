<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 20:56
 */

namespace Wbengine;
use RouteInterface;
use Wbengine\Api\Exception\ApiException;
// use Wbengine\Api\Routes\ApiRoutesAbstract;
// use Wbengine\Api\Routes\RoutesInterface;
use Wbengine\Api\Section;
use Wbengine\Api\Auth;
use Wbengine\Api\User;
use Wbengine\Api\WbengineRestapiAbstract;
use Wbengine\Application\Env\Http;

class Api
{
    // private $_instances = array();


    public function Register(WbengineRestapiAbstract $apiModule){
        $apiModule->getApiRoutes($apiModule)->init();
    }

    /**
     * Register all API controllers...
     */
    public function Initialize(){
        try {
            $this->registerRootApi();
            $this->Register(new Section($this));
            $this->Register(new User($this));
            $this->Register(new Auth($this));
        }catch(ApiException $e){
            throw new ApiException($e->getMessage());
        }
    }

    public function registerRootApi(){
        Router::get('/api/', function () {
            return $this->toString('WBengine Rest API v1.0');
        });

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