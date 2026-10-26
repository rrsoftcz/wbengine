<?php

/**
 * Exception
 */

namespace Wbengine\Application;

use Wbengine\Exception\RuntimeException;

class ApplicationException extends \Exception {
    public static function RegisterHandler(): void
    {
        set_exception_handler(static function (\Throwable $exception): void {
            (new self($exception->getMessage(), (int) $exception->getCode(), $exception))->Show();
        });
    }

    public function Show(){
        $cause = $this->getPrevious() ?? $this;
        $debug = false;
        try {
            $debug = (bool) \Wbengine\Config::isDebugEnabled();
        } catch (\Throwable $e) {
            // Config not loaded yet: behave as production.
        }

        $reference = strtoupper(bin2hex(random_bytes(4)));
        error_log(sprintf('[WBengine %s] %s(%s): %s in %s:%d', $reference, get_class($cause), $cause->getCode(), $cause->getMessage(), $cause->getFile(), $cause->getLine()));

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (!$debug) {
            $configError = $cause instanceof \Wbengine\Config\Adapter\Exception\ConfigException;
            $title = $configError ? 'The website configuration needs attention' : 'This page is temporarily unavailable';
            $message = $configError
                ? 'The application could not load its configuration. Check that src/Config/Settings.json exists, contains valid JSON, and is readable by PHP.'
                : 'The application could not complete this request. The error has been recorded for the site administrator.';
            die(sprintf('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>%s</title><style>body{margin:0;padding:8vh 1rem;background:#f1f5f9;color:#0f172a;font:16px/1.6 system-ui,sans-serif}main{box-sizing:border-box;max-width:48rem;margin:auto;padding:clamp(1.5rem,5vw,3rem);border:1px solid #cbd5e1;border-radius:1rem;background:#fff;box-shadow:0 1rem 3rem #0f172a12}h1{line-height:1.2}.ref{color:#475569;font-size:.9rem}</style></head><body><main><h1>%s</h1><p>%s</p><p class="ref">Reference: %s</p></main></body></html>', htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $reference));
        }

        $templatePath = __DIR__ . '/exception.tpl';
        $template = is_readable($templatePath) ? file_get_contents($templatePath) : false;
        if (false === $template) {
            $template = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>WBengine exception details</title></head><body><main><h1>WBengine caught an exception</h1><p><strong>%s</strong> (%s): %s</p><p>%s:%s</p><pre>%s</pre></main></body></html>';
        }
        $h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        die(sprintf(
            $template,
            $h(get_class($cause)),
            $h($cause->getCode()),
            $h($cause->getMessage()),
            $h($cause->getFile()),
            $h($cause->getLine()),
            $h($cause->getTraceAsString())));

    }

}
