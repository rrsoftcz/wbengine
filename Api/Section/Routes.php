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

            Router::post('/api/sections/', function () {
                return $this->getApiModule()->addNewSection(Http::Json(true));
            });

            Router::put('/api/sections/{id}/', function (Router\Route $route) {
                return $this->getApiModule()->updateSection($route->getParams('id'), Http::Json(true));
            });

            Router::delete('/api/sections/{id}/', function (Router\Route $route) {
                return $this->getApiModule()->deleteSection($route->getParams('id'));
            });

            Router::get('/api/sections/', function ($module) {//die(var_dump($this));
                return $this->getApiModule()->getSections(null);
            });

            Router::get('/api/sections/active/{active}/', function (Router\Route $route) {
                return $this->getApiModule()->getSections($route->getParams('active'));
            });

            Router::get('/api/sections/', function () {
                return $this->getApiModule()->getSections();
            });

            Router::get('/api/sections/{id}/', function (Router\Route $route) {
                return $this->getApiModule()->getSectionById($route->getParams('id'));
            });


        } catch (\Exception $e) {
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