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
    public static function query($sql);
    public function fetchRow($sql);
    public function fetchOne($sql);
    public function fetchAll($sql);
    public static function fetchObject($sql);
    public static function fetchAllAssoc($sql);
    public static function fetchAssoc($sql);
    public static function getQueriesCount();
    public static function getAllQueries();
    public function dumpAllQueries();
    public static function getAllQueriesEstimatedTime();
}