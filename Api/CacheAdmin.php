<?php

namespace Wbengine\Api;

use Wbengine\Audit\ChangeLog;
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
        $this->isAuthenticated(function ($payload, $actor) {
            Cache::flush();
            ChangeLog::recordQuietly('cache', 'all', 'flush', null, null, $this->changeActor($actor), 'v1');
            $this->Api()->toJson(array("success" => true, "message" => "The cache was flushed."));
        }, Role::ADMIN);
    }
}
