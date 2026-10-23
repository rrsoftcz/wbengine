# WBengine inventory (October 2026)

Scope: the framework (`wbengine`, branch `php84-hardening`) and the example application (`wbengine-skeleton-app`). Findings come from reading all code and from running the stack in Docker (PHP 8.4.27, MySQL 8.4, Apache) against the skeleton.

Status legend: Fixed (change made and verified in the running stack), Open (not done).

## Target PHP version

PHP 8.4, minimum 8.3 (`composer.json`: `^8.3`). PHP has no long term support: 8.4 gets security fixes until the end of 2028, 8.3 until the end of 2027. 8.5 was considered too new for the library set at the time of writing.

## Architecture in one page

Application (lazy service container) -> Site (cms_sites row by URL, ACL, menu) -> Section (cms_sections) -> Box (cms_boxes: module and method, instantiated as `<AppNamespace>\Box\<Section>\<Module>`) -> content: a static template, or a Model with data from MySQL passed to a template. Utilities: Db, Config, Router, Url, Session, User, Auth (JWT), Renderer (Smarty 5), Vars, Registry, Path, Env, Debug, Error handler. REST API modules under `Api/`.

## Findings

### Security

| Finding | Status |
|---------|--------|
| SQL injection through the URL path (`Site/SiteModel`) and the `User-Agent` header (`Session/Model`), no login needed | Fixed: bound parameters, tested with quote and `UNION` payloads |
| API section and user writes built SQL from client column names and values | Fixed: whitelists, bound values, `Db::insert` and `Db::update` validate identifiers |
| All `/api/sections` routes had no authentication | Fixed: token required on every route |
| Malformed JWT caused an uncaught exception instead of 401 | Fixed |
| JWT keys and reCAPTCHA secret hardcoded in the source | Fixed in code: read from configuration or environment, fails closed. The old values remain in git history and must be treated as compromised |
| firebase/php-jwt 5.x blocked by security advisories | Fixed: upgraded to 7.x, tamper, `alg=none` and old key tokens are rejected |
| Unsalted MD5 passwords | Fixed: `password_hash`, legacy hashes upgraded on login. Requires widening `cms_users.password` |
| Passwords altered by `htmlspecialchars` and `addslashes` before hashing | Fixed: `Http::PostRaw` |
| User API returned password hash and activation key | Fixed |
| Mass assignment on user create and update (`user_type`, `group_id`) | Fixed: rejected with 400 |
| Session fixation (no id regeneration), no cookie flags | Fixed: regenerate on login, `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, strict mode |
| `unserialize` of session data without class restriction | Fixed: only `stdClass` |
| SQL and exception details shown to clients, unescaped debug output | Fixed: logged server side, generic page unless debug, escaped |
| API has no role check: any valid token can use every route | Fixed: `user`, `editor`, `admin`, checked against the database, verified with 17 scenarios including immediate effect of a demotion |
| No rate limiting on `/api/auth/login`; web login counter lives in the session so a client without cookies avoids it | Fixed: server side limiter for all three login paths (pair, IP and account counters), verified end to end |
| Refresh token is a second JWT with no server side store, rotation or revocation; logout does not invalidate tokens | Fixed: hashed server side tokens, rotation, theft detection, logout, logout everywhere, revoked on password change and deletion; verified end to end |
| Refresh and identify endpoints depended on a PHP session, the cookie `domain` defaulted to `localhost` | Fixed: stateless, host-only cookie, `SameSite=Strict` |
| Smarty output is not escaped by default (`escape_html` off) | Fixed (opt-in): the native engine escapes by default and the bundled templates are explicit. Smarty stays unescaped unless templates use `\|escape` |
| A session row is created for every request without a cookie (database write per bot hit) | Fixed: rows are created only when something is stored |
| Private keys and a customer certificate were tracked in the skeleton repository | Fixed in the working tree (untracked, ignored). History still contains them: rotate the keys and, if needed, rewrite history |

### PHP 8.3 and 8.4 compatibility

| Finding | Status |
|---------|--------|
| Implicit nullable parameters (`Site`, `Mysqli`, `Api`, `Api\Auth`, `Detector`) | Fixed |
| Optional parameter before required (`Application::setPath`) | Fixed |
| Dynamic properties (`Section`, `Site`, `Url`, `Auth`, `Mysqli`, session singleton) | Fixed |
| Error handler threw on deprecations and ignored `@`; `sizeof(null)` TypeError | Fixed |
| `microtime()` without `true` (wrong debug timings), `=+` query time sum | Fixed |
| Removed magic quotes helpers in `Url` | Fixed |
| `getModel()` array offset on null, `User` null resource TypeError, `end(explode())` notice | Fixed |
| Router: query string broke matching, trailing slash made user routes unreachable behind the skeleton `.htaccess` | Fixed |
| `Http::CONFLICT` was 408 | Fixed (409) |
| `PATCH /api/users/patch/{id}` called a missing method | Fixed |
| `Zend` adapter, `Config\Adapter*`, `Box\Model`, `Config::autodetectEnvironment` | Fixed: removed |
| `Locale` (its model called methods that do not exist) | Fixed: replaced by the new `I18n` (user interface translation, English and Czech catalogs, verified in the skeleton in two languages); translating database content is open |
| `Registry` (static misuse, unused) | Open: unfinished, decide whether to finish or remove |
| Typo in exception file names (`RunetimeException.php`) breaks autoload on case sensitive systems | Fixed, verified with an optimized classmap |
| `Renderer/Adapter` methods call `getAdapter()` without its argument, `registerObject` uses a removed Smarty call, `setConfigDir` ignored | Fixed, covered by tests |
| Remaining legacy style: no type declarations on most methods, `declare(strict_types)` not used | Open |

### Performance

| Finding | Status |
|---------|--------|
| About eight to ten queries per anonymous page; one box query per section | Fixed (opt-in): with the cache a warm page needs 0 to 3 queries |
| Anonymous pages are rendered on every request | Fixed (opt-in): page cache, 0 queries on a hit, safety rules verified end to end |
| `Site` menu and url helpers (`isMenuSelected`, `getUrlPairsWithSiteId`) are N+1 | Open |
| Session written on every `setValue`; a global `DELETE` of expired sessions on reads; a row inserted for every visitor | Fixed: one lazy write at shutdown, probabilistic purge, no lookup without a cookie |
| Article view counter is a write on every GET | Fixed (opt-in): batched counter, 12 views gave 2 writes and an exact total |
| No cache of site, sections, menu or box list | Fixed: `Wbengine\Cache` (APCu or file driver), opt-in, flushed by the API |
| HTML minification is a whitespace collapse that breaks `<pre>`, `<textarea>` and inline script comments | Open |
| CSS minify hashes files on every request | Open |
| Composer autoload with an empty PSR-4 prefix root | Open: use a `src/` layout or a classmap |

### Skeleton application

| Finding | Status |
|---------|--------|
| Config template named differently from the file the framework reads; `adapterName` wrong in the template | Fixed, plus `bin/setup.sh` |
| Docker used `Public` and `Schemas` while directories are lowercase (fails on Linux), mounted a missing config, `AllowOverride none` made `.htaccess` ineffective, PHP 8.1 image, certs mounted | Fixed |
| API box called methods that do not exist and left a `var_dump` | Fixed |
| `/profile/` box class was missing | Fixed |
| `Blog::LocalStoryBox` resolved the wrong model class | Fixed |
| `.htaccess` 301 redirect to a trailing slash turns API POST into GET | Fixed |
| Seed data contained a real looking account | Fixed: `admin@example.com` / `changeme`, change it |
| README described PHP 5.6 and Bitbucket | Fixed |

## What is verified, and how

* Syntax: `php -l` on every framework file under PHP 8.4 with `E_ALL`, no errors.
* Runtime: all skeleton pages render with no PHP warnings or deprecations in the log, sections and boxes come from MySQL 8.4.
* Security tests against the running stack: SQL payloads in URL and header, API injection attempts, token tampering, `alg=none`, forged old key, mass assignment, login and logout, session id regeneration and replay of the old id, legacy MD5 login with a narrow column, hash upgrade after widening, special character passwords through the web form and the API.
* Session behaviour (lazy row, deferred write, a destroyed session stays dead, regeneration, expiry, class-restricted unserialize), the API controllers (role matrix, refresh lifecycle, identify, logout everywhere, cache flush) and the page cache login decision have automated tests; the page cache store rules have automated tests (a rule disabled by mutation makes exactly its test fail) and the whole page cache was verified end to end against the running skeleton on Apache and Nginx.
* Automated: a PHPUnit suite (782 tests, unit and integration, against MySQL 8.4 and MariaDB) runs in Docker with `composer test`.
* PHP 8.3, 8.4 and 8.5: the linter and the whole suite are clean on all three (checked in Docker), and CI runs the same matrix on every push.
* MySQL 8.4, MariaDB 10.11 and 11.4: the whole suite passes on each (CI runs them). The skeleton was run end to end on Nginx 1.30 with PHP-FPM and MariaDB 11.4 (pages, redirects, login, roles, refresh rotation and theft detection, page cache and ETag, rate limit) and on Apache with MySQL 8.4.
* Not covered: load testing, other web servers (Caddy, LiteSpeed), Windows.

## Recommended next steps

2. Per-page control of the page cache (an allow or deny list) and a `Vary` option for device or language.
3. Cover `Url`, `Site`, `Session`, `Box\Auth` and the page cache with automated tests (all were verified by hand).
4. Translate database content (articles, menu names, site titles) and the error box; decide about `Registry`.
5. Convert the skeleton's templates to explicit `\|raw` and `\|escape`, then switch it to the native engine.
6. Tag a release (suggested 3.1.0) so the skeleton can require it instead of a development branch.
