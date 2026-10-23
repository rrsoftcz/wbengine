<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 29.09.17
 * Time: 11:42
 */

namespace Wbengine\Db;


interface DbInterface
{
    public static function query($sql, array $params = array());
    public static function fetchRow($sql, array $params = array());
    public static function fetchOne($sql, array $params = array());
    public static function fetchAll($sql, array $params = array());
    public static function fetchObject($sql, array $params = array());
    public static function fetchAllAssoc($sql, array $params = array());
    public static function fetchAssoc($sql, array $params = array());
    public static function getQueriesCount();
    public static function getAllQueries();
    public static function dumpAllQueries();
    public static function getAllQueriesEstimatedTime();
}