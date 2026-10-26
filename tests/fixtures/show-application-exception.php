<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
\Wbengine\Config::$config = [];
(new \Wbengine\Config\Adapter\Exception\ConfigException('Invalid <settings> JSON'))->Show();
