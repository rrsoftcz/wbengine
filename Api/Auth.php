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

class Auth extends WbengineRestapiAbstract implements WbengineRestapiInterface
{
    private $_api;
    public function __construct(Api $api)
    {
        $this->_api = $api;
    }

    public function getApi(){
        return $this->_api;
    }

    public function getInstanceOfApiRoutes(){
        return new Api\Auth\Routes($this);
    }

}