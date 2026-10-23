# Changelog

All notable changes to WBengine are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/). Versions before 3.1.0 are not
documented here, see the git tags.

## [Unreleased] (planned 3.1.0)

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
- `renderer.compileCheck: false` skips Smarty's per-request template check.

### Added

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

### Fixed

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
