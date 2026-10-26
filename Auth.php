<?php

namespace Wbengine;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use \Wbengine\Auth\Exception\AuthException;

class Auth {

    /**
     * Signing keys, loaded from the configuration (never stored in the source code).
     */
    protected $default_token_key;
    protected $refresh_token_key;

    const MIN_KEY_LENGTH = 32;

    private $payload = array(
        "iss" => "",
        "iat" => "",
        "exp" => "",
        "data" => array()
    );

    protected $default_jwt;
    protected $key;
    protected $refresh_jwt;

    private $_user = null;

    const HASH_ALGORITHM = 'HS256';

    public function __construct() {

        $keys = Config::getJwtKeys();
        foreach (array('access', 'refresh') as $name) {
            if (!is_string($keys[$name]) || strlen($keys[$name]) < self::MIN_KEY_LENGTH) {
                throw new AuthException(
                    sprintf("The JWT %s key is not configured or is shorter than %d characters. Set \"jwt\" in the settings file or the WBENGINE_JWT_%sKEY environment variable.",
                        $name, self::MIN_KEY_LENGTH, ($name === 'refresh') ? 'REFRESH_' : ''),
                    AuthException::ERROR_MISSING_KEY
                );
            }
        }
        $this->default_token_key = $keys['access'];
        $this->refresh_token_key = $keys['refresh'];

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
        $this->_setPaylodValue("iat", $time);
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
        return (array) JWT::decode($jwt, new Key($this->default_token_key, self::HASH_ALGORITHM));
    }

    public function validateRefreshToken(string $refreshToken) {
        return (array) JWT::decode($refreshToken, new Key($this->refresh_token_key, self::HASH_ALGORITHM));
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
