<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 25.09.17
 * Time: 9:22
 */

namespace Wbengine\Db\Adapter;


use Wbengine\Config\Value;
use Wbengine\Db\Adapter\Exception\DbAdapterException;

class Mysqli implements DbAdapterInterface
{
    protected $_username    = null;
    protected $_password    = null;
    protected $_hostname    = null;
    protected $_database    = null;
    private $_connection    = null;

    private $query_count    = 0;
    private $queries        = array();

    public function __construct(Value $config = null)
    {
        if ($config instanceof Value) {
//            var_dump($config->hostsss);die();
            $this->_username = $this->Validate($config->username, 'username');
            $this->_password = $this->Validate($config->password, 'password');
            $this->_hostname = $this->Validate($config->hostname, 'hostname');
            $this->_database = $this->Validate($config->database, 'database');
        } else {
            throw New DbAdapterException(
                __CLASS__ . "->" . __FUNCTION__ .
                ": Bad Mysqli connection values, expect instance of object Wbengine\Config\Value but " .
                gettype($config) . " given.");
        }
        return $this;
    }

    /**
     * Return created Mysqli connection ...
     * @return \mysqli
     */
    public function getConnection(){
        if($this->_connection === null){
            $this->_createConnection();
        }
        return $this->_connection;
    }

    private function Validate($value, $name = null){
//        var_dump($name);
        if(is_null($value)){
            throw New DbAdapterException(
                __CLASS__ . "->" . __FUNCTION__ .
                ((!$name)? ": An adapter argumet has empty value.":
                ": Adapter argumet '" . $name . "' has empty value.")
            );
        }
        return $value;
    }

    private function _createConnection(){
        $this->_connection = new \mysqli(
            $this->_hostname,
            $this->_username,
            $this->_password,
            $this->_database
        );
//        $this->_connection->query('')->fetch_assoc();
        if (mysqli_connect_error()) {
            die('Connect Error (' . mysqli_connect_errno() . ') '
                . mysqli_connect_error());
        }
    }

    public function query($sql){
        $this->query_count++;
        $this->queries[] = $sql;

        var_dump($this->query_count);

        return $this->getConnection()->query($sql);
    }
//
    public function fetchRow($sql){
        return $this->getConnection()->query($sql)->fetch_row();
    }

    public function getAllAssoc($sql){
        return mysqli_fetch_all($this->getConnection()->query($sql),MYSQLI_ASSOC);
    }
    //
//    public function fetchOne($sql){
//        return $this->getConnection()->query($sql)->fetch_field();
//    }
//
//    public function fetchAll($sql){
//        return $this->getConnection()->query($sql)->fetch_all();
//    }
//
//    public function fetchAssoc($sql){
//        return $this->getConnection()->query($sql)->fetch_assoc();
//    }

}