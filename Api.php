<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 20:56
 */

namespace Wbengine;
//use RouteInterface;
use Wbengine\Api\Exception\ApiException;
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
        $this->_found[] = $apiModule->getApiRouteModule($apiModule)->initializeModuleRoutes()->isRoutematch();
    }

    public function end() {
        if(in_array(true, $this->_found) === FALSE){
            $this->printApiError(
                array(
                    "code" => HTML_ERROR_404,
                    "message" => "Page Not Found"
                ), HTML_ERROR_404
            );
        }
    }

    /**
     * Register all API controllers...
     */
    public function Initialize(){

//        $resp = array();
//
//        $resp[] = array(
//            "username"=>"test1",
//            "email"=>"test@test.cz",
//            "user_id"=>1
//        );
//        $resp[] = array(
//            "username"=>"test2",
//            "email"=>"test@test.cz",
//            "user_id"=>2
//        );
//        header("Access-Control-Allow-Origin: *");
//        header("Access-Control-Allow-Headers: *");
//        header('Content-Type: application/json');
//        print_r(json_encode($resp, JSON_PRETTY_PRINT));
//        die();


//        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//            header('Access-Control-Allow-Origin: *');
//            header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
//            header('Access-Control-Allow-Headers: token, Content-Type');
//            header('Access-Control-Max-Age: 3600');
//            header('Content-Length: 0');
//            header('Content-Type: text/plain');
//            die();
//        }
////
//        header("Access-Control-Allow-Origin: *");
//        header("Access-Control-Allow-Headers: *");


        try {

            $this->setHeaderOptions();
            $this->registerRootApi();
            $this->Register(new Section($this));
            $this->Register(new User($this));
            $this->Register(new Auth($this));

        }catch(ApiException $e){
            throw new ApiException($e->getMessage());
        }
        return $this;
    }

    public function setHeaderOptions(){
        // Print global Allowed Origin...
        Http::PrintHeader("Access-Control-Allow-Origin: https://devel.com:8080");
        Http::PrintHeader('Access-Control-Allow-Credentials: true');
        // Manage additional CORS options...
        Router::options(Http::Uri(), function () {
            Http::PrintHeader("Access-Control-Allow-Headers: Origin, X-Requested-With, Accept, Content-Type, credentials, withcredentials, Authorization, Access-Control-Allow-Origin, Access-Control-Allow-Headers");
            Http::PrintHeader('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, UPDATE, PATCH, OPTIONS');
            die;
        });
    }

    public function registerRootApi() {
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

    public function printApiError($msg, $code = Http::OK) {
        Http::PrintCode($code);
        Http::PrintHeader(Http::HEADER_TYPE_JSON);
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