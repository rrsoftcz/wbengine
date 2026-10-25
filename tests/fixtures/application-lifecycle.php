<?php

namespace {
    require dirname(__DIR__) . '/bootstrap.php';
}
namespace LifecycleFixture\Box\Central {
    class Probe extends \Wbengine\Box\WbengineBoxAbstract {
        public function Probe() {
            file_put_contents(getenv('WB_LIFECYCLE_DIR') . '/events.log', "box\n", FILE_APPEND);
            \Wbengine\Router::get('/legacy-api/', function () {
                (new \Wbengine\Api())->toJson(['legacy_api' => true]);
            });
            $app = $this->getParent()->getParent()->getParent();
            if (($_SERVER['HTTP_X_LIFECYCLE_MODE'] ?? '') === 'display-box') {
                $app->display('box-display');
                return 'after-nested-display';
            }
            return 'box:' . ($app->getClassVars()->getValue('prefix') ?? 'default');
        }
    }
}
namespace {
    use Wbengine\Application\Application;
    use Wbengine\Db;
    use Wbengine\I18n;

    $dir = getenv('WB_LIFECYCLE_DIR');
    $mode = $_SERVER['HTTP_X_LIFECYCLE_MODE'] ?? '';
    // Mirror the normal webserver rewrite input before i18n strips a prefix.
    $_REQUEST['link'] = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    header('Content-Type: text/html; charset=UTF-8');
    $app = new Application($dir . '/app', 'LifecycleFixture');
    $metrics = static fn () => ['queries' => Db::getQueriesCount(),
        'connections' => (int) mysqli_get_client_stats()['connect_success'],
        'boxes' => is_file($dir . '/events.log') ? substr_count(file_get_contents($dir . '/events.log'), "box\n") : 0];
    if ('constructor' === $mode) {
        header('Content-Type: application/json');
        echo json_encode($metrics());
        return;
    }
    $registered = false;
    $app->get('/health/{id}', function ($route, $app) use ($metrics, &$registered) {
        header('Content-Type: application/json');
        echo json_encode($metrics() + ['registered' => $registered, 'id' => $route->getParams('id'), 'locale' => I18n::locale()]);
        return true;
    });
    if ('terminal' === $mode) {
        $app->get('/page', function () { header('Content-Type: text/plain'); echo 'terminal'; return true; });
    } elseif ('order' === $mode) {
        $app->get('/page', function () use ($dir) { file_put_contents($dir . '/events.log', "first\n", FILE_APPEND); return false; });
        $app->get('/page', function ($route, $app) use ($dir) { file_put_contents($dir . '/events.log', "second\n", FILE_APPEND); $app->setValue('prefix', 'route'); });
    } elseif ('decline-output' === $mode) {
        $app->get('/page', function () { ob_start(); echo 'prefix:'; ob_start(); return false; });
    } elseif ('decline-status' === $mode) {
        $app->get('/page', function () { http_response_code(403); return false; });
    } elseif ('private' === $mode) {
        $app->get('/page', function () { header('Cache-Control: private, no-store'); return false; });
    } elseif ('central' === $mode) {
        $app->setValue('central', 'manual');
    } elseif ('vars' === $mode) {
        $app->getClassVars()->addValue('prefix', 'vars');
    } elseif ('renderer' === $mode) {
        $app->getRenderer()->assign('marker', 'renderer');
    } elseif ('display-route' === $mode) {
        $app->get('/page', function ($route, $app) { $app->display('route-display'); });
    } elseif ('post' === $mode) {
        $app->route('POST', '/page', function () use ($metrics) { header('Content-Type: application/json'); echo json_encode($metrics()); return true; });
    }
    $registered = true;
    if ('zero' === $mode || 'empty' === $mode) {
        $app->display('zero' === $mode ? '0' : '');
    } else {
        $app->run('gone' === $mode ? HTML_ERROR_410 : null);
    }
    if (in_array($mode, ['once', 'display-route', 'display-box'], true)) {
        $app->run();
        $app->display('second-render');
    }
}
