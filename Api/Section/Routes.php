<?php
namespace Wbengine\Api\Section;

use Wbengine\Application\Env\Http;
use Wbengine\Router;
use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\Routes\ApiRoutesInterface;

class Routes extends ApiRoutesAbstract implements ApiRoutesInterface
{

    public function initializeModuleRoutes(){
        try {

            Router::post('/api/sections/', function () {
                return $this->getApiModuleController()->addNewSection(Http::Json(true));
            });

            Router::put('/api/sections/{id}/', function (Router\Route $route) {
                return $this->getApiModuleController()->updateSection($route->getParams('id'), Http::Json(true));
            });

            Router::delete('/api/sections/{id}/', function (Router\Route $route) {
                return $this->getApiModuleController()->deleteSection($route->getParams('id'));
            });

            Router::get('/api/sections/', function ($module) {
                return $this->getApiModuleController()->getSections(null);
            });

            Router::get('/api/sections/active/{active}/', function (Router\Route $route) {
                return $this->getApiModuleController()->getSections($route->getParams('active'));
            });

            Router::get('/api/sections/', function () {
                return $this->getApiModuleController()->getSections();
            });

            Router::get('/api/sections/{id}/', function (Router\Route $route) {
                return $this->getApiModuleController()->getSectionById($route->getParams('id'));
            });


        } catch (\Exception $e) {
            $this->dispatch($e->getMessage(), Http::BAD_REQUEST);
        }
        return $this;
    }

}