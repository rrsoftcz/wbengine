<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\Counter\BatchCounter;

class BatchCounterTest extends TestCase
{
    private string $dir;
    private int $now = 5_000;
    /** @var int[] */
    private array $flushed = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-cnt-test-' . bin2hex(random_bytes(4));
        $this->flushed = [];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function counter(): BatchCounter
    {
        return new BatchCounter($this->dir, fn () => $this->now);
    }

    private function hit(BatchCounter $c, string $key = 'a', int $hits = 5, int $seconds = 60): void
    {
        $c->increment($key, $hits, $seconds, function (int $n) { $this->flushed[] = $n; });
    }

    public function testNothingIsWrittenUntilTheBatchIsFull(): void
    {
        $c = $this->counter();

        for ($i = 0; $i < 4; $i++) {
            $this->hit($c);
        }

        $this->assertSame([], $this->flushed);

        $this->hit($c);
        $this->assertSame([5], $this->flushed);
    }

    public function testNoIncrementIsLostAcrossBatches(): void
    {
        $c = $this->counter();

        for ($i = 0; $i < 13; $i++) {
            $this->hit($c);
        }

        $this->assertSame([5, 5], $this->flushed);
        for ($i = 0; $i < 2; $i++) {
            $this->hit($c);
        }
        $this->assertSame([5, 5, 5], $this->flushed, '13 + 2 = 15 increments, three batches of 5');
    }

    public function testAgeFlushesASmallBatch(): void
    {
        $c = $this->counter();
        $this->hit($c);
        $this->hit($c);
        $this->assertSame([], $this->flushed);

        $this->now += 61;
        $this->hit($c);

        $this->assertSame([3], $this->flushed);
    }

    public function testKeysAreCountedSeparately(): void
    {
        $c = $this->counter();

        for ($i = 0; $i < 5; $i++) {
            $this->hit($c, 'article:1');
        }
        $this->hit($c, 'article:2');

        $this->assertSame([5], $this->flushed);
    }

    public function testHitsOfOneFlushesEveryIncrement(): void
    {
        $c = $this->counter();

        $this->hit($c, 'a', 1);
        $this->hit($c, 'a', 1);

        $this->assertSame([1, 1], $this->flushed);
    }

    public function testUnwritableDirectoryFallsBackToDirectWrites(): void
    {
        $log = ini_set('error_log', '/dev/null');
        try {
            $c = new BatchCounter('/proc/not/writable', fn () => $this->now);
            $this->hit($c);
            $this->hit($c);
        } finally {
            ini_set('error_log', (string)$log);
        }

        $this->assertSame([1, 1], $this->flushed);
    }

    public function testCorruptFileStartsOver(): void
    {
        $c = $this->counter();
        $this->hit($c);
        foreach (glob($this->dir . '/*.cnt') as $f) {
            file_put_contents($f, '{{{ not json');
        }

        for ($i = 0; $i < 5; $i++) {
            $this->hit($c);
        }

        $this->assertSame([5], $this->flushed);
    }

    public function testConcurrentProcessesLoseNothing(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is not available.');
        }

        $workers = 4;
        $perWorker = 25;
        $out = $this->dir . '-out';
        @mkdir($out, 0770, true);

        $pids = [];
        for ($w = 0; $w < $workers; $w++) {
            $pid = pcntl_fork();
            if (0 === $pid) {
                $c = new BatchCounter($this->dir);
                for ($i = 0; $i < $perWorker; $i++) {
                    $c->increment('shared', 7, 3600, function (int $n) use ($out, $w) {
                        file_put_contents("$out/$w.log", $n . "\n", FILE_APPEND | LOCK_EX);
                    });
                }
                // End the worker at once: the inherited PHPUnit shutdown handlers must not run.
                function_exists('posix_kill') ? posix_kill(getmypid(), SIGKILL) : exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $flushed = 0;
        foreach (glob("$out/*.log") ?: [] as $f) {
            $flushed += array_sum(array_map('intval', file($f, FILE_IGNORE_NEW_LINES)));
            unlink($f);
        }
        rmdir($out);

        $pending = json_decode(file_get_contents(glob($this->dir . '/*.cnt')[0]), true)['count'];
        $this->assertSame($workers * $perWorker, $flushed + $pending, 'every increment is either flushed or still pending');
    }
}
