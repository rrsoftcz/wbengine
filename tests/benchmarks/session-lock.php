<?php

// Run only against the test configuration: compare two CLI requests sharing a cookie.
require dirname(__DIR__) . '/bootstrap.php';

$directory = sys_get_temp_dir() . '/wbengine-session-bench-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$workers = [];
$start = static function (string $id = '') use ($directory, &$workers): array {
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/fixtures/session-worker.php', $directory, $id],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start session worker');
    }
    return $workers[] = [$process, $pipes];
};
$read = static function (array $worker): mixed {
    $streams = [$worker[1][1]];
    $write = $except = [];
    if (stream_select($streams, $write, $except, 5) !== 1 || ($line = fgets($streams[0])) === false) {
        throw new RuntimeException('Worker failed or did not reply within five seconds');
    }
    return json_decode($line, true, 512, JSON_THROW_ON_ERROR);
};
$send = static function (array $worker, array $command) use ($read): mixed {
    fwrite($worker[1][0], json_encode($command, JSON_THROW_ON_ERROR) . "\n");
    fflush($worker[1][0]);
    return $read($worker);
};
$results = [];
try {
    foreach (['held', 'early-close'] as $mode) {
        for ($i = 0; $i < 5; $i++) {
            $first = $start();
            $id = $read($first)['id'];
            $send($first, ['op' => 'set', 'key' => 'first', 'value' => 1]);
            if ($mode === 'early-close') {
                $send($first, ['op' => 'close']);
            }
            $second = $start($id);
            usleep(250000); // Simulated remaining application work in the first request.
            if ($mode === 'held') {
                $send($first, ['op' => 'close']);
            }
            $results[$mode][] = round($read($second)['waitMs'], 3);
            $send($second, ['op' => 'logout']);
            $send($first, ['op' => 'exit']);
            $send($second, ['op' => 'exit']);
        }
    }
    echo json_encode(['handler' => 'files', 'php' => PHP_VERSION, 'waitMs' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
} finally {
    foreach ($workers as [$process, $pipes]) {
        if (proc_get_status($process)['running']) {
            proc_terminate($process);
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
    }
    foreach (glob($directory . '/*') as $file) {
        unlink($file);
    }
    rmdir($directory);
}
