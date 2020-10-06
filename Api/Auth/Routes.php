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
            Router::post('/api/auth/login', function () {
              return $this->getApiModuleController()->login(Http::Json(true));
            });
            Router::post('/api/auth/logout', function () {
                return $this->getApiModuleController()->logout();
            });

        }catch(ApiException $e){
            $this->dispatch($e->getMessage(), Http::BAD_REQUEST);
        }
        return $this;
    }

}