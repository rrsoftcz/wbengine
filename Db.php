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
        try{
            return self::getAdapter()->getConnection();
        }catch(DbAdapterException $dbException){
            throw new DbException($dbException->getMessage());
        }
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
        } catch (DbAdapterException $e) {
            throw New DbException(__METHOD__
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
        $sum = 0.0;
        foreach (self::getAllQueries() as $query){
            $sum += (float)$query['time'];
        }
        return self::$_qtime = $sum;
    }


    /**
     * Dump all queries to screen
     * @var void
     */
    public static function dumpAllQueries()
    {
        foreach (self::getAllQueries() as $query){
            echo('<pre>');
            print_r($query);
            echo('</pre>');
        }
    }


    /**
     * Run a query. Values MUST be passed as $params (positional "?" placeholders),
     * never concatenated into $sql.
     *
     * @param string $sql
     * @param array $params
     * @return \mysqli_result|bool
     * @throws DbException
     */
    public static function query($sql, array $params = array())
    {
        $start = microtime(true);
        try {
            $res = self::getConnection()->execute_query($sql, $params);
        } catch (\mysqli_sql_exception $e) {
            self::updateStats($sql, sprintf('%f', microtime(true) - $start));
            // The SQL text stays in the server log, it must not reach the client.
            error_log(sprintf('%s: %s | SQL: %s', __METHOD__, $e->getMessage(), $sql));
            throw new DbException(__METHOD__ . ' : Query execution error.', 0, $e);
        }

        self::updateStats($sql, sprintf('%f', microtime(true) - $start));

        if ($res === false) {
            error_log(sprintf('%s: %s | SQL: %s', __METHOD__, self::getConnection()->error, $sql));
            throw new DbException(__METHOD__ . ' : Query execution error.');
        }

        return $res;
    }


    /**
     * Quote a table or column name. Only [A-Za-z0-9_] is accepted.
     * @param string $name
     * @return string
     * @throws DbException
     */
    public static function identifier($name)
    {
        if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new DbException(__METHOD__ . ' : Invalid SQL identifier.');
        }
        return '`' . $name . '`';
    }


    /**
     * INSERT one row. Column names are validated, values are bound.
     * @param string $table
     * @param array $data column => value
     * @return int inserted id
     * @throws DbException
     */
    public static function insert($table, array $data)
    {
        if (!$data) {
            throw new DbException(__METHOD__ . ' : No data to insert.');
        }
        $cols = implode(',', array_map(array(self::class, 'identifier'), array_keys($data)));
        $marks = implode(',', array_fill(0, count($data), '?'));
        self::query(sprintf('INSERT INTO %s (%s) VALUES (%s)', self::identifier($table), $cols, $marks), array_values($data));
        return (int)self::getInserted();
    }


    /**
     * UPDATE rows. Column names are validated, values are bound.
     * @param string $table
     * @param array $data column => value
     * @param array $where column => value, combined with AND
     * @return int affected rows
     * @throws DbException
     */
    public static function update($table, array $data, array $where)
    {
        if (!$data || !$where) {
            throw new DbException(__METHOD__ . ' : Data and where must not be empty.');
        }
        $set = implode(',', array_map(function ($c) { return self::identifier($c) . '=?'; }, array_keys($data)));
        $cond = implode(' AND ', array_map(function ($c) { return self::identifier($c) . '=?'; }, array_keys($where)));
        self::query(
            sprintf('UPDATE %s SET %s WHERE %s', self::identifier($table), $set, $cond),
            array_merge(array_values($data), array_values($where))
        );
        return (int)self::getAffected();
    }

    /**
     * Return DB data as array
     * @param $sql
     * @return mixed
     */
    public static function fetchRow($sql, array $params = array()){
        return self::query($sql, $params)->fetch_row();
    }


    /**
     * Return DB item as object
     * @param $sql
     * @return object
     */
    public static function fetchOne($sql, array $params = array()){
        return self::query($sql, $params)->fetch_field();
    }


    /**
     * Return DB data as array
     * @param $sql
     * @return mixed
     */
    public static function fetchAll($sql, array $params = array()){
        return self::query($sql, $params)->fetch_all();
    }


    /**
     * Return DB data as object
     * @param $sql
     * @return object|\stdClass
     */
    public static function fetchObject($sql, array $params = array()){
        return self::query($sql, $params)->fetch_object();
    }


    /**
     * Return DB data as assoc array
     * @param $sql
     * @return array
     */
    public static function fetchAssoc($sql, array $params = array()){
        return self::query($sql, $params)->fetch_assoc();
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


    /**
     * Return DB data all records as assoc array
     * @param $sql
     * @return mixed
     */
    public static function fetchAllAssoc($sql, array $params = array())
    {
        $start  = microtime(true);
        $res    = self::getAdapter()->getAllAssoc($sql, $params);
        $end    = microtime(true);
        $time   = ($end-$start);

        self::updateStats($sql, sprintf('%f', $time));

        return $res;
    }


}
