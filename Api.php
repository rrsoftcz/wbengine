<?php
/**
 * If you require, that cross-domain Auth work, you need to deploy api website at
 * same hostname as client api calls!!
 * Also both client and api server must be run only on HTTPS protocol with 
 * trusted certificate!
 * 
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
use Wbengine\Api\CacheAdmin;
use Wbengine\Api\Translation;
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
        "Content-Type", "X-WBengine-CSRF"
    );
    private $_content_security_policy = array(
        "default-src" => "self"
    );

    private $_allow_credential_header = false;
    private $_found = array();
    private $_terminator = null;
    private ?\Wbengine\Application\Application $_parent = null;
    private ?\Wbengine\Api\V2 $_v2 = null;
    private $_cookieWriter = null;
    private $_lastStatus = 200;
    // Short lived access token, long lived refresh token (rotated on every use).
    private $_token_expiration = 900;
    private $_cookie_expiration = 604800;
    // However often a refresh token is used, the login ends after this many seconds.
    private $_refresh_absolute_lifetime = 2592000;
    // Empty domain: the cookie belongs to the exact host that set it.
    private $_cookie_domain = "";
    private $_cookie_url = "/api/auth/";
    private $_cookie_http_only = true;
    // null: Secure when the request came over HTTPS.
    private $_cookie_secured = null;
    // Strict: the refresh cookie is never sent on a cross-site request (CSRF).
    private $_cookie_same_site = "Strict";



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

    /** Dispatch only the v2 module, without legacy CORS or CMS registration. */
    public function dispatchV2(): bool
    {
        $controller = $this->getV2();
        return $controller->getApiRouteModule($controller)->initializeModuleRoutes()->isRouteMatch();
    }

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

//        var_dump($_SERVER['HTTP_HOST']);die;
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

            $this->_found[] = $this->dispatchV2();
            $this->setHeaderOptions();
            $this->registerRootApi();
            $this->Register(new Section($this));
            $this->Register(new User($this));
            $this->Register(new Auth($this));
            $this->Register(new CacheAdmin($this));
            $this->Register(new Translation($this));

        }catch(ApiException $e){
            throw new ApiException($e->getMessage());
        }
        return $this;
    }

    public function setHeaderOptions(){
        // default security policy...
        Http::PrintHeader(sprintf("Content-Security-Policy: %s", $this->getDefaultContentSecurityPolicy()));
        // Print global Allowed Origin...
        Http::PrintHeader(sprintf("Access-Control-Allow-Origin: %s", implode(" ", $this->_allow_origins)));
        if($this->_allow_credential_header === true) {
            Http::PrintHeader('Access-Control-Allow-Credentials: true');
        }
        // Manage additional CORS options...
        Router::options(Http::Uri(), function () {
//            Http::PrintHeader('Access-Control-Allow-Credentials: true');
            Http::PrintHeader(sprintf("Access-Control-Allow-Headers: %s", implode(",", $this->_allow_headers)));
            Http::PrintHeader(sprintf("Access-Control-Allow-Methods: %s", implode(",",$this->_allow_methods)));
            $this->finish();
        });
    }

    public function registerRootApi() {
        Router::get('/api', function () {
            return $this->toString('WBengine Rest API v1.0');
        });

    }

    public function toJson($value, ?int $code = null, $pretty = true) {
        Http::PrintHeader(Http::HEADER_TYPE_JSON);
        if($code) {
            Http::PrintCode($code);
        }
        $this->_lastStatus = $code ?: 200;
        $this->finish($pretty ? json_encode($value, JSON_PRETTY_PRINT) : json_encode($value));
    }

    public function toString($value, $code = 200) {
        Http::PrintCode($code);
        Http::PrintHeader(Http::HEADER_TYPE_PLAIN_TEXT);
        $this->_lastStatus = $code;
        $this->finish((string) $value);
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

    /**
     * Replace setcookie(), for tests. Receives the name, the value and the options array.
     */
    public function setCookieWriter(?callable $writer) {
        $this->_cookieWriter = $writer;
    }

    public function writeCookie($name, $value, array $options) {
        if (null !== $this->_cookieWriter) {
            call_user_func($this->_cookieWriter, $name, $value, $options);
            return;
        }
        setcookie($name, $value, $options);
    }

    /**
     * The HTTP status of the last response written.
     */
    public function getLastStatus() {
        return $this->_lastStatus;
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
        return time() + (int) $this->_token_expiration;
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
        return (null === $this->_cookie_secured)
            ? \Wbengine\Application\Env\Http::isSecure()
            : (bool) $this->_cookie_secured;
    }

    /**
     * Strict (default), Lax or None. None also needs Secure and is only for an API on
     * another site than the client, which weakens the CSRF protection of the cookie.
     */
    public function setCookieSameSite(string $value){
        $value = ucfirst(strtolower($value));
        if (!in_array($value, array('Strict', 'Lax', 'None'), true)) {
            throw new ApiException('The SameSite attribute must be Strict, Lax or None.');
        }
        $this->_cookie_same_site = $value;
    }

    public function getCookieSameSite(){
        return $this->_cookie_same_site;
    }

    public function setRefreshAbsoluteLifetime(int $seconds){
        $this->_refresh_absolute_lifetime = max(1, $seconds);
    }

    /**
     * Seconds after the login in which a refresh token family stops working however often it is used.
     */
    public function getRefreshAbsoluteLifetime(){
        return (int) $this->_refresh_absolute_lifetime;
    }

    /**
     * Lifetime of a refresh token in seconds.
     */
    public function getRefreshTokenTtl(){
        return (int) $this->_cookie_expiration;
    }

    public function getDefaultContentSecurityPolicy(){
        return sprintf("default-src '%s'", $this->_content_security_policy['default-src']);
    }

}