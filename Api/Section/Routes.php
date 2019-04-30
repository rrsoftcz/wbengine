<?php
namespace Wbengine\Api\Section;

use Wbengine\Application\Env\Http;
use Wbengine\Router;
use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\Routes\ApiRoutesInterface;

class Routes extends ApiRoutesAbstract implements ApiRoutesInterface
{

    public function init(){
        try {

            Router::get('/api/', function () {
                $this->Api()->toString('Article Rest API v1.0');
            });

            Router::post('/api/sections/', function () {
                return $this->Api()->addNewSection(Http::Json(true));
            });

            Router::put('/api/sections/{id}/', function (Router\Route $route) {
                return $this->Api()->updateSection($route->getParams('id'), Http::Json(true));
            });

            Router::delete('/api/sections/{id}/', function (Router\Route $route) {
                return $this->Api()->deleteSection($route->getParams('id'));
            });

            Router::get('/api/sections/', function () {
                return $this->Api()->getSections(null);
            });

            Router::get('/api/sections/active/{active}/', function (Router\Route $route) {
                return $this->Api()->getSections($route->getParams('active'));
            });

            Router::get('/api/sections/', function () {
                return $this->Api()->getSections();
            });

            Router::get('/api/sections/{id}/', function (Router\Route $route) {
                return $this->Api()->getSectionById($route->getParams('id'));
            });


        } catch (\Exception $e) {
            $this->Api()->getApiError($e->getMessage());
        }
    }
}