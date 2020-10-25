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
    private $_allow_origins = array(
        "*"
    );
    private $_allow_methods = array(
        "GET",
        "POST",
        "PATCH",
        "PUT",
        "DELETE",
        "OPTIONS"
    );
    private $_allow_headers = array(
    );

    private $_allow_credential_header = false;
    private $_found = array();
    private $_token_expiration = 3600;
    private $_cookie_expiration = 3600;
    private $_cookie_domain = "localhost";
    private $_cookie_url = "/";
    private $_cookie_http_only = true;
    private $_cookie_secured = true;



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
//        $_origin = sprintf("Access-Control-Allow-Origin: %s", ($this->_allow_origin || '*'));
//        var_dump(sprintf("Access-Control-Allow-Origin: %s", implode(" ", $this->_allow_origins)));die;
        // Print global Allowed Origin...
        Http::PrintHeader(sprintf("Access-Control-Allow-Origin: %s", implode(" ", $this->_allow_origins)));
        if($this->_allow_credential_header === true) {
            Http::PrintHeader('Access-Control-Allow-Credentials: true');
        }
        // Manage additional CORS options...
        Router::options(Http::Uri(), function () {
            Http::PrintHeader(sprintf("Access-Control-Allow-Headers: %s", implode(",", $this->_allow_headers)));
            Http::PrintHeader(sprintf("Access-Control-Allow-Methods: %s", implode(",",$this->_allow_methods)));
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

    public function setHeaderAllowOredentials(bool $val){
        $this->_allow_credential_header = $val;
    }

    public function setSingleOrigin(string $originName){
        $this->_allow_origins = array();
        $this->_allow_origins[] = $originName;
    }

    public function allowOrigin(string $originName){
        $this->_allow_origins[] = $originName;
    }

    public function allowHeader(string $headerName){
        $this->_allow_headers[] = $headerName;
    }

    public function setJwtTokenExpiration(int $exp){
        $this->_token_expiration = $exp;
    }

    public function setCookieExpiration(int $exp){
        $this->_cookie_expiration = $exp;
    }

    public function getJwtTokenExpiration(){
        return (int) $this->_token_expiration;
    }

    public function getCookieExpiration(){
        return time() + (int) $this->_cookie_expiration;
    }

    public function setCookieDomain(string $domainName){
        $this->_cookie_domain = $domainName;
    }

    public function getCookieDomain(){
        return $this->_cookie_domain;
    }

    public function setCookieUrl(string $url){
        $this->_cookie_url = $url;
    }

    public function getCookieUrl(){
        return $this->_cookie_url;
    }

    public function setCookieHttpOnly($val){
        $this->_cookie_http_only = (bool)$val;
    }

    public function setCookieIsSecured($val){
        $this->_cookie_secured = (bool)$val;
    }

    public function getCookieIsHttpOnly(){
        return $this->_cookie_http_only;
    }

    public function getCookieIsSecured(){
        return $this->_cookie_secured;
    }

}