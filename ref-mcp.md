# Reference: WBengine MCP server

The MCP (Model Context Protocol) server lets an AI client manage the content of one WBengine site. It is a client of API v2: it never opens the database and never reads `Settings.json`, so every key, scope, address restriction and audit rule of the API applies unchanged. This document is the reference; the plan and the decisions are in `plan-mcp-server.md`.

This file grows with the release. Sections for the tools, their input schemas, the configuration and the client examples are added together with the code they describe.

## Protocol

Transport: stdio, JSON-RPC 2.0, one message per line, no embedded newlines, protocol messages on stdout only, diagnostics on stderr. The server exits when its input ends. Batches are not supported. A line larger than 1 MiB is discarded with a JSON-RPC error and the loop continues.

The protocol revisions were read from the specification at modelcontextprotocol.io on 2026-10-31 and are pinned in `Mcp/Protocol.php`:

| Revision | Era | How a client opens |
|----------|-----|--------------------|
| `2026-07-28` (`Protocol::MODERN`) | modern, stateless | no handshake: every request carries `_meta` with `io.modelcontextprotocol/protocolVersion` and `io.modelcontextprotocol/clientCapabilities`; `server/discover` lists the supported versions |
| `2025-11-25`, `2025-06-18` (`Protocol::LEGACY`) | legacy | `initialize`, then the `notifications/initialized` notification |

The server is dual-era and chooses by how the client opens. A request with the modern `_meta` is served statelessly; `initialize` opens legacy semantics for the rest of the process. A request without `_meta` before `initialize` is answered with `-32602` and a message naming both ways.

Methods: `initialize`, `ping`, `server/discover`, `tools/list`, `tools/call`. Notifications are accepted and ignored. Any other method answers `-32601`. Only the `tools` capability is declared (`listChanged: false`).

Errors:

| Code | When |
|------|------|
| `-32700` | the line is not JSON |
| `-32600` | not a JSON-RPC 2.0 request, a batch, an invalid id, a line over the size limit |
| `-32601` | unknown method |
| `-32602` | invalid params, unknown tool, missing `_meta` of the modern revision |
| `-32603` | internal error (the text is generic, details go to stderr) |
| `-32022` | modern request with an unsupported version; `data.supported` and `data.requested` |

Tool results follow the specification: `content` with a text block, `structuredContent` when the tool has structured data, `isError`. Failures a model can act on (invalid input, API errors, conflicts) are results with `isError: true`, not JSON-RPC errors. Modern results carry `resultType: "complete"` and `_meta` with the server info. A result larger than 256 KiB is replaced by an error that asks for less.

Input of every tool is validated against its JSON Schema before the handler runs. The supported subset is `type`, `enum`, `required`, `properties`, `additionalProperties`, `items`, `minLength`, `maxLength`, `pattern`, `minimum`, `maximum`, `minItems`, `maxItems`.

## API client rules

TLS with certificate and host name verification, no redirects followed, JSON answers only, a total timeout of 30 seconds and a response limit of 2 MiB. The address must be HTTPS; plain HTTP is accepted for `localhost` and `127.0.0.1` only with `WBENGINE_MCP_ALLOW_INSECURE=1`. The key is sent only in the `Authorization` header and is removed from every text the server produces. Each request carries `X-WBengine-Actor`, and `If-Match` for writes.

API failures become tool errors with advice. The codes with their own advice: `unauthorized`, `forbidden`, `ip_not_allowed`, `precondition_failed`, `precondition_required`, `article_site_conflict`, `missing_site`, `no_draft`, `hide_unavailable`, `duplicate_link`, `invalid_link`, `invalid_field`, `not_found`, `rate_limited`, `unavailable`; local codes: `transport`, `redirect_refused`, `response_too_large`, `bad_response`, `invalid_configuration`. Any other code is shown as the site stated it.
