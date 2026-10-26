<?php
$vendor = getenv('COMPOSER_VENDOR_DIR') ?: dirname(__DIR__) . '/vendor';
require $vendor . '/autoload.php';
define('WBENGINE_COMPAT_CORE_ROOT', realpath(Composer\InstalledVersions::getInstallPath('wbengine/wbengine')));
require_once WBENGINE_COMPAT_CORE_ROOT . '/Application/Env/Const.php';
Wbengine\Config::load(new Wbengine\Application\Path\File(dirname(__DIR__, 3) . '/tests/config/Settings.test.json'));
