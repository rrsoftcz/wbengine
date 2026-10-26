<?php
/** Test-only application: normal kernel API dispatch, persistent isolated pilot DB. */
namespace {
    require '/app/vendor/autoload.php';
    require_once '/app/Application/Env/Const.php';
    ini_set('display_errors', '0');
}
namespace PilotFixture\Box\Central {
    class Blog extends \Wbengine\Box\WbengineBoxAbstract
    {
        public function story() { return (new \Wbengine\Box\Article($this))->getArticleBox(); }
    }
}
namespace {
    \Wbengine\Api\V2\BoxRegistry::current()->register('story', 'blog', 'story', ['variant' => ['full', 'compact']], ['central']);
    $_REQUEST['link'] = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $application = new \Wbengine\Application\Application('/state/app', 'PilotFixture');
    $application->run();
}
