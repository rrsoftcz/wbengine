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

    private static ?RequestContext $_requestContext = null;

    private static function _requestContext(): RequestContext
    {
        return self::$_requestContext ??= new RequestContext();
    }

    public static function getClientIp(): string
    {
        return self::_requestContext()->getClientIp();
    }

    public static function isSecure(?array $server = null): bool
    {
        return self::_requestContext()->isSecure($server);
    }

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
    const CONFLICT                  = 409;
    const TOO_MANY_REQUESTS         = 429;
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

    /**
     * Value of a request header. Header names are case-insensitive (HTTP/2 and some proxies
     * send them in lower case) and getallheaders() does not exist everywhere, so $_SERVER
     * is used as a fallback.
     */
    public static function getHeader(string $name) {
        $wanted = strtolower($name);

        if (function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $key => $value) {
                if (strtolower((string) $key) === $wanted) {
                    return $value;
                }
            }
        }

        $server = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$server])) {
            return $_SERVER[$server];
        }
        // Some setups only pass the Authorization header through a redirected variable.
        if ('authorization' === $wanted && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        return null;
    }

    public static function getRequestMethod($method){
        // Not filter_input(INPUT_SERVER, ...): it returns null under FastCGI (Nginx with PHP-FPM).
        return (($_SERVER['REQUEST_METHOD'] ?? null) === $method);
    }

    /** Return PHP-parsed form data unchanged; validate fields and escape only at output. */
    public static function Post($name = null){
        return self::_inputValue($_POST, $name);
    }

    public static function PostString($name): ?string
    {
        $value = self::Post($name);
        return is_string($value) ? $value : null;
    }

    /**
     * Compatibility alias for the string-only POST accessor (including passwords).
     */
    public static function PostRaw($name){
        return self::PostString($name);
    }


    /** Return PHP-parsed query data unchanged, including arrays. */
    public static function Get($name = null){
        return self::_inputValue($_GET, $name);
    }

    public static function GetString($name): ?string
    {
        $value = self::Get($name);
        return is_string($value) ? $value : null;
    }

    private static function _inputValue(array $values, $name)
    {
        if (null === $name) {
            return $values;
        }
        return (is_string($name) || is_int($name)) ? ($values[$name] ?? null) : null;
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
        
        if(self::getQueryString() === null) return null;
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
            // The scheme name is case-insensitive (RFC 7235).
            if (preg_match('/^\s*Bearer\s+(\S+)/i', $authorization, $matches)) {
                return $matches[1];
            } else {
                return $authorization;
            }
        }
        return null;
    }

    public static function getCookie(string $cookieName): ?string {
        $value = $_COOKIE[$cookieName] ?? null;
        return is_string($value) ? $value : null;
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
