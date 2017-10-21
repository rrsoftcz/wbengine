<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 17.10.17
 * Time: 20:10
 */

namespace Wbengine\Application\Http;


interface RequestInterface
{
    public function getParams($param = null);
    public function getStaticBox($constructor);
}