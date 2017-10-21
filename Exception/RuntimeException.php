<?php

/**
 * Exception
 */

namespace Wbengine\Exception;

use \Exception;

class RuntimeException extends Exception {
    public function __toString()
    {
        var_dump(parent);
    }
}
