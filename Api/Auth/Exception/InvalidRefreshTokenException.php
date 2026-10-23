<?php

namespace Wbengine\Api\Auth\Exception;

/**
 * The presented refresh token cannot be used. The reason is for the log only and
 * must not be shown to the client.
 */
class InvalidRefreshTokenException extends AuthException
{
    private $_reason;

    public function __construct($reason)
    {
        parent::__construct('Invalid refresh token.');
        $this->_reason = (string) $reason;
    }

    public function getReason()
    {
        return $this->_reason;
    }
}
