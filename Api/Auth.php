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
    public function authenticate($data) {
        // var_dump($data['username']);

            $usr = new User($this);
            // checking for logout requets...
            $status = $usr->login($data['username'], $data['password']);
//            die(var_dump($status));

        $this->Api()->toJson($status);
    }

    public function logout() {
        $usr = new User($this);
        $usr->logout();
        die('{"status": "success", "action": "logout" }');
    }

}