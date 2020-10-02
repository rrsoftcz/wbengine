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
                return $this->getApiModuleController()->getUsers();
            });
            Router::get('/api/users/create', function () {
                return $this->getApiModuleController()->addUser(Http::Json(true));
            });
            Router::get('/api/users/{id}', function (Route $route) {
                return $this->getApiModuleController()->getUserById($route->getParams('id'));
            });
            Router::delete('/api/users/{id}', function (Route $route) {
                return $this->getApiModuleController()->deleteUserById($route->getParams('id'));
            });

        }catch(\Exception $e){
            $this->dispatch($e->getMessage(), Http::BAD_REQUEST);
        }
    }

}