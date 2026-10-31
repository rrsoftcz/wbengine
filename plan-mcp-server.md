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
7. (2026-10-31 night, owner) The agent is meant for an inexperienced editor who does not know the technical stack. The server therefore does the technical steps itself and knows how WBengine works: pages, article boxes, menu, SEO fields and addresses, languages, publishing. When a step is needed before an article can be shown, the server either does it or says exactly what is missing and what to do. It does not leave the editor guessing.
8. (2026-10-31 night, owner) The agent key gets the Structure group as well (boxes), so the agent can attach boxes to pages. This replaces the earlier recommendation to keep the key narrow; the narrower presets stay available.
9. (2026-10-31 night, owner) The server also reads statistics, builds reports from them and reads and analyzes the change log. Analytics is a separate choice on the key form and must be ticked.

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
- Limits: body size, translation count, one write per call, no bulk delete. There is no delete tool and release 6.4.0 adds none: every operation the agent has is reversible (hide, detach a box, hide a menu entry, change an address back). Whoever wants something removed for good does it in the site administration. Revisit only on the owner's word.
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
| 6.3.1 | warning when the page of an article has no box that shows articles (released) |
| 6.4.0, 6.4.1 | the editor agent (released and deployed; 6.4.1 makes the client try IPv4 first): the server knows WBengine (guide, prompts, resources), prepares and checks the whole article page (address, SEO fields, article box, menu), page, menu and box tools, images, the Editor key preset, skeleton registers its box type as an article renderer (see "Release 6.4.0") |
| 6.5.0 | insight: statistics, reports and change log analysis (see "Release 6.5.0") |
| later | curated and backed up settings changes with confirmation, multi-site MCP in the Admin (own plan, includes login and per-site roles) |
| not planned | deployment of a site: it runs on the host (git and Docker) and needs a separate privileged tool with approvals |

## Release 6.4.0: the editor agent (planned 2026-10-31)

Why: the live acceptance of 6.3.0 showed that an agent can write, preview, publish and hide an article, but a page it creates stays empty, because nothing attaches a box that shows the article. An inexperienced editor cannot be expected to know that. The server must carry the knowledge of WBengine and do the technical steps.

### Facts found on the live site (2026-10-31, key with the full profile)

- A page shows an article only through a box on that page that renders articles. In the skeleton the box type `story` (module `blog`, method `story`, `Box\Article::getArticleBox`) does exactly that, but it is registered without the flag `renders=article`, so the API reports `renders_articles: false` and `article_box_ids` stays empty even though the page works. The 6.3.1 warning therefore gives a false alarm for such a page. Verified: `POST /boxes {type: story, section_id: 1, sites: [page]}` on a new page, then publish and unhide, and the page showed the article (HTTP 200 with the text).
- The catalog of box types comes from the application (`BoxRegistry::register(type, module, method, settings, sections, ['renders' => 'article'])`). A client cannot choose a PHP class. The skeleton registers only `story` for the section `central`. Creating a box needs `boxes:write` (group Structure) and a registered type.
- A box has no visibility flag. It is shown on the pages listed in `sites` (or everywhere with `shared`), in a section, in `order`, subject to `device_min` and `device_strict`. Hiding a box means removing the page from `sites` (an empty list is accepted and the content disappears, verified) or limiting the device; showing it means putting the page back. The change log keeps the earlier state, so a restore exists.
- Page fields: `link`, `title`, `html_title`, `html_description` (170 bytes), `html_keywords` (255), `visible`, `acl`, `strict`, `parent_id`. The template appends the site name to `html_title` (the title became "MCP test | WBengine | WBengine" when the name was included), so the field must not contain the site name. A new page has these fields empty, which gives the title "WBengine | WBengine" and an empty description. Verified that a `PATCH` sets them and that they appear in the HTML.
- Menu fields: `site_id`, `name`, `description`, `type` (1 is the main menu), `parent`, `order`, `visible`, translations per locale (`translation_locales`). Creating, ordering and moving an entry worked. The header of the skeleton shows only top-level entries: an entry with `parent` 4 disappeared from it. Treat nesting as not displayed unless the site says otherwise.
- Writes with a missing `If-Match` are refused with 428; `POST` needs none. Deleting menu, box, article and page works through the API (`DELETE` with `If-Match`) and cleaned the test data completely; this is used by the test harness only, not offered as a tool.
- Capabilities of the live site: `images` is off for this key (a store directory is not configured, see "Images"), `templates` has no API, `analytics` needs the extra scope `analytics:read` (the key had none, 403), `config:read` and `audit:read` work, `users` exists and is not touched by the server.
- Czech titles become addresses without diacritics through `Mcp/Tools/Slug.php`; this is checked in `testTheWholeLifeOfAnArticle`.

### Goals and non-goals

Goals: from one request an editor gets a published article on a proper page with a clean address, SEO fields, a menu entry when wanted, and every site language filled in; the agent explains what it did and what it could not do. Non-goals: deleting anything, users, settings, templates, sections (read only), creating new box types (they are code).

### Knowledge layer (the server must know WBengine)

1. `wbengine_guide(topic)` tool and the same texts as MCP `resources` (`wbengine://guide/<topic>`) and a few MCP `prompts`. Tools are the main channel because many clients do not show resources or prompts. The texts are English Markdown files in `Mcp/Guide/` inside the phar, short and exact: `overview` (pages, articles, boxes, sections, menu, languages, drafts and publishing, how a page shows an article), `publish_article` (the checklist), `addresses_and_seo` (rules below), `boxes`, `menu`, `languages`, `images`, `statistics`, `troubleshooting` (every error code with the remedy). A test checks that every topic exists, that every tool name in the texts exists and that no text is longer than 6 KiB.
2. Prompts: `publish_new_article` (interview the editor, plan, draft, preview, ask, publish, report), `translate_article`, `seo_review`, `weekly_report`, `find_problems`. The protocol layer gains `prompts/list`, `prompts/get`, `resources/list`, `resources/read` in both protocol eras and announces them in the capabilities.
3. `instructions` of the server get two sentences: call `site_info` first, then `wbengine_guide overview`.
4. `site_info` is extended with facts about this site and a readiness list: languages, active sections, box types and which of them render articles, the number of pages and menu entries, whether images and statistics are available, and lines such as "Can create article pages: yes, with box type story in section central" or "no: the site has no box type that renders articles, ask the administrator to register one".
5. Tools are offered according to the scopes of the key (read from `/identity` at start), so an agent never sees a tool it cannot use. `WBENGINE_MCP_MODE=draft` keeps working.

### The article page workflow (the technical steps done by the server)

- `plan_article_page(title, locale, prefix?)`: no writes. Returns the proposal: address, page title, `html_title` (at most 60 characters, without the site name), `html_description` (at most 160), keywords, the menu entry suggestion, the box type and section to use, and blockers (no article box type, address taken, key lacks Structure). The editor confirms or changes it.
- `save_article_draft` gets `setup` (the same shape as the plan) so one call creates the page (invisible), the SEO fields, the article box (type from the catalog, `renders=article`, section central), the menu entry (invisible until publishing) and the translations, and remembers what it did in the result. The old `new_page` stays as a shortcut that now also attaches the box and fills the SEO fields.
- `check_article(id)`: a checklist with blockers and advice: the page exists, a box that renders articles is on the page, section active, SEO fields filled and within length, one translation per site language, the address clean, a menu entry (advice only), the article has a body. `publish_article` runs it first: blockers refuse the publication with the remedy, advice is appended to the result. The false alarm of 6.3.1 disappears because the check reads `renders_articles` and the box list, not `article_box_ids` alone.
- Address rules (`Slug`): lowercase ASCII from the title with diacritics removed (Czech and other Latin scripts, transliteration table for the rest), words joined with hyphens, at most 60 characters cut at a word boundary, no dates and no ids, a prefix such as `/blog/` only when the site has such a section page, unique among the existing pages (a numeric suffix as the last resort), always with the trailing slash the site uses. The address of a published page is never changed without `confirm_link_change`, because the framework has no redirects and old links would break; the result says so.
- `list_pages` gets a flag for pages that show an article, so the agent chooses an existing page without guessing.

### Page, menu and box tools (key group Structure and Content)

- Pages: `update_page` (`title`, `html_title`, `html_description`, `html_keywords`, `visible`, `link` with the rule above). Reads show the SEO fields.
- Menu: `list_menu`, `set_menu_entry` (create or update: page, name, translations per locale, `parent`, `order`, `visible`; ordering resolves clashes by renumbering within the level), `hide_menu_entry` and its reverse through `visible`.
- Boxes: `list_box_types`, `list_boxes` (filter by page, resolved client-side because the API has no page filter), `add_box` (type from the catalog only, section, pages, order, settings as the catalog allows), `update_box` (order, pages, device limits), `hide_box` and `show_box` (remove the page from `sites` and put it back; the result names the previous pages so nothing is lost). Sections are read only (`list_sections`).
- Every tool states what it changed and how to undo it. No delete tool; the change log feed lists every step with the actor.

### Images

`upload_image` (a local path or base64, PNG, JPEG or WebP up to 512 KiB, mime and size checked before sending, `alt` required) returns the public address and the Markdown to paste; `list_images`. The site needs a store directory (`apiV2.imageDirectory` or `WBENGINE_IMAGE_DIR`, outside the web root, writable, with a matching proxy body limit). This is a deployment step on the live host (volume in `docker-compose.production.yml`, variable in the environment) and a prerequisite of the live test of the images.

### Key preset and skeleton

- New preset "Editor" on the Integrations page: Content write, Publishing write, Structure write, Images write, Site off, History read, Analytics on. It replaces "Agent for content" as the recommended one for an editor; the narrower presets stay. The Structure group includes `sections:write`; the server has no tool that writes sections, the key could still do it through the API, which the owner accepted.
- The skeleton registers `story` with `['renders' => 'article']`, so the API and the server see that it renders articles. Plain facts in the public documentation through a guarded content migration (the next number after 035), mirrored in the dump.

### Testing

- In process tests as before for every tool (real API v2 in process, real database), including a page without a box, a page with a box, address collisions, Czech titles, SEO lengths, menu ordering, box hide and show, refusal by missing scopes, the guide checks and the prompts and resources protocol in both eras.
- The end to end test through the real process is extended with the full article page workflow.
- Live acceptance with a key that has all rights (see "Live acceptance procedure"), including the images after the store directory exists.

### Work breakdown for release 6.4.0

K. Protocol: prompts and resources in both eras; tool visibility by scope.
- [x] `Mcp/Server.php` and `Protocol.php`: `prompts/list`, `prompts/get`, `resources/list`, `resources/read`; capabilities; tests for both eras.
- [x] Tools are registered by scope (`/identity`), `site_info` extended with the readiness list.
L. Knowledge.
- [x] `Mcp/Guide/*.md`, `wbengine_guide`, resources, prompts, tests that keep them true.
M. Article page workflow.
- [x] `Slug` rules (length, uniqueness, prefix), `plan_article_page`, `save_article_draft` with `setup`, `check_article`, `publish_article` runs the check, `list_pages` marks article pages.
N. Page, menu and box tools.
- [x] `update_page`, `list_menu`, `set_menu_entry` (hiding an entry is `visible` false), `list_box_types`, `list_boxes`, `add_box`, `update_box`, `hide_box`, `show_box`, `list_sections`.
O. Images.
- [x] `upload_image`, `list_images`; the live host got the store directory (volume `images_data`, `WBENGINE_IMAGE_DIR`, skeleton PR #41).
P. Key preset, skeleton, documentation.
- [x] Preset "Editor" and its tests, `ref-mcp.md`, README, CHANGELOG.
- [ ] Skeleton: register `story` as an article renderer, content migration, README snippet, changelog, deploy.
Q. Release 6.4.0 (done 2026-10-31: PR #39 with green CI, tag, release with the phar and checksum, skeleton PR #42 with the registration of `story` as an article renderer and the docs migration 036, deployed, live acceptance 35 of 35 checks with the Editor key, the site back to its baseline counts). Release 6.4.1 (client tries IPv4 first, see the findings) is released and deployed.
- [x] Release, skeleton, deploy, live acceptance.
- [ ] Admin docs mention the Editor preset (small follow-up).

## Release 6.5.0: insight (statistics, reports, logs)

Statistics: the API has `GET /analytics/overview` (scope `analytics:read`, the Analytics choice of the key, migration `006-analytics.sql`, retention `analytics.retentionDays`). The server adds:
- `get_statistics(period, timezone)`: the overview as text and structure; says when `period.complete` is false (the period starts before the first recorded view) and when the retention cut the history.
- `site_report(period, compare)`: two calls (the period and the one before it), computes the change per top page, article and box and the totals, and returns a ready Markdown report with a table, the three biggest movers, the pages that nothing showed and an explicit list of caveats (no visitor identifiers exist, so no unique visitors beyond what the API gives). The narrative is written by the model; the prompt `weekly_report` guides it. Numbers never come from the model.
- Reads that matter to the editor: which articles were read most, what changed after a publication (the change log time against the page views).

Logs: what exists is the change feed (`GET /changes`, filters resource and id; actors, actions, times) and the table `cms_api_audit` with refused and failed writes (outcome `rejected`, `error` with a reference), which no endpoint reads yet. PHP `error_log` of the host is not reachable and is not going to be exposed through the key.
- `list_changes` gets filters for actor, action and a time window, and the server pages through up to a bounded number of rows.
- `analyze_activity(period)`: counts by actor, resource and action, a timeline, bursts, repeated failures; client side on the feed.
- Framework change: `GET /api/v2/audit` (scope `audit:read`, outcomes `rejected` and `error`, error code, resource, time window, actor, no bodies and no addresses, paginated) so that refused writes and errors can be analyzed. Decide with the owner whether a bounded reader of the application log is wanted; the recommendation is no, because log lines can hold personal data and secrets.

Work breakdown 6.5.0 (check the boxes in the commits that do the work):
- [ ] Framework: `GET /api/v2/audit`, docs, tests, migration none (the table exists).
- [ ] `get_statistics`, `site_report`, tests with fixtures of known counts, the guide topic `statistics`, prompt `weekly_report`.
- [ ] `list_changes` filters, `analyze_activity`, `list_audit_problems`, tests.
- [ ] Release, skeleton, deploy, live acceptance.

## Live acceptance procedure (for every release from 6.3.1)

The owner issues a key with the preset Editor plus Analytics (or the full profile plus Analytics) and passes it for the session only; it is revoked afterwards and never stored in a file or in memory notes. The run uses objects named `mcp-test-...`, drives the real phar over stdio, checks the public pages with plain HTTP and cleans up through the API with the test harness (`DELETE`), not through the server. The expected end state is the counts before the run (pages 18, menu 4, boxes 23, articles 13 on 2026-10-31). Steps: read tools, plan, draft with setup, preview in both languages, check, publish (page 200 with the text and the SEO fields in the HTML), menu entry and order, box order and hide and show, page SEO update, hide and unhide, image upload (when the store exists), statistics and report, change log analysis, refusal paths (key without scope, stale ETag), and a final comparison of the counts.

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
| 6.3.0 | MCP server phase 1 (ten article tools), agent key presets and client block, `wbengine-mcp.phar` | yes |
| 6.3.1 | warning when the page of an article has no box that shows articles | yes |

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
- [x] `site_info`: `GET /capabilities` and `GET /identity`; returns languages, scopes of the key translated into the permission groups, whether images work, and whether publishing and hiding are allowed (so the model knows before it tries).
- [x] `list_pages` (search, page), `list_articles` (search, hidden, has_draft, page), `get_article` (id; live content, translations, draft, state `draft` | `published` | `hidden`, ETag).
- [x] `save_article_draft`: create (`title`, `introtext`, `bodytext` Markdown, optional `locale` translations, `page_id` or a new page `{link, title}`) or update (`id`, changed fields, optional `expected_etag`). A new page is created hidden (`visible: 0`) and made visible by `publish_article`. Optional menu entry (`menu_name`). The tool chains ETags itself: create page, create or patch the draft, then one `PUT /translations/article/{id}/{locale}` per locale, always with the newest ETag. Derive the link from the title when absent (ASCII, lowercase, hyphens, `link_prefix` default `/`).
- [x] `preview_article` (id, locale): `GET .../preview`.
- [x] `publish_article` (id): publishes, then makes a page created by the tool visible; refuses clearly without the Publishing group; in `draft` mode the tool is not listed.
- [x] `hide_article`, `unhide_article` (id): call the hide and unhide endpoints; the result states that the URL answers 404 while hidden.
- [x] `list_changes` (resource, id, limit): `GET /changes` with the filters, needs `audit:read`.
- [ ] Moved to the plan for 6.4.0 and 6.5.0 below (`upload_image`, page, menu and box tools, statistics, logs).

D. Entry point and configuration.
- [x] `bin/wbengine-mcp.php`, registered in `composer.json` `bin`, so Composer exposes `vendor/bin/wbengine-mcp.php` (the name of the file, like `vendor/bin/wbengine.php`). Environment variables as in the table above (`WBENGINE_MCP_URL`, `WBENGINE_MCP_KEY`, `WBENGINE_MCP_ACTOR`, `WBENGINE_MCP_MODE`, `WBENGINE_MCP_ALLOW_INSECURE`). Missing or invalid configuration prints a clear message on stderr and exits non-zero.

E. Safety (see "Safety" above) implemented and tested: text read from the site is marked as data in the tool results, size limits, one write per call, no delete tool. Done: results wrap site text in BEGIN/END markers (`Tools\Untrusted`), field and translation limits, no delete tool; `save_article_draft` chains several API writes by design (see C).

F. Tests.
- [x] Unit: protocol framing and errors, schema validation of tool input, the client (redirect refused, oversize refused, invalid TLS refused, key never printed).
- [x] Integration: every tool against the real controller through the in-process transport with temporary tables (pattern of `tests/Integration/ApiV2ArticleHideTest.php`).
- [x] End to end: start `bin/wbengine-mcp.php` as a process, run a stdio session against a test site served by the PHP built-in server (pattern of `ApiV2HttpTest`): create a draft with a new page, preview, publish, hide, unhide, read the change feed with the actor. The public URL answering 404 while hidden is not part of this session (the fixture serves the API only); `ApiV2ArticleHideTest` covers it. The test runs the phar instead when `WB_MCP_PHAR` points to one.
- [x] The same session against the built phar.

G. Agent key preset on the Integrations page.
- [x] Buttons above the matrix in `Integrations.tpl` (the table in "Agent key preset", defined once in `Integrations::PRESETS`), filled by the script next to the paint function; texts in `I18n/messages/en.php` and `cs.php` and in `texts()`; no change on the server side; without JavaScript the form is unchanged.
- [x] After issuing a key, the one-time screen shows the client configuration block (JSON) and a command for Claude Code, with the site address and the key; the `draft` mode for the drafts-only preset. Add a test that the block appears only in the issue response, once, and contains the key only there.

H. The phar.
- [x] `bin/build-phar.php` (run with `php -d phar.readonly=0`): packs `Mcp/` and a small autoloader stub, sets the entry point, signs with SHA-256, writes `build/wbengine-mcp.phar` (`build/` is git-ignored).
- [x] `.github/workflows/release.yml`: on a published GitHub release, build the phar, run the stdio test against it, attach it to the release (`contents: write`). The README documents `php wbengine-mcp.phar` and the checksum. The workflow also checks that the tag equals `Protocol::SERVER_VERSION`. The `release` trigger itself cannot be exercised before a release exists: the CI job `mcp-phar` runs the same build and tests on every pull request, so the first real run of the workflow (the 6.3.0 release) only adds the version check and the upload.

I. Documentation.
- [x] Framework: README section "MCP server", `ref-mcp.md` (tools with schemas, errors, configuration, safety, client examples for Claude Code and Claude Desktop), CHANGELOG, update this plan.
- [x] Skeleton: README, `docs/CHANGELOG.md`, public documentation page or paragraph (plain facts only: what the server does, how to connect, the agent key) through a guarded content migration `035-mcp-server-docs.sql` mirrored in the content dump; `composer.json` constraint, `bin/update-database.sh` needs no change.
- [x] Admin: a short note in `README.md` and `docs/book-connect-a-site-end-to-end.md` about keys for agents.

J. Release 6.3.0 (done: all parts merged by PR with green CI, tag `v6.3.0`, the release workflow attached the phar and its checksum, the downloaded phar passes the checksum and answers over HTTPS against the deployed site; skeleton and Admin are deployed).
- [x] Release, skeleton bump, deploy.
- [ ] Manual acceptance with a real key (write an article draft, preview, publish, hide) needs a key issued by the site owner.

### Resuming after an interruption

1. Read this file, `git log --oneline -20` and `git tag --sort=-v:refname | head` in the framework, and the open pull requests, to see which of the boxes above are already done.
2. Check that `master` is green and that the live demo runs the latest framework (`composer show wbengine/wbengine` inside the web container).
3. Continue with the first unchecked box of the breakdown. Keep every step a pull request with green CI; releases are annotated tags plus a GitHub release; the skeleton and the Admin follow.
4. Do not change the decisions without the owner. Next work is the breakdown K to Q (release 6.4.0), then the one of 6.5.0. Questions that are still open: whether a bounded reader of the application log is wanted (the recommendation is no) and whether a delete tool is ever wanted (the recommendation is no).

### Conventions that apply to all of this work

- Documentation and code comments in English; the Czech texts only where a Czech translation of a user interface or of the public site is required. No em dashes and no icons in texts. Public skeleton documentation states only facts about what the system does and how to use it (no migration numbers, release history or how the text was made).
- Commits and pull requests carry no AI co-author or attribution lines.
- A new migration gets the next number in a folder named after the release (`migrations/6.3.0/013-...sql` if one is needed), is idempotent, is added to the skeleton scripts `bin/update-database.sh` and `bin/setup-production.sh`, and is reported by `doctor` as `migration.NNN`.

### Findings while building the tools (2026-10-31)

- `GET /articles?search=` matches the published title only, and an article that was never published has an empty one, so a client cannot find its own drafts by words. `list_articles` therefore also scans the draft titles (first page only, at most 400 drafts). A server side search of draft titles would be a small framework change for a later release.
- The menu collection has no filter by page. `publish_article` makes the page visible when it was not, and then pages through `GET /menu` (at most 1000 items) to make that page's menu entries visible. A `site_id` filter on `/menu` would make this one request.

### Findings of the live acceptance of 6.3.0 (2026-10-31)

- Read tools, draft with a new page and a Czech translation, preview in two languages, publish, hide and unhide all work against the live site through the released phar; the key never appears in any output.
- A new page has no article box, so the published address was empty (fixed as a warning in 6.3.1; the real fix is the workflow of 6.4.0). The warning is a false alarm for pages whose box type is not registered as an article renderer (the skeleton's `story`).
- Boxes, menu, page SEO fields and ordering work through API v2 with the structure scopes; the details are in the section "Release 6.4.0".
- Analytics needs its own scope; images need a store directory; both are prerequisites of the live test of 6.5.0 and of the images in 6.4.0.

### Findings of the live acceptance of 6.4.0 (2026-10-31)

- 35 of 35 checks passed against www.wbengine.org with the released phar and a key with the Editor profile plus Analytics: 25 tools offered, resources and prompts, `site_info` readiness, plan with a clean address under `/blog/`, image upload (the image is public and shown in the article), draft with a new page and the article box, preview, check, publish (the page shows the text, the browser title, the description and the image, the menu entry becomes visible), menu order with room making, menu translation, box add, update, hide, show, refusal of an unregistered box type, page update with a translation, protection of the address of a visible page, hide and unhide, change log with the actor. The test objects were removed through the API and the counts are back to pages 18, menu 4, boxes 23, articles 13, images 0.
- The first run was seven seconds per call slower than curl: the site publishes an IPv6 address and from some networks the IPv6 route is broken, and PHP streams try IPv6 first. 6.4.1 tries IPv4 first when the host has an IPv4 address (0.16 s instead of 7.2 s), the name and the certificate check are unchanged.
- A menu entry created through `menu_name` of `save_article_draft` gets the order 100, which sorts after every other entry but is an arbitrary number (the live menu showed 102 after later inserts). A later change should use "last position + 1" like `set_menu_entry`.
- Deleting through the API needs the dependents removed first (a menu entry with children, a page with menu entries or boxes answers `resource_in_use`). The test harness deletes children before parents. The agent has no delete tool by decision.
- The auto mode classifier blocked a cleanup script whose delete targets were computed at run time. Test cleanup is done as explicit deletes by id after the run.
- Statistics were not exercised: the tools belong to 6.5.0. The key has `analytics:read` and `site_info` reports `statistics: yes`.
