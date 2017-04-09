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
    private $db = NULL;



    /**
     * We do nothing on this constructor
     */
//    public function __construct(\Wbengine\Site $site) {
//    public function __construct()
//    {
//	$this->db = $site->getParent()->getDbAdapter();
//    }

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
//        var_dump(Config::getDbCredentials());
//        var_dump(Config::getAdapter()->getDbCredentials());
        $db = New Db(Config::getDbCredentials());
        $this->db = $db->getAdapter();
//                                  var_dump( $db->getAdapter());
        // prepare PDO connection...
//        Registry::set("db", $db->getAdapter());

//        $this->db = Wbengine\Registry::get("db");
        return $this->db; //= $dbAdapter;
    }


    /**
     * Return database connection.
     * @return \Zend\Db\Adapter\Adapter
     */
    public function getDbAdapter()
    {
        $trace = debug_backtrace();
//        var_dump($trace[2]['function']);
//        var_dump($this->db);
        if (null === $this->db) {
//            var_dump($this->_setDb());
            $this->_setDb();
        }

        return $this->db;
    }


}
