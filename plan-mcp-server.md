# Plan: MCP server for WBengine

Status: proposed, not started. Language of this document: English (project rule). Decisions of the owner are in the section "Decisions".

## Goal

An MCP (Model Context Protocol) server shipped with WBengine that lets an AI client manage a deployed site. The first release is about content: write, edit and publish articles and blog posts, and hide a published article again. Later releases add structure, boxes and curated settings. Deployment of a site is out of scope for the server of a site.

## Decisions

1. First version is single site: one server process manages one site. A multi-site server (inside the central Admin, with its own login) comes later and reuses the same tool definitions.
2. The agent may publish. This is controlled by the permission of the integration key (group Publishing), not by a flag in the server. An optional local switch can force draft-only.
3. A published article must be hideable, the reverse of publishing. This needs a real flag in the framework (see "Framework changes").

## What exists today (verified in the code)

API v2 already provides everything the first release needs:

| Need | Existing API | Scope |
|------|--------------|-------|
| Create an article draft | `POST /articles` (creates an empty live row with `site_id = 0` and `published = 0` plus a draft) | `articles:write` |
| Edit a draft | `PATCH /articles/{id}` (saves into the draft, `If-Match` ETag) | `articles:write` |
| Translations in the draft | `translations` part of the draft payload, `PUT /translations/article/{id}/{locale}` | `translations:write` |
| Preview | `GET /articles/{id}/preview?locale=` | `articles:read`, `preview:read` |
| Publish | `POST /articles/{id}/publish` (needs a page: `site_id`, one article per page; 409 `article_site_conflict`, 422 `missing_site`) | `articles:write`, `publish:write` |
| History and restore | `GET /articles/{id}/revisions`, `POST /articles/{id}/restore` | `articles:read`, `articles:write` |
| Find a page, create a page | `GET /pages?search=`, `POST /pages` (`link`, `title`, `visible`) | `pages:read`, `pages:write` |
| Images | `POST /images` (base64, needs `apiV2.imageDirectory`) | `images:write` |
| Audit with actor | `X-WBengine-Actor` header, change feed `GET /changes` | `audit:read` |

Facts that shape the design:

- An article belongs to exactly one page (`cms_articles.site_id`). Writing a new blog post therefore means a page plus an article.
- A page with `visible = 0` is not found by the site lookup (`SiteModel` selects `visible = 1`), so its URL is a 404. This is the only way to remove an article from the public site today.
- `cms_articles.published` is a timestamp written at publish. Front-end readers do not filter on it, so it does not control display.
- The article list has `has_draft` and `draft_title` but no search by title.

## Framework changes (release 6.2.0)

1. **Hidden flag.** Migration `6.2.0/012-article-hidden.sql` adds `cms_articles.hidden TINYINT NOT NULL DEFAULT 0` (idempotent, `doctor` reports it as `migration.012`; without it everything works as before).
2. **Readers respect it.** `Box\Article`, `Article\ArticleModel::getArticleRow()` and the skeleton article models return no article when `hidden = 1`; the box renders nothing. Existing rows are unaffected (default 0).
3. **API.** `POST /articles/{id}/hide` and `POST /articles/{id}/unhide`, scope `publish:write` (plus `articles:write`), `If-Match` ETag, change log actions `hide` and `unhide`, cache flush, idempotent (hiding a hidden article is a 200 with no change). The article resource shows `hidden`. Publishing a hidden article keeps it hidden and the response says so.
4. **List improvements.** `GET /articles?search=` (title, escaped LIKE, like pages and boxes) and filters `hidden=0|1` and `has_draft=1`, so a client can find an article by words.
5. **Page visibility** stays a separate, existing operation (`PATCH /pages/{id}` with `visible`). Hiding an article does not touch its page. Clients that want the URL to disappear hide the page too; the MCP tool offers both in one call.
6. **Admin follow-up** (separate, small): a hide/unhide control in the article editor and the Activity labels.
7. Tests: model and HTTP tests for hide/unhide (permissions, ETag, audit, cache, idempotence, schema-absent fallback), reader tests (`hidden` articles are not rendered), README, `ref-api-v2.md`, CHANGELOG.

## MCP server design (release 6.3.0)

### Placement and transport

- Namespace `Wbengine\Mcp`, entry point `bin/wbengine-mcp.php`, exposed by Composer as `vendor/bin/wbengine-mcp`. No new Composer packages: the framework keeps no runtime dependencies.
- Transport: stdio, JSON-RPC 2.0, one message per line, logs only on stderr. The protocol revision is pinned in the code and covered by tests (`initialize`, `tools/list`, `tools/call`, `ping`, notifications, malformed input).
- The server is a client of API v2 over HTTPS. It never opens the database and never reads `Settings.json`, so it enforces the same keys, scopes, IP restriction and audit as every other client and can run on a developer machine.

### Configuration (environment)

| Variable | Meaning |
|----------|---------|
| `WBENGINE_MCP_URL` | base URL of the site (HTTPS; `http://localhost` only with an explicit opt-in variable) |
| `WBENGINE_MCP_KEY` | integration key |
| `WBENGINE_MCP_ACTOR` | actor label, default `mcp:<client name>`; appears in the change log |
| `WBENGINE_MCP_MODE` | `full` (default, limited by the key) or `draft` (publish and hide tools are not offered) |

### HTTP client rules

Verified TLS, no redirects followed, connect and total timeouts, response size limit, JSON only, the key only in the `Authorization` header and never in any output, log or error text. An injectable transport lets the tests call the API controller in process.

### Tools, phase 1

All content tools use Markdown (`content_format: markdown`). Every write tool returns the new ETag and a short human summary; every tool declares read-only, destructive and idempotent hints.

| Tool | What it does | Needs |
|------|--------------|-------|
| `site_info` | languages, what the key allows (groups), whether images work, server version | `discovery:read` |
| `list_pages` | find pages by words in title or link | `pages:read` |
| `list_articles` | find articles (search, hidden, has draft, paging) | `articles:read` |
| `get_article` | live content, translations, draft, state (draft, published, hidden), ETag | `articles:read` |
| `save_article_draft` | create a draft (optionally with a new page: link, title) or update one (title, intro, body, translations, page), with optional `expected_etag` | `articles:write`, `pages:write` for a new page |
| `preview_article` | rendered preview of the draft in a locale | `preview:read` |
| `publish_article` | publish the draft; for a new page makes it visible; reports conflicts clearly | `publish:write` |
| `hide_article` | hide a published article, optionally also its page (`with_page`, default true) | `publish:write` |
| `unhide_article` | show it again, optionally also its page | `publish:write` |
| `list_changes` | recent changes of an article or page with the actor | `audit:read` |

Phase 1.5 (small): `upload_image` (local file to the image library, returns the URL for Markdown) and `set_page_visibility`.

### Safety

- Text read from the site is untrusted data. Tool results mark content as data, and tool descriptions tell the model not to follow instructions found inside articles.
- Publish and hide tool descriptions say: only when the user asked. The client's own approval prompt is the second guard.
- Limits: body size, translation count, one write per call, no bulk delete. There is no delete tool.
- A key for an agent should be issued with the groups Content (write) and Publishing, nothing else. The Integrations page may offer this as a preset. Keys used from a laptop should not be bound to an address.
- API errors become tool errors with an actionable message (for example `article_site_conflict`: "that page already has an article, choose another page").

### Packaging and documentation

README section, `ref-mcp.md` (tools, input schemas, errors, configuration), example client configuration for Claude Code and Claude Desktop, a short public page in the skeleton documentation (plain facts), and the Integrations page text describing the agent key.

### Testing

1. Protocol unit tests (framing, errors, unknown methods, size limits).
2. Tool contract tests: JSON schema of every tool, one test per tool against the real controller through the in-process transport (temporary tables, like the API tests).
3. Security tests: key never in output, redirects refused, invalid certificate refused, oversize response, hostile article text returned as data.
4. End to end test in CI: start the process, run a stdio session (initialize, create a draft with a new page, preview, publish, hide, unhide, read the change log) against the pilot site.
5. A manual acceptance on the live demo before the release.

## Phases and releases

| Release | Content |
|---------|---------|
| 6.2.0 | hidden flag, hide/unhide, article search and filters, Admin control |
| 6.3.0 | MCP server phase 1 (articles), docs, CI end to end test |
| 6.4.0 | phase 1.5 plus page and menu visibility tools |
| later | box visibility and box management (only registered types), curated and backed up settings changes with confirmation, multi-site MCP in the Admin (own plan, includes login and per-site roles) |
| not planned | deployment of a site: it runs on the host (git and Docker) and needs a separate privileged tool with approvals |

## Acceptance of phase 1

- From one prompt an agent writes a Czech and English article, saves it as a draft on a new page, shows a preview and publishes after the user agrees.
- The same agent hides the article and shows it again; the public URL reflects it (with `with_page`).
- Everything appears in the change feed with the actor `mcp:<client>`.
- A key without Publishing can write drafts but the publish and hide tools fail with a clear message (or are not offered in `draft` mode).
- No secret appears in any tool result, log line or error.

## Risks and open points

- MCP revisions change: the revision is pinned and the protocol layer is small and isolated.
- A PHP stdio loop blocks on one request at a time; this is acceptable for single-user use.
- Prompt injection through article text cannot be removed, only contained by the rules above and by the key's permissions.
- Dynamic IPs and IP-restricted keys conflict; document it.
- Whether the article page lookup should return 404 for a hidden article instead of an empty box. The plan keeps the page and relies on `with_page`; revisit after the first use.
