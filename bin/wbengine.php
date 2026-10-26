#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/Console/Bootstrap.php';
\Wbengine\Console\Bootstrap::load($_composer_autoload_path ?? null);
exit((new \Wbengine\Console\Application())->run($argv));
