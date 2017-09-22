<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 22.09.17
 * Time: 16:28
 */

namespace Wbengine\Config;


class Value
{
    private $_value = null;
    private $_childrens = null;

    public function __construct($value)
    {
        $this->_value = $value;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->_value)) {
            return $this->_value->$name;
        }
        return null;
    }

    public function getChildrens(){
        if($this->isArray() === true){
            foreach ($this->_value as $value){
                $this->_childs[] = New self($value);
            }
            return $this->_childrens;
        }
        return array();
    }

    public function getValue(){
        if($this->isArray() === true){
            return $this->getChildrens();
        }else {
            return $this->_value;
        }
    }

    public function isArray(){
        return is_array($this->_value);
    }
}