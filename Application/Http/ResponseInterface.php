<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 17.10.17
 * Time: 20:10
 */

namespace Wbengine\Application\Http;


interface ResponseInterface
{
    public function getSite();
    public function getStaticBox($constructor);
    public function setValue($key, $value = NULL, $parentKey = NULL);
}