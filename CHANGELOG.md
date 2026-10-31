# Changelog

All notable changes to WBengine are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/). Versions before 3.1.0 are not
documented here, see the git tags.

## [Unreleased]

### Fixed (6.4.1)

- MCP server: requests to a host with an IPv4 address go over IPv4 first. PHP streams try IPv6 first and waited several seconds on a network whose IPv6 route is broken, which made every tool call about seven seconds slower. The host name and the certificate check are unchanged; without an IPv4 address, or when IPv4 fails, the default behaviour is used.

### Added (6.4.0)

- MCP server for an editor who is not technical. Tools `wbengine_guide` (how the site works, shipped as texts and also offered as MCP resources and prompts), `plan_article_page` and `check_article` (address, browser title, description, article box, menu, languages; `publish_article` refuses a page that cannot show anything and reports what is still open), `update_page`, `list_menu`, `set_menu_entry`, `list_sections`, `list_box_types`, `list_boxes`, `add_box`, `update_box`, `hide_box`, `show_box`, `upload_image`, `list_images`. `save_article_draft` with `new_page` fills the SEO fields and attaches the box that shows the article. Tools are offered only when the key has their scopes; `site_info` says whether new article pages can be created. Nothing is deleted. See `ref-mcp.md`.
- The Integrations page offers the preset "Editor with structure and statistics" (Content, Publishing, Structure and Images write, History read, Analytics).

### Fixed (6.3.1)

- MCP server: `save_article_draft` and `publish_article` warn when the page of the article has no box that shows articles, which would leave the published address empty. A page created by `new_page` has none; the warning tells the agent to use a page that already shows articles or to ask the administrator for an article box.

### Added (6.3.0)

- MCP server (`Wbengine\Mcp`, `bin/wbengine-mcp.php`, also built as the single signed file `wbengine-mcp.phar` and attached to every release with its checksum): stdio, speaks the stateless revision 2026-07-28 and the revisions 2025-11-25 and 2025-06-18 with `initialize`. Tools for one site: `site_info`, `list_pages`, `list_articles`, `get_article`, `save_article_draft`, `preview_article`, `publish_article`, `hide_article`, `unhide_article`, `list_changes`. It is a client of API v2 with verified TLS, no redirects, size and time limits; text from the site is returned marked as data; `WBENGINE_MCP_MODE=draft` removes the publish and hide tools. See `ref-mcp.md`.
- The Integrations page offers presets for the permission matrix (Full management, Agent for content, Agent drafts only, Read only) and shows a client configuration (JSON and a `claude mcp add` command) with the key once, after a key is issued.

### Added (6.2.0)

- Hiding articles: `POST /api/v2/articles/{id}/hide` and `/unhide` (scopes `articles:write` and `publish:write`, `If-Match`, idempotent, recorded in the change log as `hide` and `unhide`). A hidden article is not displayed, the page it belongs to is not found (404) and its menu item disappears; showing it again restores both. The article resource has a `hidden` field. Migration `6.2.0/012-article-hidden.sql` (also an index on `cms_articles.site_id`); `doctor` reports it as `migration.012`. Without it nothing changes and hiding answers 409 `hide_unavailable`.
- The article list can be searched by title (`search`) and filtered (`hidden=0|1`, `has_draft=0|1`); the capabilities list `articles` among the searchable resources.

### Added (6.1.0)

- Per-key address restriction: an integration key can be bound to IP addresses or CIDR ranges. The issue form has an optional field, every key card a form to change it, and the card shows the address the key was last used from. A call from another address is answered 403 `ip_not_allowed`. Migration `6.1.0/011-integration-ip-restriction.sql`; `doctor` reports it as `migration.011`. Without it nothing changes.

### Removed (6.0.0, breaking)

- The unused settings `codePage`, `cdnPath`, `cssFiles`, `ipAdmins`, `minimizeHtml`, `minimizeCss` and `minimizeJs`, their `Config` getters (`getHtmlHeaderCharset()`, `getCdnPath()`, `getCssCollection()`, `getAdminIpCollection()`, `minimizeHtml()`, `minimizeCss()`, `minimizejs()`) and the `minimizeCss` template variable of `Site`. Nothing in the framework read them. An existing `Settings.json` with these keys still loads, the keys are ignored.

### Added

- Permission groups when issuing an integration key: Content, Publishing, Structure, Images, Site administration and History, each with a level (no access, read, read and write), plus the Analytics choice. A key cannot be changed afterwards. The key cards list the groups instead of a count. Without a group field the full profile is still issued.
- The Integrations box needs no class in the application: a `cms_boxes` row naming the module `integrations` is enough (`Section` resolves it to `Wbengine\Box\Integrations`). `integrations:install` no longer writes a class or needs `--root` and `--namespace`.

### Changed

- The permission groups of the issue form are one row each with radio buttons None, Read and Write (a group with one level has one button, Analytics is a row too). A row is red for none, blue for read and green for write; the key list uses the same colors on its group badges. Levels of single-level groups are now `write` (Publishing) and `read` (History, Analytics).

### Removed

- The "Older key" marker of the key list, which cannot be told apart from a deliberately limited key.

### Documentation

- README brought up to date with 5.0: API v2 reference table and database updates, the complete configuration reference (`http`, `apiV2`, `i18n.baseUrl`, page cache paths), command line tools including `integrations:install`, an upgrade guide from 4.x, and version-specific wording removed.

### Removed (5.0.0, breaking)

- API v1 (`/api/auth`, `/api/users`, `/api/sections`, `/api/translations`, `/api/cache/flush`) and the JWT login with refresh cookies. Users, sections, translations and the cache flush are API v2 resources; `Api::Initialize()`, the CORS and cookie settings of `Api`, `Wbengine\Auth`, `User::getJwtToken()` and `ApiSectionModel` are gone. Sign in on the web through `Box\Auth`.
- The dependency `firebase/php-jwt`: the framework has no runtime Composer package. The `jwt` section of `Settings.json` and `WBENGINE_JWT_*` are ignored, and `doctor` no longer checks them.
- The table `cms_refresh_tokens` (migration `5.0.0/010-drop-refresh-tokens.sql`).
- The change log keeps `v1` as a valid historic source of old rows; new rows use `v2` or `cli`.

### Added

- `integrations:install` command: previews, and with `--apply` creates, the application class, page, section and box record for the built-in Integrations screen, never changes existing records or files, and reports what still blocks issuing keys.

### Changed

- The integrations page lists active keys as cards (name, creation, last use, permissions, revoke behind a disclosure) with revoked keys folded into a closed section, and marks keys issued before the latest permissions as older keys.

### Added (remote users, cache and settings)

- API v2 users: list, read, create, change the role, disable and enable, issue a password link; scopes `users:read` and `users:write`. One-time password links (`cms_password_resets`, 48 hours, only the hash stored) are used on the login page (`/login/?reset=TOKEN`). Disabled accounts (`cms_users.disabled_at`) cannot sign in, get tokens or keep a web session. Migration `4.10.0/009-remote-users.sql`; without it nothing is disabled and nothing breaks.
- `POST /api/v2/cache/flush` (scope `cache:write`) and `GET /api/v2/config` (scope `config:read`, secrets replaced). The fixed integration profile has 22 scopes; keys issued earlier gain the new ones by rotation.

### Added (change feed)

- `GET /api/v2/changes` with the new scope `audit:read` (capability `audit.read`): a paginated, filterable feed of the change log (`resource`, `resource_id`, `action`, `source`, `actor`, `since`, `until`) and, with `outcome=rejected`, of rejected and failed writes, without before/after bodies. `audit:read` is part of the fixed integration profile (18 scopes); keys issued earlier gain it by rotation.

Target: **4.1.0**.

### Changed

- Box device limits are now enforced: `device_min` and `device_strict` hide a box on devices below
  (or, when strict, other than) the minimum. The stored default (`device_min` 1, not strict) still
  shows every box everywhere. The full page cache key now contains the device type
  (`PageCache::cacheKey($path, $device)`), so a page is cached once per device class.
- `BoxRegistry::register()` takes an optional `$metadata` argument (`['renders' => 'article']`) and the
  discovery catalog lists `renders` for each box type.
- All documentation (plans, references, guides) is in English.

### Added

- `GET /api/v2/pages?search=` (title or link) and `GET /api/v2/boxes?search=` (name): substring search
  with escaped LIKE wildcards, at most 100 characters, combined with `page` and `per_page`.
  Advertised as `search` in the capabilities.
- Box rows carry read-only `module`, `method` and `renders_articles`; a page read carries
  `article_box_ids`, the visible boxes that render articles. Unregistered legacy boxes count when their
  method is `getArticleBox` or their module is `Article`. All are rejected on write like unknown fields.
- Complete change log as an engine feature. New `Wbengine\Audit\ChangeLog` writes every change to
  `cms_api_changes` with actor, resource, action, before/after data and a `source` (`v2`, `v1`, `cli`).
  API v2 writes use it in the same transaction as before. API v1 user create/patch/delete, section
  writes, translation writes and cache flush, and the CLI `user:create|password|delete`, are now logged
  too. Keys named password, token, secret, key or hash are redacted, passwords and hashes are never
  stored. Migration `migrations/4.8.0/008-change-log-source.sql` adds `source`, `local_user_id`,
  `cms_api_audit.detail` and `created_at` indexes (idempotent, reported by `migration:status` as
  `migration.008`); until it is applied the new columns are skipped and nothing else changes.
- Rejected API v2 writes (validation, not found, conflict, 412, 428) are recorded in `cms_api_audit`
  with outcome `rejected` and the error code in `detail`. 5xx failures get a reference code in
  `error_log`, in the response message and in an `error` audit row. No request body and no IP address.
- `audit.retentionDays` and `audit.revisionRetentionDays` settings (default 365, 30 to 3650) and the CLI
  commands `audit:prune [--days=N] [--dry-run]` (batched; keeps the newest change per resource and
  revisions referenced by drafts) and `audit:list [--limit=N] [--resource=X] [--actor=X]`.
- Pages, menu items and articles in API v2 lists and reads carry `translation_locales`, the languages
  that have a translation, computed with one query per page of results. A client can show "3 of 20
  languages" without asking for every language of every record.
- `GET /api/v2/capabilities` lists `i18n.default` and `i18n.locales` (the locales the site really
  serves), so a client can offer a fixed choice of translation languages instead of free text.
- Optional "Analytics" choice when issuing an integration key: adds `analytics:read` to the
  fixed 17 scope profile (18 in total, `PROFILE_SCOPES` is unchanged and a key without the
  choice is issued exactly as before). It is offered only when analytics is enabled in
  `Settings.json` and the schema of `migrations/4.0.0/006-analytics.sql` exists
  (`Preflight::analyticsProblems()`, `Doctor::SCHEMA_ANALYTICS`); otherwise it is disabled
  with instructions and the server refuses it. The permission list shows the real count,
  the key list marks keys with analytics, and a rotation presets the choice from the old key.
  Also the integration name explanation is now actually rendered.
- User management CLI: `user:create`, `user:password`, `user:list` and `user:delete`
  (`--email` or `--example` for RFC 2606/6761 demo addresses, `--dry-run`). Passwords are
  read from a no-echo terminal prompt or one line of standard input, never from an
  argument or the environment. Validation, hashing and token revocation reuse
  `ApiUserModel`; deletion keeps at least one active administrator and removes refresh
  tokens and sessions in one transaction. Documented in the README, including the first
  administrator on a fresh install.
- Opt-in first-party analytics with daily aggregate page, article and box counts,
  long-lived totals, configurable timezone, bounded retention and no visitor identity.
- Read-only `GET /api/v2/analytics/overview` for integrations with the
  `analytics:read` scope, including daily, weekly and monthly series and top content.
- `analytics:prune` CLI command for bounded removal of expired daily aggregates.
- Public-page measurement start marker. API responses distinguish dates before
  measurement from dates with zero recorded views.
- Local integration key management: `Box\Integrations` screen (ADMIN role read from the
  database, POST only, action-bound CSRF, fresh password confirmation, one-time key in a
  `private, no-store` response with `Referrer-Policy: same-origin`, scope descriptions in cs
  and en, preflight with operator instructions, revoke and rotation order). The issued
  profile is a fixed list of 17 scopes and never includes `analytics:read`.
- `User\PasswordConfirmation`: rate-limited, constant-time password re-check for the session
  user, never logging the password.
- `Api\V2\Model::list()` (non-secret metadata only) and `last_used_at`, written at most
  once per 60 seconds per key and never failing a request. Migration
  `migrations/4.0.0/007-api-integration-last-used.sql` (idempotent, no runtime DDL);
  `doctor` checks it as `migration.007`; `tests/schema.sql` now contains the
  API v2 tables.
- `Api\V2\Preflight`: read-only check of API v2 configuration, schema and HTTPS.

### Changed

- The article `source` field is optional free text. API v2 accepts any text up to 255
  characters; only control characters (`\x00` to `\x1f`, `\x7f`) are rejected with
  `invalid_source` ("Source must be plain text without control characters."). An empty,
  omitted or `null` source is valid. Previously anything that was not an http(s) URL, a local
  path or a fragment was rejected.
- The article box renders the source safely: an http or https URL, a local absolute path or
  a fragment becomes a link (external links get `rel="noopener noreferrer"`), everything else
  (including `javascript:` and `data:`) is escaped plain text. `Article.tpl` shows the
  author and source line only for filled values: no empty "Source:" label or link, and no
  line at all without both. Template variables `source_text`, `source_href`,
  `source_external` and `byline` are prepared by `Box\Article::sourceView()`.

### Upgrade notes

- Apply `migrations/4.0.0/007-api-integration-last-used.sql` (idempotent) before using the
  Integrations screen; `doctor` reports it as `migration.007`.
- Apply `migrations/4.0.0/006-analytics.sql` before enabling `analytics.enabled`.
- Analytics is disabled by default. See the README for timezone, retention,
  administration scope and the regular `analytics:prune` command.

### Fixed

- `doctor` and `MigrationStatus` never detected a missing column (`Db::fetchOne()` returns
  column metadata, not a value) and the database check called an undefined
  `Db::setCredentials()`. Both now inspect the schema read-only as documented.
- `Url::getHostName()` derived the scheme from `SERVER_PROTOCOL` (the HTTP version), so
  it always returned `http://`, for example in the Location of configured site
  redirections behind a TLS proxy. It now uses the same HTTPS detection as the rest of the
  framework, honouring `http.trustedProxies` and `http.forwardedHeaders`.

## [4.0.2] - 2026-10-27

### Fixed

- Explicit locale switching now redirects without reusing a cached response.

## [4.0.1] - 2026-10-26

### Fixed

- Debug exception pages render actionable diagnostics through the framework debug
  template.

## [4.0.0] - 2026-10-26

### Added

- Deferred `Application::route()` registration for common HTTP methods; `get()`
  delegates to it. Explicit true/false callback results complete or decline a route.

- Batched `SiteModel::getSiteIdsByUrls()` resolves exact URLs using database collation,
  preserves input order and chooses the lowest site ID for duplicate links.

- `Site::assignDiagnostics()` explicitly assigns the existing server, DB-version
  and PHP-version template variables through the site's model and variable tree.

- Session-owned CSRF service with purpose-bound tokens, stable multi-tab forms,
  form/AJAX request validation and private/no-store cache handling. Application
  exposes the same lazy service; public routes without CSRF usage remain unchanged.
- Bundled POST logout form through `Box\Auth::getLogoutBox()`.

- Lazy `Application::getRequestContext()` with shared client-IP and HTTPS resolution.
  Explicit `http.trustedProxies` IP/CIDR and `http.forwardedHeaders` settings enable
  trusted IPv4/IPv6 proxy chains and original-scheme detection for session/API/locale
  cookies and absolute locale links.
  Forwarded headers are disabled by default; invalid configuration fails closed.

### Changed

- Application construction prepares services and normalizes i18n without CMS queries,
  box execution, cache HITs or redirect exits. `run()` handles custom routes before
  cache and CMS; `display()` shares the lifecycle. Update bootstrap ordering in 4.0.
- Custom Vars/renderer setup and response policies are evaluated before page cache.
  Accepted routes bypass shared cache; debug is built during request handling.

- Core HTML dispatch always uses the renderer adapter's `display()` path and keeps
  rendered whitespace intact. `minimizeHtml` is ignored; its getter remains as a
  deprecated compatibility API. Configure transfer compression at the webserver.

- `Http::Json(true)` parses the request body once instead of validating and decoding
  separately. Raw mode, associative/scalar results, parser errors and the distinction
  between valid JSON null and an error through `json_last_error()` are preserved.

- Site menu-parent selection shares one request-local batch lookup across menu items,
  with or without data cache. Hidden/strict parent and collation rules are preserved;
  warm cache still incurs at most one lookup statement for parent selection.

- Runtime diagnostic template variables are now assigned automatically only in
  debug mode. Production site initialization no longer opens a DB connection just
  to obtain `dbinfo`; templates requiring these values must request them explicitly.

- Web login uses shared CSRF tokens. GET logout now returns 405; POST logout requires
  its own purpose token. Invalid login/logout tokens return 403 before authentication.
- Cookie API login/refresh/logout require POST, JSON and `X-WBengine-CSRF: 1`.
  Clients must add this header; trusted credentialed CORS origins must be configured
  explicitly. Refresh stays stateless; bearer-authorized API actions are unchanged.

- Request helpers return PHP-parsed values unchanged. `Post()` / `Get()` without a
  field name return their complete input map; missing keys return null. `getCookie()`
  returns raw strings and rejects arrays. String-only `PostString()` / `GetString()`
  accessors provide explicit shape validation; `PostRaw()` remains a compatibility alias.
  Application code must validate fields and escape at output.
- Both renderer adapters now HTML-escape by default. Set `renderer.autoEscape` to
  false only for a temporary legacy migration, and mark trusted HTML explicitly.

### Removed

- `Http::secureClean()`: the legacy slash/tag/HTML transformations are no longer
  applied to input. See the request-data migration notes before updating older apps.

### Fixed

- `run()` / `display()` no longer dispatch duplicate layouts; display handles explicit
  empty/zero content. Missing CMS pages do not run shared boxes, empty sections work,
  and explicit 410 responses cannot be replaced by cached 200 output or a 404.
- Locale-prefix normalization also applies to a pending canonical redirect, letting
  application routes respond first. Existing static Router/API box dispatch remains.

- Removed whole-document whitespace collapsing that damaged preformatted text,
  textarea values, inline JavaScript/CSS, attributes and JSON script strings.
  Clear old HTML/page caches during upgrade to remove previously damaged responses.

- Parent menu links ending in `/` now match the URL parser's bare prefixes when no
  exact bare link exists. Empty prefix sets no longer warn; missing or nonpositive
  site IDs cannot activate an orphan menu entry. Duplicate-link selection is stable.

- Token-bearing responses cannot be stored in public or user native fragments or
  page cache. Session-ID regeneration and session destruction invalidate CSRF tokens.

- Missing or invalid connection addresses no longer fall back to spoofable forwarding
  headers. IPv6 addresses are canonicalized, and both session and API Secure cookies
  follow the same explicit trusted-proxy policy. Review session/rate-limit changes
  when deploying behind a proxy; see the configuration and migration notes.

- Login CSRF and CAPTCHA fields reject non-string input without PHP type errors;
  stored CSRF tokens are checked before constant-time comparison.
- Native output escaping now follows the final modifier of the complete expression;
  modifiers inside method arguments, array keys or concatenation operands no longer
  suppress escaping of unrelated output. Earlier escaping does not trust a later fallback.
- Native compile checks detect same-size source edits with unchanged timestamps.
  Disabled production compile checks skip the checksum.
- Smarty template identity includes escape mode so switching that setting cannot
  reuse an incompatible in-memory template. Native fragment identity includes compiler version and escaping mode.
- `Auth::setIssuedTime()` updates `iat` without overwriting the JWT issuer.
- New passwords are refused when the database column cannot hold a modern hash,
  instead of silently falling back to MD5. Legacy password verification is retained.
- Native template fragments are separated by locale. Existing fragment entries are
  not reused across the change.
- Shared page cache rejects private/no-store/no-cache responses, cookies, unsupported
  `Vary` headers and nonce-based CSP. Requests with custom cookies or personalized
  anonymous sessions bypass it. Cached security headers are restored on hits and 304
  responses; policy supplied by the current deployment takes precedence. Records
  created before the stricter storage format are ignored.

### Added

- Native fragment cache scopes: `public` (default) and `user`, with a lazy identity
  resolver integrated through Application and Renderer. Personal fragments separate
  users and locales, do not cache guests, and prevent shared page/public-wrapper storage.
- Exact `cache.pages.allowPaths` and `denyPaths` policies, plus request-wide
  `PageCache::bypass()`; deny rules take precedence.
- A persistent modernization tracker with task IDs, dependencies, verification
  requirements and the agreed WBengine OOP architecture constraints.

## [3.5.0] - 2026-10-24

### Added

- Absolute `hreflang` links: the layout variable `hreflangs` (every language plus `x-default`),
  `I18n::origin()`, `I18n::hreflangs()` and the `i18n.baseUrl` setting. Lighthouse and search
  engines reject relative `hreflang` URLs. Without `baseUrl` the address is taken from the
  validated `Host` header and the page cache keeps one copy per host.

## [3.4.0] - 2026-10-24

### Added

- Native template engine: an output tag may start with a quoted text and a modifier,
  `{'/blog/'|lurl}` or `{"x"|upper}` (Smarty accepts this too). A `{` followed by a quote and
  no modifier, such as a JavaScript object, is still plain text.

### Fixed

- The bundled article template printed a stray "Wbeigine" before every article title.
- An error page (404, 401, 410, 500) had an empty `<title>`: the title was set after the
  template variables were stored. `Site::setHtmlTitle()` now keeps `meta.title` in step.

## [3.3.1] - 2026-10-24

### Fixed

- The bundled `error.tpl` is translated (`wb.error.*`): `Renderer::getErrorBox()` now
  assigns `error` and the labels `t` to the error templates.

### Added

- `LICENSE.md` (MIT, as declared in `composer.json`).

## [3.3.0] - 2026-10-24

### Internationalisation

- Database content can be translated: site titles and meta tags, menu names and
  articles. One table per kind (`cms_site_translations`, `cms_menu_translations`,
  `cms_article_translations`), the default language stays in the original columns,
  and a missing or empty translated field falls back to the default, field by field.
  New `Wbengine\I18n\ContentTranslations`, applied in `SiteModel`, `getSiteMenu` and
  `ArticleModel`. No extra query in the default language or with `i18n` off. Sites and
  menu are cached per locale; articles are read by id. Without the new tables the
  application logs once and stays in the default language.
- REST API for editors: `GET`, `PUT` and `DELETE /api/translations/{site|menu|article}/{id}[/{locale}]`
  (role `editor`), validating locale, fields and length, flushing the cache on writes.
- Tests: 58 new (777 in total).

## [3.2.0] - 2026-10-24

### Internationalisation

- User interface translation, off by default (`i18n` setting). New `Wbengine\I18n`
  with `Translator`, `Plural`, `LocaleResolver` and `Format`.
  - The locale of a request: a language prefix in the URL (`/cs/blog/`, removed from the
    URL before the rest of the framework reads it; the default language redirects to its
    unprefixed canonical URL), then the `wb_locale` cookie, then `Accept-Language` (with
    quality values), then the default. `Content-Language` and `Vary` are sent.
  - Catalogs are PHP arrays (`src/Locale/cs.php`), looked up with a fallback chain
    (`pt_BR`, `pt`, default, the key). `%name%` parameters; plural forms by the CLDR rules
    of Czech, Slovak, Polish, Russian, Ukrainian, English, German, French, Portuguese and
    the languages without plurals.
  - Templates: the `{t 'key' name=$x count=$n raw}` tag (native engine) and the modifiers
    `|t`, `|lurl`, `|number`, `|ldate` (native engine and Smarty). Translated text is
    escaped; with `raw` the parameters are escaped individually. Template variables
    `locale`, `lang` and `alternates` for a language switcher and `hreflang` links.
  - The page cache key includes the locale: verified that a page cached in one language is
    never served in another, and that the ETags differ.
  - The login and article boxes are translated (English and Czech catalogs in
    `I18n/messages/`, keys start with `wb.`); applications can override them.
  - Number and date formatting without the `intl` extension.
- Removed the unfinished `Locale` classes (`Locale`, `Locale\*`) and `Session::getLocale()`;
  nothing used them and their model called methods that do not exist. The tables
  `cms_locales` and the `cms_users.locale` column are untouched.
- `Application::getLocale()` now returns the locale code of the request (a string).
- The login form posts to a localized URL, its labels are translated, its `label` elements
  point at the right inputs, and its AJAX and form messages are translated.
- Found by the tests: `Translator::normalize()` accepted `"cs\0"` (`trim()` removes NUL
  bytes); `Format::date()` warned on a non-scalar value, which the error handler turns into
  an exception.

## [3.1.0] - 2026-10-23

### Breaking changes

- PHP 8.3 or newer is required (was `^7.2 || ^8.1`).
- `firebase/php-jwt` moved from 5 to 7. Decoding uses `Firebase\JWT\Key`.
- JWT signing keys are no longer built in. Configure `jwt.accessKey` and
  `jwt.refreshKey` (at least 32 characters) in `Settings.json` or set the
  environment variables `WBENGINE_JWT_KEY` and `WBENGINE_JWT_REFRESH_KEY`.
  `Wbengine\Auth` throws `AuthException` without them.
- The reCAPTCHA secret is no longer built in. Configure `recaptcha.secretKey`
  or `WBENGINE_RECAPTCHA_SECRET`. Without it the captcha check fails.
- `Db::createUpdateQuery()` is removed. Use `Db::update()`.
- `Db::fetchRow()`, `fetchOne()`, `fetchAll()` and `dumpAllQueries()` are now
  static, as `DbInterface` and every caller already assumed.
- `SiteModel::getUrlId()` is replaced by `getUrlCandidates()`, which returns an
  array.
- `User::login()` no longer hashes the login and password with MD5 itself, and
  `User\Model::authenticate()` looks the user up by `email`.
- Refresh tokens are now stored on the server (table `cms_refresh_tokens`, see
  `schemas/migrations/003-add-refresh-tokens.sql` in the skeleton application).
  Without the table logging in still works but `/api/auth/jwt/revalidate`
  answers 401. The old refresh JWT cookie is not accepted any more, clients have to
  log in again once.
- `POST /api/auth/jwt/revalidate` no longer depends on a PHP session, only on the
  refresh cookie, and it rotates the token. `POST /api/auth/jwt/identify` no longer
  depends on a PHP session either: it checks that the bearer token belongs to the
  `user_id` in the body.
- New defaults: access token 900 seconds (was 3600), refresh token 7 days (was 3600),
  cookie path `/api/auth/` (was `/`), cookie domain empty, meaning the host that set
  it (was `localhost`, which browsers reject on any real host), `SameSite=Strict`,
  `Secure` when the request is HTTPS (was always on).
- The REST API now authorizes by role. Add the column
  `ALTER TABLE cms_users ADD COLUMN role varchar(20) NOT NULL DEFAULT 'user';`
  and promote your administrators (`UPDATE cms_users SET role = 'admin' WHERE ...`),
  otherwise every privileged call answers 403 (the reason is written to the error
  log). Sections are admin only. Listing, creating and deleting users is admin
  only. A user can read and edit their own profile, but not their role.
- All `/api/sections` routes now require a bearer token.
- API user and section writes accept only whitelisted fields, other fields are
  rejected with 400. `user_type`, `group_id`, `ac_status`, `ac_key`,
  `ac_active` and `user_ip` cannot be written through the API.
- The user API no longer returns the password hash or `ac_key`.

### Compatibility

- Tested on MySQL 8.4, MariaDB 10.11 and MariaDB 11.4: the whole suite passes on all
  three, including the box lookup that uses `JSON_SEARCH` (new `BoxLookupTest`), and CI
  runs MariaDB too. The skeleton's seeded schema loads on MariaDB 11.4.
- Tested with Nginx and PHP-FPM (the skeleton on Nginx 1.30, PHP 8.4 FPM and MariaDB
  11.4): pages, trailing slash redirects, login, roles, refresh rotation and theft
  detection, the page cache with ETag, and the login rate limit all behave as on Apache.
  An Nginx configuration equivalent to the skeleton's `.htaccess` is in the skeleton
  (`etc/nginx/wbengine.conf`).


### Tests found real bugs

Automated tests for the API controllers, the session and the page cache decision
(463 tests now, green on PHP 8.3, 8.4 and 8.5) found and fixed:

- The refresh endpoint started a PHP session after all (a `PHPSESSID` cookie and
  session queries on a stateless endpoint). It now builds the access token straight
  from the database row.
- `WbengineRestapiAbstract` called `getallheaders()` directly, which does not exist
  outside Apache and FPM, so the API crashed there. `Http::getHeader()` is now
  case-insensitive (HTTP/2 and some proxies send lower case names) and falls back to
  `$_SERVER`.
- The `Bearer` scheme was matched case-sensitively, `bearer <token>` was rejected
  (RFC 7235: schemes are case-insensitive).
- A request without a `User-Agent` header raised a warning that the error handler
  turned into a 500 (`Utils::getUserAgent()`); `Http::getCookie()` did the same for a
  missing cookie.
- `Api` can be tested without ending the request or sending cookies:
  `Api::setTerminator()` and `Api::setCookieWriter()`, plus `Api::getLastStatus()`.

### Template engine

- A native template engine, `Renderer\Adapter\NativeRenderer` (`Template\Engine`,
  `Template\Compiler`, `Template\ExpressionParser`, `Template\Runtime`). Select it
  with `"renderer": { "adapterName": "NativeRenderer" }`; Smarty stays the default
  and supported. Templates are compiled once to plain PHP and included, so with
  opcache a render is just running compiled code.
  - Output is HTML-escaped by default (`{$value|raw}` for trusted HTML). A
    template written with explicit `|escape` and `|raw` works in both engines.
    `renderer.autoEscape: false` keeps the Smarty behaviour for old templates.
  - A subset of the Smarty syntax: `{$a.b}`, `{$o->p}`, `{$o->m()}`, modifiers,
    `{if}`/`{elseif}`/`{else}`, `{foreach}` with key and `{foreachelse}`,
    `{include}`, `{set}`, `{literal}`, comments. Lines break like Smarty after control
    tags. New: `{cache 'key' ttl=60}...{/cache}` fragment caching on top of
    `Cache`. Braces that do not start a tag (inline CSS and JavaScript) are plain
    text, no `{literal}` needed.
  - Safe by construction: expressions use a fixed grammar, so a template cannot call
    functions or run PHP; PHP tags in template text and template syntax in data are
    never executed; template names cannot leave the template directories; a missing
    variable, key or property is empty and never a warning.
  - Verified: 43 templates render byte for byte the same as Smarty, all skeleton pages
    are identical with a settings change only, and a stored XSS probe is rendered
    live by Smarty and as harmless text by the native engine. Measured: about the same
    speed as Smarty for repeated renders (11 against 9 microseconds for the
    skeleton layout) and about 2 ms less overhead on a cold request (2.3 against 4.5 ms
    above a bare PHP process), because Smarty's classes are not loaded.
- The bundled templates (`Login.tpl`, `Article.tpl`, `error.tpl`) now escape data
  explicitly and mark trusted HTML with `|raw`. `Login.tpl` no longer writes a
  password back into the form.

### Refresh tokens

- A token family has an absolute lifetime (30 days by default, `Api::setRefreshAbsoluteLifetime()`),
  however often it is used. This needs the column `family_started_at`
  (`schemas/migrations/004-refresh-token-family-start.sql`); without it logging in
  works but no refresh token is issued.
- Server side refresh tokens (`Api\Auth\RefreshTokens`): an opaque random value, only
  its SHA-256 hash is stored. Every refresh rotates the token. Presenting a token
  that was already used revokes its whole family (a stolen copy and the real owner
  are both logged out); a 10 second grace period covers two browser tabs refreshing
  at once, and exactly one of several simultaneous requests wins (tested with six
  parallel processes). Logout revokes the device, the new
  `POST /api/auth/logout/all` revokes every device of the user, a password change
  and deleting a user revoke all their tokens.
- `POST /api/cache/flush` (admin only) drops the cached data and pages after content
  was edited directly in the database. It runs inside the web server, so it reaches
  an APCu cache too, which a command line script could not.
- `PageCache::sessionBelongsToAUser()`.
- `Api::setCookieSameSite()`, `Api::getRefreshTokenTtl()`, `Db::forgetConnection()`
  (for forked workers), `Http::TOO_MANY_REQUESTS`.

### Rate limiting

- Failed logins are limited for the web form, the AJAX login and `POST /api/auth/login`
  (all go through `User::login()`): 5 failures per client IP and account together,
  20 per IP over all accounts, 25 per account from all IPs, within 15 minutes. The
  API answers `429` with a `Retry-After` header. The strict limit is per IP and
  account pair, so somebody failing on purpose cannot lock the real owner out.
  Counters are small files (no new table); configure or switch off with
  `rateLimit.login` in `Settings.json`. New: `RateLimit\Limiter`,
  `User\TooManyAttemptsException`, `Config::getLoginRateLimit()`,
  `Http::TOO_MANY_REQUESTS`.

### Performance

- New opt-in cache for the site table, the menu, the sections and the box lists
  (`Wbengine\Cache`, APCu or file driver, enable with the `cache` setting). On the
  skeleton application a warm page needs 1 to 3 queries instead of 8 to 10, and
  none for a visitor without a session cookie. The API flushes the cache when
  sections change, call `Cache::flush()` after other direct edits.
- Sessions: the row is read once per request, created only when something is
  stored (anonymous visitors cost no `INSERT`), written once at the end of the
  request instead of on every `setValue()`, and expired sessions are purged on
  about 1 in 100 loads instead of on every request. A request without a session
  cookie skips the lookup entirely.
- Opt-in whole page cache for anonymous visitors (`cache.pages`, `PageCache`). A
  hit is served from the `Application` constructor before any box runs: no query
  for a visitor without a session cookie, one session lookup for a returning one,
  `ETag` and `304 Not Modified` support. A page is stored only for a plain GET of a
  site's own URL, for a visitor who is not logged in, with a 200 HTML response, when
  nothing was written to the session (a page with a login token is never stored) and
  the site needs no login. Verified: a logged-in visitor never reads or fills the
  cache and the admin's e-mail never appears in the anonymous copy. New:
  `Cache::fetch()`, `Cache::store()`, `Session::wasModified()`,
  `Application::hasModifiedSession()`. The API flushes pages together with the data
  cache.
- Opt-in batched view counting (`counters.views`): the article view counter is
  written once per N views or per interval instead of on every request (new
  `Counter\BatchCounter`, `Config::getViewCounterSettings()`). Off by default, so
  views are written immediately as before.
- `renderer.compileCheck: false` skips Smarty's per-request template check.

### Added

- Continuous integration (`.github/workflows/ci.yml`): on every push and pull request,
  PHP 8.3, 8.4 and 8.5 with MySQL 8.4 run `composer validate --strict`,
  `composer audit`, `composer lint` and the test suite. Dependabot watches the
  Composer packages and the workflow actions. New scripts: `composer lint`.
- Automated tests: PHPUnit 12 with unit and integration suites (63 tests) and a
  Docker test environment (`docker compose run --rm php composer test`).
- Bound parameters: `Db::query()`, `fetchAssoc()`, `fetchObject()`,
  `fetchAllAssoc()`, `fetchRow()`, `fetchOne()` and `fetchAll()` accept an array
  of parameters for `?` placeholders. New `Db::insert()`, `Db::update()` and
  `Db::identifier()`.
- `User\Password`: `password_hash()` based hashing. Legacy MD5 hashes still
  verify and are upgraded on the next successful login when
  `cms_users.password` can hold a modern hash (`varchar(255)` recommended).
- `Http::PostRaw()` for secrets that must not be altered by HTML cleaning.
- `Config::getJwtKeys()` and `Config::getRecaptchaSecret()`.
- `User\Role` (`user`, `editor`, `admin`) and `cms_users.role`.
  `WbengineRestapiAbstract::isAuthenticated($callable, $role)` requires a role and
  hands the callable the token payload and the actor row. The role is read from the
  database on every privileged call, so a demotion or deletion takes effect at once
  and not when the token expires. The token also carries the role, for display only.
- Guards: nobody can delete their own account through the API, and the last
  administrator can be neither demoted nor deleted.
- `Cache` (`remember()`, `flush()`, `isEnabled()`, `setDriver()`), `Cache\FileCache`,
  `Cache\ApcuCache`, `Cache\NullCache`, `Config::getCacheSettings()`.
- `Session::flush()` writes pending session changes (called automatically at
  shutdown).
- `Session::regenerateId()`, called on login to prevent session fixation.
- `Api\User::updateUser()`, which `PATCH /api/users/patch/{id}` had always
  routed to without it existing.
- `Auth\Exception\AuthException::ERROR_MISSING_KEY`.
- `PageCache::setHeaderProvider()` (the command line SAPI keeps no response headers,
  so tests supply them). The store rules of the page cache have automated tests,
  each one proven by mutation: disabling a rule makes exactly its test fail.

### Removed

- The `Db\Adapter\Zend` adapter (its dependency was never required and it did
  not implement the adapter interface), the unused `Config\Adapter*` classes,
  `Box\Model`, and the broken `Config::autodetectEnvironment()` with its
  helpers (`addEnvironmentSafeKeyword()`, `addEnvironmentSafeIp()`,
  `DETECT_ENV_TYPE_*`). They could not run: they used undefined constants and
  methods that do not exist.

### Changed

- `Session::save()` no longer writes immediately, it marks the session for the
  write at the end of the request. A destroyed (logged out) session is never
  written again during that request.
- `Renderer` options: new `renderer.compileCheck` setting. Set it to `false` in
  production so Smarty does not check template files on every request (clear
  `View/Compiled` on deploy). Defaults to `true`.
- Session cookies are `HttpOnly`, `SameSite=Lax` (and `Secure` over HTTPS) and
  strict mode is on. Stored session data is unserialized with
  `allowed_classes` limited to `stdClass`.
- Query errors throw `DbException` with a generic message. The SQL and the
  server error are written to the PHP error log instead of being returned to
  clients. mysqli reporting is `ERROR | STRICT` on every PHP version.
- Exception pages show a generic 500 unless debug is enabled, and escape all
  output. The debug bar escapes the SQL it prints.
- Router: a trailing slash is optional and the query string is ignored when
  matching routes.
- An invalid, malformed or expired token is always a 401. Errors raised by the
  action itself are no longer reported as authentication failures.
- `POST /api/sections` returns 201. `Http::CONFLICT` is 409.
- The error handler no longer throws for deprecations and notices, and honors
  the `@` operator.
- `username` defaults to the e-mail when a user is created through the API.
- `PageCache` sets its `X-WBengine-Cache` header only when headers can still be sent.
- `Url` and `Http::getRequestMethod()` read `$_SERVER` instead of
  `filter_input(INPUT_SERVER, ...)`, which is not available in every SAPI and
  configuration (it always returns null on the command line). Behaviour is unchanged
  where it worked; it is now also testable.

### Fixed

- Deleting a user through the API reported `success: false` although the user was
  deleted (the token revocation ran before the affected row count was read).
- PHP 8.5 deprecations: the `(boolean)` cast spelling and a `case x;` with a
  semicolon. The suite and the linter are clean on PHP 8.3, 8.4 and 8.5.
- `Url\Exception\RunetimeException.php` and `Formater\Exception\RunetimeException.php`
  had a typo in the file name, so the classes could not be autoloaded on case
  sensitive file systems or with an authoritative classmap.
- `Renderer\Adapter` wrappers (`setCacheDir`, `setLeftDelimiter`, `setRightDelimiter`,
  `registerObject`) called a method with a missing argument. `SmartyRenderer`
  used a Smarty call that no longer exists and ignored `setConfigDir`.
- PHP 8.3 and 8.4: implicit nullable parameters, an optional parameter before a
  required one (`Application::setPath`), dynamic properties in `Section`, `Site`,
  `Url`, `Auth`, `Mysqli` and the session singleton, `sizeof(null)`, removed
  magic quotes helpers in `Url`, array access on null in `getModel()` and
  `User`, the `end(explode())` notice.
- `microtime()` was used without `true`, which made the debug timings wrong.
  The summed query time only counted the last query.
- `Session`: the singleton was assigned to an instance property and checked
  against the wrong class.
- Passwords were altered by `htmlspecialchars` and `addslashes` before hashing.
- Site lookups, title lookup, menu, box existence and site type queries built
  SQL from request data. They use bound parameters now. Two of them called
  methods that did not exist.
- API routes without a trailing slash (all `/api/users` routes) were
  unreachable behind a redirecting `.htaccess`.

### Security

- SQL injection through the URL path and the `User-Agent` header (no login
  needed) and through API request bodies.
- The JWT keys and the reCAPTCHA secret were hardcoded in the source. Treat the
  previous values as compromised.
- `firebase/php-jwt` 5.x is affected by published security advisories.
- Unauthenticated create, update and delete of sections.
- User creation and update could set privileged columns.
- Debug and exception output could inject HTML and exposed SQL and file paths.

### Upgrade notes

1. Add the `jwt` section (and `recaptcha` if you use the login captcha) to
   `Settings.json`.
2. Run `ALTER TABLE cms_users MODIFY password varchar(255) NOT NULL DEFAULT '0';`
3. Move string built SQL to bound parameters.
4. If your `.htaccess` redirects URLs to add a trailing slash, exclude `/api`.
5. Rotate any credential that was ever committed to a repository.

## [3.0.0] - 2025-08-27

Initial documented release, see the git history.
