<?php

namespace Wbengine\Mcp;

/**
 * Protocol revisions the server speaks. The modern revision has no handshake: every request carries
 * its version in `_meta`. The legacy revisions open with `initialize`. The server serves both,
 * chosen by how the client opens (see docs ref-mcp.md).
 */
final class Protocol
{
    /** Version of the server, reported to clients. Keep equal to the release tag; the release workflow checks it. */
    public const SERVER_VERSION = '6.7.0';
    public const MODERN = '2026-07-28';
    /** Legacy revisions, newest first. */
    public const LEGACY = ['2025-11-25', '2025-06-18'];

    public const META_VERSION = 'io.modelcontextprotocol/protocolVersion';
    public const META_CAPABILITIES = 'io.modelcontextprotocol/clientCapabilities';
    public const META_CLIENT = 'io.modelcontextprotocol/clientInfo';
    public const META_SERVER = 'io.modelcontextprotocol/serverInfo';

    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const RESOURCE_NOT_FOUND = -32002;
    public const INTERNAL_ERROR = -32603;
    public const UNSUPPORTED_VERSION = -32022;

    /** @return string[] */
    public static function supported(): array
    {
        return [self::MODERN, ...self::LEGACY];
    }
}
