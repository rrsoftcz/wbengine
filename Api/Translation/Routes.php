<?php

namespace Wbengine\Api\Translation;

use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\Routes\ApiRoutesInterface;
use Wbengine\Application\Env\Http;
use Wbengine\Router;
use Wbengine\Router\Route;

class Routes extends ApiRoutesAbstract implements ApiRoutesInterface
{
    public function initializeModuleRoutes()
    {
        Router::get('/api/translations/{entity}/{id}', function (Route $route) {
            $this->getApiModuleController()->getTranslations($route->getParams('entity'), $route->getParams('id'));
        });
        Router::put('/api/translations/{entity}/{id}/{locale}', function (Route $route) {
            $this->getApiModuleController()->putTranslation($route->getParams('entity'), $route->getParams('id'), $route->getParams('locale'), Http::Json());
        });
        Router::delete('/api/translations/{entity}/{id}/{locale}', function (Route $route) {
            $this->getApiModuleController()->deleteTranslation($route->getParams('entity'), $route->getParams('id'), $route->getParams('locale'));
        });
        return $this;
    }
}
