<?php

namespace Wbengine\Counter;

/**
 * Collects increments in small files and hands the sum to a flush callback, so a
 * hot counter (page views) costs one database write per batch instead of one per
 * request.
 *
 * A batch is flushed when it reaches $hits increments or is older than $seconds,
 * whichever comes first. The files are locked while they are updated, so concurrent
 * requests are counted correctly, and the lock is released before the callback runs.
 *
 * Increments still pending when the server crashes are lost (they are only views).
 * If the directory is not writable the callback is called for every increment,
 * which is exactly the behaviour without batching.
 */
class BatchCounter
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

    private function _file($key)
    {
        return $this->_dir . '/' . hash('sha256', $key) . '.cnt';
    }

    /**
     * Count one increment of $key.
     *
     * @param string $key
     * @param int $hits flush when this many increments are pending
     * @param int $seconds flush when the oldest pending increment is this old
     * @param callable $flush function (int $count) writing the sum to its destination
     */
    public function increment($key, $hits, $seconds, callable $flush)
    {
        if (!(is_dir($this->_dir) || @mkdir($this->_dir, 0770, true) || is_dir($this->_dir)) || !is_writable($this->_dir)) {
            error_log(__CLASS__ . ': the counter directory "' . $this->_dir . '" is not writable, counting is not batched.');
            $flush(1);
            return;
        }

        $handle = @fopen($this->_file($key), 'c+');
        if (false === $handle) {
            $flush(1);
            return;
        }

        $pending = 0;
        try {
            if (!flock($handle, LOCK_EX)) {
                $flush(1);
                return;
            }

            $entry = json_decode((string) stream_get_contents($handle), true);
            $now = (int) call_user_func($this->_clock);
            if (!is_array($entry) || !isset($entry['since'], $entry['count'])) {
                $entry = array('since' => $now, 'count' => 0);
            }
            $entry['count']++;

            if ($entry['count'] >= (int) $hits || $now - $entry['since'] >= (int) $seconds) {
                $pending = $entry['count'];
                $entry = array('since' => $now, 'count' => 0);
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($entry));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if ($pending > 0) {
            $flush($pending);
        }
    }
}
