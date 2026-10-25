<?php

namespace Wbengine\Application\Env {
    // Instrument the native parser only in this isolated HTTP fixture.
    function json_decode(...$arguments) {
        ++$GLOBALS['wb_json_decode_count'];
        return \json_decode(...$arguments);
    }
}

namespace {
    require dirname(__DIR__, 2) . '/vendor/autoload.php';
    $GLOBALS['wb_json_decode_count'] = 0;
    $mode = $_GET['mode'] ?? 'decoded';
    if ('validate' === $mode) {
        $value = \Wbengine\Application\Env\Http::isJson(file_get_contents('php://input'));
    } elseif ('repeat' === $mode) {
        $value = [\Wbengine\Application\Env\Http::Json(), \Wbengine\Application\Env\Http::Json()];
    } else {
        $value = \Wbengine\Application\Env\Http::Json(match ($mode) {
            'raw' => false,
            'legacy-truthy' => 1,
            default => true,
        });
    }
    $error = json_last_error();
    header('Content-Type: application/json');
    echo json_encode(['value' => $value, 'error' => $error, 'count' => $GLOBALS['wb_json_decode_count']],
        JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION, 1024);
}
