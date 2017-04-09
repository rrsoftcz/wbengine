<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Registry
 *
 * @author roza
 */

namespace Wbengine;

Class Registry {

    private static $data = array();
    private static $count = 0;

    public function __isset($name) {
	return isset($this->data[$name]);
    }

    public function __get($name) {
	return self::get($name);
    }

    static function get($name, $default = null) {
	if (array_key_exists($name, self::$data)) {
	    return self::$data[$name];
	}

	return null;
    }

    static function set($name, $value) {
	if (null === $name) {
	    self::$data[] = $value;
	} else {
	    self::$data[$name] = $value;
	}

	self::$count++;
    }

    public function __set($name, $value) {

	if (null === $name) {
	    self::$data[] = $value;
	} else {
	    self::$data[$name] = $value;
	}

	self::$count++;
    }

    public function toArray() {
	$array = array();
	$data = $this->data;

	/** @var self $value */
	foreach ($data as $key => $value) {
	    if ($value instanceof self) {
		$array[$key] = $value->toArray();
	    } else {
		$array[$key] = $value;
	    }
	}

	return $array;
    }

}
