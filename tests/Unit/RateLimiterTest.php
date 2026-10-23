<?php

namespace Wbengine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wbengine\RateLimit\Limiter;
use Wbengine\User\TooManyAttemptsException;

class RateLimiterTest extends TestCase
{
    private string $dir;
    private int $now = 1_000_000;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wb-rl-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function limiter(): Limiter
    {
        return new Limiter($this->dir, fn () => $this->now);
    }

    public function testAttemptsAreAllowedUntilTheLimitIsReached(): void
    {
        $l = $this->limiter();

        for ($i = 1; $i <= 4; $i++) {
            $l->hit('k', 900);
            $this->assertSame(0, $l->retryAfter('k', 5, 900), "attempt $i must still be allowed");
        }

        $l->hit('k', 900);
        $this->assertSame(900, $l->retryAfter('k', 5, 900));
    }

    public function testWaitShrinksAndTheWindowExpires(): void
    {
        $l = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $l->hit('k', 900);
        }

        $this->now += 400;
        $this->assertSame(500, $l->retryAfter('k', 5, 900));

        $this->now += 500;
        $this->assertSame(0, $l->retryAfter('k', 5, 900));
    }

    public function testNewWindowStartsAfterTheOldOneExpired(): void
    {
        $l = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $l->hit('k', 900);
        }
        $this->now += 901;

        $l->hit('k', 900);

        $this->assertSame(0, $l->retryAfter('k', 5, 900), 'the counter restarted at 1');
    }

    public function testKeysAreIndependent(): void
    {
        $l = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $l->hit('login:account:alice', 900);
        }

        $this->assertGreaterThan(0, $l->retryAfter('login:account:alice', 5, 900));
        $this->assertSame(0, $l->retryAfter('login:account:bob', 5, 900));
    }

    public function testClearForgetsTheFailures(): void
    {
        $l = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $l->hit('k', 900);
        }

        $l->clear('k');

        $this->assertSame(0, $l->retryAfter('k', 5, 900));
    }

    public function testHostileKeysStayInsideTheDirectory(): void
    {
        $l = $this->limiter();

        $l->hit('../../../etc/passwd', 900);
        $l->hit("a\0b", 900);

        $files = glob($this->dir . '/*');
        $this->assertCount(2, $files);
        foreach ($files as $f) {
            $this->assertStringEndsWith('.rl', $f);
        }
    }

    public function testUnwritableDirectoryFailsOpen(): void
    {
        $log = ini_set('error_log', '/dev/null');
        try {
            $l = new Limiter('/proc/not/writable', fn () => $this->now);
            $l->hit('k', 900);
            $this->assertSame(0, $l->retryAfter('k', 1, 900));
        } finally {
            ini_set('error_log', (string)$log);
        }
    }

    public function testCorruptCounterFileDoesNotBlockAnyone(): void
    {
        $l = $this->limiter();
        $l->hit('k', 900);
        foreach (glob($this->dir . '/*.rl') as $f) {
            file_put_contents($f, 'not json {{{');
        }

        $this->assertSame(0, $l->retryAfter('k', 1, 900));
        $l->hit('k', 900);
        $this->assertSame(900, $l->retryAfter('k', 1, 900));
    }

    public function testExceptionCarriesTheWaitingTime(): void
    {
        $e = new TooManyAttemptsException(37);
        $this->assertSame(37, $e->getRetryAfter());
        $this->assertSame(1, (new TooManyAttemptsException(0))->getRetryAfter());
    }
}
