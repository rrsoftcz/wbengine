<?php

namespace Wbengine\Api\User;

use Wbengine\Application\Env\Http;
use Wbengine\Api\Exception\ApiException;
use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\Routes\ApiRoutesInterface;

use Wbengine\Router\Route;
use Wbengine\Router;

class Routes extends ApiRoutesAbstract implements ApiRoutesInterface
{
    public function init(){
        try {
            Router::get('/api/users', function () {
                return $this->getApiModule()->getUsers();
            });
            Router::post('/api/users/create', function () {
                return $this->getApiModule()->addUser(Http::Json(true));
            });
            Router::get('/api/users/{id}', function (Route $route) {
                return $this->getApiModule()->getUserById($route->getParams('id'));
            });
            Router::delete('/api/users/{id}', function (Route $route) {
                return $this->getApiModule()->deleteUserById($route->getParams('id'));
            });

        }catch(ApiException $e){
            $this->Api()->getApiError($e->getMessage());
        }
    }

    /**
     * Return instance of API module User from Abstract class...
     * @return \Wbengine\Api\Sections 
     */
    private function getApiModule(){
        return $this->Api();
    }

}