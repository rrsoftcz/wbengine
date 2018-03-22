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

    const TYPE_POST         = 'POST';

    const TYPE_GET          = 'GET';
    const TYPE_PUT          = 'PUT';
    const TYPE_DELETE       = 'DELETE';
    const TYPE_NONE         = 'unknown';



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
            case self::TYPE_DELETE:
                return self::TYPE_DELETE;
            default:
                return self::TYPE_NONE;
        }
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

    
    public static function isJson($string) {
     json_decode($string);
     return (json_last_error() == JSON_ERROR_NONE);
    }


    public static function generateToken($length = 32){
        return bin2hex(random_bytes($length));
    }


}