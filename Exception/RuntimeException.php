<?php

/**
 * Exception
 */

namespace Wbengine\Exception;

use \Exception;

class RuntimeException extends Exception {
    public function Show(){
        $debug = false;
        try {
            $debug = (bool) \Wbengine\Config::isDebugEnabled();
        } catch (\Throwable $e) {
            // Config not loaded yet: behave as production.
        }

        error_log(sprintf('%s(%s): %s in %s:%d', get_class($this), $this->getCode(), $this->getMessage(), $this->getFile(), $this->getLine()));

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (!$debug) {
            die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Error</title></head><body><h1>Internal Server Error</h1><p>Something went wrong. Please try again later.</p></body></html>');
        }

        $h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
        die(
        sprintf(
            file_get_contents(
                __DIR__.'/exception.tpl'),
            $h(get_class($this)),
            $h($this->getCode()),
            $h($this->getMessage()),
            $h($this->getFile()),
            $h($this->getLine()),
            $h($this->getTraceAsString())));

    }
}
