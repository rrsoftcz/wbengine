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
- All `/api/sections` routes now require a bearer token.
- API user and section writes accept only whitelisted fields, other fields are
  rejected with 400. `user_type`, `group_id`, `ac_status`, `ac_key`,
  `ac_active` and `user_ip` cannot be written through the API.
- The user API no longer returns the password hash or `ac_key`.

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
- `Session::regenerateId()`, called on login to prevent session fixation.
- `Api\User::updateUser()`, which `PATCH /api/users/patch/{id}` had always
  routed to without it existing.
- `Auth\Exception\AuthException::ERROR_MISSING_KEY`.

### Changed

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
