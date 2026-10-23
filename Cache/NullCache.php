<?php

namespace Wbengine\Cache;

/**
 * Stores nothing. Used when the cache is disabled.
 */
class NullCache implements CacheInterface
{
    public function get($key, &$found)
    {
        $found = false;
        return null;
    }

    public function set($key, $value, $ttl)
    {
    }

    public function flush()
    {
    }
}
