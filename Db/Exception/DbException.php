<?php

/**
 * @see Class_Exception
 */

namespace Wbengine\Db\Exception;

use Wbengine\Exception;

class DbException extends Exception\RuntimeException {
    CONST ERROR_DB_ADAPTER_NAME = 5001;

}
