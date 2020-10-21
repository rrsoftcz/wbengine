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
    public function initializeModuleRoutes(){
        try {
            Router::get('/api/users', function () {
                $this->getApiModuleController()->getUsers();
            });
            Router::post('/api/users/create', function () {
                $this->getApiModuleController()->addUser(Http::Json());
            });
            Router::patch('/api/users/patch/{id}', function (Route $route) {
                $this->getApiModuleController()->updateUser($route->getParams('id'), Http::Json());
            });
            Router::get('/api/users/{id}', function (Route $route) {
                $this->getApiModuleController()->getUserById($route->getParams('id'));
            });
            Router::delete('/api/users/{id}', function (Route $route) {
                $this->getApiModuleController()->deleteUserById($route->getParams('id'));
            });

        }catch(\Exception $e){
            $this->dispatch($e->getMessage(), Http::ACCEPTED);
        }
        return $this;
    }

}