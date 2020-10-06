<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 20:56
 */

namespace Wbengine;
use RouteInterface;
use Wbengine\Api\Exception\ApiException;
// use Wbengine\Api\Routes\ApiRoutesAbstract;
// use Wbengine\Api\Routes\RoutesInterface;
use Wbengine\Api\Section;
use Wbengine\Api\Auth;
use Wbengine\Api\User;
use Wbengine\Api\WbengineRestapiAbstract;
use Wbengine\Application\Env\Http;

class Api
{
    // private $_instances = array();
    private $_found = array();


    public function Register(WbengineRestapiAbstract $apiModule){
        $this->_found[] = $apiModule->getApiRoutes($apiModule)->init()->isRoutematch();
    }

    public function end() {
        if(in_array(true, $this->_found) === FALSE){
            http_response_code(404);
//            $this->printApiError(array("code"=>HTML_ERROR_404, "message"=>"Page Not Found"), HTML_ERROR_404);
            exit();
        }
    }

    /**
     * Register all API controllers...
     */
    public function Initialize(){
//var_dump(Http::Uri());die();
//        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
////            var_dump(Http::Uri());die();
//            header('Access-Control-Allow-Origin: *');
//            header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
//            header('Access-Control-Allow-Headers: token, Content-Type');
//            header('Access-Control-Max-Age: 3600');
//            header('Content-Length: 0');
//            header('Content-Type: text/plain');
////            die();
//        }
////
//        header("Access-Control-Allow-Origin: *");
//        header("Access-Control-Allow-Headers: *");


        try {
            $this->setOptions();
            $this->registerRootApi();
            $this->Register(new Section($this));
            $this->Register(new User($this));
//            die('dwdwdqwdwqdq');
//            Http::PrintCode(200);
//            Http::PrintCode(404);
            $this->Register(new Auth($this));
//            var_dump('za routama');
//            $this->printApiError('404 Not Found', 404);
//            $this->_found[] = true;
//            var_dump($this->_found);
//            var_dump(Http::isAjaxCall());
//            exit();
//            if(in_array(true, $this->_found) === false){
//                $this->printApiError(array("code"=>404,"message"=>"Not Found"), 502);
//                exit();
//            }
        }catch(ApiException $e){
            throw new ApiException($e->getMessage());
        }
        return $this;
    }

    public function setOptions(){
        Router::options(Http::Uri(), function () {
            Http::PrintHeader('Access-Control-Allow-Origin: *');
            Http::PrintHeader('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
            die;
        });
    }

    public function registerRootApi() {
//        Router::options(Http::Uri(), function () {
//            header('Access-Control-Allow-Origin: *');
//            header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
//            header('Access-Control-Allow-Headers: token, Content-Type');
//            header('Access-Control-Max-Age: 3600');
//            header('Content-Length: 0');
//            header('Content-Type: text/plain');
//            die();

//            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//                header('Access-Control-Allow-Origin: *');
//                header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
//                header('Access-Control-Allow-Headers: token, Content-Type');
//                header('Access-Control-Max-Age: 3600');
//                header('Content-Length: 0');
//                header('Content-Type: text/plain');
//                die();
//            }
//
//            header("Access-Control-Allow-Origin: *");
//            header("Access-Control-Allow-Headers: *");

//        });
        Router::get('/api', function () {
            return $this->toString('WBengine Rest API v1.0');
        });

    }

    public function toJson($value, int $code = null, $pretty = true) {
        Http::PrintHeader(Http::HEADER_TYPE_JSON);
        if($code) {
            Http::PrintCode($code);
        }
        if($pretty) {
            print_r( json_encode( $value, JSON_PRETTY_PRINT ) );
        }else{
            die(json_encode($value));
        }
        die;
    }

    public function toString($value, $code = 200) {
        Http::PrintCode($code);
        Http::PrintHeader(Http::HEADER_TYPE_PLAIN_TEXT);
        die((string) $value);
    }

    public function printApiError($msg, $code = Http::BAD_REQUEST) {
        Http::PrintCode($code);
        Http::PrintHeader(Http::HEADER_TYPE_JSON);
        self::toJson(array('error' => $msg), $code);
    }



}