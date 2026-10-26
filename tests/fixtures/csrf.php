<?php

require dirname(__DIR__) . '/bootstrap.php';

use Wbengine\Application\Application;
use Wbengine\Box\Auth;
use Wbengine\Box\WbengineBoxAbstract;
use Wbengine\Cache;
use Wbengine\Config;
use Wbengine\PageCache;
use Wbengine\Site;
use Wbengine\Template\Engine;

$dir = getenv('WB_PAGE_CACHE_HTTP_DIR');
Config::$config->debug = false;
Config::$config->rateLimit = (object) ['login' => (object) ['enabled' => false]];
Config::$config->cache = (object) ['enabled' => true, 'pages' => (object) ['enabled' => true]];
Cache::setDriver(new \Wbengine\Cache\FileCache($dir . '/cache'));

class CsrfFixtureSite extends Site
{
    public function isLoaded() { return true; }
    public function getLink() { return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); }
    public function __get($name) { return 'acl' === $name ? 0 : null; }
}

class CsrfFixtureRenderer
{
    private Engine $engine;
    public function __construct(string $dir) {
        $this->engine = (new Engine())->setTemplateDir(dirname(__DIR__, 2) . '/Box')->setCompileDir($dir);
    }
    public function assign($name, $value, $nocache = false) { $this->engine->assign($name, $value); }
    public function render($template, $vars = null, $static = false) { return $this->engine->fetch($template . '.tpl'); }
}

class CsrfFixtureApplication extends Application
{
    private $fixtureRenderer;
    public function __construct() {}
    public function getSite() { return new CsrfFixtureSite(); }
    public function getRenderer() { return $this->fixtureRenderer ??= new CsrfFixtureRenderer(getenv('WB_PAGE_CACHE_HTTP_DIR')); }
}

$app = new CsrfFixtureApplication();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
PageCache::handle($app);
header('Content-Type: text/html; charset=UTF-8');
if ('/public/' === $path) {
    echo bin2hex(random_bytes(8));
    return;
}
$box = new class(null, $app) extends WbengineBoxAbstract {};
$auth = new Auth($box);
if ('/logout-form/' === $path) {
    echo $auth->getLogoutBox();
} elseif ('/state/' === $path) {
    echo (new \Wbengine\User($box))->getUserIsLogged() ? 'logged' : 'anonymous';
} else {
    echo $auth->getLoginBox();
}
