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

use Wbengine\Config\Value;
use Wbengine\Db\Adapter\DbAdapterInterface;
use Wbengine\Db\Adapter\Exception\DbAdapterException;
use Wbengine\Db\Adapter\Mysqli;
use Wbengine\Db\DbInterface;
use Wbengine\Db\Exception\DbException;


abstract class Db implements DbInterface
{

    /**
     * Instance of Class_Cms
     * @var Value
     */
    private static $dbCredentials = NULL;


    /**
     * Database adapter name as string
     * @var string
     */
    private static $_adapterName = null;


    /**
     * @var DbAdapterInterface
     */
    private static $_adapter = NULL;

    /**
     * Return Queries count as integer
     * @var int
     */
    private static $_qcount    = 0;
    private static $_qarray    = array();
    private static $_qtime     = 0;


    private static function updateStats($query = null, $time = null){
        self::$_qcount++;
        if($query) {
            self::$_qarray[] = array('query'=>$query,'time'=>$time);
        }
    }

    public static function isConnected(){
        return (self::$_adapter instanceof DbAdapterInterface);
    }

    /**
     * @return DbAdapterInterface
     */
    public static function getAdapter(){
        if(!self::isConnected()){
            self::createAdapter();
        }
        return self::$_adapter;
    }

    /**
     * Return Db connection as adapter object...
     * @return Mysqli
     */
    public static function getConnection()
    {
        return self::getAdapter()->getConnection();
    }


    private static function createAdapter()
    {
        $className = self::buildClassName(self::getAdapterName());

        if (!class_exists($className, true)) {
            throw new DbException(__METHOD__ .
                ': Cannot create adapter instance of \Wbengine\Db\Adapter\\' . $className);
        }

        try {
            /**
             * Create adapter object
             */
            self::$_adapter = New $className(self::$dbCredentials);
        } catch (DbException $e) {
            throw New DbAdapterException(__METHOD__
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

    private static function getAdapterName(){
        if(self::$dbCredentials === null){
            self::$dbCredentials = Config::getDbCredentials();
        }

        self::$_adapterName = self::$dbCredentials->adapterName;

        if(empty(self::$_adapterName)){
            Throw New DbException(sprintf("%s -> %s: Get DB adapter name Error!",
                __CLASS__,
                __FUNCTION__),
                DbException::ERROR_DB_ADAPTER_NAME);
        }else{
            return self::$_adapterName;
        }
    }

    public static function getQueriesCount(){
        return self::$_qcount;
    }

    public static function getAllQueries(){
        return self::$_qarray;
    }

    public static function getAllQueriesEstimatedTime(){
        foreach (self::getAllQueries() as $query){
            self::$_qtime =+ $query['time'];
        }
        return self::$_qtime;
    }

    public function dumpAllQueries(){
        foreach (self::getAllQueries() as $query){
            echo('<pre>');
            print_r($query);
            echo('</pre>');
        }
    }

    /**
     * @param $sql
     * @return \mysqli_result
     */
    public static function query($sql){
        $start = microtime(true);
        $res = self::getConnection()->query($sql);
        $end = microtime(true);
        $time = ($end-$start);
        self::updateStats($sql, sprintf('%f', $time));
        return $res;
    }

    public function fetchRow($sql){
        return self::query($sql)->fetch_row();
    }

    public function fetchOne($sql){
        return self::query($sql)->fetch_field();
    }

    public function fetchAll($sql){
        return self::query($sql)->fetch_all();
    }

    public static function fetchObject($sql){
        return self::query($sql)->fetch_object();
    }

    public static function fetchAssoc($sql){
        return self::query($sql)->fetch_assoc();
    }

    public static function fetchAllAssoc($sql){
        self::updateStats($sql);
        return self::getAdapter()->getAllAssoc($sql);
    }


}
