<?php
// Router for `php -S`: serves API v2 of the test database over "HTTPS" for the MCP end to end test.
require dirname(__DIR__) . '/bootstrap.php';
ini_set('error_log', '/dev/null');
$_SERVER['HTTPS'] = 'on';
\Wbengine\Config::$config->apiV2 = (object) ['enabled' => true];
(new \Wbengine\Api())->dispatchV2();
