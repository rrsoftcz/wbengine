<?php

/**
 * @see Class_Exception
 */

namespace Wbengine\Db\Exception;

use Wbengine\Application\ApplicationException;
use Wbengine\Exception;

class DbException extends ApplicationException {
    CONST ERROR_DB_ADAPTER_NAME = 5001;
}
