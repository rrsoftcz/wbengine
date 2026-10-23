<?php

namespace Wbengine\Cache;

interface CacheInterface
{
    /**
     * @param string $key
     * @param bool $found set to true when a live entry exists
     * @return mixed
     */
    public function get($key, &$found);

    /**
     * @param string $key
     * @param mixed $value arrays, scalars and null only
     * @param int $ttl seconds
     */
    public function set($key, $value, $ttl);

    /**
     * Remove every entry of this cache.
     */
    public function flush();
}
