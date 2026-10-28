<?php

require dirname(__DIR__) . '/bootstrap.php';

use Wbengine\Application\Application;
use Wbengine\Box\Auth;
use Wbengine\Box\Integrations;
use Wbengine\Box\WbengineBoxAbstract;
use Wbengine\Cache;
use Wbengine\Config;
use Wbengine\PageCache;
use Wbengine\Site;
use Wbengine\Template\Engine;

$dir = getenv('WB_PAGE_CACHE_HTTP_DIR');
Config::$config->debug = false;
Config::$config->rateLimit = (object) ['login' => (object) ['enabled' => false, 'dir' => $dir . '/ratelimit']];
Config::$config->apiV2 = (object) ['enabled' => true];
// The page cache is enabled on purpose: the integrations page must never enter it.
Config::$config->cache = (object) ['enabled' => true, 'pages' => (object) ['enabled' => true]];
// Plain HTTP on loopback; the test marks requests as HTTPS through a trusted proxy header.
Config::$config->http = (object) ['trustedProxies' => ['127.0.0.1'], 'forwardedHeaders' => ['x-forwarded-proto']];
Cache::setDriver(new \Wbengine\Cache\FileCache($dir . '/cache'));

class IntegrationsFixtureSite extends Site
{
    public function isLoaded() { return true; }
    public function getLink() { return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); }
    public function __get($name) { return 'acl' === $name ? 0 : null; }
}

class IntegrationsFixtureRenderer
{
    private Engine $engine;
    public function __construct(string $dir) {
        $this->engine = (new Engine())->setTemplateDir(dirname(__DIR__, 2) . '/Box')->setCompileDir($dir);
    }
    public function assign($name, $value, $nocache = false) { $this->engine->assign($name, $value); }
    public function render($template, $vars = null, $static = false) { return $this->engine->fetch($template . '.tpl'); }
}

class IntegrationsFixtureApplication extends Application
{
    private $fixtureRenderer;
    public function __construct() {}
    public function getSite() { return new IntegrationsFixtureSite(); }
    public function getRenderer() { return $this->fixtureRenderer ??= new IntegrationsFixtureRenderer(getenv('WB_PAGE_CACHE_HTTP_DIR')); }
}

$app = new IntegrationsFixtureApplication();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
PageCache::handle($app);
header('Content-Type: text/html; charset=UTF-8');
if ('/login/' === $path) {
    echo (new Auth(new class(null, $app) extends WbengineBoxAbstract {}))->getLoginBox();
} else {
    echo (new Integrations(null, $app))->Integrations();
}
