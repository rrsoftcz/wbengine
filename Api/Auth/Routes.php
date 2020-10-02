<?php


namespace Wbengine\Api\Auth;


use Wbengine\Application\Env\Http;
use Wbengine\Api\Exception\ApiException;
use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\Routes\ApiRoutesInterface;

use Wbengine\Router;

class Routes extends ApiRoutesAbstract implements ApiRoutesInterface
{
    public function init(){
        try {
            Router::post('/api/auth/login/', function () {
              return $this->getApiModule()->login(Http::Json(true));
            });
            Router::get('/api/auth/logout', function () {
                return $this->getApiModule()->logout();
            });

        }catch(ApiException $e){
            $this->Api()->getApiError($e->getMessage());
        }
    }

    /**
     * Return instance of API module Sections from Abstract class...
     * @return \Wbengine\Api\Sections 
     */
    private function getApiModule(){
        return $this->Api();
    }
}