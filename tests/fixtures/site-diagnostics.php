<?php

// Fresh process: neither the DB adapter nor a socket can be inherited from PHPUnit.
require dirname(__DIR__) . '/bootstrap.php';

use Wbengine\Application\Application;
use Wbengine\Cache;
use Wbengine\Cache\FileCache;
use Wbengine\Config;
use Wbengine\Db;
use Wbengine\Site;

class DiagnosticsFixtureSite extends Site
{
    public function getUrl() { return '/cached/'; }
    public function isUrlStrict() { return false; }
    // Isolate initialization and diagnostics from content and navigation queries.
    public function getSections() { return []; }
    public function getMenuCollection() { return []; }
}

class DiagnosticsFixtureApplication extends Application
{
    private Site $fixtureSite;
    public function __construct() { $this->fixtureSite = new DiagnosticsFixtureSite($this); }
    public function getSite() { return $this->fixtureSite; }
}

$mode = $argv[1];
Config::$config->debug = 'debug' === $mode;
Cache::setDriver(new FileCache($argv[2]));
if ('missing-server' === $mode) {
    unset($_SERVER['SERVER_SOFTWARE']);
} else {
    $_SERVER['SERVER_SOFTWARE'] = 'WBengine diagnostics fixture';
}
$app = new DiagnosticsFixtureApplication();
$beforeConnections = mysqli_get_client_stats()['connect_success'];
$beforeQueries = Db::getQueriesCount();
$site = $app->getSite();
$site->initialize($app);
$initialized = [
    'connections' => mysqli_get_client_stats()['connect_success'] - $beforeConnections,
    'queries' => Db::getQueriesCount() - $beforeQueries,
    'adapterCreated' => Db::isConnected(),
];
$explicit = null;
if (in_array($mode, ['explicit', 'missing-server'], true)) {
    $explicit = $site->assignDiagnostics() === $site;
}
echo json_encode([
    'initialized' => $initialized,
    'connections' => mysqli_get_client_stats()['connect_success'] - $beforeConnections,
    'queries' => Db::getQueriesCount() - $beforeQueries,
    'adapterCreated' => Db::isConnected(),
    'explicitReturnsSite' => $explicit,
    'loaded' => $site->isLoaded(),
    'vars' => $app->getClassVars()->getValues(),
], JSON_THROW_ON_ERROR);
