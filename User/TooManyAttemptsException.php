<?php

namespace Wbengine\User;

/**
 * Too many failed logins. The caller has to wait $retryAfter seconds.
 */
class TooManyAttemptsException extends UserException
{
    private $_retryAfter;

    public function __construct($retryAfter)
    {
        parent::__construct('Too many failed login attempts. Try again later.');
        $this->_retryAfter = max(1, (int) $retryAfter);
    }

    public function getRetryAfter()
    {
        return $this->_retryAfter;
    }
}
