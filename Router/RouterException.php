<?php

 /**
 * @see Class_Exception
 */

namespace Wbengine\Router;

use Wbengine\Application\ApplicationException;
use Wbengine\Exception\RuntimeException;

class RouterException extends ApplicationException
{
    const ROUTE_ERROR_NO_MEZHOD_FOUND   = 888;
}
