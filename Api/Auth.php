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

class Auth extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    private $_user = null;
    protected $_username = null;
    protected $_password = null;

    private function User() {
        if(null === $this->_user) {
            return $this->_user = new User($this);
        } else {
            return $this->_user;
        }
    }

    public function login($data) {
        try {
            $status = $this->User()->login($this->validate($data)->_username, $this->validate($data)->_password);
            if(!$status) {
                throw new Api\Exception\ApiException("Login failed, wrong username or password.");
            }
            $this->Api()->toJson(
                array(
                    "success" => $status,
                    "token" => $this->User()->getToken(),
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

    public function validateJwtToken(string $token = null, array $body = null){
        if(null === $body || !array_key_exists("user_id", $body)) {
            $this->Api()->toJson(
                Array(
                    "success" => false,
                    "message"=> "Empty UID"
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

    private function validate(array $credentials) {
        if(array_key_exists("username", $credentials) && !empty($credentials["username"])) {
            $this->_username = $credentials["username"];
        } else { throw new AuthException("Empty or invalid username");}

        if(array_key_exists("password", $credentials) && !empty($credentials["password"])) {
            $this->_password = $credentials["password"];
        } else { throw new AuthException("Empty or invalid password");}
        return $this;
    }
}