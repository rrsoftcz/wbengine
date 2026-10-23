# Changelog

All notable changes to WBengine are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/). Versions before 3.1.0 are not
documented here, see the git tags.

## [Unreleased]

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
