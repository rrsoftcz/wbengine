<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
putenv('WBENGINE_DEBUG=0');
\Wbengine\Application\ApplicationException::RegisterHandler();
throw new \RuntimeException('Private runtime detail <secret>');
