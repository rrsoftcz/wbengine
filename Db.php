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

namespace Wbengine;

use Wbengine\Application\Env\Stac\Utils;
use Wbengine\Config\Adapter\AdapterInterface;
use Wbengine\Db\Adapter\DbAdapterInterface;
use Wbengine\Db\Exception\DbException;
use Wbengine\Exception;

//use Wbengine\Db\Exception;

class Db
{

    /**
     * Instance of Class_Cms
     * @var \Wbengine\Config\Adapter\AdapterAbstract
     */
    private static $dbCredentials = NULL;


    private static $_adapterName = null;


    /**
     * @var DbAdapterInterface
     */
    private static $_adapter = NULL;

    private static $_qcount    = 0;
    private static $_qarray    = array();


    /**
     * Create array with all variables needed for render site.
     * @param \Wbengine\Config\Adapter\AdapterAbstract
     */
    public function __construct($config)
    {
        $this->dbCredentials = $config;
    }


    public static function setCredentials($credentials){
        var_dump($credentials);die();
        self::$dbCredentials = $credentials;
        return self;
    }

    public function isConnected(){
        return ($this->_adapter instanceof DbAdapterInterface);
    }

    /**
     * @return DbAdapterInterface
     */
    public static function getAdapter(){
        if(null === self::$_adapter){
            self::createAdapter();
        }
        return self::$_adapter;
    }

    public static function getConnection()
    {
//        if (self::$_adapter === null) {
//            self::createAdapter();
//        } else {
//            return self::$_adapter;
//        }
        var_dump(self::getAdapter());
        return self::getAdapter()->getConnection();
    }


    /**
     *
     * @param type $config
     * @return DbAdapterInterface
     * @throws Db\dbException
     */
    private static function createAdapter()
    {
        self::$_adapterName = self::$dbCredentials->adapterName;
        if (empty(self::$_adapterName)) {
            throw new DbException(__METHOD__ .
                ': adapter name cannot be empty.');
        }

        $className = self::buildClassName(self::$dbCredentials->adapterName);

        if (!class_exists($className, true)) {
            throw new \Wbengine\Db\Exception\DbException(__METHOD__ .
                ': Cannot create adapter instance of \Wbengine\Db\Adapter\\' . $className);
        }

        try {
            /**
             * Create adapter object
             */
            self::$_adapter = New $className(self::$dbCredentials);
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
    private static function buildClassName($name)
    {
        return "Wbengine\Db\Adapter\\" .
            ucfirst((string)$name);
    }


    public static function getAllQueries(){
        return self::$_qarray;
    }

    public static function query($sql){
        self::$_qcount++;
        self::$_qarray[] = $sql;

        var_dump(self::getConnection()->query($sql));

        return self::getConnection()->query($sql);
    }

    public function fetchRow($sql){
        return $this->getConnection()->query($sql)->fetch_row();
    }

    public function fetchOne($sql){
        return $this->getConnection()->query($sql)->fetch_field();
    }

    public function fetchAll($sql){
        return $this->getConnection()->query($sql)->fetch_all();
    }

    public function fetchAssoc($sql){
        return $this->getConnection()->query($sql)->fetch_assoc();
    }


}
