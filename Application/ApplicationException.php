<?php

/**
 * Exception
 */

namespace Wbengine\Application;

use Wbengine\Exception\RuntimeException;

class ApplicationException extends \Exception {
    public function Show(){
        die(
        sprintf(
            file_get_contents(
                __DIR__.'/exception.tpl'),
            get_class($this),
            $this->getCode(),
            $this->getMessage(),
            $this->getFile(),
            $this->getLine(),
            $this->getTraceAsString()));

    }

}
