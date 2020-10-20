<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 21.10.17
 * Time: 13:20
 */

namespace Wbengine\Application\Env;

abstract class Http
{

    const TYPE_POST                 = 'POST';
    const TYPE_GET                  = 'GET';
    const TYPE_PUT                  = 'PUT';
    const TYPE_UPDATE               = 'UPDATE';
    const TYPE_DELETE               = 'DELETE';
    const TYPE_PATCH                = 'PATCH';
    const TYPE_COPY                 = 'COPY';
    const TYPE_HEAD                 = 'HEAD';
    const TYPE_OPTIONS              = 'OPTIONS';
    const TYPE_LINK                 = 'LINK';
    const TYPE_UNLINK               = 'UNLINK';
    const TYPE_PURGE                = 'PURGE';
    const TYPE_LOCK                 = 'LOCK';
    const TYPE_UNLOCK               = 'UNLOCK';
    const TYPE_PROPFIND             = 'PROPFIND';
    const TYPE_VIEW                 = 'VIEW';
    const TYPE_NONE                 = 'UNKNOWN';

    /** @var HTTP CODES */
    const SWITCHING_PROTOCOLS       = 101;
    const OK                        = 200;
    const CREATED                   = 201;
    const ACCEPTED                  = 202;
    const NONAUTHORITATIVE_INFO     = 203;
    const NO_CONTENT                = 204;
    const RESET_CONTENT             = 205;
    const PARTIAL_CONTENT           = 206;
    const MULTIPLE_CHOICES          = 300;
    const MOVED_PERMANENTLY         = 301;
    const MOVED_TEMPORARILY         = 302;
    const SEE_OTHER                 = 303;
    const NOT_MODIFIED              = 304;
    const USE_PROXY                 = 305;
    const BAD_REQUEST               = 400;
    const UNAUTHORIZED              = 401;
    const PAYMENT_REQUIRED          = 402;
    const FORBIDDEN                 = 403;
    const NOT_FOUND                 = 404;
    const METHOD_NOT_ALLOWED        = 405;
    const NOT_ACCEPTABLE            = 406;
    const PROXY_AUTH_REQUIRED       = 407;
    const REQUEST_TIMEOUT           = 408;
    const CONFLICT                  = 408;
    const GONE                      = 410;
    const LENGTH_REQUIRED           = 411;
    const PRECONDITION_FAILED       = 412;
    const REQUEST_ENTITY_TOO_LARGE  = 413;
    const REQUESTURI_TOO_LARGE      = 414;
    const UNSUPPORTED_MEDIA_TYPE    = 415;
    const REQ_RANGE_NOT_SATISFIABLE = 416;
    const EXPECTATION_FAILED        = 417;
    const IM_A_TEAPOT               = 418;
    const INTERNAL_SERVER_ERROR     = 500;
    const NOT_IMPLEMENTED           = 501;
    const BAD_GATEWAY               = 502;
    const SERVICE_UNAVAILABLE       = 503;
    const GATEWAY_TIMEOUT           = 504;
    const HTTP_VER_NOT_SUPPORTED    = 505;

    /** @var HTTP HEADERS */

    const HEADER_TYPE_AUTHORIZATION = 'Authorization';

    const HEADER_TYPE_JSON          = 'content-type: application/json; charset=UTF-8';
    const HEADER_TYPE_PLAIN_TEXT    = 'Content-Type: text/plain; charset=UTF-8';

    public static function getRequestType()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($method){
            case self::TYPE_POST:
                return self::TYPE_POST;
            case self::TYPE_GET:
                return self::TYPE_GET;
            case self::TYPE_PUT:
                return self::TYPE_PUT;
            case self::TYPE_PATCH:
                return self::TYPE_PATCH;
            case self::TYPE_DELETE:
                return self::TYPE_DELETE;
            case self::TYPE_OPTIONS:
                return self::TYPE_OPTIONS;
            default:
                return self::TYPE_NONE;
        }
    }

    public static function getHeader(string $name) {
         $_headers = getallheaders();
         if(array_key_exists($name, $_headers)) {
             return $_headers[$name];
         }
         return null;
    }

    public static function secureClean($value = null){
        if($value === null) return null;

        $value = stripslashes($value);
        $value = htmlspecialchars($value, ENT_IGNORE, 'utf-8');
        $value = strip_tags($value);
        $value = addslashes($value);
        return $value;
    }


    public static function getRequestMethod($method){
        return ((filter_input(INPUT_SERVER, 'REQUEST_METHOD') === $method)) ? true : false;
    }

    public static function Post($name = null){
        return ($name) ? self::secureClean($_POST[$name]) : $_POST;
    }


    public static function Get($name = null){
        return ($name) ? self::secureClean($_GET[$name]) : $_GET;
    }

    public static function Json($decode = true){
        $raw = file_get_contents('php://input');
        if(self::isJson($raw)){
            return ($decode === true) ? json_decode($raw, true) : $raw;
        }else{
            return null;
        }
    }


    public static function Uri(){
        return $_SERVER["REQUEST_URI"];
    }


    public static function getParam($name = null){
        $params = array();
        parse_str(self::getQueryString(),$params);
        if(key_exists($name, $params)){
            return $params[$name];
        }
    }


    public static function getQueryString(){
        return parse_url(self::Uri(), PHP_URL_QUERY);
    }


    public static function isAjaxCall(){
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
            return true;
        }else{
            return false;
        }
    }

    public static function getBearerToken() {
        $authorization = Http::getHeader(Http::HEADER_TYPE_AUTHORIZATION);
        // HEADER: Get the access token from the header
        if (!empty($authorization)) {
            if (preg_match('/Bearer\s(\S+)/', $authorization, $matches)) {
                return $matches[1];
            } else {
                return $authorization;
            }
        }
        return null;
    }


    public static function isJson($string) {
     json_decode($string);
     return (json_last_error() == JSON_ERROR_NONE);
    }


    public static function generateToken($length = 32){
        return bin2hex(random_bytes($length));
    }

    public static function PrintHeader($type){
        header($type);
    }

    public static function PrintCode(int $code){
        http_response_code($code);
    }

}