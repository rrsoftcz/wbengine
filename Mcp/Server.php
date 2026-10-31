<?php

namespace Wbengine\Mcp;

/**
 * JSON-RPC 2.0 dispatcher for the MCP methods the server supports: initialize and ping (legacy
 * revisions), server/discover (modern revision), tools/list and tools/call. One message in, at most
 * one message out. It knows nothing about the transport and nothing about WBengine.
 */
final class Server
{
    public const MAX_MESSAGE_BYTES = 1048576;
    public const MAX_RESULT_BYTES = 262144;

    private bool $legacyOpened = false;
    private ?string $clientName = null;

    /** @param null|callable(string): void $log receives diagnostics (the transport sends them to stderr) */
    public function __construct(
        private readonly ToolRegistry $tools,
        private readonly string $name = 'wbengine-mcp',
        private readonly string $version = '0.0.0',
        private readonly string $instructions = '',
        private $log = null,
        private readonly int $maxResultBytes = self::MAX_RESULT_BYTES,
    ) {
    }

    /** Handle one raw message. Returns the encoded response, or null when none is due. */
    public function handle(string $raw): ?string
    {
        $message = json_decode($raw, true, 64);
        $this->rememberClient($message);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->encode($this->error(null, Protocol::PARSE_ERROR, 'Parse error: the message is not valid JSON.'));
        }
        if (!is_array($message) || array_is_list($message)) {
            return $this->encode($this->error(null, Protocol::INVALID_REQUEST, is_array($message) ? 'Batches are not supported, send one message per line.' : 'A message must be a JSON object.'));
        }
        $hasId = array_key_exists('id', $message);
        $id = $message['id'] ?? null;
        if ($hasId && !is_int($id) && !is_string($id)) {
            return $this->encode($this->error(null, Protocol::INVALID_REQUEST, 'The id must be a string or an integer.'));
        }
        if (($message['jsonrpc'] ?? null) !== '2.0' || !is_string($message['method'] ?? null)) {
            // A response or a malformed message: servers never expect responses, so there is nothing to answer.
            return $hasId && !isset($message['result']) && !isset($message['error'])
                ? $this->encode($this->error($id, Protocol::INVALID_REQUEST, 'Not a JSON-RPC 2.0 request.'))
                : null;
        }
        $params = $message['params'] ?? [];
        if (!is_array($params)) {
            return $hasId ? $this->encode($this->error($id, Protocol::INVALID_PARAMS, 'The params must be an object.')) : null;
        }
        if (!$hasId) {
            return null; // notifications (initialized, cancelled, progress) need no answer
        }
        try {
            return $this->encode($this->dispatch($id, $message['method'], $params));
        } catch (\Throwable $e) {
            $this->log('internal error in ' . $message['method'] . ': ' . get_class($e));
            return $this->encode($this->error($id, Protocol::INTERNAL_ERROR, 'Internal error.'));
        }
    }

    /** The client name from initialize or from the per-request metadata, shortened to safe characters. */
    private function rememberClient(mixed $message): void
    {
        if (!is_array($message)) { return; }
        $info = $message['params']['clientInfo'] ?? $message['params']['_meta'][Protocol::META_CLIENT] ?? null;
        $name = is_array($info) && is_string($info['name'] ?? null) ? preg_replace('/[^A-Za-z0-9._ -]/', '', $info['name']) : '';
        $name = substr(trim((string) $name), 0, 64);
        if ('' !== $name) { $this->clientName = $name; }
    }

    /** Name the client gave about itself (display only, never trusted), or null. */
    public function clientName(): ?string
    {
        return $this->clientName;
    }

    private function dispatch(int|string $id, string $method, array $params): array
    {
        if ('ping' === $method) {
            return $this->result($id, []);
        }
        if ('initialize' === $method) {
            return $this->initialize($id, $params);
        }
        $modern = isset($params['_meta']) && is_array($params['_meta']) && array_key_exists(Protocol::META_VERSION, $params['_meta']);
        if (!in_array($method, ['server/discover', 'tools/list', 'tools/call'], true)) {
            return $this->error($id, Protocol::METHOD_NOT_FOUND, 'Method not found: ' . $method);
        }
        if ($modern) {
            $problem = $this->checkModern($id, $params['_meta']);
            if (null !== $problem) { return $problem; }
        } elseif (!$this->legacyOpened || 'server/discover' === $method) {
            return $this->error($id, Protocol::INVALID_PARAMS, 'Missing _meta "' . Protocol::META_VERSION . '" and "' . Protocol::META_CAPABILITIES . '" (revision ' . Protocol::MODERN . '), or send initialize first (older revisions).');
        }
        return match ($method) {
            'server/discover' => $this->result($id, ['supportedVersions' => Protocol::supported(), 'capabilities' => $this->capabilities(), 'instructions' => $this->instructions, 'ttlMs' => 0, 'cacheScope' => 'private'], true),
            'tools/list' => $this->toolsList($id, $params, $modern),
            default => $this->toolsCall($id, $params, $modern),
        };
    }

    private function initialize(int|string $id, array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;
        if (!is_string($requested)) {
            return $this->error($id, Protocol::INVALID_PARAMS, 'initialize needs a protocolVersion.');
        }
        $this->legacyOpened = true;
        $version = in_array($requested, Protocol::LEGACY, true) ? $requested : Protocol::LEGACY[0];
        $result = ['protocolVersion' => $version, 'capabilities' => $this->capabilities(), 'serverInfo' => ['name' => $this->name, 'version' => $this->version]];
        if ('' !== $this->instructions) { $result['instructions'] = $this->instructions; }
        return $this->result($id, $result);
    }

    /** Validate the per-request metadata of the modern revision; an error response or null. */
    private function checkModern(int|string $id, array $meta): ?array
    {
        $version = $meta[Protocol::META_VERSION];
        if (!is_string($version) || !in_array($version, Protocol::supported(), true)) {
            return $this->error($id, Protocol::UNSUPPORTED_VERSION, 'Unsupported protocol version', ['supported' => Protocol::supported(), 'requested' => $version]);
        }
        if (Protocol::MODERN !== $version) {
            return $this->error($id, Protocol::INVALID_PARAMS, 'Revision ' . $version . ' uses initialize; per-request _meta belongs to ' . Protocol::MODERN . '.');
        }
        if (!isset($meta[Protocol::META_CAPABILITIES]) || !is_array($meta[Protocol::META_CAPABILITIES])) {
            return $this->error($id, Protocol::INVALID_PARAMS, 'Missing _meta "' . Protocol::META_CAPABILITIES . '".');
        }
        return null;
    }

    private function capabilities(): array
    {
        return ['tools' => ['listChanged' => false]];
    }

    private function toolsList(int|string $id, array $params, bool $modern): array
    {
        if (isset($params['cursor']) && '' !== $params['cursor']) {
            return $this->error($id, Protocol::INVALID_PARAMS, 'Unknown cursor: the tool list has one page.');
        }
        $result = ['tools' => array_map(static fn(Tool $tool) => $tool->definition(), $this->tools->all())];
        if ($modern) { $result += ['ttlMs' => 0, 'cacheScope' => 'private']; }
        return $this->result($id, $result, $modern);
    }

    private function toolsCall(int|string $id, array $params, bool $modern): array
    {
        $name = $params['name'] ?? null;
        if (!is_string($name)) {
            return $this->error($id, Protocol::INVALID_PARAMS, 'tools/call needs a tool name.');
        }
        $tool = $this->tools->get($name);
        if (null === $tool) {
            return $this->error($id, Protocol::INVALID_PARAMS, 'Unknown tool: ' . $name);
        }
        $arguments = $params['arguments'] ?? [];
        if (!is_array($arguments) || ([] !== $arguments && array_is_list($arguments))) {
            return $this->error($id, Protocol::INVALID_PARAMS, 'The arguments must be an object.');
        }
        $problem = Schema::validate($arguments, $tool->inputSchema);
        $outcome = null !== $problem ? ToolResult::error('Invalid input: ' . $problem) : $this->run($tool, $arguments);
        return $this->result($id, $this->toolResult($outcome), $modern);
    }

    private function run(Tool $tool, array $arguments): ToolResult
    {
        try {
            return $tool->run($arguments);
        } catch (ToolError $e) {
            return ToolResult::error($e->getMessage());
        } catch (\Throwable $e) {
            $this->log('tool ' . $tool->name . ' failed: ' . get_class($e));
            return ToolResult::error('The tool failed unexpectedly. Nothing was changed by this call that the message does not state; check the site before retrying.');
        }
    }

    private function toolResult(ToolResult $outcome): array
    {
        $result = ['content' => [['type' => 'text', 'text' => $outcome->text]], 'isError' => $outcome->isError];
        if (null !== $outcome->structured) { $result['structuredContent'] = $outcome->structured; }
        if (strlen((string) json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE)) > $this->maxResultBytes) {
            return ['content' => [['type' => 'text', 'text' => 'The result is larger than ' . $this->maxResultBytes . ' bytes and was not returned. Ask for less (a smaller page, one article, one locale).']], 'isError' => true];
        }
        return $result;
    }

    private function result(int|string $id, array $result, bool $modern = false): array
    {
        if ($modern) {
            $result = ['resultType' => 'complete'] + $result;
            $result['_meta'] = [Protocol::META_SERVER => ['name' => $this->name, 'version' => $this->version]];
        }
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => (object) $result];
    }

    private function error(int|string|null $id, int $code, string $message, ?array $data = null): array
    {
        $error = ['code' => $code, 'message' => $message];
        if (null !== $data) { $error['data'] = $data; }
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => $error];
    }

    private function encode(array $message): string
    {
        // One line per message: json_encode never emits a raw newline, invalid UTF-8 is replaced.
        return json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function log(string $line): void
    {
        if (null !== $this->log) { ($this->log)($line); }
    }
}
