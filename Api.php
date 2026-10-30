<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 20:55
 */

namespace Wbengine;

use Wbengine\Application\Env\Http;

/**
 * Entry point of the API. The only API is v2 (Wbengine\Api\V2): server to server, authenticated by
 * integration keys with scopes. This class dispatches it and writes JSON responses.
 */
class Api
{
    private $_terminator = null;
    private ?\Wbengine\Application\Application $_parent = null;
    private ?\Wbengine\Api\V2 $_v2 = null;
    private $_lastStatus = 200;

    public function __construct(?\Wbengine\Application\Application $parent = null)
    {
        $this->_parent = $parent;
    }

    public function getParent(): ?\Wbengine\Application\Application
    {
        return $this->_parent;
    }

    public function getV2(): \Wbengine\Api\V2
    {
        return $this->_v2 ??= new \Wbengine\Api\V2($this);
    }

    /** Dispatch the v2 module; true when the request path belongs to it. */
    public function dispatchV2(): bool
    {
        $controller = $this->getV2();
        return $controller->getApiRouteModule($controller)->initializeModuleRoutes()->isRouteMatch();
    }

    public function toJson($value, ?int $code = null, $pretty = true) {
        Http::PrintHeader(Http::HEADER_TYPE_JSON);
        if($code) {
            Http::PrintCode($code);
        }
        $this->_lastStatus = $code ?: 200;
        $this->finish($pretty ? json_encode($value, JSON_PRETTY_PRINT) : json_encode($value));
    }

    /**
     * Print the response body and end the request. An API response is always the last
     * thing a request does.
     */
    private function finish($output = '') {
        echo $output;
        if (null !== $this->_terminator) {
            call_user_func($this->_terminator);
        }
        exit;
    }

    /**
     * Replace the end of the request, for tests. The callable must throw (an exception
     * unwinds to the caller, which can then inspect the response); if it returns, the
     * script exits as usual.
     */
    public function setTerminator(?callable $terminator) {
        $this->_terminator = $terminator;
    }

    /** The HTTP status of the last response written. */
    public function getLastStatus() {
        return $this->_lastStatus;
    }

    public function printApiError($msg, $code = Http::OK) {
        self::toJson(
            array(
                'success' => false,
                'error' => $code,
                'message' => $msg
            ),
            $code
        );
    }
}
