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
| API has no role check: any valid token can use every route | Open |
| No rate limiting on `/api/auth/login`; web login counter lives in the session so a client without cookies avoids it | Open |
| Refresh token is a second JWT with no server side store, rotation or revocation; logout does not invalidate tokens | Open |
| Smarty output is not escaped by default (`escape_html` off) | Open |
| A session row is created for every request without a cookie (database write per bot hit) | Open |
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
| `Zend` adapter, `Config\Adapter*`, `Locale` model, `Box\Model`, `Config::autodetectEnvironment`, `Registry` and similar dead or broken code | Open: remove or finish |
| Typo in exception file names (`RunetimeException.php`) breaks autoload on case sensitive systems | Open |
| `Renderer/Adapter` methods call `getAdapter()` without its argument, `registerObject` uses a removed Smarty call | Open |
| Remaining legacy style: no type declarations on most methods, `declare(strict_types)` not used | Open |

### Performance

| Finding | Status |
|---------|--------|
| About eight to ten queries per anonymous page; one box query per section; `Site` menu and url helpers are N+1 | Open |
| Session written on every `setValue`; a global `DELETE` of expired sessions on reads | Open |
| Article view counter is a write on every GET | Open |
| No cache of site, sections, menu or box list | Open |
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
* Not covered: automated tests (there is no PHPUnit suite yet), load testing, MariaDB, Nginx, PHP 8.3 and 8.5.

## Recommended next steps

1. Add a role claim to tokens and check it in every API module.
2. Cache site, sections, menu and box list, defer session writes, batch view counting.
3. Add PHPUnit with a Docker MySQL service and CI; start with `Router`, `Url`, `Db`, `Password` and the API models.
4. Remove the dead code listed above and fix the exception file name typos.
5. Template compiler with automatic escaping and fragment caching, keeping Smarty as an adapter.
6. Tag a release (suggested 3.1.0) so the skeleton can require it instead of a development branch.
