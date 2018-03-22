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

    /**
     * Array of executed DB queries
     * @var array
     */
    private static $_qarray    = array();

    /**
     * Query execution time as microtime
     * @var int
     */
    private static $_qtime     = 0;


    /**
     * Update query statistic ...
     * @param null $query
     * @param null $time
     */
    private static function updateStats($query = null, $time = null){
        if($query) {
            self::$_qarray[] = array('query'=>$query,'time'=>$time);
        }
        self::$_qcount++;
    }


    /**
     * Get state of db connected
     * @return bool
     */
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
    public static function getConnection(){
        return self::getAdapter()->getConnection();
    }


    /**
     * Create DB adapter by name from config file
     * @throws DbAdapterException
     * @throws DbException
     */
    private static function createAdapter()
    {
        $className = self::buildClassName(self::getAdapterName());

        if (!class_exists($className, true)) {
            throw new DbException(__METHOD__ .
                ': Can\'t create instance of DB adapter "' . $className.'". Adapter class not found.');
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
    private static function buildClassName($name){
        return "Wbengine\Db\Adapter\\" . ucfirst((string)$name);
    }


    /**
     * Return adapter name defined in config file
     * @return null|string
     * @throws DbException
     */
    private static function getAdapterName()
    {
        if(self::$dbCredentials === null){
            self::$dbCredentials = Config::getDbCredentials();
        }

        self::$_adapterName = self::$dbCredentials->adapterName;

        if(empty(self::$_adapterName)){
            Throw New DbException(sprintf("%s -> %s: Empty db adapter name.",
                __CLASS__,
                __FUNCTION__),
                DbException::ERROR_DB_ADAPTER_NAME);
        }else{
            return self::$_adapterName;
        }
    }


    /**
     * Return executed queries count
     * @return int
     */
    public static function getQueriesCount(){
        return self::$_qcount;
    }


    /**
     * Return array of all executed queries
     * @return array
     */
    public static function getAllQueries(){
        return self::$_qarray;
    }


    /**
     * Return time sum of allexecuted queries
     * @return int
     */
    public static function getAllQueriesEstimatedTime()
    {
        foreach (self::getAllQueries() as $query){
            self::$_qtime =+ $query['time'];
        }
        return self::$_qtime;
    }


    /**
     * Dump all queries to screen
     * @var void
     */
    public function dumpAllQueries()
    {
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
    public static function query($sql)
    {
        $start = microtime(true);
        $res = self::getConnection()->query($sql);
        $end = microtime(true);
        $time = ($end-$start);

        self::updateStats($sql, sprintf('%f', $time));

        if($res === false){
            throw new DbException(
                sprintf('%s : Query execution error: %s <br><h4>Query:</h4><pre>%s</pre>)'
                , __METHOD__
                , mysqli_error(self::getConnection())
                , $sql)
            );
        }

        return $res;
    }

    /**
     * Return DB data as array
     * @param $sql
     * @return mixed
     */
    public function fetchRow($sql){
        return self::query($sql)->fetch_row();
    }


    /**
     * Return DB item as object
     * @param $sql
     * @return object
     */
    public function fetchOne($sql){
        return self::query($sql)->fetch_field();
    }


    /**
     * Return DB data as array
     * @param $sql
     * @return mixed
     */
    public function fetchAll($sql){
        return self::query($sql)->fetch_all();
    }


    /**
     * Return DB data as object
     * @param $sql
     * @return object|\stdClass
     */
    public static function fetchObject($sql){
        return self::query($sql)->fetch_object();
    }


    /**
     * Return DB data as assoc array
     * @param $sql
     * @return array
     */
    public static function fetchAssoc($sql){
        return self::query($sql)->fetch_assoc();
    }

    public static function getInserted(){
        return self::getConnection()->insert_id;
    }


    public static function getAffected(){
        return self::getConnection()->affected_rows;
    }

    public static function getError(){
        return self::getConnection()->error;
    }


    public static function createUpdateQuery($table, $data, $statements = array()){
        $sql = "UPDATE ".$table . " SET ";
        $i=1;
        array_walk($data,function(&$value, &$key){$value = "`".$key."`='".$value."'";});
        foreach ($data as $value){
            $sql .= $value;
            $sql .= ($i< sizeof($data))?",":"";
            ++$i;
        }

        $sql .= " WHERE " . implode(" AND ", $statements).";";
        return $sql;
    }


    /**
     * Return DB data all records as assoc array
     * @param $sql
     * @return mixed
     */
    public static function fetchAllAssoc($sql)
    {
        $start  = microtime(true);
        $res    = self::getAdapter()->getAllAssoc($sql);
        $end    = microtime(true);
        $time   = ($end-$start);

        self::updateStats($sql, sprintf('%f', $time));

        return $res;
    }


}
