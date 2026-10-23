<?php

namespace Wbengine\Cache;

use Wbengine\Cache\Exception\CacheException;

/**
 * One PHP file per entry. Files are written atomically and read with include, so
 * with opcache a hit costs no file parsing. Works on every host, shared by all
 * PHP workers and processes.
 */
class FileCache implements CacheInterface
{
    private $_dir;

    public function __construct($dir)
    {
        $dir = rtrim((string) $dir, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new CacheException(sprintf('The cache directory "%s" cannot be created.', $dir));
        }
        if (!is_writable($dir)) {
            throw new CacheException(sprintf('The cache directory "%s" is not writable.', $dir));
        }
        $this->_dir = $dir;
    }

    private function _file($key)
    {
        return $this->_dir . '/' . hash('sha256', $key) . '.cache.php';
    }

    public function get($key, &$found)
    {
        $found = false;
        $file = $this->_file($key);
        if (!is_file($file)) {
            return null;
        }

        $entry = @include $file;
        if (!is_array($entry) || !isset($entry['e']) || $entry['e'] < time()) {
            return null;
        }

        $found = true;
        return $entry['v'];
    }

    public function set($key, $value, $ttl)
    {
        $code = '<?php return ' . var_export(array('e' => time() + (int) $ttl, 'v' => $value), true) . ';';
        $tmp = tempnam($this->_dir, 'tmp');
        if (false === $tmp) {
            return;
        }
        if (false === file_put_contents($tmp, $code) || !@rename($tmp, $this->_file($key))) {
            @unlink($tmp);
            return;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->_file($key), true);
        }
    }

    public function flush()
    {
        foreach (glob($this->_dir . '/*.cache.php') ?: array() as $file) {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
            @unlink($file);
        }
    }
}
