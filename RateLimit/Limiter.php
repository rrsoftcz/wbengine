<?php

namespace Wbengine\RateLimit;

/**
 * Fixed window failure counter stored in small files, one per key.
 *
 * The window starts with the first failure. After $max failures further attempts
 * are refused until the window ends. Files are locked while they are updated, so
 * concurrent requests are counted correctly. The counters are shared by every PHP
 * worker of one server. With several servers use a shared directory.
 *
 * It fails open: if the directory is not writable the problem is logged and nobody
 * is blocked, because a broken limiter must not lock every user out.
 */
class Limiter
{
    private $_dir;
    private $_clock;

    /**
     * @param string $dir
     * @param callable|null $clock returns the current unix time, for tests
     */
    public function __construct($dir, ?callable $clock = null)
    {
        $this->_dir = rtrim((string) $dir, '/');
        $this->_clock = $clock ?? 'time';
    }

    private function _now()
    {
        return (int) call_user_func($this->_clock);
    }

    private function _file($key)
    {
        return $this->_dir . '/' . hash('sha256', $key) . '.rl';
    }

    private function _ensureDir()
    {
        if (is_dir($this->_dir) || @mkdir($this->_dir, 0770, true) || is_dir($this->_dir)) {
            return is_writable($this->_dir);
        }
        return false;
    }

    /**
     * Seconds the caller has to wait, 0 when another attempt is allowed.
     */
    public function retryAfter($key, $max, $window)
    {
        $entry = $this->_read($key);
        if (null === $entry) {
            return 0;
        }
        $left = $entry['start'] + (int) $window - $this->_now();
        return ($entry['count'] >= (int) $max && $left > 0) ? $left : 0;
    }

    /**
     * Count one failure.
     */
    public function hit($key, $window)
    {
        if (!$this->_ensureDir()) {
            error_log(__CLASS__ . ': the rate limit directory "' . $this->_dir . '" is not writable, limiting is off.');
            return;
        }

        $handle = @fopen($this->_file($key), 'c+');
        if (false === $handle) {
            return;
        }

        try {
            if (flock($handle, LOCK_EX)) {
                $entry = json_decode((string) stream_get_contents($handle), true);
                $now = $this->_now();
                if (!is_array($entry) || !isset($entry['start'], $entry['count']) || $entry['start'] + (int) $window <= $now) {
                    $entry = array('start' => $now, 'count' => 0);
                }
                $entry['count']++;

                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode($entry));
                fflush($handle);
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }

        $this->_purge();
    }

    /**
     * Forget the failures of a key (after a successful login).
     */
    public function clear($key)
    {
        @unlink($this->_file($key));
    }

    private function _read($key)
    {
        $raw = @file_get_contents($this->_file($key));
        $entry = (false === $raw) ? null : json_decode($raw, true);
        return (is_array($entry) && isset($entry['start'], $entry['count'])) ? $entry : null;
    }

    /**
     * Now and then remove counters that are long expired, so the directory stays small.
     */
    private function _purge()
    {
        if (random_int(1, 50) !== 1) {
            return;
        }
        $limit = $this->_now() - 86400;
        foreach (glob($this->_dir . '/*.rl') ?: array() as $file) {
            if (@filemtime($file) < $limit) {
                @unlink($file);
            }
        }
    }
}
