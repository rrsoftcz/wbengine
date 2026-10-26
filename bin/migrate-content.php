<?php
/** Administrator CLI only. No application request, session or direct SQL implementation. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/Application/Env/Const.php';
exit((new \Wbengine\Content\Migration\Cli())->run(array_slice($argv, 1)));
