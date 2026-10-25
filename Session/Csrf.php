<?php

namespace Wbengine\Session;

use Wbengine\Application\Env\Http;
use Wbengine\PageCache;
use Wbengine\Session;
use Wbengine\Template\FragmentCache;

/** Session synchronizer tokens, separated by form purpose and stable across tabs. */
class Csrf
{
    const SESSION_KEY = '__wbengine_csrf_secret';
    private Session $_parent;

    public function __construct(Session $parent)
    {
        $this->_parent = $parent;
    }

    public function getParent(): Session
    {
        return $this->_parent;
    }

    public function token(string $purpose = 'default'): string
    {
        $this->_purpose($purpose);
        $this->_privateResponse();
        $secret = $this->getParent()->getValue(self::SESSION_KEY);
        if (!$this->_validToken($secret)) {
            $secret = bin2hex(random_bytes(32));
            $this->getParent()->setValue(self::SESSION_KEY, $secret);
        }
        return hash_hmac('sha256', $purpose, $secret);
    }

    public function verify($token, string $purpose = 'default'): bool
    {
        $this->_purpose($purpose);
        $this->_privateResponse();
        if (!$this->_validToken($token)) {
            return false;
        }
        $secret = $this->getParent()->getValue(self::SESSION_KEY);
        return $this->_validToken($secret) && hash_equals(hash_hmac('sha256', $purpose, $secret), $token);
    }

    /** Accept a form field or AJAX header, never a token in the query string. */
    public function validateRequest(string $purpose = 'default'): bool
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? null, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }
        $header = Http::getHeader('X-CSRF-Token');
        return $this->verify(null !== $header ? $header : Http::PostString('token'), $purpose);
    }

    public function reset(): void
    {
        if (null !== $this->getParent()->getValue(self::SESSION_KEY)) {
            $this->getParent()->unsetKey(self::SESSION_KEY);
        }
    }

    private function _validToken($token): bool
    {
        return is_string($token) && 1 === preg_match('/^[a-f0-9]{64}$/D', $token);
    }

    private function _purpose(string $purpose): void
    {
        if ('' === $purpose || strlen($purpose) > 128) {
            throw new \InvalidArgumentException('CSRF purpose must contain between 1 and 128 bytes.');
        }
    }

    private function _privateResponse(): void
    {
        PageCache::bypass();
        FragmentCache::bypass();
        if (!headers_sent()) {
            header('Cache-Control: private, no-store');
        }
    }
}
