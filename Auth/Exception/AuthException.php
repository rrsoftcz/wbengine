<?php

namespace Wbengine\Auth\Exception;

use Wbengine\Application\ApplicationException;

class AuthException extends ApplicationException {
    CONST ERROR_INVALID_PAYLOAD_KEY = 4000;
}