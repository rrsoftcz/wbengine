<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 21.10.17
 * Time: 13:20
 */

namespace Wbengine\Application\Env;

abstract class Http
{

    const TYPE_POST     = 'POST';
    const TYPE_GET      = 'GET';
    const TYPE_PUT      = 'PUT';
    const TYPE_DELETE   = 'DELETE';
    const TYPE_NONE     = 'unknown';

    public static function type()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($method){
            case self::TYPE_POST:
                return self::TYPE_POST;
            case self::TYPE_GET:
                return self::TYPE_GET;
            case self::TYPE_PUT:
                return self::TYPE_PUT;
            case self::TYPE_DELETE:
                return self::TYPE_DELETE;
            default:
                return self::TYPE_NONE;
        }
    }

    public static function request($type = self::TYPE_NONE){
        if($type === self::TYPE_NONE)
            return null;

        $value = $_REQUEST[$type];

        $value = stripslashes($value);
        $value = htmlspecialchars($value, ENT_IGNORE, 'utf-8');
        $value = strip_tags($value);
        return $value;
    }


    public static function Post($name = null){
        return ($name) ? self::request($name,self::TYPE_POST) : $_POST;
    }
}