# Plan: MCP server for WBengine

Status: approved by the owner. Release 6.2.0 (hide and unhide articles) is finished and deployed; release 6.3.0 (the MCP server itself) is in progress (see the checkboxes in the work breakdown). The section "Progress and how to resume" tells exactly where things stand and what to do next. Language of this document: English (project rule). Decisions of the owner are in the section "Decisions".

## Goal

An MCP (Model Context Protocol) server shipped with WBengine that lets an AI client manage a deployed site. The first release is about content: write, edit and publish articles and blog posts, and hide a published article again. Later releases add structure, boxes and curated settings. Deployment of a site is out of scope for the server of a site.

## Decisions

1. First version is single site: one server process manages one site. A multi-site server (inside the central Admin, with its own login) comes later and reuses the same tool definitions.
2. The agent may publish. This is controlled by the permission of the integration key (group Publishing), not by a flag in the server. An optional local switch can force draft-only.
3. A published article must be hideable, the reverse of publishing. This needs a real flag in the framework (see "Framework changes").
4. Hiding an article hides its URL automatically: the address answers 404 and the menu item disappears. Unhiding brings both back. The page row itself is not changed.
5. The Integrations page offers a preset key for an agent (see "Agent key preset").
6. The server is also released as one file, `wbengine-mcp.phar`, attached to each release, so a machine without the site's code can run it. Approved by the owner as a separate release asset.

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
- A page with `visible = 0` is not found by the site lookup (`SiteModel::loadSiteData()` selects `visible = 1`), so its URL is a 404. This is the only way to remove an article from the public site today. The menu has its own `visible` flag and does not look at the page.
- `cms_articles.published` is a timestamp written at publish. Front-end readers do not filter on it, so it does not control display.
- The article list has `has_draft` and `draft_title` but no search by title.

## Framework changes (release 6.2.0)

1. **Hidden flag.** Migration `6.2.0/012-article-hidden.sql` adds `cms_articles.hidden TINYINT NOT NULL DEFAULT 0` (idempotent, `doctor` reports it as `migration.012`; without it everything works as before).
2. **A hidden article hides its URL.** `SiteModel::loadSiteData()` skips a page that has a hidden article (`NOT EXISTS (SELECT 1 FROM cms_articles WHERE site_id = page AND hidden = 1)`), so the existing 404 handling applies and every page cache entry is gone after the cache flush of the API call. The menu query (`getSiteMenu()`) skips items of such pages. `Box\Article`, `ArticleModel::getArticleRow()` and the skeleton article models also return no article when `hidden = 1` (defence for custom routes). The migration adds an index on `cms_articles.site_id`, because the lookup runs on every request. The queries fall back to the old form while the column does not exist yet. Existing rows are unaffected (default 0).
3. **API.** `POST /articles/{id}/hide` and `POST /articles/{id}/unhide`, scope `publish:write` (plus `articles:write`), `If-Match` ETag, change log actions `hide` and `unhide`, cache flush, idempotent (hiding a hidden article is a 200 with no change). The article resource shows `hidden`. Publishing a hidden article keeps it hidden and the response says so.
4. **List improvements.** `GET /articles?search=` (title, escaped LIKE, like pages and boxes) and filters `hidden=0|1` and `has_draft=1`, so a client can find an article by words.
5. **Page visibility** stays a separate, existing operation (`PATCH /pages/{id}` with `visible`), for pages without an article. Hiding an article does not change the page row; the page just stops being found while the article is hidden. A page that holds an article and other boxes is hidden as a whole, which is documented.
6. **Admin follow-up** (separate, small): a hide/unhide control in the article editor and the Activity labels.
7. Tests: model and HTTP tests for hide/unhide (permissions, ETag, audit, cache, idempotence, schema-absent fallback), a request test that the URL of a hidden article answers 404 and comes back after unhide, menu test, reader tests, README, `ref-api-v2.md`, CHANGELOG.

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
| `hide_article` | hide a published article; its URL answers 404 and its menu item disappears | `publish:write` |
| `unhide_article` | show it again, with its URL and menu item | `publish:write` |
| `list_changes` | recent changes of an article or page with the actor | `audit:read` |

Phase 1.5 (small): `upload_image` (local file to the image library, returns the URL for Markdown) and `set_page_visibility`.

### Safety

- Text read from the site is untrusted data. Tool results mark content as data, and tool descriptions tell the model not to follow instructions found inside articles.
- Publish and hide tool descriptions say: only when the user asked. The client's own approval prompt is the second guard.
- Limits: body size, translation count, one write per call, no bulk delete. There is no delete tool.
- A key for an agent should be issued with the groups Content (write) and Publishing, nothing else. The Integrations page may offer this as a preset. Keys used from a laptop should not be bound to an address.
- API errors become tool errors with an actionable message (for example `article_site_conflict`: "that page already has an article, choose another page").

### Agent key preset

A convenience on the Integrations page, not a new kind of key. Above the permission matrix a row of buttons fills the matrix and the name; the administrator can adjust everything afterwards. Without JavaScript the form works as today (full profile).

| Preset | Content | Publishing | Structure | Images | Site administration | History | Name |
|--------|---------|------------|-----------|--------|---------------------|---------|------|
| Full management (default) | write | write | write | write | write | read | WBengine Admin |
| Agent for content | write | write | none | write | none | read | MCP agent |
| Agent, drafts only | write | none | none | write | none | read | MCP agent (drafts) |
| Read only | read | none | read | read | none | read | Monitoring |

History is read for agents because `list_changes` needs `audit:read`. Analytics stays off unless chosen. The allowed addresses are not prefilled and the field warns that a key used from a laptop with a changing address should not be bound to one.

After issuing the key the one-time screen shows a ready-to-copy client block together with the key (and a command for Claude Code), with the site address filled in and `WBENGINE_MCP_MODE` set to `draft` for the drafts-only preset:

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

The preset is not stored and is not a security boundary: only the scopes the key really got count.

### Packaging and documentation

The release workflow builds `wbengine-mcp.phar` (the `Wbengine\Mcp` classes and the entry point, no other code) and attaches it to the release; the same code is available through Composer as `vendor/bin/wbengine-mcp`. A test runs the phar through the same stdio session as the end to end test.


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
| 6.2.0 | hidden flag, hide/unhide with automatic 404 and menu removal, article search and filters, Admin control |
| 6.3.0 | MCP server phase 1 (articles), agent key preset with the client block, `wbengine-mcp.phar`, docs, CI end to end test |
| 6.4.0 | phase 1.5 plus page and menu visibility tools |
| later | box visibility and box management (only registered types), curated and backed up settings changes with confirmation, multi-site MCP in the Admin (own plan, includes login and per-site roles) |
| not planned | deployment of a site: it runs on the host (git and Docker) and needs a separate privileged tool with approvals |

## Acceptance of phase 1

- From one prompt an agent writes a Czech and English article, saves it as a draft on a new page, shows a preview and publishes after the user agrees.
- The same agent hides the article and shows it again; the public URL answers 404 while it is hidden and works again afterwards.
- Everything appears in the change feed with the actor `mcp:<client>`.
- A key without Publishing can write drafts but the publish and hide tools fail with a clear message (or are not offered in `draft` mode).
- No secret appears in any tool result, log line or error.

## Risks and open points

- MCP revisions change: the revision is pinned and the protocol layer is small and isolated.
- A PHP stdio loop blocks on one request at a time; this is acceptable for single-user use.
- Prompt injection through article text cannot be removed, only contained by the rules above and by the key's permissions.
- Dynamic IPs and IP-restricted keys conflict; document it.
- The lookup of a hidden article adds a condition to the query that runs on every request. It uses an index and is measured with the existing content-lookup benchmark before release.

## Progress and how to resume

### Releases so far (all on `master` of the framework, tagged, with a GitHub release)

| Release | Content | Deployed |
|---------|---------|----------|
| 5.0.0 | API v1 and the JWT dependency removed, API v2 is the only API | yes |
| 5.1.0, 5.2.0 | permission groups for integration keys, `Integrations` box needs no application class (`Section::BUNDLED_BOXES`), `integrations:install`, permission matrix with None, Read, Write radio buttons colored red, blue, green | yes |
| 6.0.0 | unused settings removed (`codePage`, `cdnPath`, `cssFiles`, `ipAdmins`, `minimizeHtml`, `minimizeCss`, `minimizeJs`) | yes |
| 6.1.0 | per-key IP address restriction (`allowed_ips`, `last_used_ip`, 403 `ip_not_allowed`, migration `6.1.0/011`) | yes |
| 6.2.0 | hide and unhide articles (migration `6.2.0/012`, `Content\ArticleVisibility`, 404 of the page, menu item removed, article search and filters), hide control in the Admin | yes |

The skeleton application and the central Admin follow every release (the skeleton pins the framework in `composer.json` and `.github/workflows/smoke.yml`, the Admin pilot pins a framework commit in `.github/workflows/ci.yml`; the Admin pilot is still on 6.0.0, which is fine).

### Building blocks that exist and that the MCP server relies on

- `Wbengine\Api\V2` controller: routes for articles (`POST /articles` creates an empty live row plus a draft, `PATCH /articles/{id}` saves the draft, `GET .../preview`, `POST .../publish`, `POST .../hide`, `POST .../unhide`, `GET .../revisions`, `POST .../restore`), pages, menu, sections, boxes, translations (`PUT /translations/article/{id}/{locale}` writes into the draft), images, users, cache flush, config (read only), changes. All writes need `If-Match` with the latest ETag; every write response carries the new ETag in the `ETag` header. Success bodies are `{"success": true, "data": ..., "pagination": ...}`, errors `{"success": false, "error": {"code", "message"}}`.
- The actor header `X-WBengine-Actor` (at most 128 characters, no control characters) is stored as `central_actor` in the change log. The Admin uses `admin:<id>`; any other value is shown as is, so `mcp:<client>` works without changes.
- `Box\Integrations::GROUPS`, `scopesForGroups()`, `levelsOf()`, `levelsFromInput()`: the single definition of the permission groups. The form is a matrix (`groupForm()`, template `Box/Integrations/View/Integrations.tpl`, rows `.wb-mrow`, script `data-wb-matrix`), texts in `I18n/messages/en.php` and `cs.php` under `wb.integrations.*`, listed in `texts()`.
- `Api\V2\IpList`, `Model::restrict()`, `Model::authenticate($token, $clientIp)`: address restriction per key.
- Local test setup (see the framework README, "Development and tests"): Docker with PHP and MySQL; the HTTP tests run in CI.

### Work breakdown for release 6.3.0 (check the boxes in the commits that do the work)

A. Protocol core, namespace `Wbengine\Mcp`, no dependency on the rest of the framework (no `Db`, no `Config`), so it can be packed alone into the phar.
- [x] Before coding, read the current MCP specification and pin its protocol revision in a constant and in `ref-mcp.md`. Done 2026-10-31: the current revision `2026-07-28` is stateless (no `initialize`, `_meta` on every request, `server/discover`), most clients still speak `2025-11-25` or `2025-06-18`, so the server is dual-era. Do not rely on memory of the protocol: check `initialize` (capabilities, protocol version negotiation), `tools/list`, `tools/call` results (`content`, `structuredContent`, `isError`), tool annotations, `ping`, notifications such as `notifications/initialized`, JSON-RPC error codes and the stdio framing.
- [x] `Mcp/Server.php` (message loop, dispatch), `Mcp/Transport/Stdio.php` (reads lines from a stream, writes one JSON per line, logs only to stderr), `Mcp/Tool.php` (name, description, input schema, annotations, handler), `Mcp/ToolRegistry.php`.
- [x] Limits: maximum message size, maximum tool result size, malformed JSON gives a JSON-RPC error and the loop continues.

B. API client, `Mcp/Api/Client.php`.
- [x] HTTPS with certificate verification (PHP streams, no new Composer package), no redirects, connect and total timeouts, response size limit, JSON only. `http://localhost` and `http://127.0.0.1` only when `WBENGINE_MCP_ALLOW_INSECURE=1`.
- [x] Headers: `Authorization: Bearer <key>`, `X-WBengine-Actor`, `If-Match` when given, `Accept: application/json`. The key never appears in logs, results or exception messages (a test greps every output for it).
- [x] An injectable transport (a callable) so the tests call `Wbengine\Api\V2::handle()` in process.
- [x] Error mapping: `{code, message, status}` to a tool error with advice (`article_site_conflict`, `missing_site`, `precondition_failed` (reload and retry), `ip_not_allowed`, `forbidden` (the key lacks a group), `hide_unavailable` (site needs the update 6.2.0/012), `invalid_field`, `duplicate_link`).

C. Tools (phase 1), file `Mcp/Tools/Articles.php` (or one class per tool). Every tool has annotations (read-only, destructive, idempotent) and a JSON schema; results contain a short text and a structured object.
- [ ] `site_info`: `GET /capabilities` and `GET /identity`; returns languages, scopes of the key translated into the permission groups, whether images work, and whether publishing and hiding are allowed (so the model knows before it tries).
- [ ] `list_pages` (search, page), `list_articles` (search, hidden, has_draft, page), `get_article` (id; live content, translations, draft, state `draft` | `published` | `hidden`, ETag).
- [ ] `save_article_draft`: create (`title`, `introtext`, `bodytext` Markdown, optional `locale` translations, `page_id` or a new page `{link, title}`) or update (`id`, changed fields, optional `expected_etag`). A new page is created hidden (`visible: 0`) and made visible by `publish_article`. Optional menu entry (`menu_name`). The tool chains ETags itself: create page, create or patch the draft, then one `PUT /translations/article/{id}/{locale}` per locale, always with the newest ETag. Derive the link from the title when absent (ASCII, lowercase, hyphens, `link_prefix` default `/`).
- [ ] `preview_article` (id, locale): `GET .../preview`.
- [ ] `publish_article` (id): publishes, then makes a page created by the tool visible; refuses clearly without the Publishing group; in `draft` mode the tool is not listed.
- [ ] `hide_article`, `unhide_article` (id): call the hide and unhide endpoints; the result states that the URL answers 404 while hidden.
- [ ] `list_changes` (resource, id, limit): `GET /changes` with the filters, needs `audit:read`.
- [ ] Later in 6.4.0: `upload_image`, `set_page_visibility`, menu tools.

D. Entry point and configuration.
- [ ] `bin/wbengine-mcp.php`, registered in `composer.json` `bin`, so Composer exposes `vendor/bin/wbengine-mcp`. Environment variables as in the table above (`WBENGINE_MCP_URL`, `WBENGINE_MCP_KEY`, `WBENGINE_MCP_ACTOR`, `WBENGINE_MCP_MODE`, `WBENGINE_MCP_ALLOW_INSECURE`). Missing or invalid configuration prints a clear message on stderr and exits non-zero.

E. Safety (see "Safety" above) implemented and tested: text read from the site is marked as data in the tool results, size limits, one write per call, no delete tool.

F. Tests.
- [x] Unit: protocol framing and errors, schema validation of tool input, the client (redirect refused, oversize refused, invalid TLS refused, key never printed).
- [ ] Integration: every tool against the real controller through the in-process transport with temporary tables (pattern of `tests/Integration/ApiV2ArticleHideTest.php`).
- [ ] End to end: start `bin/wbengine-mcp.php` as a process, run a stdio session against a test site served by the PHP built-in server (pattern of `ApiV2HttpTest`): create a draft with a new page, preview, publish, hide (URL 404), unhide, read the change feed with the actor.
- [ ] The same session against the built phar.

G. Agent key preset on the Integrations page.
- [ ] Buttons above the matrix in `Integrations.tpl` (the table in "Agent key preset"), filled by the script next to the paint function; texts in `I18n/messages/en.php` and `cs.php` and in `texts()`; no change on the server side; without JavaScript the form is unchanged.
- [ ] After issuing a key, the one-time screen shows the client configuration block (JSON) and a command for Claude Code, with the site address and the key; the `draft` mode for the drafts-only preset. Add a test that the block appears only in the issue response, once, and contains the key only there.

H. The phar.
- [ ] `bin/build-phar.php` (run with `php -d phar.readonly=0`): packs `Mcp/` and a small autoloader stub, sets the entry point, signs with SHA-256, writes `build/wbengine-mcp.phar` (`build/` is git-ignored).
- [ ] `.github/workflows/release.yml`: on a published GitHub release, build the phar, run the stdio test against it, attach it to the release (`contents: write`). The README documents `php wbengine-mcp.phar` and the checksum.

I. Documentation.
- [ ] Framework: README section "MCP server", `ref-mcp.md` (tools with schemas, errors, configuration, safety, client examples for Claude Code and Claude Desktop), CHANGELOG, update this plan.
- [ ] Skeleton: README, `docs/CHANGELOG.md`, public documentation page or paragraph (plain facts only: what the server does, how to connect, the agent key) through a guarded content migration `036-...` mirrored in the content dump; `composer.json` constraint, `bin/update-database.sh` needs no change.
- [ ] Admin: a short note in `README.md` and `docs/book-connect-a-site-end-to-end.md` about keys for agents.

J. Release 6.3.0: merge each part by PR with green CI, tag `v6.3.0`, GitHub release (the workflow attaches the phar), bump the skeleton, deploy, check that the phar asset exists and runs.

### Resuming after an interruption

1. Read this file, `git log --oneline -20` and `git tag --sort=-v:refname | head` in the framework, and the open pull requests, to see which of the boxes above are already done.
2. Check that `master` is green and that the live demo runs the latest framework (`composer show wbengine/wbengine` inside the web container).
3. Continue with the first unchecked box of the breakdown. Keep every step a pull request with green CI; releases are annotated tags plus a GitHub release; the skeleton and the Admin follow.
4. Do not change the decisions without the owner. Questions that are still open: none for phase 1.

### Conventions that apply to all of this work

- Documentation and code comments in English; the Czech texts only where a Czech translation of a user interface or of the public site is required. No em dashes and no icons in texts. Public skeleton documentation states only facts about what the system does and how to use it (no migration numbers, release history or how the text was made).
- Commits and pull requests carry no AI co-author or attribution lines.
- A new migration gets the next number in a folder named after the release (`migrations/6.3.0/013-...sql` if one is needed), is idempotent, is added to the skeleton scripts `bin/update-database.sh` and `bin/setup-production.sh`, and is reported by `doctor` as `migration.NNN`.
