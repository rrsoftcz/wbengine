<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 27.09.17
 * Time: 19:46
 */

namespace Wbengine\Session;


use Wbengine\Session\Value\ValueException;

class Value
{
    private $values;


    public function __construct($name = null, $value = null)
    {
        $this->values = new \stdClass();
        if($name){
            $this->$name = $value;
        }
    }

    public function __set($name, $value)
    {
        if(!$name){
            Throw new ValueException(sprintf("%s -> %s: The value name cannot be empty.",
                    __CLASS__,
                    __FUNCTION__),
                    ValueException::VALUE_ERROR_NOT_VALUE_NAME);
        }
        $this->values->$name = $value;
    }


    public function __get($name)
    {
        return $this->values->$name;
    }

    public function get($name){
        return $this->$name;
    }

    public function add($name, $value){
        $this->values->$name = $value;
    }

    public function toString(){
        return serialize($this->values);
    }
}