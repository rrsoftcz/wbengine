<?php

namespace Wbengine\Session\Exception;
use Wbengine\Exception\RuntimeException;


/**
 * Class SessionException
 */
class SessionException extends RuntimeException {
    CONST SESSION_ERROR_DATA_NOT_LOADED = 155;
    CONST SESSION_ERROR_PROPERTIES_NOT_EXIST = 156;
    CONST SESSION_ERROR_INVALID_SOURCE = 157;
    CONST SESSION_MAGIC_QUOTE_IS_NULL = 158;
}
