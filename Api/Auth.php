<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 04/07/2018
 * Time: 21:19
 */

namespace Wbengine\Api;


use Wbengine\Api;
use Wbengine\Api\Routes\ApiRoutesAbstract;
use Wbengine\Api\WbengineRestapiAbstract;
use Wbengine\Api\Model\Exception\ApiModelException;
use Wbengine\Application\Env\Http;
use Wbengine\User;
use Wbengine\Api\Auth\Exception\AuthException;

class Auth extends WbengineRestapiAbstract implements WbengineRestapiInterface {

    const PWD_COMPLEXITY_MIN_LENGTH = 6;

    private $_user = null;
    protected $_username = null;
    protected $_password = null;


    private function User() {
        if(null === $this->_user) {
            $this->setUser(new User($this));
            return $this->getUser()->useJwt(true);
        } else {
            return $this->_user;
        }
    }

    /**
     * @return User|null
     */
    public function getUser()
    {
        return $this->_user;
    }

    public function setUser(User $user){
        $this->_user = $user;
    }

    public function login($data) {
        try {
            $status = $this->User()->login($this->validate($data)->_username, $this->validate($data)->_password);
            if(!$status) {
                throw new Api\Exception\ApiException("Login failed, wrong username or password.");
            }

            setcookie(
                "refresh_token",
                $this->User()->getRefreshToken(),
                $this->Api()->getCookieExpiration(),
                $this->Api()->getCookieUrl(),
                $this->Api()->getCookieDomain(),
                $this->Api()->getCookieIsSecured(),
                $this->Api()->getCookieIsHttpOnly()
            );

            $this->Api()->toJson(
                array(
                    "success" => $status,
                    "token" => $this->User()->getJwtToken(),
                    "uid" => $this->User()->getUserId(),
                ), ($status)? Http::OK : Http::UNAUTHORIZED
            );

        }catch (\Exception $e){
            $this->Api()->toJson(Array("success"=>false, "message"=>$e->getMessage()), Http::UNAUTHORIZED);
        }
    }

    public function logout() {
        $this->Api()->toJson(
            Array(
                "success" => $this->User()->logout(),
                "message"=> "Successfully logged out"
            )
        );
    }

    public function validateJwtToken(string $token = null, $body = null){
        if(null === $body || !is_array($body) || !array_key_exists("user_id", $body)) {
            $this->Api()->toJson(
                Array(
                    "success" => false,
                    "message"=> __FUNCTION__.": Empty UID"
                ),Http::UNAUTHORIZED
            );
        }

        try {
            $payload = $this->wbAuth()->getDecodedData($token);
            $fce = fn($b) => (int)$b["user_id"] === (int)$payload["data"]->user_id;
            $this->Api()->toJson(
                Array(
                    "success" =>  $fce($body),
                    "message"=> "JWT token successfuly decoded and user has authenticated."
                ),($fce($body)) ? Http::OK : Http::UNAUTHORIZED
            );

        }catch(\Exception $e){
            $this->Api()->toJson(
                Array(
                    "success" => false,
                    "message"=> $e->getMessage()
                ),Http::UNAUTHORIZED
            );
        }
    }


    public function validateRefreshToken(){
        $refresh_token = Http::getCookie("refresh_token");

        if(null === $refresh_token || empty($refresh_token)) {
            $this->Api()->toJson(
                Array(
                    "success" => false,
                    "message"=> "Unavailable refresh token."
                ),Http::UNAUTHORIZED
            );
        }

        try {
            $payload = $this->wbAuth()->validateRefreshToken($refresh_token);
            $fn = fn($b) => (int)$b === (int)$payload["data"]->user_id;
            $this->User()->loadUserDataFromModel();
            $this->Api()->toJson(
                Array(
                    "success" => $fn($this->User()->getUserId()),
                    "token" => $this->User()->createJwtTokn(),
                    "message"=> "Verification OK. The new JWT token has successfuly created."
                ),($fn($this->User()->getUserId())) ? Http::OK : Http::UNAUTHORIZED
            );

        }catch(\Exception $e){
            $this->Api()->toJson(
                Array(
                    "success" => false,
                    "message"=> $e->getMessage()
                ),Http::UNAUTHORIZED
            );
        }
    }

    private function validate($credentials) {
        if(!is_array($credentials)){
            throw new AuthException(
                sprintf(
                    "Invalid provided credentials. Expected an Array, but '%s' given.",
                    gettype($credentials)
                )
            );
        }

        if(array_key_exists("username", $credentials) && !empty($credentials["username"])) {
            $this->_username = $credentials["username"];
        } else {
            $this->throwError("Empty username");
        }

        if(array_key_exists("password", $credentials) && !empty($credentials["password"])) {
//            if(strlen($credentials["password"]) < self::PWD_COMPLEXITY_MIN_LENGTH){
//                $this->throwError(
//                    sprintf(
//                        "Password complexity error, the password is too short. The expected minimum length is %s characters long.",
//                        self::PWD_COMPLEXITY_MIN_LENGTH
//                    )
//                );
//            }
            $this->_password = $credentials["password"];
        } else {
            $this->throwError("Empty password");
        }
        return $this;
    }

    public function throwError(string $message){
        throw new \Wbengine\Auth\Exception\AuthException($message, 400);
    }
}