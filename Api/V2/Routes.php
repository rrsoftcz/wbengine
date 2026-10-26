<?php

namespace Wbengine\Api\V2;

use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\Routes\ApiRoutesInterface;
use Wbengine\Application\Env\Http;

/** Own the /api/v2 prefix, including unknown paths and unsupported methods. */
class Routes extends ApiRoutesAbstract implements ApiRoutesInterface
{
    public function initializeModuleRoutes()
    {
        $path = (string) parse_url(Http::Uri(), PHP_URL_PATH);
        if ('/api/v2' === $path || str_starts_with($path, '/api/v2/')) {
            $this->match = true;
            $this->getApiModuleController()->dispatch(
                Http::getRequestType(),
                $path,
                (string) Http::getHeader('Authorization'),
                Http::isSecure(),
                Http::getHeader('X-WBengine-Actor')
            );
        }
        return $this;
    }
}
