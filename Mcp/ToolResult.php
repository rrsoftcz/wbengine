<?php

namespace Wbengine\Mcp;

/** What a tool returns: a short human text and an optional structured object. */
final class ToolResult
{
    private function __construct(public readonly string $text, public readonly ?array $structured, public readonly bool $isError)
    {
    }

    public static function ok(string $text, ?array $structured = null): self
    {
        return new self($text, $structured, false);
    }

    public static function error(string $text): self
    {
        return new self($text, null, true);
    }
}
