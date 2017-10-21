<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 21.10.17
 * Time: 13:20
 */

namespace Wbengine\Application\Env;

class Http
{

    public function __construct()
    {
    }


    public static function type()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method == 'POST') {
            return 'POST';
        } elseif ($method == 'GET') {
            return 'GET';
        } elseif ($method == 'PUT') {
            return 'PUT';
        } elseif ($method == 'DELETE') {
            return 'DELETE';
        } else {
            return 'unknown';
        }
    }
}