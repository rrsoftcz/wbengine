<?php

namespace Wbengine\Api\V2;

/** A client-safe API problem. Internal exceptions never become public messages. */
class Problem extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, int $status = 400)
    {
        parent::__construct($message, $status);
    }
}
