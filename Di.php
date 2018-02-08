<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 08/02/2018
 * Time: 20:27
 */

namespace Wbengine;

use Wbengine\Di\Exception\DiException;
use Wbengine\User;

abstract class Di
{
    //@TODO CREATE CONSTANTS OF ALL CLASSES
    
    private static $container = array();


    static function get($name, $pass){
        if(key_exists($name, self::$container)){
            return self::$container[$name];
        }else{
            self::createContainer($name, $pass);
        }
        return self::$container[$name];
    }


    private static function createContainer($name, $pass){
        switch (strtolower($name)){
            case 'user':
                self::$container[$name] = new User($pass);
                return;
            default:
                throw new DiException(sprintf('Class "%s" not found. Container instantinate failed.', $name));
        }
    }
}