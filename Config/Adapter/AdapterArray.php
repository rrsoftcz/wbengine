<?php

/**
 * Provides a property based interface to an array.
 * The data are read-only unless $allowModifications is set to true
 * on construction.
 *
 * Implements Countable, Iterator and ArrayAccess
 * to facilitate easy access to the data.
 */

namespace Wbengine\Config\Adapter;

Class AdapterArray
{

    /**
     * Whether modifications to configuration data are allowed.
     *
     * @var bool
     */
    protected $allowModify;


    /**
     * Number of elements in configuration data.
     *
     * @var int
     */
    protected $count;


    /**
     * Data withing the configuration.
     *
     * @var array
     */
    protected $data = array();


    /**
     * Used when unsetting values during iteration to ensure we do not skip
     * the next element.
     *
     * @var bool
     */
    protected $skipNextIteration;
    protected $test = array();


    public function __construct($resource, $allowModify = false)
    {

        $this->allowModify = (bool)$allowModify;

        foreach ($resource as $key => $value) {
            if (is_array($value)) {
                $this->data[$key] = new static($value, $this->allowModify);
            } else {
                $this->data[$key] = $value;
            }

            $this->count++;
        }
    }


    public function get($name, $default = null)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        return $default;
    }


    public function __get($name)
    {
        return $this->get($name);
    }


    public function __set($name, $value)
    {
        if ($this->allowModify) {

            if (is_array($value)) {
                $value = new static($value, true);
            }

            if (null === $name) {
                $this->data[] = $value;
            } else {
                $this->data[$name] = $value;
            }

            $this->count++;
        } else {
            throw new Exception\RuntimeException('Config is read only');
        }
    }


    public function toArray()
    {
        $array = array();
        $data = $this->data;

        /** @var AdapterArray $value */
        foreach ($data as $key => $value) {
            if ($value instanceof self) {
                $array[$key] = $value->toArray();
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }


    public function __isset($name)
    {
        return isset($this->data[$name]);
    }


    public function __unset($name)
    {
        if (!$this->allowModify) {
            throw new Exception('Config is read only');
        } elseif (isset($this->data[$name])) {
            unset($this->data[$name]);
            $this->count--;
            $this->skipNextIteration = true;
        }
    }


    public function count()
    {
        return $this->count;
    }


    public function key()
    {
        return key($this->data);
    }


    public function valid()
    {
        return ($this->key() !== null);
    }


    public function merge(Config $merge)
    {
        /** @var Config $value */
        foreach ($merge as $key => $value) {
            if (array_key_exists($key, $this->data)) {
                if (is_int($key)) {
                    $this->data[] = $value;
                } elseif ($value instanceof self && $this->data[$key] instanceof self) {
                    $this->data[$key]->merge($value);
                } else {
                    if ($value instanceof self) {
                        $this->data[$key] = new static($value->toArray(), $this->allowModify);
                    } else {
                        $this->data[$key] = $value;
                    }
                }
            } else {
                if ($value instanceof self) {
                    $this->data[$key] = new static($value->toArray(), $this->allowModify);
                } else {
                    $this->data[$key] = $value;
                }

                $this->count++;
            }
        }

        return $this;
    }


    public function setReadOnly()
    {
        $this->allowModify = false;

        /** @var Config $value */
        foreach ($this->data as $value) {
            if ($value instanceof self) {
                $value->setReadOnly();
            }
        }
    }


    public function isReadOnly()
    {
        return !$this->allowModify;
    }

}
