<?php

 /**
 * @see Class_Exception
 */

namespace Wbengine\Router\Route;

use Wbengine\Router\RouterException;

class RouteException extends RouterException
{
    const ROUTE_ERROR_NO_MEZHOD_FOUND   = 888;
    const ROUTE_ERROR_NO_CLASS_FOUND   = 889;
}
