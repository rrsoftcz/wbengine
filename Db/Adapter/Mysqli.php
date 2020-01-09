<?php
/**
 * Created by PhpStorm.
 * User: roza
 * Date: 25.09.17
 * Time: 9:22
 */

namespace Wbengine\Db\Adapter;


use Wbengine\Application\ApplicationException;
use Wbengine\Config\Value;
use Wbengine\Db\Adapter\Exception\DbAdapterException;

class Mysqli implements DbAdapterInterface
{
    protected $_username;
    protected $_password;
    protected $_hostname;
    protected $_database;
    protected $_connection;
    protected $_charset;


    public function __construct(Value $config = null)
    {
        if ($config instanceof Value) {
            $this->_username = $this->Validate($config->username, 'username');
            $this->_password = $this->Validate($config->password, 'password');
            $this->_hostname = $this->Validate($config->hostname, 'hostname');
            $this->_database = $this->Validate($config->database, 'database');
            $this->_dbencode = $this->Validate($config->charset, 'charset');
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

        mysqli_report(MYSQLI_REPORT_STRICT );

        try {
            $this->_connection = new \mysqli(
                $this->_hostname,
                $this->_username,
                $this->_password,
                $this->_database
            );

            if($this->_dbencode){
                if (!$this->_connection->set_charset($this->_dbencode)) {
                    throw new DbAdapterException(sprintf('%s->%s: %s.'
                            , __CLASS__
                            , __FUNCTION__
                            , $this->_connection->error
                        )
                    );
                    exit();
                }
            }

        }catch (\mysqli_sql_exception $e){
            throw new DbAdapterException(
                sprintf('%s->%s: %s.'
                    , __CLASS__
                    , __FUNCTION__
                    , $e->getMessage()
                )
            );

        }
    }

    public function getDbVersion() {
        return $this->getConnection()->server_version;
    }

    public function getAllAssoc($sql)
    {
        $mysqli_result = $this->getConnection()->query($sql);
        if($mysqli_result instanceof \mysqli_result) {
            return mysqli_fetch_all($mysqli_result, MYSQLI_ASSOC);
        }else{
            throw New DbAdapterException(
                __CLASS__ . "->" . __FUNCTION__ . ": Mysqli_result error."
            );
        }
    }
}