<?php

namespace Wbengine\Cache;

use Wbengine\Cache\Exception\CacheException;

/**
 * Shared memory cache, shared by all PHP workers of one web server.
 * Entries are lost on a web server restart, which is fine for a cache.
 */
class ApcuCache implements CacheInterface
{
    private $_prefix;

    public function __construct($prefix)
    {
        if (!function_exists('apcu_fetch') || !filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            throw new CacheException('The APCu extension is not available or not enabled.');
        }
        $this->_prefix = (string) $prefix;
    }

    public function get($key, &$found)
    {
        $value = apcu_fetch($this->_prefix . $key, $found);
        return $found ? $value : null;
    }

    public function set($key, $value, $ttl)
    {
        apcu_store($this->_prefix . $key, $value, (int) $ttl);
    }

    public function flush()
    {
        $pattern = '/^' . preg_quote($this->_prefix, '/') . '/';
        foreach (new \APCUIterator($pattern, APC_ITER_KEY) as $entry) {
            apcu_delete($entry['key']);
        }
    }
}
