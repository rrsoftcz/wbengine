<?php

namespace Wbengine\I18n;

/**
 * A translation cannot be read or stored. The code says why, so a caller (the REST API)
 * can answer with the right status.
 */
class TranslationException extends \Exception
{
    const INVALID = 400;
    const NOT_FOUND = 404;

    public function __construct($message, $code = self::INVALID)
    {
        parent::__construct($message, $code);
    }
}
