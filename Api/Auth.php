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
            $this->Api()->toJson(
                array(
                    "success" => $this->User()->login($this->validate($data)->_username, $this->validate($data)->_password),
                    "token" => $this->User()->getToken(),
                    "uid" => $this->User()->getUserId()
                )
            );

        }catch (\Exception $e){
            $this->Api()->toJson(Array("success"=>false, "message"=>$e->getMessage()));
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