<?php

use Wbengine\Application\Path\File;
use Wbengine\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

// Table name constants (S_TABLE_*), normally loaded by the Application.
require_once dirname(__DIR__) . '/Application/Env/Const.php';

// Test configuration, with the database host taken from the environment.
$config = json_decode(file_get_contents(__DIR__ . '/config/Settings.test.json'), true);
$config['dbAdapterDefinition']['hostname'] = getenv('WB_TEST_DB_HOST') ?: 'db';

$file = sys_get_temp_dir() . '/wbengine-test-settings-' . getmypid() . '.json';
file_put_contents($file, json_encode($config));
$ownerPid = getmypid();
register_shutdown_function(static function () use ($file, $ownerPid) {
    // Forked test workers share this callback, only the owner may clean up.
    if (getmypid() === $ownerPid) {
        @unlink($file);
    }
});

Config::load(new File($file));
