<?php

namespace Wbengine;
use \Firebase\JWT\JWT;
use \Wbengine\Auth\Exception\AuthException;

class Auth {

    private $key = "1966eb141f90588314e97af0c28e8354";
    private $payload = array(
        "iss" => "",
        "iat" => "",
        "exp" => "",
        "data" => array()
    );

    protected $jwt;

    private $_user = null;

    const HASH_ALGORITHM = 'HS256';

    public function __construct() {

        $_issued_time = time();
        $_expired_time = $_issued_time + (60);

        $this->_setPaylodValue("iss", "");
        $this->_setPaylodValue("iat", $_issued_time);
        $this->_setPaylodValue("exp", $_expired_time);

    }

    private function getUser() {
        if(null === $this->_user) {
            return $this->_user = new User($this);
        }
        return $this->_user;
    }

    public function setLoginName(string $name) {
        $this->getUser()->setLoginName($name);
    }

    public function setLoginPassword(string $password) {
        $this->getUser()->setLoginPassword($password);
    }

    public function setIssuedTime(number $time) {
        $this->_setPaylodValue("iss", $time);
        return $this;
    }

    public function setExpiredTime(number $time) {
        $this->_setPaylodValue("exp", $time);
        return $this;
    }

    public function setIssuer(string $issuer) {
        $this->_setPaylodValue("iss", $issuer);
        return $this;
    }

    public function setPayloadData(array $data) {
        $this->_setPaylodValue("data", $data);
        return $this;
    }

    public function setKey(number $key) {
        $this->key = $key;
        return $this;
    }

    public function getJwtToken() {
        return $this->jwt = JWT::encode($this->payload, $this->key);
    }

    public function getDecodedData(string $jwt) {
        return (array) JWT::decode($jwt, $this->key, array(self::HASH_ALGORITHM));
    }

    private function _setPaylodValue($name, $value) {
        if(array_key_exists($name, $this->payload)) {
            $this->payload[$name] = $value;
        } else {
            Throw New AuthException(
                sprintf("Payload value '%s' does not exist", $name),
                AuthException::ERROR_INVALID_PAYLOAD_KEY);
        }
    }
}