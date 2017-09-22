<?php

/**
 * $Id: Site.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Site Vars class.
 *
 * @package RRsoft-CMS * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Db;

use PDO;
use Wbengine\Config\Adapter\AdapterInterface;
use Wbengine\Db\Exception\DbException;
use Wbengine\Exception;

//use Wbengine\Db\Exception;

class Db
{

    /**
     * Instance of Class_Cms
     * @var \Wbengine\Config\Adapter\AdapterAbstract
     */
    protected $dbCredentials = NULL;


    /**
     * @var Wbengine\Db\Adapter\DbAdapterInterface
     */
    protected $adapter = NULL;


    /**
     * Create array with all variables needed for render site.
     * @param \Wbengine\Config\Adapter\AdapterAbstract
     */
    public function __construct($config)
    {
        $this->dbCredentials = $config;
    }


    public function getAdapter()
    {
        if ($this->adapter instanceof \Wbengine\Db\Adapter\DbAdapterInterface) {
            return $this->adapter;
        } else {
            $this->createAdapter();
            return $this->adapter->getAdapter();
        }
    }


    /**
     *
     * @param type $config
     * @return PDO
     * @throws Db\dbException
     */
    private function createAdapter()
    {
        if (empty($this->dbCredentials->adapterName)) {
            throw new DbException(__METHOD__ .
                ': adapter name cannot be empty.');
        }

        $className = $this->buildClassName($this->dbCredentials->adapterName);

        if (!class_exists($className, true)) {
            throw new \Wbengine\Db\Exception\DbException(__METHOD__ .
                ': Cannot create adapter instance of \Wbengine\Db\Adapter\\' . $className);
        }

        try {
            /**
             * Create adapter object
             */
            $this->adapter = New $className((array)$this->dbCredentials);
        } catch (Exception\DbAdapterException $e) {
            throw New Exception\DbException(__METHOD__
                . ': Wbengine\Db\Exception\DbException with a message: ' . $e->getMessage());
        }
    }


    /**
     * Create namespaced class name
     * @param string $name
     * @return string
     */
    private function buildClassName($name)
    {
        return "Wbengine\Db\Adapter\\" .
            ucfirst((string)$name);
    }

}
