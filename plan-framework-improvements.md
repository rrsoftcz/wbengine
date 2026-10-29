# WBengine Improvement Plan

Started: 2026-10-25
Current checkpoint: 2026-10-27

## Purpose and status

The target release **4.0.0**, a new major version after 3.5.0, was published after the release gate was completed. The current framework release is `v4.1.0`.

A permanent shared list of proposals from the framework analysis and the user's subsequent additions. The user wants to implement the proposals. This document records the direction, priorities and verification conditions; implementation was started in a separate worktree `/private/tmp/wbengine-modernization`. The specific interface design and migration procedure are worked out before each individual change.

Main goal: keep website creation easy and request processing fast. Keep the Application, Site, Section and Box model, small components, deferred service initialization, parameterized SQL and compiled templates. Every new abstraction must have a concrete benefit.

Binding architectural rule from the user: new PHP core features must preserve the WBengine tree OOP architecture, the corresponding namespaces, parent links and the current model/adapter patterns. Wire services through the existing lazy application access, content through Site/Section/Box, database operations through models and Db, API modules through the current namespaces and route/controller patterns. Do not create a parallel framework or loose production scripts bypassing the tree. Existing static facades may be kept as the entry interface. The separate SvelteKit administration remains a detached API client according to the approved design; the PHP connector respects the WBengine architecture.

The priorities below are a recommendation from the analysis. P1 means security and correctness, P2 ordinary performance and predictability, P3 development convenience or optimization conditional on measurement. The status of individual items is given in the register below. When resuming work, always read this register and the progress log first.

## Working register

Current status as of 2026-10-27: framework `v4.1.0` and the private WBengine Admin `v0.2.0` are released; the public Skeleton App is cleanly deployed from the current `main` branch on a new VPS. The application runs over HTTPS with a host Nginx and PHP 8.4/MySQL 8.4 in Docker. The new database was filled with the versioned schema and content of the application; the old production database was not transferred. The API and analytics tables were created by framework migrations. Analytics is disabled for now, so the API does not announce `analytics.read`; GA4 on the site stays active according to the approved plan until our own analytics is finished and verified. The original demo password was invalidated and verified by a failed HTTP 403. WBengine Admin is not hosted on the VPS; its local connection to the site and creation of a limited integration key remains the next step. Manual conversion of ten historical HTML items is optional, because it requires an editorial decision. This checkpoint overrides older notes below stating that the live deployment did not take place.

### 2026-10-27: clean deployment of Skeleton App to a new VPS

- `www.wbengine.org` points to the new server. Nginx 1.24 and Certbot 2.9 serve HTTP/HTTPS; the Let's Encrypt certificate is issued, the automatic timer is active. `certbot renew --dry-run --no-random-sleep-on-renew` passed.
- Skeleton App commit `fcaf38a` runs in Docker Compose under `/opt/wbengine-skeleton-app`; the framework in the container is `v4.1.0`. Docker Engine 29.9.2 and Compose 5.6.1. MySQL 8.4 is reachable only inside the Docker network, web port 8080 is published only on loopback.
- The database was created from an empty volume from the current `schemas/` files; no backup or restore of the old database was performed. The framework migrations `002-api-v2-integrations.sql`, `005-api-content.sql` and `006-analytics.sql` were applied. The analytics setting stays `enabled: false`.
- The production `.env` has permissions 0600, `Settings.json` 0640 `root:www-data`. Apache can read the settings, but other users on the host cannot. The default demo password was replaced with a random bcrypt hash; a test of the original sign-in returned 403.
- Verified publicly over HTTPS: `/`, `/cs/`, `/docs/` and `/cs/docs/` return 200. The healthcheck of the web and the database is healthy. The Skeleton deployment uses commits `2416ddf` and the permissions fix `fcaf38a`.
- Next step: prepare the local WBengine Admin for connection to this site's API and perform a non-invasive verification. The administration will not be hosted on the VPS; a public integration key is not created and analytics collection is not enabled without a further controlled step.

Nearest follow-up step: finish the local operation of the administration per `plan-analytics-administration.md`, configure its access to the public site safely and verify capability/discovery and authentication. Our own analytics is implemented in the framework, but its production collection and display in the administration remain disabled/unconnected. GA4 is switched off only after verifying our own metrics according to `plan-analytics.md`. The design of the deletion tool remains before implementation; the decision "CLI first" remains valid.

Statuses: `waiting`, `ready`, `in progress`, `blocked`, `done`, `deferred`.

Every task must have a purpose, responsibility, dependencies, procedure, migration impact, acceptance tests, date of last change and evidence of completion. `Ready` means the interface and test scenarios are resolved. `Done` means implemented, verified and documented. Work in progress must state the next concrete step. Evidence and commits are in the progress log.

### Binding versioning and safe continuation

After each larger standalone change and at the end of each phase, create a checkpoint:

1. Run the checks matching the change and record the actual result.
2. Update this plan: completed and in-progress tasks, remaining work, decisions, any blockers, migrations and the nearest concrete step.
3. Commit the related code together with the current state of the plan. Purely documentation changes may have a separate commit.
4. Push to the working branch `codex/wbengine-4.0` and verify that the remote branch contains the created commit. The current PR must match the actual scope of changes.
5. Only then continue with the next larger change or phase. Pushing a commit is not by itself a merge, tag or release of a new version.

When interrupted in the middle of a change, save the reached state as an explicitly in-progress checkpoint, list failed or unperformed checks and the exact next step. Such a commit must not be passed off as finished or ready for deployment. If the push fails, state explicitly that the checkpoint is local only and complete the remote storage before the next larger change.

When resuming, first read this plan, verify the branch, the latest commits, working changes and the state of the remote branch. Do not repeat completed steps; continue from the recorded next step and preserve others' changes.

- [x] SEC-01: done (Codex, 2026-10-26), public/personal responses and post-commit invalidation of all new content API/publish/migration paths; external SQL and CDN require their own purge.
- [x] SEC-02: done (Codex, 2026-10-25). No new MD5 fallback, check of the actual hash length, verified also on a real varchar(32) with legacy sign-in preserved.
- [x] SEC-03: done (Codex, 2026-10-25). Both adapters escape by default, native metadata and fallbacks fixed, cache modes separated, migration and context rules documented.
- [x] SEC-04: done (Codex, 2026-10-25), unchanged inputs, string helpers, validation of authentication fields and migration documentation.
- [x] SEC-05: done (Codex, 2026-10-25), explicit trusted proxy IPv4/IPv6, shared IP/HTTPS detection and cookie flags including I18n.
- [x] SEC-06: done (Codex, 2026-10-25), session CSRF service, form purposes, POST logout, cache protection and the stateless cookie API boundary.
- [x] FIX-01: done (Codex, 2026-10-25). The iat setter preserves iss, regression test RED and GREEN.
- [x] FIX-02: done (Codex, 2026-10-26), a single parameterized SQL lookup even with cache, actual collation/padding/strict/visible/duplicates verified on MySQL and MariaDB.
- [x] CACHE-01: done (Codex, 2026-10-25). Public/personal scope, lazy identity, locale, anonymous bypass and protection of public wrappers verified including HTTP. Other content variants require explicit keys.
- [x] PERF-01: done (Codex, 2026-10-25), diagnostics only in debug or via Site::assignDiagnostics; removal of one unnecessary connection on a cache HIT verified.
- [x] PERF-02: done (Codex, 2026-10-25), a request-level set of active IDs and a single batch SQL statement for prefixes, preserved hidden/strict/collation and fixed trailing slash of parents.
- [x] PERF-03: done (Codex, 2026-10-25), explicit Session::close/Application::closeSession, write under lock and a subsequent snapshot without further persistent changes, measurements and tests of independent processes.
- [x] PERF-04: closed by decision after measurement (Codex, 2026-10-26). The URL map is not introduced in v4.0: no demonstrated benefit for a single lookup and with the risk of different collation. FIX-02 has exactly one SQL lookup, data and reasons below.
- [x] PERF-05: done (Codex, 2026-10-25), MySQL/MariaDB measurement, shared section condition and an optional (section_id, order) index migration, preservation of results and rollback verified. Relational redesign deferred without demonstrated need.
- [x] PERF-06: done (Codex, 2026-10-25), one parser pass per Json call, the raw/decoded contract and native errors preserved including NUL-leading keys.
- [x] PERF-07: done (Codex, 2026-10-25), global whitespace regex removed, dispatch preserves the output of both adapters and ignores legacy minimizeHtml.
- [x] ARCH-01: done (Codex, 2026-10-25), constructor without CMS, deferred Application routes before cache/CMS, a single render and the migration contract.
- [x] DX-01: done (Codex, 2026-10-26), explicit class/template/SQL scaffold preview, safe atomic --write and a real parent tree test.
- [x] DX-02: done (Codex, 2026-10-26), installed Composer CLI, read-only doctor/config/schema and the production profile/secret redaction.
- [x] DX-03: done (Codex, 2026-10-26), a real CMS matrix of 15 cohorts/300 requests without errors, CSRF login/DB sessions, measured SQL/connections/memory, result and runner in skeleton d0b1e79.

- [x] PLAN-01: done, approved decisions and the staged plan recorded, 2026-10-25.
- [x] BASE-01: done, baseline suite of 802 tests / 1534 assertions in a separate Docker environment, 2026-10-25.
- [x] TPL-01: done (Codex, 2026-10-26), inventory of 7 framework and 13 sample sources, read-only compile report, a corpus of expected outputs and differences and ref-template-migration.md.
- [x] TPL-02: done (Codex, 2026-10-26), diagnostics, include/set/buffers and an isolated application migration including 20 HTTP checks and green CI of skeleton PR #1, commit 35d2d54.
- [x] TPL-03: done (Codex, 2026-10-26), Smarty outside the core in the optional compat/smarty, a separate suite 86/123, clean core 1888/5101 and fresh skeleton HTTP 20 checks.
- [x] MD-01: done (Codex, 2026-10-26), own AST parser and safe renderer, all official CommonMark/GFM examples and explicit security deviations, limits of work/input/output.
- [x] MD-02: done (Codex, 2026-10-26), lazy App Markdown, safe .md sources/layout, explicit legacy_html/markdown in the DB, mixed translations and a concurrency format guard.
- [x] MD-03: done (Codex, 2026-10-26), explicit HTML/Texy dry-run, manually approved hash, atomic exact backups/translations and conflict rollback, 38 tests/138 assertions.
- [x] MD-04: done (Codex, 2026-10-26), Texy outside the core, lazy Markdown formatter, offline bridge with a separate test/CI; clean sample installation and 20 HTTP green.
- [x] API-01: done (Codex, 2026-10-26), Api/V2 tree, public minimal discovery and real capability flags, a unified JSON/error/pagination contract.
- [x] API-02: done (Codex, 2026-10-26), scoped CRUD, registered boxes, structural restore/translations and public safe media.
- [x] API-03: done (Codex, 2026-10-26), private draft, immutable revisions, atomic publish/translations, safe preview and ETag conflicts.
- [x] API-04: done (Codex, 2026-10-26), scopes/revoke, audit, failed-auth limiter, ADMIN bounded retention, transaction ownership and post-commit invalidation.
- [x] ADM-01: done, separate private rrsoftcz/wbengine-admin, MySQL/Docker, base 533f0e7.
- [x] ADM-02: done, invitations/MFA/sessions/per-site roles and concurrency regressions, CI green.
- [x] ADM-03: done, encrypted keys, CLI provisioning and pinned HTTPS/SSRF/deadline.
- [x] ADM-04: done, editor/structure/translations/boxes/images, 357d410, real PHP pilot.
- [x] ADM-05: done, sandbox preview/draft/publish/revisions/recovery/conflicts and a fix for browser required, 33 tests green.
- [x] REL-01: done 2026-10-26. Dual-site/revocation/outages/DB restore and the migration guide verified; core PR #2, skeleton #1 and administration #1 merged, tag and GitHub release v4.0.0 published. Merge commits: cf710e5, 19fde66, a74892a. Production deployment is not part of this phase.

## Baseline verification

- In the previous analysis, all 802 tests with 1,534 assertions passed in Docker on PHP 8.4.27. Composer audit reported no known vulnerabilities of the installed dependencies. This is the state at that analysis, not a guarantee of future state.
- A supplementary isolated script verified storing a page with `Cache-Control: no-store`, `Cache-Control: private` and `Set-Cookie`, sharing a fragment between the Czech and English locale and creating MD5 with a narrow password column.
- The other findings below are based on reading the current code unless stated otherwise. No new throughput measurement or production penetration test was performed.
- The existing `ref-inventory.md` and the README contain historical measurements and findings; their lists of open tasks partly diverge from the current code. Verify the real state before implementation.

## Security and correctness

### SEC-01: Rules and control of full-page cache, P1

Source: `PageCache.php`, especially `_mayStore()`, `capture()` and `_serve()`.

- Respect `Cache-Control: private` and `no-store`; define the behavior for `no-cache` and `Vary`.
- The default safe policy should bypass storing on `Set-Cookie`, with possible explicitly supported exceptions. `Set-Cookie` alone is not an automatic ban on caching under HTTP.
- Add easy per-page cache control and the possibility of an explicit bypass from application code. The bypass decision must affect also reading an old entry, not only storing a new one.
- Take into account custom cookies, device variants and other sources of personalization. The locale already separates the full-page cache; keep this protection.
- On a hit, preserve the necessary security and content headers, including CSP consistency and any nonces. Do not store or replay foreign cookies.
- Make invalidation clearer on changes of articles, pages, menus, sections and boxes. Keep the existing invalidation of sections and translations; for direct SQL changes describe explicit invalidation or a TTL.

Verification: a personal response is not shared between visitors, the storing ban works, headers are consistent on MISS and HIT, content changes do not return unreasonably old data.

### SEC-02: Stop creating new MD5 passwords, P1

Source: `User/Password.php::hash()` and `canStoreModernHash()`.

- Keep temporary verification of old MD5 and conversion after a successful sign-in.
- For new and changed passwords use a modern hash; when the schema migration was not performed, return a safe and understandable error instead of a fallback to MD5.
- Require a column matching the recommended `varchar(255)` and check it at installation or deployment.

Verification: a newly stored password is never MD5, the hash is not truncated and migration of old accounts stays functional.

### SEC-03: Safe default escaping and template trust, P1

Source: `Renderer/Adapter/SmartyRenderer.php`, `Renderer/Adapter.php`, `Template/Runtime.php`.

- Introduce automatic HTML escaping for new sites. NativeRenderer already supports it, the Smarty adapter does not enable it by itself.
- Migrate existing templates with a distinction between trusted HTML and ordinary data, prevent double escaping and preserve section content.
- Document the HTML, attribute, URL and JavaScript contexts; general HTML escaping does not solve all contexts or the allowed URL schemes.
- Document that templates are trusted code: NativeRenderer allows calling public methods of passed objects. Before making templates available to untrusted editors, design a restricted data interface.

Verification: XSS payloads in the supported contexts, compatibility of both renderers and a conscious use of `raw` for trusted content.

### SEC-04: Replace preliminary input cleaning, P1

Source: `Application/Env/Http.php::secureClean()`, `Post()`, `Get()` and related helpers.

- Separate unchanged input values, validation of type and meaning, SQL parameters and output escaping.
- Remove the new code's dependence on the combination of `stripslashes`, `htmlspecialchars`, `strip_tags` and `addslashes`.
- Introduce the change of existing helpers with a migration procedure and a check of callers. Simply removing the cleaning may expose XSS so far masked in old templates.
- Define a missing value, an array instead of a string and invalid encoding. Also examine `getCookie()`, which today HTML-escapes already on reading.

Verification: apostrophes, backslashes, HTML and passwords are preserved unchanged; database operations use parameters and output is escaped in the corresponding context.

### SEC-05: Trusted proxies and HTTPS detection, P1

Source: `Application/Env/Stac/Utils.php::getUserIp()`, setting of session cookies and API cookies.

- Introduce an explicit list of trusted proxy IPs or CIDRs and supported headers. By default trust only the direct connection.
- Accept forwarded addresses only from the trusted immediate proxy; evaluate the address chain from the trusted end, validate IPv4 and IPv6.
- Remove the untrusted fallback to `X-Forwarded-For` and `Client-IP` when `REMOTE_ADDR` is missing.
- Also unify detection of the original HTTPS for `Secure` cookies behind TLS termination. Forged forwarded headers must not change the IP or scheme.
- The current code prefers `REMOTE_ADDR`; an ordinary client therefore cannot override it with a forged `X-Forwarded-For` alone. The problem behind a proxy is mainly the shared IP for rate limiting and sessions.

Verification: direct connection, one and several proxies, a forged left end of the chain, IPv6, invalid values and correct cookie flags behind TLS termination.

### SEC-06: General CSRF protection, P1

Source: the private token protection in `Box/Auth.php`.

- Create a small reusable helper for issuing and verifying a token, forms and AJAX, tied to the session and optionally the form purpose.
- Protect operations changing state of authenticated cookies, including POST, PUT, PATCH and DELETE according to use. Judge an API with an explicit bearer token by its actual authentication mechanism.
- Convert the web logout via the GET parameter `a=logout` to a state-changing operation with corresponding protection.
- Do not create a session globally on every public GET. Pages with tokens must not share a personal token through the page cache or the fragment cache.
- Ensure that multiple forms and open panels work. SameSite or captcha alone are not a substitute for a general solution.

Verification: a missing, wrong and foreign token is rejected, legitimate forms work, a public page without a form does not get a new session write.

### FIX-01: Fix the JWT issued-at setter, P1

Source: `Auth.php::setIssuedTime()` writes to `iss` instead of `iat`.

- Write the time to `iat`, preserve the issuer and test the meaning of individual setters. The constructor already sets the initial `iat` correctly; the problem is specifically the setter.
- The setter bug alone does not prove an authentication bypass. Describe it as a JWT correctness bug with possible impact on integrations and time validation.

Verification: the decoded token contains the required `iat`, unchanged `iss` and correct `exp`.

## Cache and performance

### CACHE-01: Separate fragments by locale and sharing scope, P1

Source: `Template/Runtime.php::cache()`.

- Separate the locale automatically. Define the public and personal scope of a fragment and key rules for other variants.
- Prevent the author from having to remember the locale for every ordinary translated fragment.

Verification: the same fragment name returns the correct Czech and English content; any personal fragments are not shared between users.

### PERF-01: Diagnostic data only as needed, P2

Source: `Site.php::initialize()` and `Site/SiteModel.php::getDBVersion()`.

- Prepare DB, PHP and server versions only for debug or an explicit request.
- Remove opening a DB connection solely for `dbinfo` on a page served by the data cache.

Verification: a warmed public page covered by the data cache does not need a connection just for diagnostics; measure connections and SQL queries separately.

### PERF-02: Remove N+1 menu queries, P2

Source: `Site.php::isMenuSelected()`, `getUrlPairsWithSiteId()`, `Site/SiteModel.php::getSiteIdByUrl()`.

- Compute the active ID set once per request and reuse it.
- Use the map of already loaded pages or a single batch query when the cache is off.
- Examine the semantics: the cache `sites:visible` contains only visible pages, whereas the current lookup by URL does not filter by visibility. Preserve or consciously unify the handling of hidden pages, duplicate URLs and collation.

Verification: the number of lookup queries does not grow with the number of menu items; active items and parents match the originally intended behavior.

### PERF-03: Session lock duration, P2

Source: `Session/SessionAbstract.php::sessionStart()`, `Session.php::flush()` and ID regeneration.

- Measure parallel requests with the same cookie under the session handler in use.
- Propose an earlier safe release of the PHP session lock. Preserve ID regeneration and correctness of deferred database writes; prevent lost updates and restoration of a logged-out session.

Verification: compare parallel latencies and concurrent writes, sign-in and sign-out. Do not promise a specific speedup without measurement.

### PERF-04: URL index for larger sites, P3

Source: `Site/SiteModel.php::_findVisibleSite()`.

- Consider a map by normalized URL instead of iterating over all visible pages.
- Preserve strict and prefix search, the current result priority and compatibility with database comparison.

Verification: match of the cache and DB branches and cost with a growing number of pages; for small sites do not add complexity without benefit.

### PERF-05: Box indexes and a possible relational link, P3

Source: `Section/Model.php::getBoxes()`, table `cms_boxes`.

- Compare the candidate `(section_id, shared, order)` mainly with `(section_id, order)` and with the actual existing indexes of the target schema.
- Rewrite the condition as needed to a form with a shared `section_id`, preserving results and parameters.
- The current combination of `JSON_SEARCH` and `OR` does not allow guaranteeing in advance the removal of a scan or sort. A candidate with `shared` between section and order may not cover the global ordering of both branches.
- For larger installations consider a relational link table of boxes and pages with indexes. Preserve convenient control when building a site.

Verification: plans via `EXPLAIN` and runtime statistics as MySQL and MariaDB allow, a realistic number of boxes and shared ratio, cold and warm cache, impact on writes and match of results.

### PERF-06: Single JSON decoding, P2

Source: `Application/Env/Http.php::Json()` and `isJson()`.

- With `Json(true)` decode once and evaluate the error of the same decoding. Today `isJson()` decodes and `Json()` decodes again.
- Preserve the documented contract of `Json(false)` and determine the difference between invalid JSON and valid JSON `null`.
- Test scalars, an empty body, arrays, objects, invalid encoding and the depth limit. Introduce the new error behavior consciously with respect to existing controllers.

Verification: a single parse of the decoded input, regression tests of return values. Treat the benefit as a small local optimization, not a main site speedup.

### PERF-07: Safe handling of HTML and transfer compression, P2

Source: `Renderer.php::dispatch()`.

- Turn off or replace the global merging of whitespace by a regular expression.
- For transfer use web server compression; perform any content minification with a tool that understands its syntax.

Verification: no change in the meaning of `pre`, `textarea`, inline JavaScript, CSS and text spaces.

## Architecture and development convenience

### ARCH-01: Defer request processing, P2

Source: `Application/Application.php::__construct()`, `get()`, `run()` and API box integration.

- Separate application preparation, registration and evaluation of routes, CMS initialization and rendering.
- Move the execution of boxes from object construction to an explicit request handling phase. Allow setting services and variables before rendering.
- A terminated custom API route should bypass CMS queries, sections and boxes. Merely moving `initialize()` without defining the order of routes and cache is not enough.
- The page cache must not terminate the request before the custom routes and their policies. Keep the early i18n normalization needed for routes.
- Ensure migration of existing applications and API registration inside boxes, a correct 404 and a single render.

Verification: a custom route works without CMS initialization, ordinary pages keep their behavior, a HIT does not skip the required application setup.

### DX-01: Creating a page and box with one command, P3

- Design a small generator of the class, template and necessary configuration or database migration.
- Respect the namespace and directory conventions of the application, do not overwrite existing work and offer a preview of changes.

Verification: new simple web content arises through a short and repeatable procedure without manually searching for links.

### DX-02: Installation check and production profile, P2

- Add a command to check configuration, migrations, writable directories, secret keys and availability of the chosen cache driver.
- Add recommended production settings: debug off, correct proxy and HTTPS configuration, OPcache, optimized Composer autoload, template change checking off and clear clearing of the compiler cache on deploy.
- The check should be explicit during setup or deploy, not an expensive check on every request.

Verification: a faulty installation has a concrete repair guide; a healthy production request carries no additional check overhead.

### DX-03: Repeatable performance measurement, P2

- Measure separately with cache off, warmed data cache and full-page cache.
- Cover a visitor without a cookie, an anonymous one with a session, a signed-in one and a custom API route; also measure parallel requests of the same session.
- Record p50 and p95 latency, throughput, errors, memory, SQL queries and DB connections. State PHP, server, DB, OPcache, data and concurrency.
- Treat claims of a nearly instant response or minimal memory as a hypothesis until measured. Do not convert historical results into guarantees.

## Recommended order and dependencies

1. Fix the page cache rules, the MD5 fallback, fragment locale and the JWT setter. These are confirmed and fairly bounded problems.
2. Design and implement together safe templates and migration of the input helpers; add general CSRF protection and trusted proxies.
3. Remove the diagnostic DB connection and repeated menu lookups, simplify JSON and retire dangerous minification.
4. Refactor the application lifecycle and session locking with integration tests and a migration procedure.
5. Measure performance; according to results select box indexes, the URL map and a possible relational link. Add the production check and the generator.

No time estimate has been set yet. Split changes into small, reviewable units; supplement security changes with regression tests of the concrete scenario. Keep larger changes compatible or provide them with a clear migration path. Before each unit, verify the current state of the code and tests.

## Reference sources

- [RFC 9111: HTTP caching](https://www.rfc-editor.org/rfc/rfc9111.html)
- [OWASP: Password storage](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)
- [OWASP: CSRF prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [Symfony: Trusted proxies](https://symfony.com/doc/current/deployment/proxies.html), a reference principle of trust in proxies, without a requirement to add a dependency.
- [MySQL 8.4: Multiple-column indexes](https://dev.mysql.com/doc/refman/8.4/en/multiple-column-indexes.html)
- [MySQL 8.4: ORDER BY optimization](https://dev.mysql.com/doc/refman/8.4/en/order-by-optimization.html)
- [RFC 7519: JWT claims](https://www.rfc-editor.org/rfc/rfc7519.html#section-4.1.6)
- [PHP: session_write_close](https://www.php.net/session-write-close)

## Approved target architecture 4.0.0

### Own templates and Markdown

Develop the existing NativeRenderer with compilation to PHP and OPcache. Keep the supported syntax; do not promise a complete replacement of all Smarty constructs. Automatic escaping of ordinary data and explicit trusted HTML. Smarty remains only a temporary compatibility layer during the migration.

Own parser: CommonMark 0.31.2 plus tables, strikethrough and task lists according to the relevant GFM rules. Block and inline parser with an internal tree, a separate safe HTML renderer, limits of size and nesting, checking of URL schemes. Embedded HTML is escaped in the safe profile. The parser must pass the standard examples; conscious differences of the safe render are recorded.

The same parser for the DB and for .md files in the allowed content directory. Markdown is content embedded in an ordinary HTML layout; no variables, PHP, loops or template logic, even in trusted .md files. The preview uses the parser of the target site. The format of stored text is explicit, not guessed. The Texy migration has a dry-run, originals, translations, a report of manual fixes and output checks. Keep firebase/php-jwt and the development tools.

### Separate central administration

A separate project `wbengine-admin`, SvelteKit + TypeScript with a server, its own MySQL 8.4 and Docker. The browser communicates only with the central server; after checking permissions it calls the site's API. Integration keys do not travel to the browser. The sites own content, drafts and revisions; the central server owns accounts, connections, access rights and audit. An outage of the central server must not take the sites down. No direct access to remote DBs/FTP and no automatic site update.

Local accounts by invitation, access recovery, TOTP and backup codes; MFA mandatory for administrators. The global administrator connects sites and assigns rights, an editor manages content of assigned sites, a site administrator also its structure. Each site issues a revocable integration key with limited scopes, the key hash on the site and the encrypted key in the central server with the encryption secret outside the DB. The audit distinguishes the integration identity and the central user.

Public HTTPS sites by default; intranet/VPN only with an operational allowlist and an available network path. Protection against SSRF, redirects and DNS rebinding. The first version manages articles, pages, menus, sections, registered boxes, translations and images, preview and publishing. No remote editing of PHP or templates, no multi-organization SaaS, approval workflow or scheduled publishing in the first version.

### API v2 and publishing

`/api/v2` alongside the preserved API v1. Capability discovery, API version and a catalog of allowed boxes with validated settings. CRUD of resources, unified errors and pagination, ETag/If-Match as protection against overwriting someone else's change. Reject a stale write, invalidate related caches after commit. The API does not accept an arbitrary PHP class/method. An older installation requires an explicit upgrade of the API and schema before connecting.

Drafts and revisions concern articles and their translations. Saving a draft does not change published content; restoring a revision creates a draft. The structure changes directly after explicit confirmation, with audit and recovery. Atomic publishing of the whole site is not part of the first version. Personal previews must not enter the public cache.

## Stages and implementation procedure

1. Stage 0: store the tracker and decisions; record baseline tests; prepare the DX-03 repeatable benchmark. The test baseline is done, the benchmark remains open.
2. Stage 1: SEC-01, SEC-02, CACHE-01, FIX-01, each bug with a regression scenario before the fix.
3. Stage 2: SEC-03 to SEC-06 together with the migration guide for inputs and templates.
4. Stage 3: PERF-01, PERF-02, PERF-06, PERF-07, ARCH-01 and PERF-03; PERF-04/PERF-05 only according to measurement.
5. Stage 4: TPL-01 to TPL-03, inventory, compatibility, migration and separation of Smarty.
6. Stage 5: MD-01 to MD-04, parser, .md/DB, conversion and removal of Texy.
7. Stage 6: API-01 to API-04, contract, resources, publishing, keys and audit.
8. Stage 7: ADM-01 to ADM-05, central server, identities, sites, editor and publishing.
9. Stage 8: DX-01, DX-02, REL-01, pilot and gradual deployment of 4.0.0.

Before each task: verify the state, write down the concrete interface and migration procedure, determine regression tests, take responsibility. Then a test failing before the change, implementation, targeted tests, the whole necessary suite and documentation. After completion record the actual results and the commit, update the register. Do not mark the whole SEC-01 as done if only part of the cache rules is finished.

Release gate: a sample application and one pilot, a working backup and rollback, verified content originals, completed manual migration fixes, no mandatory Smarty/Texy dependency, end-to-end tests of two sites and a limited user, concurrent edits, a revoked key, site/central outages, safe preview, SSRF and recoveries. Tag and GitHub release only after the gate is met; an ongoing draft PR is not a finished release.

## Progress log

### 2026-10-27: decision on own analytics

- Approved product direction: WBengine will move toward its own aggregate analytics available through a separate administration. The metrics should correspond to the number of views and trends, not the identity of specific visitors.
- Lifetime view counts of pages/articles are preserved even on a request to delete a user account, as long as they cannot be linked to a specific person. A deletion request must not become a reason to delete global aggregates.
- Proposed storage boundaries: no IP address, analytics cookie, user identifier or individual visit profile in the analytics storage. Any temporary technical data get a separate necessary retention period. The exact legal regime and anonymization properties must be assessed according to the concrete implementation.
- The view count must not be labeled as a count of unique people. Exact deduplication of unique visitors without an identifier is not possible. Counting a box appearance means an appearance in the rendered page, not proof that the visitor saw it on screen.
- Technical requirements for the design: include requests served by a full-page cache HIT so that the measurement does not undercount visits; evaluate the existing `Wbengine\\Counter\\BatchCounter`; limit work per request and avoid a synchronous database write for every view; expose aggregates through a narrowly authorized API to the administration.
- Transition: until our own metrics are implemented and verified, the existing GA4 remains an operational matter. Its possible switch-off will be a separate step; switching off measurement does not by itself delete historical Google Analytics reports.
- Status: the user approved the technical analytics design. Implementation is waiting. The approved default boundary is 62-day retention of daily totals, permanent lifetime totals and counting of known and unknown robot requests.
- Detailed metrics, periods, API contract, retention and migrations are in `plan-analytics.md`. The strategy for cache HIT, batch flushing and performance is a condition of implementation.
- 2026-10-27: the user approved the analytics design. The admin integration/test implementation plan is in `plan-analytics-administration.md`; it is only a plan for now, no part of analytics or the administration changes in this phase.

### 2026-10-25: Start of 4.0.0

- Worktree `/private/tmp/wbengine-modernization`, branch `codex/wbengine-4.0`, base commit `9149fef`, last release `v3.5.0`.
- Baseline verification: `docker compose -p wbengine-modernization run --rm php composer test`: 802 tests, 1534 assertions, success on PHP 8.4.27.
- Decision: new major version 4.0.0, compatibility with old installations through explicit migration. Existing detailed findings and IDs are preserved.
- Decision: the first implementation package fixes confirmed bugs; the extension of SEC-01 will be reported in parts until all criteria are met.

- 2026-10-25: the user added the binding preservation of the tree OOP architecture and namespaces for new core features. The rule was added to the global constraints of the plan.
- 2026-10-25: 14 new regression scenarios failed before the change for the expected reasons; after the change the targeted suite of 171 tests / 252 assertions passed. Final verification and review remain.

### 2026-10-25: First verified implementation package of 4.0.0

- Implemented: the JWT iat setter; rejection of new passwords with a narrow column without an MD5 fallback; separation of fragments by locale; stricter page cache, ignoring old entries, allowPaths/denyPaths and a request-wide bypass.
- The cache preserves standard security headers including cross-origin policies on HIT and 304. The current deployment policy takes precedence over the stored one. CSP nonce, private responses, arbitrary application cookies and an anonymous session with personal data bypass the shared cache.
- Decision: custom cookies conservatively mean a bypass, which may reduce the HIT share on sites with analytics cookies. Without an explicit variant scheme, separating personal data takes precedence; any safe exceptions are to be solved separately.
- Decision: `PageCache::bypass()` called before the Application is constructed protects both reading and writing; a call in a box protects only the writing of the current response. This boundary is explicit in the README.
- Review: an independent read-only reviewer found missing cross-origin headers; a new HTTP test failed, the headers were added and the test passed. Added an integration test of a real varchar(32) including legacy authentication; the original schema is restored in finally.
- One standard Composer run timed out; the following complete diagnostic run and two standard runs passed. Final result: `docker compose -p wbengine-modernization run --rm php composer test`, **835 tests / 1603 assertions**, 9.913 s, PHP 8.4.27, MySQL 8.4.
- `docker compose -p wbengine-modernization run --rm php composer lint`: success. `git diff --check`: success.
- The changes stay in the existing production classes and namespaces. The only new standalone PHP files in this package are the HTTP test and its fixture; no parallel production framework was created.
- Not implemented: own Markdown, removal of Smarty/Texy, API v2 or the central administration. Their tasks remain open.
- Next step: finish the explicit fragment scope and the content invalidation design, then SEC-03 to SEC-06. Prepare a draft PR 4.0.0 for GitHub, without a tag/release and without a merge to master.

- Git commit of the first package: `6e799ed`. A historical record of working PR #2, now merged as `cf710e5`. The local test database was stopped after verification.

- GitHub CI for commit `6e799ed`: all 5 jobs successful, PHP 8.3/8.4/8.5 with MySQL 8.4 and PHP 8.4 with MariaDB 10.11/11.4. The results are available in draft PR #2.

- 2026-10-25: at the user's request, a binding rule was added to commit and push after each larger change or phase also the current state of the plan, including the rules of in-progress checkpoints and safe continuation. Before the change, the match of the local HEAD and upstream at `1d98025` was verified.

### CACHE-01: Follow-up implementation procedure

Status: in progress, Codex. Base checkpoint `b3a7116`, local and remote branch identical.

- Extend the `{cache}` block with a literal `scope='public'` (default) and `scope='user'`, optionally with `ttl`. Reject an unknown or duplicate parameter.
- Add the object `Wbengine\Template\FragmentCache` as a lazy descendant of `Template\Engine`. The compiler calls the engine, the renderer adapter passes the user resolver and `Renderer` connects it through Application to the current User. The public scope does not call the resolver.
- Interface: `Engine::setCacheUserResolver(?callable)`, `Engine::cache($key, $ttl, callable $render, $scope = 'public')`; NativeRenderer and Renderer\Adapter mediate setting the resolver.
- A personal key contains a valid positive user ID, the locale and the fragment name; public and personal keys have different namespaces. A missing or invalid identity must not fall into the public cache. The resolver is evaluated at use, not at construction.
- A personal scope triggers a page-cache bypass even with the cache off or an anonymous visitor. A public wrapper that used a personal fragment during rendering must not be stored in the shared cache. A public block must have content independent of the user; other variants belong to explicit keys.
- Keep the original syntax of cache blocks and Runtime::cache for a direct public call; change the compiler version and the namespace of fragment keys so that old wrappers are not used.
- Tests before implementation: separation of two users and locales, public versus personal scope, anonymous/invalid identity, a deferred resolver, nested/private wrapper, invalid syntax, passing through NativeRenderer and Renderer\Adapter, page-cache bypass through real HTTP.
- After the targeted tests run the whole suite, lint, independent review and record the results. Commit and push the code and the updated plan before the next larger task.

### 2026-10-25: CACHE-01 completed

- The new service `Template\FragmentCache` is a lazy descendant of Engine. The compiler calls the engine; NativeRenderer and Renderer\Adapter pass a resolver connected to Application. The public scope does not trigger authentication or a session lookup.
- The user scope separates keys by the current authenticated session and locale. An invalid or missing identity or a resolver error does not use the public cache. When the identity changes, a stale ID from the User resource is not used.
- Personal fragments switch off the page cache; a public wrapper with an executed personal fragment is not stored. The same protection applies through the compatible Runtime helper. A rendering error cleans up nested buffers.
- Decision: after a personal scope, other public fragments are conservatively bypassed until the end of the request. This may reduce the number of HITs for signed-in users, but prevents shared storage of a personal wrapper. A public block must be independent of the visitor and of branches that do not run on a cache hit.
- Decision: the public-only Runtime helper is kept, internally delegating to the same OOP cache mechanism. Compiler VERSION=2 and the prefix tpl:v3 prevent use of old shared wrappers.
- Before implementation 17 scenarios were missing (a non-existent resolver/syntax); another regression test caught a stale User ID. After the fix the whole suite: **862 tests, 1676 assertions**, 10.126 s, PHP 8.4.27 / MySQL 8.4. Lint and git diff --check passed. Independent review without specific findings.
- Next step after the push: SEC-03, unify automatic escaping of both renderers and document safe output contexts; leave changes of input helpers for the following separate package SEC-04.

### SEC-03: Follow-up implementation procedure

- Set automatic HTML escaping also in the Smarty adapter and add setAutoEscape to the same adapter interface as NativeRenderer. Keep an explicit false as a temporary migration option.
- Keep explicit raw and escape in the bundled templates. The historical equivalence corpus compares explicitly the legacy mode of both renderers, not the new safe defaults.
- Fix the native pipeline: output safety is determined by the last modifier, not by any earlier safe modifier. For example escape|default:$fallback must escape the new fallback. Change the compiler version and tie fragment keys to this version.
- Document the trustworthiness of templates and the differences between HTML text, attribute, URL and script/JSON contexts. Do not change the input helpers yet (SEC-04).
- Tests: an XSS payload through the default Smarty output, true/false through the adapters, no double escaping with an explicit escape, raw only explicitly, an unsafe fallback after a safe modifier in native.

- SEC-03, clarification from regression tests: the parser must track only the modifiers of the resulting expression. An escaped operand, a method argument or an array index must not mark other content as safe. The corresponding XSS scenarios failed before the fix.
- SEC-03, decision: the Smarty compile ID contains the autoEscape mode, because otherwise its memory cache, after switching true/false, may reuse an old template object. The original user compile ID remains part of the resulting ID.
- SEC-03, decision: the tests caught an old compileCheck problem, where the same size and timestamp did not reveal a source change. An SHA-256 signature was added with compileCheck enabled; a production deployment with compileCheck=false does not compute the checksum. The cost is an additional source read in development mode, the benefit is reliable refresh of templates and security fixes.

- SEC-03, review: no new findings in the diff so far. The reviewer additionally reproduced an older fragment collision between autoEscape=false and true. Included in this security package: both scopes confirmed it with a RED test, the key now also contains the escaping mode. Manual cache cleanup is thus not the only protection against taking over raw HTML from the migration mode.

### 2026-10-25: SEC-03 completed

- Done: automatic escaping of both renderers, an explicit migration autoEscape=false, a fix of the modifiers of the final expression in the native parser and preservation of raw/escape for explicitly determined contexts.
- The initial 8 XSS/default scenarios failed before the fix; another 3 scenarios confirmed the transfer of incorrect trust from an operand, a method argument and an array index. Fixed without extending the grammar of template tags.
- A separate RED test confirmed stale compilation at the same timestamp and length. The SHA-256 freshness is now computed only with compileCheck=true; the signature at compile time comes from the same source snapshot.
- Review of the diff so far without new findings. An older collision between raw and escaped fragments was additionally fixed according to 2 RED tests; the escaping mode and compiler version are part of the key of public and personal fragments.
- Final verification: **878 tests / 1703 assertions**, 9.859 s, PHP 8.4.27 / MySQL 8.4; composer lint and git diff --check passed.
- The documentation describes safe HTML text, quoted attributes, validation of URL schemes, script-safe JSON and template trustworthiness. Smarty is still a temporary adapter; removal from the runtime dependencies belongs to TPL-03.
- CACHE-01 is saved and pushed as `f998303`. This following checkpoint includes the SEC-03 code and the plan state; the exact hash is in the git log of the working branch.
- Next concrete step: SEC-04, inventory of calls of Http::Post/Get/getCookie/secureClean, introduction of unchanged inputs and explicit validation, regression tests of apostrophes, slashes, arrays and missing values. Do not change the authentication model or routing at the same time. The SEC-01 content invalidation follows the new write API in a later stage.

### SEC-04: Procedure and contract of the input helpers

Base checkpoint `4ae723f`, local and remote branch identical. Working branch `codex/wbengine-4.0`, responsibility Codex.

- Inventory: the internal secureClean is used only by Post/Get. Box\Auth uses Post for CSRF and CAPTCHA; sign-in already uses PostRaw. No other runtime callers were found in the library or in the sources of the sample application. API JSON input is not changed in this package.
- Post($name=null) and Get($name=null) return unchanged the value from the PHP superglobal, and with null the whole corresponding input map; a missing key returns null without a warning. Valid names are string and int, including 0 and an empty string. Any other type of name returns null.
- PostString($name), GetString($name) and getCookie(string $name) return only an unchanged string or null, never converting an array/object/scalar to text. PostRaw remains a compatible alias of PostString.
- Decision: raw means values after standard HTTP processing in PHP, not the original wire bytes. No trimming, HTML entities or adding/removing slashes. Even invalid UTF-8 bytes remain unchanged; text fields must validate type, UTF-8, length and meaning in their application layer. The string helper solves only the shape of the value, not its trustworthiness.
- Decision for major 4.0: remove secureClean, do not silently return raw values under its security-misleading name. Explicitly describe the migration of direct external calls in the README and CHANGELOG. Do not automatically decode old already polluted DB values.
- Box\Auth converts the token and CAPTCHA to PostString. A non-string CSRF token is rejected before hash_equals. SQL stays parameterized in Db, output is escaped by the renderer (SEC-03 done).
- Procedure: first RED tests of apostrophes, slashes, HTML, empty/zero values, nested arrays, missing keys, invalid UTF-8 and malformed tokens. Then a minimal change of helpers and callers; an integration pass raw -> parameterized SQL -> escaped HTML in both adapters.
- Acceptance: no change of values in the DB, no double escaping in the result, invalid shapes of protocol inputs without a TypeError. Targeted tests, the full suite, lint and independent review; then commit+push of the code and the plan before the next larger change.

- SEC-04 review: no runtime findings. The reviewer pointed out that the original CAPTCHA test with an empty secret does not prove rejection before the network. Decision: sharpen the required evidence, do not defer it as a cosmetic change. The network code stays the same, only extracted into a protected verifyCaptcha in the same Box\Auth. A mocked verifier with a configured test secret must have zero calls for malformed input in both a normal and an AJAX POST; valid strings are passed unchanged. No real CAPTCHA requests are sent during tests.

### 2026-10-25: SEC-04 completed

- Post/Get preserve the original PHP-parsed values and, when the name is omitted, return the whole map. PostString/GetString, the compatible PostRaw and getCookie accept only strings. Missing values return null without warnings. secureClean is removed in major 4.0.
- Box\Auth rejects invalid shapes of the CSRF and CAPTCHA fields. The existing network verification of CAPTCHA is extracted into a protected method in the same class; no further production service or dependency was created. Architecture and namespaces are preserved.
- TDD: the original 42 targeted tests showed 14 errors and 7 failures. Two integration passes through the parameterized DB and both renderers failed before the change on changed values. The new CAPTCHA boundary tests failed before implementation due to a missing verifier; after adding it they prove rejection before the network and exact passing of valid strings in a normal and an AJAX request.
- Final verification: `docker compose -p wbengine-modernization run --rm php composer test`, **933 tests / 1876 assertions**, 10.049 s, 32 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` and `git diff --check` passed. Independent review without runtime findings; the note about the weaker CAPTCHA test was incorporated.
- The README and CHANGELOG describe the major change, explicit validation, SQL parameterization and output escaping. Historical data are not automatically rewritten. API JSON and the authentication model do not change in this package.
- The checkpoint includes code, tests and this plan; the exact hash is in the history of the working branch. Push and upstream match are verified before the following phase.
- Next concrete step: SEC-05, inventory of IP/HTTPS detection and all cookie flags, design of a lazy service in the existing tree architecture and tests of explicitly trusted proxy IPv4/IPv6. The default configuration will trust only the direct connection.

### SEC-05: Implementation procedure and decisions

- Base checkpoint d1e319a, isolated worktree /private/tmp/wbengine-modernization. The existing Utils use REMOTE_ADDR and an unsafe header fallback when the peer is missing. SessionAbstract and Api detect HTTPS separately. API refresh issuance and deletion both use common cookie options.
- Introduce Application\Env\RequestContext as a lazy descendant of Application with getParent. For the existing standalone API and the static Http/Utils the same service remains available without an Application parent. The resolver has no cache of user data, reads the current server/config at use, so it does not change the bootstrap or session initialization.
- Decision: explicit http.trustedProxies (IP/CIDR IPv4/IPv6) and http.forwardedHeaders (only x-forwarded-for, x-forwarded-proto); both lists empty by default. A wrong configuration raises an exception, it does not widen trust. Do not use the old IPv4-only ipInRange for a security boundary.
- XFF: verify the direct peer, walk the chain from the right through the allowed proxies and stop at the first untrusted client. A missing/invalid peer returns an empty IP, never a fallback to Client-IP or another header. An invalid walked hop or an over-limit header uses the direct peer. Canonicalize IPv6 output, no ports, zone IDs or obfuscated addresses.
- XFP: support only a single http/https value from the allowed direct peer and only with explicit enabling of this header. The proxy must overwrite it according to the real original scheme, not append client text. Lists and alternative headers are ignored. A valid http may override backend TLS. Without a trusted value use only local HTTPS on/1. Keep the explicit API override of Secure.
- Procedure: RED tests of spoofing, a missing peer, chains, IPv6/CIDR boundaries and configuration; minimal implementation; an integration test of real session/API cookie flags; targeted and full tests, lint and independent review. Then update the plan, commit and push.
- Source of the rules for chains and scheme: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Forwarded-For and https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Forwarded-Proto. No automatic loading of Cloudflare/AWS ranges or universal trust of all proxies.

- SEC-05 review: an independent read-only review without security/regression findings. Two minor gaps in evidence were filled: an invalid peer for XFP and a change of both metadata and configuration in a long-lived static facade.
- Diagnostics of the full suite: 988 tests, one older flaky failure testARequestWithoutACookieCostsNoSessionQuery. Session::_load triggers cleanup with a probability of 1/100 before the lookup. An isolated diagnostic process with forced GC proved exactly one DELETE FROM cms_sessions WHERE session_expire < ?, without a SELECT. The same process with setAutoClean(false) proved zero queries. Decision: in the lookup test turn off GC with the existing setter; production cleanup does not change. The temporary diagnostic file was in the ignored .phpunit.cache and was removed after verification.

- The final global inventory also found I18n::_remember and I18n::origin with separate HTTPS detection. Included in SEC-05: isSecure accepts an optional server snapshot, so that the existing explicit interface I18n::boot/origin is preserved. First two RED failures of cookie flags, after wiring the cookie another two RED failures of the origin scheme. The test deliberately sets the opposite ambient HTTPS to prove use of the passed request. Hreflang page-cache keys already derive from I18n::origin, so they will distinguish the original scheme; a host from forwarded headers is not accepted.

### 2026-10-25: SEC-05 completed

- Done: lazy Application::getRequestContext, parent link and a common resolver for Http/Utils, session, API and I18n. No further packages, DB queries or session start on the mere evaluation of the connection. The cache contains only the validated deployment policy, not request data.
- By default no trust in forwarded headers. Explicit proxy IP/CIDR and headers, IPv4/IPv6, peer validation, XFF from the right, IPv6 canonicalization, length/hop limits, rejection of invalid configuration. XFP only a single value from a trusted direct peer; untrusted and alternative headers are ignored. The API Secure override remains.
- TDD: the initial 51-test suite showed 26 failures and a missing getRequestContext. Two integration RED scenarios proved incorrect Secure behind TLS termination. Further RED scenarios confirmed own I18n cookie and origin bugs; all were subsequently fixed and verified. The session integration also verifies the actually stored IP in the DB; the API integration both issuance and deletion of the refresh cookie.
- Final verification: `docker compose -p wbengine-modernization run --rm php composer test`, **993 tests / 1993 assertions**, 10.061 s, 32 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` and `git diff --check` passed. Independent review and the follow-up review of the I18n change without findings.
- The documentation contains the deployment configuration, the requirement to overwrite XFP and correctly build XFF on the proxy, separation of address families and the impact of an IP change on sessions/rate limit. Clean the page cache on deployment; absolute locale links now respect the trusted original scheme. No changes of real production proxies or deployment were made.
- The checkpoint includes code, tests, documentation and the plan state. The exact hash is in the git history; commit and push before the next larger step.
- Next concrete step: SEC-06, inventory of forms and operations of authenticated cookies; a lazy CSRF service tied to the session in the Application tree, protection of forms/AJAX and removal of logout via GET. Keep public GET without a session write and prevent sharing tokens through the cache.

### SEC-06: Procedure and protection contract

- Base checkpoint 4025119, branch codex/wbengine-4.0, isolated worktree /private/tmp/wbengine-modernization. The only supplied POST form is Box\Auth. The existing token is overwritten on every render, the AJAX branch is not restricted by method and GET a=logout changes state. Custom project forms must use the protection explicitly.
- Session\Csrf will be a lazy descendant of Session (getParent); Application::getCsrf only delegates through the existing session. Without explicit use of the CSRF service a public GET does not change. The token is an HMAC-SHA256 of the purpose from a random 32-byte session secret; the purpose has 1 to 128 bytes, the token is 64 hex characters. A single secret instead of an unbounded map of forms. Tokens of the same purpose are stable across multiple panels, a foreign session/purpose is rejected. Verification never creates the secret. On session ID regeneration after sign-in, invalidate the secret.
- The token/verify/validateRequest service supports the field token or the header X-CSRF-Token. validateRequest accepts only POST/PUT/PATCH/DELETE, never a query token. A present invalid header must not fall back to a valid POST. Invalid token shapes are not cast.
- Token responses explicitly private,no-store and PageCache bypass. The new request-wide fragment bypass must stop HIT and storing of both public and user fragments, including wrappers that issue the token during render. Obtain the token before entering a cached render; Smarty's own output cache is not part of the supported cache mechanism.
- Box\Auth moves to the purpose auth.login; the existing name=token remains for template/AJAX compatibility. Reject GET logout with 405 without changing the session. POST a=logout requires an auth.logout token, an error is 403; add a public getLogoutBox with a supplied POST template. Verify CSRF before CAPTCHA and authentication, AJAX only POST.
- Decision for cookie API login/refresh/logout: POST mandatory, application/json and X-WBengine-CSRF: 1. This is not a synchronizer token, but enforcement of a non-simple request and the preflight/CORS boundary. Allow this header in the existing CORS configuration; the operator may allow only trusted origins for credentialed requests. Refresh stays without a PHP session. Other API mutations with an explicit verified bearer token do not take over session CSRF. Source: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html.
- Acceptance: RED/GREEN purposes, a foreign session, types, methods, multiple panels, repeated issuance without a write, a rejected GET logout without change of identity, a valid POST logout, login/AJAX before authentication, tokens outside both cache scopes and real HTTP no-store; the API rejects a simple request before changing the DB/cookie. Complete tests, lint, independent review; then commit and push of the code and the plan.

- SEC-06 TDD course: 25 new unit scenarios first failed on the missing service; after implementation 25/70 assertions passed. HTTP RED proved a token change between panels and GET logout with 302 instead of 405. After wiring the service 3 HTTP scenarios passed. API RED proved a missing preflight boundary; the new check verifies rejection of all three cookie operations without DB/cookie/session side effects.
- Lifecycle RED proved preservation of the token after ID regeneration; a reset was added to Session::regenerateId. Another RED proved that after destroy the secret stayed in the internal Session data within the same request; discardPendingWrites now removes it directly, without restoring the dirty state or writing the destroyed session.
- Independent review without specific security/regression findings, targeted unit verification by the reviewer 57/122 assertions. Supplementary HTTP scenarios cover AJAX GET 405, a valid AJAX login with X-CSRF-Token and a foreign session. The CORS policy is operational trust in origins; this phase does not introduce browser end-to-end automation.

### 2026-10-25: SEC-06 completed

- Session\Csrf is a lazy descendant of Session with getParent; Application delegates to the same instance. Stable purpose tokens, verification without creating the secret, the field token and X-CSRF-Token, only unsafe methods for validateRequest. The root secret is invalidated on ID regeneration and on destruction of the session, including the current request.
- The supplied login and AJAX use auth.login, logout auth.logout. GET logout and AJAX GET return 405, invalid tokens 403 before CAPTCHA/login. Added getLogoutBox and an escaped POST template with a Czech/English label. The legacy template name=token remains, the historical session token is no longer accepted.
- Private,no-store and a request-wide page cache bypass of both native fragment scopes; wrappers that issued a token during render are not stored. Call the helper before a cached render. Custom applications must protect their own cookie forms explicitly and exclude personalized form routes from the earlier page cache in the bootstrap.
- Cookie API login/refresh/logout require POST, application/json (charset allowed) and X-WBengine-CSRF: 1. HTTP 405/415/403 before the DB, token rotation or cookie change. The header is allowed in the existing CORS configuration, credentialed origins are an explicit operational trust boundary. Refresh stays without a PHP session. Bearer-authorized mutations keep using role/token, without session CSRF.
- Final verification: `docker compose -p wbengine-modernization run --rm php composer test`, **1025 tests / 2164 assertions**, 11.005 s, 32 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` and `git diff --check` passed. Independent review of the whole phase and of the supplementary lifecycle change without findings.
- HTTP tests: multiple panels, a foreign session, invalid/valid AJAX, GET and POST logout with different purposes, no-store even with a repeated token, a public GET as a cache HIT without a session cookie and DB write. The integration test verifies invalidation/persistence after ID rotation and the impossibility of restoring a destroyed session through flush. The unit test documents rejection of shapes and exclusion of already cached user/public fragments and of the new token wrapper.
- The README and CHANGELOG contain the interface, use in a template/AJAX, the cache and the upgrade of API clients. This major change is neither deployed nor released. The checkpoint contains code and plan; the hash is in the history and the push is verified before the next phase.
- Next concrete step: PERF-01, the diagnostic dbinfo in Site::initialize/getDBVersion only in debug or on an explicit request. First measure SQL queries and opening of DB connections for a warmed page with the data cache, then remove only the unnecessary diagnostic access. PERF-02 menu N+1 and PERF-06 single JSON decoding follow according to the plan dependencies.

### PERF-01: Procedure and diagnostics contract

- Base checkpoint dd352b8, branch codex/wbengine-4.0, worktree /private/tmp/wbengine-modernization. Local HEAD and upstream identical. Site::initialize always assigns server, dbinfo and phpversion; SiteModel::getDBVersion through Mysqli::getDbVersion opens a real connection without an SQL query. Db::isConnected means only a created adapter, not a confirmed socket.
- Decision: automatically assign the existing diagnostic variables only according to Application::isDebugOn. For explicit use add Site::assignDiagnostics returning Site, with the original variable names. It stays in the existing tree Site -> model -> Db, without a new service, configuration or package. A missing SERVER_SOFTWARE has a safe empty result.
- Production initialization no longer prepares the diagnostic keys; templates that need them must ask explicitly. Diagnostics may open a DB connection and are not for public pages. Do not change routing/lifecycle or cache identity in this package.
- Tests: warm the lookup of a real SiteModel into the file cache, run initialize in a new PHP process with a separate adapter and without menu/section content, measure the difference of SQL statistics and mysqlnd connect_success. Compare production without diagnostics, debug, an explicit request and missing server metadata; verify preservation of site/metadata. This measurement isolates the diagnostic cost, it does not promise zero connections for arbitrary content or custom boxes.
- RED must prove one connection with zero SQL queries in the original production initialization. After the fix production 0/0, debug/explicit request one connection and the same diagnostic data. Then the complete suite, lint, independent review, recording of results, commit and push of the code and the plan.

### 2026-10-25: PERF-01 completed

- Site::initialize automatically assigns server/dbinfo/phpversion only in debug. The explicit Site::assignDiagnostics preserves the original names, the model path and the assignment to Vars and returns Site. No new runtime class or dependency. SERVER_SOFTWARE returns an empty text when the value is missing.
- RED: of four integration scenarios three failed, the production warm lookup opened one connection with zero SQL; the explicit method was missing and missing server metadata raised a warning. After the fix four scenarios / 36 assertions passed. New PHP processes do not inherit the adapter or socket from PHPUnit, mysqlnd connect_success verifies the real connection separately from SQL statistics.
- Result of the isolated initialization with a warm site-data cache: production 0 connections / 0 SQL, debug and explicit diagnostics 1 connection / 0 SQL; the site resource and meta title preserved. Menu, section content and custom boxes are excluded from the measurement, so this result is not presented as a universal connection count of a complete site or as a latency measurement.
- Final verification: `docker compose -p wbengine-modernization run --rm php composer test`, **1029 tests / 2200 assertions**, 11.207 s, 32 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` and `git diff --check` passed. Independent read-only review without findings.
- The README and CHANGELOG describe the change of default template variables in major 4.0 and the explicit use of diagnostics. Code and this plan form one checkpoint; the exact hash is in the git history. Push and upstream match are verified before the next phase.
- Next concrete step: PERF-02, removal of N+1 menu lookups. Before the change verify the selection of parents, hidden pages, duplicate URLs, strict pages and DB collation; share a once-evaluated set of active IDs without changing the intended navigation. With the cache off use a batch lookup. PERF-06 and further stages remain open.

### PERF-02: Procedure and decision on semantics

- Base checkpoint 2047280, branch codex/wbengine-4.0, isolated worktree /private/tmp/wbengine-modernization; HEAD and upstream identical. Site::isMenuSelected re-evaluates all URL prefixes through SQL getSiteIdByUrl for every non-current item. Menu::selected has its own direct comparison of the current page, which is not changed in this package.
- Decision: request memoization of the resulting prefix -> ID pairs and the set of existing active IDs in Site. A single method SiteModel::getSiteIdsByUrls performs all index lookups in one parameterized SQL statement, the output aligned with the original order including repeated/missing prefixes. An empty/invalid collection of prefixes without SQL and without a foreach warning. The current positive site ID stays active without a lookup.
- Ruling: use the batch SQL lookup also with the cache on. The mere sites:visible map omits hidden parents and its PHP lowercase comparison does not reproduce the database collation (accent/case/binary rules). Full DB semantics without a connection would require a new canonical URL contract or another collation index. The cost of this conservative decision: when parents are needed, at most one SQL statement even with a warm cache, not a promise of zero SQL. The benefit: a query count independent of the number of menu items and URL depth, without an unbounded persistent cache for random URLs.
- Ruling: duplicate DB links deterministically select the lowest site_id, instead of the existing LIMIT 1 without ORDER BY; the single-item helper uses the same rule. Visibility and strict mode are still not filtered for selecting a prefix parent. Site ID 0 and negative values are not active items, a missing lookup returns 0 only in public prefix pairs.
- Keep the SQL comparison link = ? directly over the column, without PHP imitating collation and without a JOIN between two implicitly different collations. An SQL UNION ALL of scalar lookups returns positions and the minimum ID; parameters stay data. Tree Site -> SiteModel -> Db, without another production subsystem.
- Tests first RED: many menu items and several prefixes with and without the cache, hidden and strict parents, duplicates, a non-existent prefix, case/accent sensitivity and binary collation, apostrophe/injection input, repetition and change of prefixes, empty URL and ID 0. Acceptance: 0 queries for the current ID alone, at most 1 for the whole other selection over the same prefixes; real match with the DB lookup. Then the whole suite, lint, independent review, plan+commit+push.

- The inventory of the real Url::_setUrlPairs additionally revealed prefixes without a trailing slash (/blog), whereas the standard DB link is /blog/. The original helper therefore does not mark such a parent at all. Ruling: Site preserves the original URL in the returned pairs, first looks for the exact prefix and only on a miss the variant with one trailing slash. The SiteModel batch remains an exact-URL contract; Site passes both variants in one statement. Exact links without a slash take precedence, so existing sites stored this way keep working. The test must include the real Url parser, not just manually constructed prefixes with a slash.

### 2026-10-25: PERF-02 completed

- Site stores the resulting URL/ID pairs and the set of active positive IDs for the current list of prefixes. A change of prefixes and the initialize snapshot invalidate. The current ID without SQL; other selections share one batch. SiteModel::getSiteIdsByUrls preserves order, repetition, miss=0, parameterization and real collation; the single-URL helper uses the same path.
- Intentional major changes: duplicates deterministically the lowest site_id; the prefix first exact and only on a miss with a trailing slash; zero/negative IDs do not mark an active item. Hidden and strict parents are still allowed. Menu::selected remains a direct selection of the current page.
- The ruling stands: even with a warm cache at most one statement, because sites:visible is not equivalent to the old SQL. We do not promise a zero warm lookup from this phase; no unbounded persistent cache key by URL. The DB index lookups are still proportional to prefixes, but network round trips are not proportional to menu items. It is not a latency benchmark of the whole site.
- RED: 7 scenarios, 2 errors due to the missing batch API, 4 failures and a foreach warning. Repeated menu checks + the public getter originally performed 244 SQL statements with the cache off and with a warm cache. Now in both cases **1 statement**, the same expected selections, the real parser/slash case fixed. Targeted tests 7 / 156 assertions passed.
- The collation test on a real DB alternates utf8mb4_unicode_ci and utf8mb4_bin, verifies case and accent matches and restores the original schema in finally. Further scenarios: hidden/strict, exact bare link versus slash, lowest ID of duplicates, apostrophe/injection, empty prefixes/ID 0, change of the list. Independent review without findings.
- Final verification: `docker compose -p wbengine-modernization run --rm php composer test`, **1036 tests / 2356 assertions**, 11.198 s, 32 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` and `git diff --check` passed. The OOP tree Site -> model -> Db preserved; no new runtime subsystem or dependency.
- The README, CHANGELOG and ref-inventory describe the behavior and the cost of one query with the cache. This checkpoint includes code, tests and the plan; the exact hash is in the git history. Push and upstream match are verified before the next stage.
- Next concrete step according to the order of stage 3: PERF-06, single JSON decoding. First describe the existing Json(true/false) for a valid null, scalars, objects/arrays, empty and invalid body, UTF-8 and depth; then remove the duplicate json_decode without an unintended change of the controller contract. PERF-07 and ARCH-01 follow, the other items remain open in the register.

### PERF-06: Procedure and JSON input contract

- Base checkpoint dbda94e, branch codex/wbengine-4.0, worktree /private/tmp/wbengine-modernization; HEAD and upstream identical. Http::Json reads php://input, validates through isJson (json_decode) and, with decode === true, decodes again associatively. The API auth/user/section/translation routes expect this contract.
- Decision: one json_decode per Json call, evaluate the error immediately from the same parse; ordinary decoded input directly associatively, the compatible NUL/object path is described below. No change of signature or controllers, no exception, payload cache or new runtime object. isJson remains a separately callable validator with the original behavior.
- Keep strict decode === true: only boolean true returns the decoded PHP array/scalar/null; false and other so far supported non-true values return the original valid text including whitespace. Invalid JSON returns null in both modes. A valid null has JSON_ERROR_NONE, an error another json_last_error; the code must read the state immediately before the next JSON call. Keep the default depth 512, UTF-8 validation and PHP's numeric policy.
- Tests through a real HTTP POST and php://input. The fixture counts a namespaced json_decode that always delegates to the native PHP parser; the count is reset for each request. It is not a production injection interface or a timing benchmark. First RED for a valid decoded payload, which originally parses 2x; other scenarios characterize compatibility. raw and isJson continue to validate with one parse.
- Scenarios: null/bool/numbers/strings/arrays/objects, whitespace and an empty body, bad syntax/UTF-8/depth, repeated calls over the same body and strict mode. After the minimal change the full suite, lint, independent review, update of the plan, commit and push.

- Review P2 reproduced a difference between object and associative decode for a key beginning with an escaped NUL: the original validation returned JSON_ERROR_INVALID_PROPERTY_NAME, a plain assoc decode accepted it. Regression tests first confirmed two errors; the output contract is therefore preserved, not extended in a local optimization.
- Ruling: raw mode parses like the original object validation. Decoded mode for ordinary text uses a direct associative decode; only with a possible sequence \u0000 does it use one object decode and a recursive conversion of the result to an associative array. The NUL-leading key error thus remains native error 9. Cost: a fast substring scan of ordinary raw text, and for rare NUL values an additional PHP conversion of objects/arrays. The number of JSON parser passes is still exactly 1; no new exceptions and no additional runtime dependency.

### 2026-10-25: PERF-06 completed

- Http::Json performs exactly one json_decode and evaluates the error of the same parse. Only boolean true returns a decoded array/scalar/null; raw and the legacy non-true mode return the original valid text. isJson remains a separate validator. The interfaces of API controllers, the default depth 512, UTF-8/numeric rules and the json_last_error contract are unchanged.
- Review P2 fixed: JSON with a NUL-leading key is still rejected with the native JSON_ERROR_INVALID_PROPERTY_NAME. For an ordinary decoded body a direct assoc parse; a conservatively detected NUL escape leads to one object parse and a subsequent conversion. NUL values, numeric keys, escaped backslash text and a valid depth 510 have regression coverage. The cost of compatibility is a substring scan and a rare PHP conversion, not a second parser pass.
- TDD: the original 23 HTTP scenarios showed 15 failures, always 2 parses instead of 1 for a valid decoded body and 4 instead of 2 on two calls. After the minimal change 23/188 assertions passed. The review reproduction added two RED failures of NUL keys; after the fix the extended 28/228 assertions passed, then a deep valid NUL case was added. The final HTTP corpus has 29 scenarios and measures the real php://input through a namespaced wrapper of the native parser only in the separate fixture.
- Final verification: `docker compose -p wbengine-modernization run --rm php composer test`, **1065 tests / 2592 assertions**, 12.173 s, 34 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` and `git diff --check` passed. The independent review found the compatibility bug above, reproduced and fixed by RED/GREEN tests; no further findings. The reviewer did not run the HTTP tests itself because of its socket sandbox, the final verification was done here in Docker.
- The README and CHANGELOG describe return values, immediate reading of the error state, a valid null and legacy NUL keys. It is a local reduction of the number of parser passes, not a latency/memory benchmark of the whole site. The existing Http facade and namespaces preserved, no new runtime subsystem or package.
- Code, tests and the plan belong to the same checkpoint; the hash is in the git history. Push and upstream match are verified before continuing.
- Next concrete step: PERF-07, Renderer::dispatch and the global whitespace merging. First a regression corpus of pre/textarea/inline JS/CSS and meaningful text spaces, then a safe default output and migration rules for the existing minimizeHtml. Leave transfer compression to the web server. After this stage ARCH-01, the deferred lifecycle, according to the approved dependencies.

### PERF-07: Procedure and HTML output contract

- Base checkpoint ef28474, branch codex/wbengine-4.0, worktree /private/tmp/wbengine-modernization; HEAD and upstream identical. With minimizeHtml=true, Renderer::dispatch performs preg_replace on the whole fetch result, otherwise display. The regex changes pre/textarea, JS line comments and whitespace in string literals/attributes.
- Decision: dispatch always assigns the application variables and outputs the site template through the existing display adapter without global modification of the content. Keep the tree Application -> Renderer -> adapter and the existing escaping. No HTML parser, dependency or alternative unsafe legacy mode.
- minimizeHtml remains a compatible readable configuration item/getter for external code, but in the core dispatch it is ignored from major 4.0; mark the getter deprecated in the documentation without runtime warnings. minimizeCss/minimizeJs do not change in this phase. Transfer compression is configured by the web server/reverse proxy, not by the PHP framework runtime.
- Tests first RED through a real Renderer::dispatch with both NativeRenderer and SmartyRenderer and with both values of the flag; byte-exact output of the passed trusted content and escaping of ordinary variables. Pre, textarea, JS line comment and multiline string, CSS string, meaningful inline spaces/attributes and script JSON. A missing minimizeHtml must not raise a warning.
- Acceptance: both adapters display the same unchanged emitted content with true/false/missing config. Do not introduce production compression or deployment changes. The documentation explicitly requires, on upgrade, clearing the earlier HTML page cache, which may have contained corrupted output. Then the full suite, lint, independent review, plan+commit+push.

### 2026-10-25: PERF-07 completed

- Renderer::dispatch always assigns the application variables and outputs the site template through the existing adapter display. The site-wide preg_replace was removed. The OOP tree and output escaping preserved, no new runtime class/dependency or compression handler.
- minimizeHtml is ignored in the core dispatch even with true; the Config::minimizeHtml getter is kept and marked deprecated without runtime warnings. The change fits major 4.0. The cost of safe output: uncompressed HTML may be larger; gzip/Brotli belongs to the deployment configuration of the web server/proxy. No real server setting was changed in this phase.
- TDD: 30 scenarios for NativeRenderer and SmartyRenderer, true/false/missing configuration. RED showed 14 corrupted outputs and one PHP warning raised in two missing cases. GREEN **30 tests / 30 assertions**: byte-exact pre/textarea, JS line comment/template literal, CSS string, inline spaces/attributes and JSON script; an ordinary suffix is still escaped.
- Final verification: `docker compose -p wbengine-modernization run --rm php composer test`, **1095 tests / 2622 assertions**, 12.719 s, 34 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` and `git diff --check` passed. Independent review without findings; the reviewer also separately ran the targeted 30/30 unit verification.
- README/CHANGELOG/ref-inventory updated. On deployment clear the page/external HTML cache, because a previously stored corrupted body cannot be repaired by merely changing dispatch. Template compilation and minimizeCss/minimizeJs stay out of scope.
- This checkpoint includes the implementation, tests and the plan; the hash is in the git history. Push and upstream match are verified before the next phase.
- Next concrete step: ARCH-01, inventory of constructor/get/run/Router and the i18n/page-cache order. Work out a concrete lifecycle in the existing Application tree: configuration of services and registration of custom routes before CMS/boxes, a custom terminated route without CMS queries, the page cache only after routes and correct policies. First regression tests of order and compatibility, do not move only Site::initialize without defined request control.

### ARCH-01: Concrete lifecycle and migration contract

- Base checkpoint c80907d, branch codex/wbengine-4.0, worktree /private/tmp/wbengine-modernization; HEAD and upstream identical. Today the constructor prepares the configuration and i18n, but may also exit with a redirect/cache HIT, initializes Site and runs boxes. Application::get executes the callback immediately and only then writes the matched route. display calls a non-existent init; run may render repeatedly. The static Router performs the match immediately and API boxes use it too.
- The constructor will be only preparation of configuration, namespaces/path, error handler/timezone and early i18n normalization + bootstrap locale variable. A redirect is stored, not sent. No CMS queries, boxes, page-cache HIT or ordinary rendering. The Site/Vars getters may create empty lazy objects without loading data.
- Application::get will register a deferred GET through a new Application::route(method,path,callable), both returning Application for chaining. A route supports ordinary GET/POST/PUT/PATCH/DELETE/HEAD/OPTIONS and delegates matching to the existing Router/Route. An invalid pattern type or callback is rejected at registration. The static Router stays immediate because of existing API modules inside boxes.
- Ruling: the callback is called in run with (route,app). Boolean true means a finished response (the callback already set the headers/printed the body), without CMS and layout. Boolean false continues to the next route and possibly CMS. Other returns preserve the HTML callback model: set variables/central and render the layout. The first accepted route wins. The cost of the new explicit terminal contract: custom API callbacks must return true or exit as before; echo with null alone would lead to the layout.
- run: protection against duplicate/reentrant handling -> deferred custom routes -> unhandled i18n canonical redirect -> cache gate -> possible CMS initialization -> 404/410 -> debug -> a single dispatch. A matched custom route and an explicit central bypass the shared page cache. A 410 response must not be overwritten by an old 200 HIT or 404.
- The cache gate compares Vars with the bootstrap snapshot, so pre-run/earlier declined route changes made directly through Vars also prevent a HIT. Early access to the renderer (before the cache gate) conservatively bypasses the page cache for its direct assign/config interventions. Cost: a request adapted this way loses the page HIT until the application decides on a safe separate cache policy.
- Unify display(content) with the lifecycle, inside a route allow an explicit single render without recursion. An empty string and "0" are explicit content. central set before run must not be overwritten by CMS content. The debug object only after handling CMS/route, not in the constructor.
- The early i18n boot normalizes the prefix even with a pending default-language redirect; a custom route thus sees the canonical path and may handle the request before the redirect. Without custom handling the 301 with the original query string stays. On 404 the Site initialization without a resource must end before sections; empty sections must be a valid empty collection, not sizeof(null).
- Tests: a real constructor + no SQL/socket/boxes; a deferred callback, priority/decline/true terminal and generic POST; pre-run Vars/renderer setting and a CMS box observing the value; repeated run/display without a second render; explicit 0/empty content; the original API box; 404/410; an i18n route before the redirect; an existing page-cache HIT preempted by a route or private policy and a preserved anonymous HIT. Verify HTTP and internal states, the full suite/lint/review; then plan+commit+push.

- TDD first HTTP corpus after correcting the test rewrite input: 15 scenarios, 14 RED failures. The constructor originally did 4 SQL / 1 connection / 1 box, in the new lifecycle 0/0/0. The original display called a non-existent init and run displayed the layout multiple times. First GREEN almost complete: "0" masked the default modifier only in the test template, removed from the fixture, the production template grammar did not change.
- Added a safe cache gate for earlier output and status. RED proved taking over an old HIT with a declined handler with 403. Also verified the standard MISS/HIT, a private policy, pre-run Vars/direct renderer assign and the legacy API router inside a real CMS box.
- Independent review: P2 for a lower output buffer with an empty upper buffer and a second render when display is called from a CMS box. Both findings were included in the fixes, because the guarantee of a single response and respecting output applies to the whole lifecycle. Two HTTP RED tests reproduced the bugs. Fix: check buffer_used of all layers of ob_get_status(true) and after CMS initialization verify requestFinished again. Without storing/reading the contents of the buffers and without another request subsystem.

### 2026-10-25: ARCH-01 completed

- The constructor prepares configuration, path/namespace, error handler/timezone and early i18n. It does not run CMS, boxes or a page-cache HIT and does not terminate with a redirect. I18n normalizes also the canonical default prefix before the stored redirect. Creating a lazy Site/Vars is without loading the resource.
- Deferred Application::get/route (ordinary HTTP methods, validating registration and chaining). In run the first accepted route: true terminates handling without a layout, false continues, other returns prepare the layout with variables. The static Router stays immediate and a real legacy API box works in the test. The new terminal contract is a major migration rule, not a change of an existing API controller.
- run/display idempotent, including a direct display from a callback and a defensive reentrant display from a box. An explicit "0" and empty content do not trigger CMS. 404 does not pull shared boxes; empty sections return []; an explicit 410 preserves the status and skips CMS/cache. Debug is created at handling, not in the constructor.
- The cache gate follows custom routes and their policy. Changed Vars versus the bootstrap, a renderer used before the gate, accepted routes, earlier output in any buffer/sent headers and a non-standard HTTP status conservatively switch off the shared cache. The common PageCache bypass also affects public native fragments. Cost: requests adapted this way may have fewer HITs; an ordinary anonymous page MISS/HIT still works. Put globally cacheable settings into Settings, handle dynamic personalization explicitly.
- HTTP measurement: the constructor originally 4 SQL / 1 connection / 1 box, now 0/0/0. A deferred terminal GET and POST likewise 0/0/0, the callback does not run at registration. 18 targeted scenarios / 55 assertions passed: locale/pending redirect, route priority and decline, pre-run Vars/renderer/central, an old HIT versus route/private/status/output, a single render, 404/410, a legacy API box and empty sections.
- Review: a confirmed P2 nested-buffer cache conflict and a reentrant double dispatch from a box, both reproduced by two HTTP RED tests and fixed in one pass. No further findings. The reviewer did not run the shared DB tests; all runtime checks were done here in Docker.
- Final verification: `docker compose -p wbengine-modernization run --rm php composer test`, **1113 tests / 2677 assertions**, 12.953 s, 34 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` and `git diff --check` passed. The existing Application/Site/Section/Box/Renderer/Router namespaces and lazy services preserved, no additional runtime package or middleware stack.
- The README contains the new lifecycle, callback return values, mandatory run/display, conservative cache behavior and migration of API bootstrap before CMS while keeping the box variant. CHANGELOG and inventory updated. On deployment clear the old page caches. This checkpoint contains code/tests/plan; the exact hash in the git history, verify the push before the next phase.
- Next concrete step according to stage 3: PERF-03, the duration of the PHP session lock. Measure parallel requests of one session, inventory regenerateId/destroy/CSRF/shutdown flush and access to the native session state. Propose an explicit safe release/read-only phase without premature closing, lost writes or breaking sign-in. Do not start a refactor without regressions and measurement. The other register items including the SEC-01 content invalidation remain open.


### 2026-10-25: PERF-03 completed

- Inventory: WBengine data are not stored in `$_SESSION`; its own model writes deferred through the shutdown `flush`. The native PHP session holds a synchronization lock. `session_write_close()` alone would release the lock before the WBengine write. Login regenerates the ID and resets CSRF, logout deletes the DB row and further persistent writes.
- Decision: explicit `Session::close(): void`, `isClosed(): bool` and a lazy delegation `Application::closeSession(): void`. No automatic closing after the first read and no implicit reopening. The new state stays in the existing Session tree and namespace, DB operations use the current Model/Db. Closing the application without a session does not create it.
- Close procedure: verify the match of the active native ID, load the DB snapshot while still under the lock, store the deferred changes, close the object for persistent changes and call the native close. A repeated close has no effect. An INSERT/UPDATE failure keeps the dirty state and the lock, raises a SessionException with the cause; a retry stores the changes. A failure of the lazy load does not mark the snapshot as loaded. Closing an already destroyed session does not create a row.
- After close: getValue and CSRF verify work with the snapshot; an existing secret may derive a token without a write even for another purpose. Creating a missing secret, a setter including user_id, a magic setter, save, unsetKey, regenerateId, destroy and sessionStart raise a SessionException. Object references may change the local snapshot, but flush of a closed object stores nothing. The snapshot is not a new authorization check after a concurrent logout. Complete sign-in, authorization and state changes before close.
- Measurement: PHP 8.4.27 / MySQL 8.4, native files handler, two separate CLI PHP processes with the same cookie; the first request has 250 ms of simulated work after the close point. Five waits of the second process with the lock held: 246.028, 246.789, 246.779, 247.315, 248.017 ms, median 246.789 ms. After an early close: 1.074, 1.210, 1.007, 1.061, 1.168 ms, median 1.074 ms. This is not site throughput or a promise for other handlers. Repeatable procedure: `docker compose -p wbengine-modernization run --rm php php tests/benchmarks/session-lock.php`; it uses only the test configuration, its own session ID and a temporary directory, and signs out its own rows.
- The concurrency test through proc_open and IPC verifies the real blocking of the second process before close, passing after close while the first process is still running, preservation of the changes of both writers, ignoring local changes of the closed snapshot and no restoration of the row after the second request's logout and the later shutdown of the first. It is not a sequential simulation of requests or a single-threaded HTTP server.
- TDD: 12 initial RED cases for the missing close/closeSession; then green integration scenarios of persistence, reading an untouched session after close, changes of rights/ID rotation and CSRF, a ban on later mutations, a no-op without a session, no INSERT for an unused/destroyed session and rejection of closing after a manual native unlock. Failure coverage includes INSERT and UPDATE, exception and false and a subsequent retry.
- Independent read-only review without blocking findings. The contract of CSRF derivation and locally mutable references clarified; UPDATE/false failure scenarios added. No new runtime package, interface or automatic change of the lifecycle of ordinary sites.
- Limitation: the serialization guarantee requires a locking handler. Native handler errors have PHP semantics; once the native close starts, the object stays closed even on an exception. No global closing before render, a delayed login/CSRF write would stop working.
- Final verification: `docker compose -p wbengine-modernization run --rm php composer test`, **1132 tests / 2765 assertions**, 14.275 s, 34 MB, PHP 8.4.27 / MySQL 8.4. `composer lint`, `git diff --check` and the stored benchmark passed. Repeated measurement with the stored script: medians 249.531 ms / 1.265 ms. A commit and push of this stage together with the plan and CI verification in draft PR #2 follow.
- Next concrete step: assess PERF-04/PERF-05 by measuring a growing number of pages and EXPLAIN over realistic boxes in MySQL/MariaDB. Preserve collation, order and shared semantics, without a demonstrated benefit do not add indexes/maps. Then TPL-01: inventory of Smarty constructs, a compatibility corpus and a migration contract for the own renderer. The SEC-01 content invalidation waits for the write API.


### 2026-10-25: PERF-04 measured, PERF-05 completed

- Responsibility: Codex. The test tool `tests/benchmarks/content-lookup.php` uses `SHOW CREATE TABLE` and connection-local TEMPORARY tables shadowing cms_sites/cms_boxes only in the given connection. No permanent DDL or deletion of data. Temporary data and its own file cache are removed in finally. The first attempt CREATE TEMPORARY ... LIKE with the same name was rejected by MySQL as a duplicate alias; rewriting the loaded CREATE TABLE preserves the real schema and indexes without this problem.
- Procedure: PHP 8.4.27, MySQL 8.4.13 and MariaDB 11.4.13; 100/1000/10000 visible pages, one lookup per Site instance, 15 repetitions and the median. SQL with the existing link index; a memory check without serialization and a real FileCache, cold and warm. Lookup results for the base corpus must be identical. The map build is measured separately, not presented as an implemented URL map or as an APCu result.
- PERF-04, MySQL for 100/1000/10000 pages: SQL 0.2120/0.0612/0.1468 ms, warm memory scan 0.0113/0.0962/0.9076 ms, warm FileCache 0.2320/1.8920/20.0181 ms, the map build alone 0.0073/0.0848/0.7137 ms. MariaDB for 10000: SQL 0.1380 ms, memory 0.8522 ms, FileCache 19.7163 ms, map build 0.7115 ms. Cold FileCache for 10000: MySQL 22.1060 ms and MariaDB 20.2981 ms. This is not a cold-disk, end-to-end or production benchmark.
- Ruling: do not add a URL map now. A single lookup per request still costs O(n) to build or to transfer/decode the whole cache; the indexed SQL was cheaper in the larger sample. A persistent shared map and its invalidation are worth re-evaluating only after FIX-02 and a measurement of memory/serialization. The cost of a possibly wrong deferral: the linear scan of the existing cache remains on large sites; no change of the runtime API is made.
- FIX-02: the benchmark proved existing mismatches between SiteModel::_findVisibleSite and SQL. With a stored /café/ and unicode_ci SQL finds /cafe/, the cache does not; with bin the cache finds /CAFÉ/, SQL does not. This phase does not change the URL comparison. Before the URL map and release 4.0, define and implement a matching DB/cache contract for case, diacritics, Unicode, padding, strict/prefix, hidden, duplicates and the priority of the highest site_id. Verify MySQL/MariaDB and at least unicode_ci/bin, zero cache growth for random URLs, invalidation and real SQL/connection costs. Do not assume that mb_strtolower reproduces an arbitrary collation.
- PERF-05 dataset: 30000 boxes, 100 sections of 300, 20 percent shared in each section, a third of site membership 42, the rest 99; 140 results for section 1/site 42. Compared: no secondary index, (section_id, order) and (section_id, shared, order), the original and the shared section condition. Each variant must return the same rows/order. EXPLAIN is stored in the benchmark output. Without an index an estimate of about 30000 rows/ALL; with both candidates 300/ref or range.
- MySQL median original/no-index 5.3782 ms, shared/no-index 5.1404 ms, original/section_order 0.4216 ms, shared/section_order 0.3839 ms, shared/section_shared_order 0.3949 ms. The original query on MySQL sorts even with section_order; the shared condition with section_order eliminates the filesort. The candidate with shared keeps the filesort.
- MariaDB median original/no-index 6.6089 ms, shared/no-index 4.4199 ms, original/section_order 0.3543 ms, shared/section_order 0.3637 ms, shared/section_shared_order 0.4070 ms. With section_order, here even the original condition has no filesort. Do not present small differences as a universal speedup, the main effect is restricting the section rows.
- Writes measured separately: 5 batches of 1000 INSERT, cleanup outside the measured interval. MySQL medians without index/section_order/section_shared_order 2.4174/2.9838/3.0217 ms, MariaDB 2.1693/2.7339/2.8832 ms. The index is not free. A warmed box FileCache and the memory driver have 0 SQL in 15 lookups, a cold cache reads the DB. The sample is not proof of benefit for small tables or an arbitrary shared ratio.
- Implementation: Section\Model::getBoxes has a shared b.section_id and a parameterized JSON_SEARCH/shared OR; SELECT, cache keys and the tree unchanged. The optional migration `migrations/4.0.0/001-boxes-section-order.sql` adds (section_id, order). Without runtime DDL, new packages or a change of the JSON schema. Installation procedure: SHOW INDEX, do not add an equivalent index, adjust the prefix, use the ordinary migration procedure and EXPLAIN; rollback DROP INDEX wb_boxes_section_order only for an index added by this migration. Do not introduce a box/site relational link yet without a demonstrated need.
- TDD: integration RED first the missing migration, then a filesort on the real MySQL query even with the index. After the SQL change GREEN, results preserved before/after the migration and after the rollback. The independent review pointed out cleaning of permanent test tables by the inherited DbTestCase and measuring INSERT including DELETE; the test now inherits directly TestCase and verifies only the connection, the INSERT timing does not include cleanup. The benchmark and the new test manipulate only temporary tables.
- Final verification: the complete MySQL suite `composer test`, **1133 tests / 2771 assertions**, 14.188 s, 34 MB. MariaDB 11.4 BoxIndexTest/BoxLookupTest **7 tests / 16 assertions**. Both benchmarks passed. `composer lint` and `git diff --check` passed. A common commit/push and CI of draft PR #2 follow.
- Repeating on MySQL: `docker compose -p wbengine-modernization run --rm php php tests/benchmarks/content-lookup.php`. Run MariaDB in a separate Compose project with DB_IMAGE=mariadb:11.4 and the same PHP image, after the measurement stop only its DB. Do not swap the image of a running project with foreign data.
- Next concrete step: TPL-01, inventory of Smarty constructs in the core and the sample application, identification of compatible native constructs, a test corpus and a migration contract. FIX-02 must be resolved before returning to the URL map and before release. The SEC-01 content invalidation follows the write API; no merge/tag/deployment is performed.


### 2026-10-26: TPL-01 completed

- Responsibility: Codex. The reference inventory and migration contract are stored in `ref-template-migration.md` according to the prefix system; source identities in `tests/fixtures/template-migration/inventory.json`. The framework is based on b7cf3d4. The sample application src/View only read, clean commit d22281fc86e9efd63a333ac6668b9261f22777e9; no changes of this second repository.
- Library inventory: 7 `.tpl`, of which 4 renderer sources (Application/error, Article, Login, Logout) and 3 sprintf exception templates. Sample application: 13 sources, NativeRenderer already set. All renderer sources compile natively; this is not proof of rendering the whole site. The examples additionally use native set/t, foreach, object methods and i18n/formatting modifiers.
- The read-only tool `tests/tools/template-inventory.php [application-template-dir]` prints JSON with SHA-256, consumer, compile state and heuristic reviewHints. It only calls the existing Compiler::compile, never executes a source or creates compiled files, does not start the DB/session. It ignores symlinks and vendor/node_modules/.git/Compiled/tests. A successful compilation is only compiled-only; unknown Smarty tags may be text. The hints are not a lexer, runtime validation or a compatibility guarantee.
- A new versioned corpus: 17 named scenarios / 31 concrete native/Smarty outputs, explicit expected outputs instead of mere equality of adapters. Another 5 scenarios render directly the real framework templates with automatic escaping, both CAPTCHA branches and preservation of the trusted article HTML. The integration concurrency with API/session/cache of the existing suite preserved.
- Confirmed differences: the native default replaces even zero, Smarty preserves it; truncate native counts the length before the suffix, Smarty including the suffix; native nl2br emits escaped input and an HTML break, Smarty with autoescape on also escapes the inserted break. Include and assignment require a syntax conversion. The tag t/set is native-only, the direct Smarty API and plugins require an application migration. Changes of runtime modifiers are not part of TPL-01.
- Decision: do not repeat the promise of full Smarty compatibility, keep a small compiled language and the existing Application/Renderer/Adapter/Template tree. The README no longer claims that arbitrary legacy pages or the current skeleton are automatically identical after merely switching the adapter. Templates are the developer's trusted code, because they may call methods of assigned objects; Markdown will be standalone content without this logic.
- The migration contract also captures the no-op config/cache API and the ignored cache_id/compile_id/nocache in NativeRenderer, rejection of delimiters, clearing of all caches on deployment and rollback of compatible sources/config. Returning to Smarty is not enough by changing the adapter for native-only set/t. Composer and production renderers unchanged.
- TDD: RED missing inventory tool and RED missing corpus; GREEN after delivering the implementation/fixtures. Calibration of the expected outputs revealed Smarty autoescape for nl2br, recorded as a conscious difference, not a hidden modification of the production render. The isolated suite **37 tests / 87 assertions**. The whole MySQL suite **1170 tests / 2858 assertions**, 14.367 s, 34 MB, PHP 8.4.27 / MySQL 8.4. Smarty locally 5.8.5. `composer lint` and `git diff --check` passed.
- Independent read-only review without important findings or blockers; the read-only nature of the tool, the independence of CI from an external checkout and the match of the documentation with the adapters were verified. A common commit/push of this stage including the plan and CI of draft PR #2 follow. The reference document contains exact commands for repeating the inventory and the corpus tests; CI does not need a neighboring checkout.
- Next concrete step: TPL-02, first diagnostics of known unsupported Smarty control tags in the existing Compiler (including closing tags, path/line), preserve CSS/JS/literal. Change the current assign-as-text characterization test to an expected exception. Then a migration corpus of include/set/modifiers and an HTTP smoke of the sample site (login/POST logout, locale, 404, articles, fragments/page cache); take into account its old GET logout link and the debug-only diagnostic variables. Remove the Smarty package only in TPL-03 after these gates. FIX-02 and SEC-01 remain open release conditions.


### 2026-10-26: TPL-02A, compiler diagnostics completed

- Scope of the checkpoint: the first part of TPL-02, not completion of the whole migration or removal of Smarty. Base commit 4719bb0. Responsibility Codex. The register keeps TPL-02 in progress, the next step is given below.
- Implementation in the existing Wbengine\Template\Compiler: known unsupported Smarty syntax is returned as a token and processed in the ordinary pass. Before the exception the preceding text is counted, so the TemplateException states the real path and line including CRLF, indentation and native control flow. Without a new preprocessor, dependency, framework or change of the Application/Renderer tree.
- Concrete forms: assign/append, extends/block, capture, section/sectionelse, config_load, eval/fetch/insert, php/include_php, strip/nocache, ldelim/rdelim, call/forelse, Smarty headers function/for/while and closing forms. Finite rules and limitations are in ref-template-migration.md. Function recognizes named attributes even with a different order and long spaces; for requires a Smarty $ variable; while does not recognize parenthesized headers, so as not to collide with JavaScript. It is not full detection of Smarty or plugins.
- Preserved ordinary CSS rules, JS object properties with a colon, ordinary declarations and loops including $ready, similarly named identifiers and unknown plugins. Literal and comments protect literal examples. Exactly reserved notations such as JS shorthand {capture} must be inside literal; the compiler does not parse the HTML/JS context.
- Compiler::VERSION raised 3 -> 4. The test inserts a real old compiled file under the version-3 identity and proves that with compileCheck=false it cannot bypass the new diagnostics. Version-dependent fragment identities are separated; on deployment keep clearing the compiled/page/fragment/external HTML cache according to the migration contract.
- The TPL-01 corpus intentionally updated: instead of assign-as-text an exception is expected. It still has 17 scenarios / 31 expected results, now 30 outputs and one diagnostic. The inventory test verifies that assign has a failed compile and a concrete error message. The repeated read-only inventory of the library and 13 external templates matches the stored snapshot; the sample checkout was not changed.
- TDD: initial 33 RED (32 unsupported headers/closings and long spaces), another RED old compiled file, then RED order of function attributes and while true. All GREEN after the changes. Tested are path/line, name boundary and preservation of CSS/JS/literal, not just searching text in the source.
- Review found a collision of valid JS function run() {while ($ready) ...}. A dedicated RED regression test confirmed it, the while detection was narrowed and ordinary JS preserved. Ruling: do not forbid parenthesized while headers, because the same bytes may be valid JavaScript; the cost of the decision is that a corresponding Smarty while(...) may remain text and requires an inventory/manual conversion. The exact collision {capture} remains reserved syntax with an explicit literal solution. A second review was not repeated, the fix is covered by regressions and the final suite.
- Final verification: `docker compose -p wbengine-modernization run --rm php composer test`, **1215 tests / 2975 assertions**, 14.580 s, 34 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` and `git diff --check` passed; the repeated inventory matches the original snapshot. TPL-02A is prepared as a separate versioned checkpoint; a commit/push with this state and verification of CI of draft PR #2 follow.
- Next concrete step: TPL-02B, close the tested contract of include/set and modifiers for application migration, including invalid/missing partials, passing and isolation of data, paths and buffer recovery. Then TPL-02C: a separate isolated checkout of the sample application, conversion of the old GET logout link and diagnostic variables, HTTP smoke of menu/login/CSRF/POST logout/404/articles/locale/fragments/page HIT/BYPASS. Remove Smarty only after these gates in TPL-03. FIX-02/SEC-01 and other release tasks remain open.


### 2026-10-26: TPL-02B completed, an ongoing separate stage

- Verified the contract of root/dynamic include paths, inheritance of local set values, isolation of partials and of the next fetch. The existing suite covers syntax errors, non-existent files, traversal/symlink, recursion and modifiers. The meanings of modifiers from the migration corpus do not change, a full Smarty emulation is not added.
- RED tests proved two real problems of ordinary methods opening their own buffer: a successful render lost the prefix and a nested partial exception left an open buffer. Fixed by the common Engine::capture in the original Template tree; the procedure is also used inside cache callbacks. Render depth and output buffers are restored without leaking partial HTML.
- The independent review caught the same problem in the generated cache callback, where the original ob_get_clean remained. A dedicated RED cold/warm test proved the missing prefix and then GREEN verifies the whole fragment, a single invocation of the service and the original buffer level. Compiler::VERSION 5 invalidates old callbacks. FragmentCache cleanup has protection against an infinite loop with an unremovable buffer.
- Limitation: the service is trusted code; it must not cancel renderer buffers or create unremovable buffers. PHP cannot fully recover such side effects. The changes keep to the existing Engine/Compiler/FragmentCache namespaces and without packages.
- Verification: explicit TemplateEngineTest **170 tests / 265 assertions**. The integration working suite with the concurrently completed FIX-02 **1222 tests / 3142 assertions**, 14.385 s, 36 MB. The full set of so far versioned tests plus the new SiteLookupCollationTest was used, so as not to load in-progress tests of independent Markdown/API workers. A separate commit/push of TPL-02B and FIX-02 follows, then CI of the exact remote state.
- Continuous work according to the user's new instruction: between phases we no longer wait for further approval. For the independent MD-01 and API-01/04 separately owned modules and subagent-driven-development are used. The root ensures review, the common plan and versioning; working files of other stages are not mixed into this commit.
- Next step: TPL-02C, an isolated checkout of the sample application, adjusting the lifecycle and POST logout/layout diagnostics, HTTP smoke in a separate Docker project. FIX-02 is recorded separately in the immediately following checkpoint. Smarty is removed only after verifying TPL-02C.


### 2026-10-26: FIX-02 completed

- Responsibility Codex + a separate worker, independent read-only review without blocking findings. Removed the PHP mb_strtolower comparison over the whole sites:visible table. SiteModel uses the original parameterized indexed SQL resolver without dependence on the cache driver. No cache for random URLs and no memory transfer of the whole table is created.
- Ruling: the correctness of exact DB comparison takes precedence over the earlier promise of 0 SQL on a warmed data cache. Page selection now costs one query/connection, returns fresh rows and respects the concrete collation including padding and strict=0/1 values; a successful whole-page HIT and a terminal route may still skip CMS. The cost of the change is one real SQL lookup even with a warmed data cache. The translation/menu/box caches remain and need their own invalidation.
- TDD RED on MySQL proved a missing unicode_ci accent match and an incorrect bin case match; GREEN covers unicode_ci/bin, composed/decomposed Unicode, trailing spaces, strict/prefix/null/-1/2, hidden, the highest ID of duplicates, quote/injection and missing. MySQL 8.4 and MariaDB 11.4 **14 tests / 179 assertions**, always exactly one SQL lookup for each driver variant. The cache test no longer requires a false zero, it verifies immediate change of title/visibility and zero URL cache files.
- The diagnostic test adjusted substantively: the necessary page selection has 1 SQL/1 connection, debug or explicit server-version access adds no further SQL. Diagnostic variables are still missing in an ordinary production render. The benchmark and README updated; historical times of the old full-table cache remain explicitly historical.
- Current benchmark MySQL 8.4.13: SQL medians for 100/1000/10000 pages 0.2309/0.1297/0.0873 ms. An enabled memory or file driver now also measures SQL, not the old cache path. Synthetic map building outside the table fetch 0.0089/0.0696/0.7497 ms. No promise of production latency and no new URL map.
- The common verified suite of so far versioned tests and the new SiteLookupCollationTest: **1222 tests / 3142 assertions**, 14.385 s, 36 MB, PHP 8.4.27/MySQL 8.4; TPL-02B already separately committed and pushed as 06818d7. Lint and git diff --check passed. The work of the other Markdown/API workers is not part of this checkpoint. A separate commit/push of FIX-02 with this plan and CI of the exact commit follow.
- Next step: TPL-02C continues and in parallel MD-01/API-01/04 according to the assigned files. The SEC-01 content invalidation remains open, the future URL map PERF-04 deferred without need. Between checkpoints we no longer wait for a user message.


### 2026-10-26: MD-01 completed

- The own Wbengine\Markdown facade composes the separate Parser/InlineParser/readonly Node/HtmlRenderer and a shared Budget, without another runtime package. It is a content tree in the same namespaces; no executed template/PHP logic from Markdown. Direct sources and the App/DB link continue in MD-02.
- CommonMark 0.31.2: all 652 official examples have an exact syntactic test through a test-only AST projection and the exact production safe output. 76 explicit results in safe-deviations.json: 72 escaped HTML and 4 forbidden URL schemes. 12 official GFM examples of tables/task/strikethrough also pass exactly. Sources/authors/licenses/SHA are in sources.json, the public contract in ref-markdown.md. This is not a claim of conformance for all possible strings.
- Limits: 1 MiB input, parser 64/renderer 128 depth, 8M work, 100k node allocations, 8 MiB expanded HTML output. Exceeding raises a LengthException. HTML is always escaped, URL http/https/mailto/relative, without an unsafe switch. Counters are reset between documents.
- The independent review brought concrete cases outside the official corpus. RED/GREEN: escaped less-than in an angle destination, a disallowed parenthesis in a title, an escaped closing label, 999 Unicode characters versus bytes, invalid UTF-8 without a PHP deprecation and amplification of a short reference source into a huge output. The output limit stops it; ordinary 1000 links/code spans are not rejected by an overly conservative suffix accounting. Further regressions for the depth of image alt/inline/blocks, the node count and adversarial delimiter reduction.
- Isolated Docker verification PHP 8.4.27 **695 tests / 1826 assertions**. A supplementary deterministic malformed-input smoke of 1000 inputs without PHP warnings. The integration suite of all so far versioned tests + the Markdown files **1917 tests / 4968 assertions**, 14.607 s, 46 MB; in-progress API/MD-02 tests were deliberately separated. composer lint and git diff --check passed. The previous checkpoint TPL-02B/FIX-02 has all 5 CI green.
- The review fixes verified by regression tests and the common suite; another review round is not a condition of waiting. A separate commit/push of MD-01 with this plan and CI of the exact remote commit follow. Texy/Smarty dependencies are not changed in this checkpoint.
- Next step: the MD-02 worker implements the lazy App service, .md source dispatch in Engine, explicit format of articles/translations. Note: the real old Article getter today does not call the Texy formatter, it may already contain HTML. Preserve existing bytes as legacy_html, require the source for MD-03 explicitly (HTML/Texy), do not guess the syntax. The root continues in a separate checkout of the sample application and the API foundation, after review fixing transaction ownership.


### 2026-10-26: MD-02 completed

- Wiring of the lazy App->Renderer->Adapter->NativeRenderer->Engine resolver, without a parser on an ordinary HTML render. `.md` as the requested or canonical extension, case-insensitive, always as content without PHP/variables/template logic; roots checked before dispatch and reading limited to 1 MiB. Also allowed inside a standard HTML layout. NativeRenderer without App has its own safe lazy fallback.
- The optional 003 migration adds the legacy_html/markdown format. The Article getter really only passed DB HTML before, so old rows are not reinterpreted as Texy. Preserved bytes/unchanged old schema without probe queries; the format is explicit. Translations pass the per-field format, so a fallback does not mix HTML and Markdown. The comment of Article.tpl corrected according to the real behavior.
- The independent review found a concurrency bug of reading/validating the format before UPDATE. A deterministic fork/two-connection RED proved it under the FOR UPDATE lock; a conditional UPDATE guards the original format, a stale change and an initial-INSERT collision are rejected, a legitimate no-op allowed. It does not change the caller's transaction boundaries. Changing an existing format requires both text fields (replace/or null fallback).
- The isolated first 770-test verification of sources/parser/content/regressions **770/2064**; the final focused DB regression after the concurrency fix **65 tests/221 assertions**. The common suite of so far versioned tests + MD02 + the completed API foundation and UrlRequest **1966 tests/5178 assertions**, 14.825 s, 46 MB, PHP 8.4.27/MySQL 8.4. composer lint and git diff --check passed. The API/URL files will have following separate checkpoints, they are not mixed into the MD02 commit. MD01 has all 5 CI green.
- The new ref-markdown.md section describes formats, null/empty, limits and the migration procedure. No new dependencies, runtime DDL or rewriting of application data. The MD03 content migration is already arising in separately owned new files, with backups/dry-run/rollback and an explicit HTML or Texy source. The MD02 commit/push and CI follow; the root keeps going with app TPL02C and the API foundation.


### 2026-10-26: API-01 and the base of API-04 completed

- The current Api controller registers Wbengine\Api\V2 and its lazy Routes/Model/Contract/Problem, Model inherits ModelAbstract. Minimal public GET discovery without DB, HTTPS identity with the discovery:read scope. Unified JSON errors/pagination and object-body helpers. The capability flags CRUD/preview/publish are false, they do not pretend finished features. V1 preserved; V2 server-to-server without CORS, OPTIONS 405.
- The local trusted Model provision/revoke checks the current DB ADMIN role, returns the raw random key only once, DB hash SHA256 and a final scope whitelist. Every request reads the active/revoked state; the audit distinguishes the integration ID, the local admin ID and a bounded untrusted actor label without influence on authorization. Optional 002 SQL without runtime DDL, without a public provisioning endpoint.
- The independent review, without an auth bypass, found transaction ownership. RED for provision/revoke x BEGIN/autocommit(false) proved an unwanted commit of the caller's data; a SET TRANSACTION READ WRITE guard rejects an active txn before begin and its rollback stays with the caller. MySQL errno 1568 is mapped to 409 nested_transaction, other errors are not swallowed. A regression of revocation audit failure confirms rollback of the revocation and the key remaining valid.
- API focused Docker **22 tests/126 assertions**, PHP 8.4.27/MySQL 8.4, the new integration fixture only TEMP tables. The common verified suite including MD02 and UrlRequest **1966/5178**, 14.825 s 46 MB; these further changes have their own commits. composer lint and git diff --check passed, after a small addition of ModelAbstract inheritance all 22/126 green again. The HTTP smoke of the sample application confirms v2 discovery and v1 unauth 401. Ref-api-v2.md documents installation/contracts/limitations.
- API-04 remains in progress for rate limiting, audit retention and content invalidation. API-02/03 follow: the content tree API, ETag, revisions/publish, safe images and connecting the central administration. Arbitrary remotely executed PHP methods/template editors are not implemented. The next separate checkpoint is UrlRequest/TPL02C and the app repo HTTP migration.


### 2026-10-26: TPL-02C, a fix of Url for direct/FPM requests

- The HTTP smoke in the isolated sample application found the cause of the login failure: without the Apache rewrite parameter link, Url::_link was null, trim printed a PHP deprecation before the session cookie/redirect. Root Url now uses the real REQUEST_URI path if the string rewrite alias is missing. The old link=front is preserved, an array/null does not create a string-conversion warning. The extension is appended only as a string.
- TDD: 3 RED (missing/null/array alias) and 1 preserved legacy behavior; after the fix **4 tests/8 assertions GREEN**. The smoke router does not have to simulate any Apache manipulation; a real GET/POST login already passes. Another HTTP test then independently proved the missing POST logout in the layout and it is being fixed in the app repository.
- The common current suite **1966 tests/5178 assertions**, 14.825 s, 46 MB; API+MD02 already have their own checkpoints fb479e0/a3fff96. The sample application in a separate checkout now **20 HTTP checks GREEN**, including v2 discovery/v1 unauth 401. Changes of App\Application/Site and the layout are versioned separately, not mixed into the library. The root Url fix is now committed/pushed separately with the plan.
- Next step: finish the app CI/reference migration plan, record its commit in this tracker and mark TPL02C. Then TPL03, moving Smarty to an optional compatibility package and verifying a clean core without its installation. Work on MD03 and the central administration continues with separate owners.


### 2026-10-26: TPL-02C and TPL-03 completed

- The sample site has its own branch and draft PR rrsoftcz/wbengine-skeleton-app#1, commit 35d2d54, CI green. The native layout, deferred lifecycle, sign-in/POST logout/CSRF, anonymous and signed-in user, locale, article, 404, fragments/page HIT and API verified by 20 HTTP checks. A fresh installation without Smarty after cache clearing passes the same checks.
- The Smarty adapter moved to the optional Composer package compat/smarty, the original FQCN and escaping preserved. Neither the core nor its tests install Smarty. A missing adapter reports an installation recommendation. The historical expectation in JSON was left as a migration reference, the executed core corpus verifies native results.
- The compatible profile has a separate vendor outside the checkout and a mirror of the core: a symlink to the local core collided with its classmap exclude and could create a recursive path. Regression fixed by a separate installation, tests use the real roots of the installed core. The optional CI job runs separately from the base PHP/DB matrix. The package is not yet published in the Composer registry, the local path repository is described in the README.
- The optimized autoload revealed a historically incorrect path of Config\Exception\RuntimeException. Moved to the PSR-4 corresponding file, the old file kept as a require_once shim; a test verifies the public namespace. composer dump-autoload --optimize --strict-psr passed without warnings.
- The parent verified the base suite without Smarty **1888 tests/5101 assertions**, 14.354 s 44 MB PHP 8.4.27/MySQL 8.4. Separate compatibility **86/123**, 0.114 s 20 MB. An independent static review without blockers, runtime found installation problems fixed and verified by the whole compatible suite. Syntax and diff-check are verified before the commit.
- Next step: the MD-03 checkpoint of the migration tool, then removal of Texy from the core MD-04. API content and the central administration are versioned separately. No production conversion, merge or tag release.


### 2026-10-26: MD-03 completed

- Content\Migration in the existing OOP namespace separates Converter/HtmlConverter/Model/Migrator/Cli. A thin CLI entry does not create a parallel runtime. Explicit html/texy, the original HTML bytes are not guessed. A safe conservative DOM converter and a render comparison mark unsupported or lossy constructs for manual handling. The optional Texy bridge stays outside the core.
- The default dry-run includes translations, the overview contains original/resulting values, source/converter/scope hashes and a per-record approval hash. Apply accepts only an exactly approved safe record, locks the row, atomically stores the ledger 004 and the new content. A concurrency/rollback conflict is rejected. No production data were changed.
- Review findings verified RED/GREEN: permissive MySQL truncation detected by the real post-write hash of the content and the backup and the whole transaction rolls back; multiple code children do not damage the meaning; the report has limits of 8 MiB aggregation/16 MiB CLI/256 locales.
- The parent verified again **38 tests/138 assertions**, PHP 8.4.27/MySQL 8.4, only TEMP tables. Lint of the previous common working suite passed; migration/rollback commands and protection of sensitive reports are in ref-markdown.md. TPL03 already separately pushed 80208da, its own compatibility 86/123 green.
- Next step MD-04: remove the mandatory Texy package and the Formatter dependency, optionally keep an explicit compatible adapter; keep the new Markdown format and the old legacy_html without reinterpretation. API and administration follow separately.


### 2026-10-26: MD-04 completed

- The core Composer now requires only the JWT package. Own templates and Markdown have no external parser. Removed the non-functional old Texy adapter referring to APP_DIR/Vendor. Formater preserves the factory/adapter tree and the process input, the new default Markdown adapter lazily calls the safe Markdown service. An explicit texy runtime ends with a concrete migration recommendation, not an automatic reinterpretation of the source.
- Major migration impact: external direct users of getFormater()->process must first convert Texy sources. The Article getter/legacy_html and the original DB bytes do not change in this stage. The new documentation and the optional package state the real Composer path procedure before the package is published.
- RED proved the mandatory Texy dependency, the missing Markdown adapter and the old APP_DIR error. GREEN after the fix. Core Markdown/sources/profile **714 tests/1866 assertions**, 0.186 s 28 MB. Optional offline bridge **1 test/5 assertions** in an isolated vendor with Texy. No skipped Texy test in the base profile, moved to the package with its own CI. An independent code review without blockers, the found outdated README fixed.
- A fresh skeleton vendor has only JWT+WBengine, without Smarty and Texy, after cache clearing **20 HTTP checks GREEN**. No changes or conversion of production data took place. A separate commit/push of this phase and the final common suite follow, as soon as the API lifecycle returns the exclusive DB window.
- Next step: the API02/03/04 checkpoint after the early-v2 dispatch test and the DX01/02 checkpoint including the fixed installed CLI. ADM04/05 continue in the separate private repository, foundation 533f0e7 and CI green.


### 2026-10-26: API-02/03/04 and SEC-01 completed

- Api/V2 ContentModel/Resources/BoxRegistry/ImageModel in the existing namespaces and model patterns. Scoped CRUD and finite fields, strong If-Match, draft/revisions/publish/preview/translations, structural changes/restore even after deletion. Writes lock/verify references, audit atomically and flush the common cache after commit. Optional SQL 005 without runtime DDL.
- Media public PNG/JPEG/WebP <=512 KiB under an opaque ID outside the document root, staging/metadata/hash/MIME/nosniff, capability false without explicit durable storage. The box registry empty by default, the client never passes a PHP method. The section namespace is validated even on rename/restore.
- Review blockers reproduced RED/GREEN: changing legacy HTML requires Markdown and both texts, views do not invalidate the edit ETag, a section rename cannot change the namespace outside the registry; the recovery token now covers parents/translations/the last event and protects against concurrency. The lower-priority raster check is not a full pixel decoder, the limitation is explicitly described.
- An additional lifecycle gate: apiV2.enabled===true switches on the application's Api service before CMS/cache/locale redirect after custom routes. Moving/deleting the API page does not disconnect the connector. TDD real HTTP 5 new scenarios, discovery 0 SQL/0 connections/0 box/without session and without CMS, exact prefix/query and opt-out, HTTPS 403/anon 401. Focused 36/256 green.
- API focused 58/331 green, the parent the whole current suite **1981 tests/5596 assertions**, 15.250 s 48 MB PHP 8.4.27/MySQL 8.4. Content fixes frozen, ref-api-v2.md added with the contract and limitations. A separate commit/push of the API and the plan follow.
- Next step: the DX01/02 checkpoint, the real CMS benchmark matrix DX03 and the ADM04/05 pilot. The administration foundation is the private rrsoftcz/wbengine-admin PR 1, 533f0e7, CI green. No release/tag/deploy.


### 2026-10-26: DX-01 and DX-02 completed

- Console Application/BoxGenerator/Doctor/MigrationStatus/Bootstrap wired only to the explicit CLI, no overhead of an ordinary request. Scaffold default preview, finite identifiers, symlink/no-overwrite checks, atomic create/rollback of its own files. SQL only a review proposal with an unpublished page and an unfilled page_link; it applies nothing by itself. The test really runs the generated Box through the original parent tree.
- Doctor config:check without DB, doctor/migration:status only read-only schema, explicit optional API migrations, production Composer/autoload/OPcache/cache/proxy/HTTPS/secrets and write path. Secret values and DB exceptions are not printed. The operational check runs as the CLI user, the web SAPI/user must be verified separately.
- Review RED/GREEN fixes: the CLI originally worked only in a checkout, now it supports a real Composer proxy and a direct installed package path, never searching for an autoload in the CWD. The MD03 CLI also fixed with the same Bootstrap. Composer now registers both bin entries. The Doctor default file cache path + driver case matches the Cache runtime, a healthy default does not report a fail.
- Worker focused 19/57 GREEN, the parent before the fixes 12/34 and then the whole common suite 1981/5596 green in Docker PHP 8.4.27. Lint/diff check passed. Review of the fixes without remaining code blockers, the bin manifest added by the parent. A separate commit/push of DX with the current plan follows.
- DX03 RequestMatrix is still a separate in-progress item, the real CMS benchmark is being finished by the isolated skeleton checkout. Synthetic 300/0 errors is not a claim of CMS auth performance. ADM04/05 UI and server tests done, a common real HTTPS PHP pilot is being prepared.


### 2026-10-26: DX-03 completed, release CI blockers being resolved

- The real CMS benchmark in the skeleton repo d0b1e79: 15 cohorts x 20 requests, concurrency 4, 300/0 errors, all HTTP 200. Real CSRF sign-in/DB sessions and a concurrent same signed-in cookie. The root config and an ordinary server were not changed, the runner cleans only its own random cache roots.
- The parent independently repeated the same 300/0 errors. Public page warm has 20/20 HIT, 0 SQL/0 connections, p50/p95=1.527/1.746 ms, 1733 req/s. The original stored worker run 1.475/1.787 ms, 1753 req/s. Public off 6 SQL/data warm 1 SQL, both 1 connection. Private/custom/same cookie never shared HIT. Server peak 2 MiB, OPcache CLI 0, small fixture 14 sites 9 articles 20 boxes 2 users, PHP 8.4.27/MySQL 8.4.13. This is not a production guarantee, the throughput includes fork/client collection.
- Both really installed Composer bin proxies in the application passed help. Composer correctly sets the executable bit, stored also in the core.
- The new CI found two release blockers: the MariaDB rollback translation test returns a missing row and the CI canonical redirect HTTP fixture does not open a stream (green locally). The MariaDB finding was reproduced locally, the worker examines real atomicity and persistent InnoDB. The HTTP test now prints the server log on failure so that the cause can be determined. They are not marked as fixed, the release remains open.
- The common isolated HTTPS/PHP/admin pilot meanwhile passes login/MFA, content/draft/publish/conflict/recovery and permission deny; the parent review and verification are in progress.


### 2026-10-26: two CI fixture blockers fixed

- The CI server log determined the cause of the redirect failure: the header callback could, on a bodyless redirect, run after the Composer autoload was unregistered and tried to load Db for the first time. The test instrumentation now counts zero if Db was not loaded at all, without an autoload in shutdown. An added deterministic deferred-header/no-autoload test first RED, after the fix the whole lifecycle suite 24/178 GREEN. The production lifecycle does not change.
- The MariaDB 11.4 TEMPORARY InnoDB minimal reproduction loses the original row on DELETE+INSERT of the same PK and a subsequent rollback; persistent InnoDB correctly returns the same scenario. The publish rollback test now verifies the real persistent publish tables, with preservation of the original test tables under unique backups and a finally restore, guard DATABASE=wbengine_test. All original assertions strengthened with the whole article, the original two locales, the draft and an unchanged count of revisions/events and a zero flush. Production SQL unchanged. The worker verified the whole ApiV2ContentTest 23/129 GREEN on MySQL 8.4 and MariaDB 11.4.13.
- The final whole-branch review found two integration gaps: the API api_settings did not reach the executed Box and the CLI generator used a DB section key with an incompatible uppercase. A single common fix wave with regression unit and real Section/API integration tests is in progress. The release is still in progress, not a finished tag.


### 2026-10-26: final integration fix wave completed

- The independent whole-branch review found P1 runtime box settings and P2 generator DB key; a single common fixer fixed both. Section loads b.* in the original single query and row caches; the optional api_settings is available without a schema probe/DDL and without breaking an unmigrated schema. Box has getSettings/getSetting, a bounded JSON object of string enums, invalid input fails closed to the default. The cost is a broader internal row projection of future columns.
- The CLI separates the lowercase DB section key from the capitalized PHP namespace/path and accepts only a registry-compatible length. TDD 4 unit RED and a real API POST->SectionBox missing-setting RED, then PATCH full->compact verifies the real executed box, post-commit cache flush and lazy 1 SQL/old schema.
- Focused 45 tests/247 assertions MySQL and MariaDB green. The parent the whole current suite **1988 tests/5663 assertions**, 16.289 s 48 MB PHP 8.4.27/MySQL 8.4. Lint/diff check green. A single scoped re-review confirmed both original gaps removed, without further blockers in this scope.
- CI of the previous 6fc3de8 has all 7 jobs green, including PHP 8.3/8.4/8.5 MySQL and Maria 10.11/11.4, optional Smarty/Texy. A separate fix wave commit/push and verification of its CI follow. ADM04/05 checkpoint 357d410 and the parent 33 tests/check/build/Docker+actual HTTPS PHP workflow 1/1 green.
- Next step: REL01, two separate API installations, a limited user, revocation/outage, a migration/rollback playbook and the final reference. No production databases or the checkout master were modified.


### 2026-10-26: final operational check of the CLI layout

- While preparing the upgrade guide, the parent ran config:check on a real application with base=/app/src and Composer vendor=/app/vendor. This revealed a false composer.production fail: the check looked for the vendor only under src, although the real metadata had dev=false.
- A new RED regression uses the same project/src layout and real PHP Composer metadata. The Doctor now respects its own vendor of the explicit application base, otherwise the single direct parent with composer.json+vendor metadata. It does not search arbitrary parent directories or the CWD. GREEN 17/59 focused, the real app CLI production check is now ok. Development compileCheck/HTTP/OPcache/uncreated optimized autoload correctly remain fail, they are not masked.
- Prepared book-wbengine-4-upgrade.md: staging/inventory, backups and verification of recovery, optional SQL 001-005, explicit HTML/Texy migration, CSRF/deferred lifecycle, keys/MFA, cache, the gate and conflict rollback. It contains a procedure, not a claim of production execution. A common checkpoint of this small operational fix and guide follows.


### 2026-10-26: REL-01 technical gate verified

- All implementation stages done. PERF04 closed by a conscious decision not to introduce an unproven URL map; this is not a claim of implementing a map. The original framework master and production data unchanged, the working branches and PRs are checkpoints.
- The parent the whole latest suite **1989 tests/5666 assertions**, 16.079 s 48 MB PHP 8.4.27/MySQL 8.4, Composer audit without advisories and lint green. The latest remote production checkpoint c56f9a3 has all 7 CI jobs green. Skeleton real no-dev optimized strict PSR-4 autoload 177 classes without warnings + 20 HTTP green, without Smarty/Texy.
- Two really separated API installations and the central server in tests/pilot, a fixed test project without host ports, private generated CA/keys ignored. Provider 3/3 GREEN: verified TLS/render, cross-key 401, separated changes/ETag and rejection without a CA.
- The parent repeated the production Node 24 build and **34 admin tests**, check 0/0; the final real dual-web HTML pilot 1/1 GREEN (~14.05 s): MFA/content/conflicts/recovery, an editor limited to web 1, a web 2 outage while web 1 runs, preserved dirty text+ETag, an explicit retry without auto-write, revocation 401 without effect on web 1 and both public sites 200 after a SIGKILL of the central server.
- Acceptance revealed a bug of the subsequent load during an outage: it overrode the action fail and lost drafted texts. A narrow request-local fix first actual RED, after the fix GREEN; an independent scoped review confirmed the local requireSite before the fallback, no serialized keys/cause and fresh CSRF/role/scope/cap/ETag for the next POST. The admin fix has its own following checkpoint.
- The parent performed a real dump/restore: **17 tables/861 rows**, identical database defaults, table definitions and SHA256 of all rows. The helper restore DB and the private dump removed in finally, the source unchanged. MD03 original bytes/conflict rollback is verified by its own suite, the production migration is not simulated as done.
- Repeating in tests/pilot/ref-pilot.md, upgrade/recovery in book-wbengine-4-upgrade.md. Admin CI has a prepared separate common pilot job. Local path compatible packages explicitly use symlink=false, so that the core /compat/ classmap exclusion does not swallow their symlink.
- Checkpoint before release: the final test gate, the admin outage fix and CI were pushed and verified on the remote. The subsequent merge, tag and release are recorded in the section "release of WBengine 4.0.0" below.


### 2026-10-26: common remote CI gate green

- Framework 0c15eba: all 7 jobs GREEN, PHP 8.3/8.4/8.5 MySQL and Maria 10.11/11.4 and both optional compatibility. Administration 287274d: the ordinary test job GREEN and the new Two-site PHP API pilot GREEN 2m18s, run 40360443062. This job really built both images, verified TLS/isolation, the actual dual-web form workflow, revocation/outages, dump/restore and a finally reset. Skeleton d0b1e79: smoke CI green, the parent additionally the latest optimized autoload + 20 HTTP green.
- Release notes are in ref-release-4.0.0.md. After approval the PRs were merged, the tag and release published. Production data and deployment did not take place.
- We are still verifying the existing skeleton content by a dry run/reversible conversion on TEMP copies of real rows, so that its own tracker also contains concrete evidence of origin and preservation of the old HTML. This is not rewriting the original demo data.


### 2026-10-26: real originals of the sample content also verified

- The skeleton's original SQL contains 9 HTML articles and 9 translations, not proven Texy sources. The new migration smoke uses TEMP copies and the real Migrator/converter/ledger 004. The parent repeated 18 dry-run plans, 8 safe approved apply/rollback and 10 manual review records without application (8 unsupported element, 2 semantic mismatch). The original full rows/hash and both schemas unchanged; then 20 HTTP green.
- Valid original HTML is preserved as legacy_html, not converted lossily just to standardize new texts. The manual issues are an instruction in case conversion is chosen, not proof of defective content or a non-functioning Markdown parser. No Texy runtime or Smarty is installed. Details and concrete IDs in the application plan.
- All implementation stages and technical release tests are completed. The following publication step was approved, the PRs merged, the tag and GitHub release published. Production deployment and conversion of operational data did not take place.


### 2026-10-26: release of WBengine 4.0.0

- Core PR #2 merged as cf710e5adbc25809868a864e615f8b9fc62a7489.
- Tag v4.0.0 published on the core merge commit. GitHub release: https://github.com/rrsoftcz/wbengine/releases/tag/v4.0.0.
- Skeleton PR #1 merged as 19fde667530a273e95ce05a118bd438c705520b5; administration PR #1 as a74892ab17205a15955264f0a1ca5a669d585ca0. Both CI integrations use the tag v4.0.0.
- CI master/main of all three repositories passed; the administration pilot included TLS, separation of sites, outage/revocation and database recovery.
- Production deployment and migration of operational data are not yet performed and remain a separate step of the operator.
