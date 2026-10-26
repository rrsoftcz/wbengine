#!/usr/bin/env php
<?php
/**
 * php tests/benchmarks/request-matrix.php /private/manifest.json [requests=20] [concurrency=4]
 * Manifest: environment {php,server,database,opcache,data}, matrix {off,data-warm,page-warm},
 * each containing {anonymous,anonymous-session,logged,custom-api,same-session}: {url,cookie?}.
 * URLs must select independently configured cache modes on an isolated fixture or test deployment.
 * Cookies are caller supplied, never printed. same-session sends the identical cookie concurrently.
 * Benchmark only after arranging exclusive DB access if your endpoints mutate shared fixtures.
 * No DB setup, DDL, fixture mutation or production instrumentation is performed by this client.
 */
require dirname(__DIR__, 2) . '/vendor/autoload.php';
try {
    if (!isset($argv[1]) || !is_readable($argv[1])) { throw new InvalidArgumentException(); }
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $result = (new \Wbengine\Console\RequestMatrix())->run($manifest, (int) ($argv[2] ?? 20), (int) ($argv[3] ?? 4));
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    foreach ($result['cases'] as $cases) { foreach ($cases as $case) { if ($case['errors']) { exit(1); } } }
} catch (Throwable) {
    fwrite(STDERR, "Benchmark failed. Check the manifest shape, HTTP targets and curl extension; credentials and cookies are never printed.\n"); exit(2);
}
