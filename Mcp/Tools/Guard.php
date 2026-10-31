<?php

namespace Wbengine\Mcp\Tools;

use Wbengine\Mcp\Api\ApiError;
use Wbengine\Mcp\ToolError;
use Wbengine\Mcp\ToolResult;

/** Runs a tool body and turns API failures into advice for the model. */
final class Guard
{
    public static function run(callable $body): ToolResult
    {
        try {
            return $body();
        } catch (ApiError $e) {
            throw new ToolError($e->advice());
        }
    }

    /** Runs a read that a key may not be allowed to do: the fallback when the site answers 403 or 404. */
    public static function optional(callable $read, mixed $fallback = null): mixed
    {
        try {
            return $read();
        } catch (ApiError $e) {
            if (in_array($e->errorCode, ['forbidden', 'not_found'], true)) { return $fallback; }
            throw $e;
        }
    }
}
