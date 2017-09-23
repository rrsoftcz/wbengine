<?php

/**
 * $Id: Abstract.php 85 2010-07-07 17:42:43Z bajt $
 * ----------------------------------------------
 * Abstract Class for the site models.
 *
 * @package RRsoft-CMS
 * @version $Rev: 30 $
 * @copyright (c) 2009-2010 RRsoft www.rrsoft.cz
 * @license GNU Public License
 *
 * Minimum Requirement: PHP 5.1.x
 */

namespace Wbengine\Model;

use Wbengine;
use Wbengine\Config;
use Wbengine\Registry;
use Zend\Db\Sql\Select;
use Wbengine\Db\Db;


abstract class ModelAbstract
{


    /**
     * Instance of database connection
     * @var \Zend\Db\Adapter\Pdo_Mysql
     */
    private static $_db = NULL;


    /**
     * Return parsed sql statement
     * @param $select Select
     * @return mixed
     */
    public function getSqlString($select)
    {
        if ($select instanceof Select) {
            return $select->getSqlString($this->getDbAdapter()->getPlatform());
        }
    }


    /**
     * Set Zend_Db_Adapter_Pdo_Mysql
     */
    private function _setDb()
    {
//        $e = new \Exception();
//        echo('<pre>');
//        print_r($e->getTraceAsString());
//        echo('</pre>');

        $db = New Db(Config::getDbCredentials());
        self::$_db = $db->getAdapter();
        return self::$_db;
    }


    /**
     * Return database connection.
     * @return \Zend\Db\Adapter\Adapter
     */
    public function getDbAdapter()
    {
        if (null === self::$_db) {
            $this->_setDb();
        }
        return self::$_db;
    }


}
