<?php

namespace Wbengine\Api;

use Wbengine\Cache;
use Wbengine\User\Role;

/**
 * Cache administration: drop the cached data and pages after content was changed
 * directly in the database. It runs inside the web server, so it reaches an APCu
 * cache too, which a command line script cannot.
 */
class CacheAdmin extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    public function flush()
    {
        $this->isAuthenticated(function () {
            Cache::flush();
            $this->Api()->toJson(array("success" => true, "message" => "The cache was flushed."));
        }, Role::ADMIN);
    }
}
