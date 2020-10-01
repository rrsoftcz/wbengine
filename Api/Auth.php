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

class Auth extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    private $_user = null;

    private function _getUser() {
        if(null === $this->_user) {
            return $this->_user = new User($this);
        } else {
            return $this->_user;
        }
    }

    public function login($data) {
        // var_dump($data['username']);

            $usr = new User($this);
//            $auth = new \Wbengine\Auth();
            // checking for logout requets...
//            $usr->setLoginName($data['username']);
//            $usr->setLoginPassword($data['password']);

//            $_status = $usr->login($data['username'], $data['password']);
//            $_user_data = $usr->getIdentity();

//            $auth->setPayloadData($_user_data);
            $response = array(
                "status" => $this->_getUser()->login($data['username'], $data['password']),
                "token" => $this->_getUser()->getToken()
            );
//            die(var_dump($response));

        $this->Api()->toJson($response);
    }

    public function logout() {
        $usr = new User($this);
        $usr->logout();
        die('{"status": "success", "action": "logout" }');
    }

}