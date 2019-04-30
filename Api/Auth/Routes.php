<?php


namespace Wbengine\Api\Auth;


use Wbengine\Application\Env\Http;
use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\Routes\ApiRoutesInterface;

use Wbengine\Router;

class Routes extends ApiRoutesAbstract implements ApiRoutesInterface
{
    public function init(){
        try {
            Router::post('/api/auth/login/', function () {
               die('Login');
            });

        }catch(Exception $e){
            $this->Api()->getApiError($e->getMessage());
        }
    }
}