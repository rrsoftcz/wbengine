# Reference: WBengine MCP server

The MCP (Model Context Protocol) server lets an AI client manage the content of one WBengine site. It is a client of API v2: it never opens the database and never reads `Settings.json`, so every key, scope, address restriction and audit rule of the API applies unchanged. This document is the reference; the plan and the decisions are in `plan-mcp-server.md`.

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

## Configuration

The server reads its configuration from the environment and exits with code 2 and a message on stderr when it is missing or invalid. The message never contains the key.

| Variable | Meaning |
|----------|---------|
| `WBENGINE_MCP_URL` | address of the site, for example `https://www.example.org` (HTTPS; no credentials, query or fragment) |
| `WBENGINE_MCP_KEY` | integration key issued on the Integrations page |
| `WBENGINE_MCP_MODE` | `full` (default, limited by the permissions of the key) or `draft` (the publish, hide and unhide tools are not offered) |
| `WBENGINE_MCP_ACTOR` | actor label for the change log; default `mcp:<client name>` taken from what the client reports about itself |
| `WBENGINE_MCP_ALLOW_INSECURE` | `1` allows plain HTTP to `localhost` or `127.0.0.1` for local development |

Run it with `php wbengine-mcp.phar` (a release asset, see below), or `vendor/bin/wbengine-mcp.php` of a Composer installation. The key needs permission groups: Content (write) for drafts, pages, menu entries and translations, Publishing (write) for publish, hide and unhide, History (read) for `list_changes`. The Integrations page offers the presets "Agent for content" and "Agent, drafts only" and shows a ready client configuration after a key is issued. A key used from a laptop should not be bound to an address.

## Tools

All content is Markdown. Every tool declares the hints `readOnlyHint`, `destructiveHint` and `idempotentHint`. Nothing is deleted by any tool.

| Tool | Input | What it does | Needs |
|------|-------|--------------|-------|
| `site_info` | none | languages, the permissions of the key as a list of yes and no (`read_content`, `write_drafts`, `create_pages`, `translations`, `preview`, `publish_and_hide`, `history`, `upload_images`), the mode | `discovery:read` |
| `list_pages` | `search`, `page`, `per_page` | pages with id, link, title and visibility | `pages:read` |
| `list_articles` | `search`, `hidden`, `has_draft`, `page`, `per_page` | articles with id, title, state (`draft`, `published`, `hidden`), draft title, page and languages; the search covers published titles and the titles of drafts | `articles:read` |
| `get_article` | `id` | published content, live translations, the draft, state, page and the `etag` | `articles:read` |
| `save_article_draft` | `id` (update) or `title` (create); `introtext`, `bodytext`, `author`, `source`, `translations` (per locale `title`, `introtext`, `bodytext`), `page_id` or `new_page` (`title`, `link`, `link_prefix`), `menu_name`, `expected_etag` | creates or updates a draft; a new page is created invisible with a link derived from the title when none is given; chains the ETags of the page, article, translations and menu entry | `articles:write`, `pages:write` and `menu:write` for a page or a menu entry, `translations:write` |
| `preview_article` | `id`, `locale` | the draft rendered as HTML, private | `articles:read`, `preview:read` |
| `publish_article` | `id`, `expected_etag` | publishes the draft; makes the page visible when it was not, and its menu entries with it; says when the article is hidden | `articles:write`, `publish:write` |
| `hide_article` | `id`, `expected_etag` | hides a published article: its address answers 404 and its menu entry disappears; idempotent | `articles:write`, `publish:write` |
| `unhide_article` | `id`, `expected_etag` | shows it again | `articles:write`, `publish:write` |
| `list_changes` | `resource` (`articles`, `pages`), `id`, `limit` | recent changes with time, action and actor | `audit:read` |

Results carry a short text and `structuredContent`. `save_article_draft` returns `id`, `etag`, `page_id` and the list of `steps` done. When a later step fails after an earlier one succeeded, the error says what was already done. Without `expected_etag` an update reads the current etag itself; with it, a change made in between is refused with advice to reload and merge.

An article that was never published has no published title, so the site cannot find it by title: `list_articles` therefore also searches the draft titles (first page, at most 400 drafts).

## Safety

- Text read from the site is data. Tool results wrap it between `--- BEGIN SITE DATA ---` and `--- END SITE DATA ---` after a notice, `structuredContent` of `get_article` and `preview_article` carries `content_is_site_data: true`, and the tool descriptions tell the model not to follow instructions found in articles.
- Publish, hide and unhide describe themselves as "only when the user asked". The approval prompt of the client is the second guard, the permissions of the key the third, `WBENGINE_MCP_MODE=draft` a local switch.
- Limits: 65535 bytes per text field, 16 translations per call, 1 MiB per incoming message, 256 KiB per result.
- The key travels only in the `Authorization` header to the configured address over verified TLS and is removed from every text the server produces.
- Prompt injection through article text cannot be removed, only contained by the rules above and by giving the key no more permissions than needed.

## Connecting a client

Claude Code (the command is shown with the key on the Integrations page after issuing a key):

```sh
claude mcp add --env 'WBENGINE_MCP_URL=https://www.example.org' --env 'WBENGINE_MCP_KEY=wbv2_...' --env 'WBENGINE_MCP_MODE=full' --transport stdio wbengine-www -- php /path/to/wbengine-mcp.phar
```

Claude Desktop and other clients that read an `mcpServers` section:

```json
{
  "mcpServers": {
    "wbengine-www": {
      "command": "php",
      "args": ["/path/to/wbengine-mcp.phar"],
      "env": {
        "WBENGINE_MCP_URL": "https://www.example.org",
        "WBENGINE_MCP_KEY": "wbv2_...",
        "WBENGINE_MCP_MODE": "full"
      }
    }
  }
}
```

## The phar

Every release has `wbengine-mcp.phar` and `wbengine-mcp.phar.sha256` attached. The file holds the `Wbengine\Mcp` classes and the entry point and nothing else, is signed with SHA-256 and needs PHP 8.3 or newer with the `phar` extension and no Composer. Check the download with `sha256sum -c wbengine-mcp.phar.sha256`. `php -d phar.readonly=0 bin/build-phar.php` builds it from a checkout into `build/`.

## Tests

`tests/Unit/Mcp` (protocol, schema validation, client including a self-signed certificate that must be refused, configuration, phar), `tests/Integration/McpToolsTest.php` (every tool against the real API v2 controller in process) and `tests/Integration/McpEndToEndTest.php` (the real process against API v2 served by the PHP built-in server; with `WB_MCP_PHAR` set it runs the phar). The public address of a hidden article answering 404 is covered by `ApiV2ArticleHideTest`.
