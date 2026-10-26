<?php

namespace Wbengine\Tests\Integration;

use Wbengine\Db;

class SessionConcurrencyTest extends DbTestCase
{
    private array $workers = [];
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/wbengine-session-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as [$process, $pipes]) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
        foreach (glob($this->directory . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    private function worker(string $id = ''): int
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/fixtures/session-worker.php', $this->directory, $id],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $this->workers[] = [$process, $pipes];
        return array_key_last($this->workers);
    }

    private function reply(int $worker): mixed
    {
        $stream = $this->workers[$worker][1][1];
        $read = [$stream];
        $write = $except = [];
        $this->assertSame(1, stream_select($read, $write, $except, 5), 'Worker did not reply within five seconds');
        $line = fgets($stream);
        if ($line === false) {
            $this->fail('Worker exited: ' . stream_get_contents($this->workers[$worker][1][2]));
        }
        return json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    }

    private function command(int $worker, array $command): mixed
    {
        fwrite($this->workers[$worker][1][0], json_encode($command, JSON_THROW_ON_ERROR) . "\n");
        fflush($this->workers[$worker][1][0]);
        return $this->reply($worker);
    }

    public function testDefaultLockSerializesWritersAndCloseReleasesBeforeRequestEnd(): void
    {
        $first = $this->worker();
        $id = $this->reply($first)['id'];
        $this->command($first, ['op' => 'set', 'key' => 'first', 'value' => 1]);
        $second = $this->worker($id);
        $read = [$this->workers[$second][1][1]];
        $write = $except = [];
        $this->assertSame(0, stream_select($read, $write, $except, 0, 250000), 'Default writable session must retain its lock');
        $this->command($first, ['op' => 'close']);
        $this->assertSame($id, $this->reply($second)['id']);
        $this->assertTrue(proc_get_status($this->workers[$first][0])['running']);
        $this->assertSame(1, $this->command($second, ['op' => 'read', 'key' => 'first']));
        $this->command($second, ['op' => 'set', 'key' => 'second', 'value' => 2]);
        $this->command($second, ['op' => 'close']);
        $this->command($first, ['op' => 'mutateSnapshot']);
        $third = $this->worker($id);
        $this->assertSame($id, $this->reply($third)['id']);
        $this->assertSame(1, $this->command($third, ['op' => 'read', 'key' => 'first']));
        $this->assertSame(2, $this->command($third, ['op' => 'read', 'key' => 'second']));
        $this->command($third, ['op' => 'logout']);
        // A closed first request may finish after another request has logged out.
        $this->command($first, ['op' => 'exit']);
        $this->assertSame('', stream_get_contents($this->workers[$first][1][2]));
        $this->assertSame(0, (int) Db::fetchAssoc('SELECT COUNT(*) c FROM cms_sessions')['c']);
    }
}
