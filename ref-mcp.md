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

More tools, release 6.4.0. All of them are offered only when the key has the scopes in the last column (the scopes are read from `/identity` when the first tool list is requested; when the site cannot be reached every tool is listed and the API decides).

| Tool | Input | What it does | Needs |
|------|-------|--------------|-------|
| `wbengine_guide` | `topic` | short texts about how the site works: `overview`, `publish_article`, `addresses_and_seo`, `boxes`, `menu`, `languages`, `images`, `troubleshooting`; without a topic it lists them | none |
| `plan_article_page` | `title`, `introtext`, `prefix` | proposes, without writing, an address nobody uses (diacritics removed, at most 60 characters, `/blog/` when the site has a blog page), the browser title (at most 60), the description (at most 160, from the introduction), the box type and section, a menu name and the blockers; the `new_page` object goes to `save_article_draft` | `pages:read` |
| `check_article` | `id` | blockers (no page, no box at all on the page, no text), warnings (no box marked as showing articles, inactive section, missing browser title, description or translation) and advice (too long fields, unclean address, no menu entry, hidden); `publish_article` runs it and refuses on blockers | `articles:read`, `pages:read` |
| `update_page` | `id`, `title`, `html_title`, `html_description`, `html_keywords`, `visible`, `link`, `confirm_link_change`, `translations` | changes the fields of a page; the address of a visible page changes only with `confirm_link_change` because the site has no redirects; addresses are validated and unique | `pages:write` |
| `list_menu` | `page_id` | menu entries by level and order | `menu:read` |
| `set_menu_entry` | `id` or `page_id` and `name`; `description`, `order`, `parent`, `visible`, `translations` | adds or changes an entry; entries from `order` on move down, no clash; a new entry for an invisible page is hidden until it is published; hiding is `visible` false | `menu:read`, `menu:write` |
| `list_sections` | none | the sections and whether they are active | `sections:read` |
| `list_box_types` | none | the registered box types, which show articles and where they may be placed | none |
| `list_boxes` | `page_id`, `search`, `page`, `per_page` | boxes with type, section, pages, order, device limit | `boxes:read` |
| `add_box` | `name`, `type`, `section`, `page_ids`, `order` | adds a box of a registered type; unknown types and wrong sections are refused | `boxes:read`, `boxes:write`, `sections:read` |
| `update_box` | `id`, `name`, `order`, `page_ids`, `device_min`, `device_strict` | changes a box and returns the earlier values | `boxes:read`, `boxes:write` |
| `hide_box`, `show_box` | `id`, `page_id` | takes a box off one page and keeps it, and the reverse; names the pages before; a shared box is refused | `boxes:read`, `boxes:write` |
| `upload_image` | `alt`, `path` or `base64` and `mime` | uploads a PNG, JPEG or WebP (at most 512 KiB, 8192 pixels), returns the address and the Markdown; the file extension and content are checked first | `images:write` |
| `list_images` | `page`, `per_page` | uploaded images | `images:read` |

Release 6.5.0 adds statistics, reports and change log analysis. The figures are computed by the server from what the site returns.

| Tool | Input | What it does | Needs |
|------|-------|--------------|-------|
| `get_statistics` | `days` (ending today, default 7) or `from` and `to` (YYYY-MM-DD), `granularity` (`day`, `week`, `month`) | page views, article views and box appearances for the period, the series, the top ten pages, articles and boxes, and the totals since counting began; says when the period starts before counting began (`period.complete` false) | `analytics:read` (the Analytics choice of the key) |
| `site_report` | `days`, `compare` | a Markdown report: totals against the period before with the change, the busiest days, the top lists with their change, and the limits of the numbers (no unique visitors, top ten only, incomplete period, no comparison available) | `analytics:read` |
| `analyze_activity` | `days`, `actor` | counts of the change log by actor, resource, action and day, what was changed three times or more, and the refused writes by error code and actor | `audit:read` |

`list_changes` now also takes `actor`, `action`, `since` and `until` (dates, UTC, until included) and `outcome` `rejected`, which lists the writes the site refused with their error code. The site's `GET /changes` already provides these filters; no framework change was needed. The prompts `weekly_report` and `find_problems` guide a report and a search for problems; the guide topic `statistics` explains the limits. Logs of the web server and of PHP are not exposed.

`save_article_draft` with `new_page` now does the whole technical job: it creates the invisible page with the browser title and description (from the title and the introduction unless given), attaches the box that shows the article (the registered type that renders articles, section `central`, switchable with `article_box: false`, overridable with `box_type` and `section`) and the menu entry when `menu_name` is given. When the box cannot be added (no such type, no Structure permission) the result says so and why. The tools never delete anything.

Prompts (`prompts/list`, `prompts/get`): `publish_new_article`, `translate_article`, `seo_review`. Resources (`resources/list`, `resources/read`): the guide texts as `wbengine://guide/<topic>` in Markdown. Both are announced in the capabilities of both protocol eras; the guide texts live in `Mcp/Guide/` and are packed into the phar.

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
