<?php

namespace Wbengine;

use Wbengine\Cache\ApcuCache;
use Wbengine\Cache\CacheInterface;
use Wbengine\Cache\Exception\CacheException;
use Wbengine\Cache\FileCache;
use Wbengine\Cache\NullCache;

/**
 * Cache for data that changes rarely: the site table, the menu, the sections and
 * the box lists. It is off by default and switched on in the settings:
 *
 *   "cache": { "enabled": true, "driver": "apcu", "ttl": 300 }
 *   "cache": { "enabled": true, "driver": "file", "ttl": 300, "dir": "/var/cache/myapp" }
 *
 * Only arrays, scalars and null are stored. Call Cache::flush() after changing the
 * cached tables outside the API (the API flushes by itself).
 */
class Cache
{
    const DEFAULT_TTL = 300;

    /** @var CacheInterface|null */
    private static $_driver = null;

    private static $_ttl = self::DEFAULT_TTL;


    /**
     * Use another driver (tests, custom backends).
     */
    public static function setDriver(?CacheInterface $driver, $ttl = self::DEFAULT_TTL)
    {
        self::$_driver = $driver;
        self::$_ttl = (int) $ttl;
    }


    public static function isEnabled()
    {
        return !(self::_driver() instanceof NullCache);
    }


    /**
     * Return the cached value for $key or, on a miss, the result of $loader, which is
     * then stored. Empty results (null, false, empty array) are not stored unless
     * $storeEmpty is set, so a request for something that does not exist cannot fill
     * the cache. Set $storeEmpty only when the number of possible keys is bounded
     * (for example one key per section id).
     *
     * @param string $key
     * @param callable $loader
     * @param int|null $ttl seconds, the configured default when null
     * @param bool $storeEmpty also store empty arrays
     * @return mixed
     */
    public static function remember($key, callable $loader, $ttl = null, $storeEmpty = false)
    {
        $driver = self::_driver();
        if ($driver instanceof NullCache) {
            return $loader();
        }

        $key = (string) $key;
        $value = $driver->get($key, $found);
        if ($found) {
            return $value;
        }

        $value = $loader();
        $storable = $storeEmpty ? is_array($value) : (!empty($value) && (is_array($value) || is_scalar($value)));
        if ($storable) {
            $driver->set($key, $value, (null === $ttl) ? self::$_ttl : (int) $ttl);
        }
        return $value;
    }


    public static function flush()
    {
        self::_driver()->flush();
    }


    private static function _driver()
    {
        if (null === self::$_driver) {
            self::$_driver = self::_createFromConfig();
        }
        return self::$_driver;
    }


    private static function _createFromConfig()
    {
        $settings = Config::getCacheSettings();
        if (empty($settings['enabled'])) {
            return new NullCache();
        }

        self::$_ttl = max(1, (int) ($settings['ttl'] ?? self::DEFAULT_TTL));

        // Applications sharing one APCu or one temp directory must not see each other's data.
        $db = Config::getDbCredentials();
        $prefix = 'wb' . substr(hash('sha256', $db->hostname . '/' . $db->database), 0, 8) . ':';

        try {
            switch (strtolower((string) ($settings['driver'] ?? 'file'))) {
                case 'apcu':
                    return new ApcuCache($prefix);
                case 'file':
                    $dir = $settings['dir'] ?? (sys_get_temp_dir() . '/wbengine-cache-' . rtrim($prefix, ':'));
                    return new FileCache($dir);
                default:
                    throw new CacheException(sprintf('Unknown cache driver "%s".', $settings['driver']));
            }
        } catch (CacheException $e) {
            // A broken cache must never take the site down.
            error_log(__CLASS__ . ': cache disabled: ' . $e->getMessage());
            return new NullCache();
        }
    }
}
