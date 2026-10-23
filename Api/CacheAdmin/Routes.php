<?php

namespace Wbengine\Api\CacheAdmin;

use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\Routes\ApiRoutesInterface;
use Wbengine\Router;

class Routes extends ApiRoutesAbstract implements ApiRoutesInterface
{
    public function initializeModuleRoutes()
    {
        Router::post('/api/cache/flush', function () {
            return $this->getApiModuleController()->flush();
        });
        return $this;
    }
}
