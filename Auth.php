<?php

namespace Wbengine;
use \Firebase\JWT\JWT;
use \Wbengine\Auth\Exception\AuthException;

class Auth {

    protected $default_token_key = "e866430c28a90a559f650791c48e8dcadd3d32b53345205d187f1002ecf64df8bcb564a04632efda6664af2083a62f09ee9f40eac165084d553dff182201e522";
    protected $refresh_token_key = "67f459df91c4c5b6d18e9b2c3e10921b34dd1cc3d3531fdd0ca13f4b5bc6899817ba80016a202d85a885390d4510975b4e93246b7a93b10cc93329c65976f274";

    private $payload = array(
        "iss" => "",
        "iat" => "",
        "exp" => "",
        "data" => array()
    );

    protected $default_jwt;
    protected $refresh_jwt;

    private $_user = null;

    const HASH_ALGORITHM = 'HS256';

    public function __construct() {

        $_issued_time = time();
        $_expired_time = $_issued_time + (60*60);

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

    public function setIssuedTime(int $time) {
        $this->_setPaylodValue("iss", $time);
        return $this;
    }

    public function setExpiredTime(int $time) {
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

    public function setKey(int $key) {
        $this->key = $key;
        return $this;
    }

    public function getJwtToken() {
        return JWT::encode($this->payload, $this->default_token_key,self::HASH_ALGORITHM);
    }

    public function getRefreshToken() {
        return JWT::encode($this->payload, $this->refresh_token_key,self::HASH_ALGORITHM);
    }

    public function getDecodedData($jwt) {
        return (array) JWT::decode($jwt, $this->default_token_key, array(self::HASH_ALGORITHM));
    }

    public function validateRefreshToken(string $refreshToken) {
        return (array) JWT::decode($refreshToken, $this->refresh_token_key, array(self::HASH_ALGORITHM));
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