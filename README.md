# WBengine

WBengine is a small object oriented PHP framework for database driven websites and backends. A page is not a script: it is a tree of objects assembled from database rows, so a site can be changed by editing data instead of code.

```
Application
  Site                 one row of cms_sites, chosen by the URL
    Section            header, central, api ... (cms_sections)
      Box              one unit of content (cms_boxes): a PHP class + method
        static box     a template, rendered as is
        dynamic box    data from the database through a Model, then a template
```

The framework is built with performance in mind: no ORM, no reflection-heavy container, one lazily created connection, compiled templates, and a built in debug bar that shows what a request really cost. A JWT REST API is included as the base for an administration backend.

A ready to run example application lives in the companion project `wbengine-skeleton-app`.

## Contents

1. [Requirements](#requirements)
2. [Install](#install)
3. [Core concepts](#core-concepts)
4. [Request lifecycle](#request-lifecycle)
5. [Writing boxes](#writing-boxes)
6. [Database layer](#database-layer)
7. [Routing and the REST API](#routing-and-the-rest-api)
8. [Authentication, sessions and users](#authentication-sessions-and-users)
9. [Configuration reference](#configuration-reference)
10. [Debug tooling](#debug-tooling)
11. [Performance notes and roadmap](#performance-notes-and-roadmap)
12. [Security notes](#security-notes)
13. [Upgrading from 3.0](#upgrading-from-30)

## Requirements

* PHP 8.3 or newer. PHP 8.4 is the development target. 8.3, 8.4 and 8.5 are tested in CI.
* Extensions: `mysqli`, `json`, `mbstring`
* MySQL 8 or MariaDB 10.6+ (box selection uses `JSON_SEARCH`)
* Composer packages: `smarty/smarty` 5, `texy/texy` 3, `firebase/php-jwt` 7

PHP has no long term support releases. 8.4 receives security fixes until the end of 2028 and 8.3 until the end of 2027, which is why 8.3 is the minimum.

## Install

```bash
composer require wbengine/wbengine
```

The framework is a library: your application provides the entry point, templates, box classes and configuration. The quickest way to see it working is the skeleton application, which has a Docker setup and demo data.

A complete entry point (`public/index.php`) is short:

```php
<?php
namespace App;

use Wbengine\Application\Application;

define('APP_DIR', dirname(__DIR__));
require APP_DIR . '/vendor/autoload.php';

try {
    $App = new Application(APP_DIR . '/src', __NAMESPACE__);
    $App->getSite()->setVariable('user', $App->getClassUser());
} catch (\Wbengine\Application\ApplicationException $e) {
    die($e->Show());
}

$App->run();
```

The application directory (`src/`) is expected to contain:

```
Config/Settings.json     configuration, see the reference below
Box/<Section>/<Module>.php   your box classes (namespace App\Box\<Section>)
View/site.tpl            the page layout
View/<Section>/<Method>.tpl  one template per box
View/Compiled/           Smarty cache, must be writable
```

Your web server must send every request that is not a file to `index.php` (the skeleton ships an Apache `.htaccess` for this). The database needs the `cms_*` tables, whose schema is in the skeleton's `schemas/` directory.

## Core concepts

**Application** is the root object and a small lazy service container. It loads the configuration, installs the error handler, and creates the session, renderer, user, site and locale only when they are first used. It also registers routes (`$App->get('/path', callable)`) and runs the request.

**Site** represents the page being requested. It looks up the `cms_sites` row for the URL, checks access control (`acl`), loads the menu and meta data, and builds the sections. Template variables set on the site (`setVariable`) are available in `site.tpl`.

**Section** is a named area of the layout: `header`, `central`, `api` and so on. Each active `cms_sections` row loads the `cms_boxes` rows that belong to the current site, and the HTML they return is assigned to a template variable named after the section key, for example `{$central}`.

**Box** is the unit of content. A `cms_boxes` row names a `module` and a `method`; WBengine creates `App\Box\<Section>\<Module>` and calls the method. The method returns HTML. Boxes extend `Wbengine\Box\WbengineBoxAbstract`, which gives access to the site, the session, the renderer and a model loader.

Relevant `cms_boxes` columns:

| Column | Meaning |
|--------|---------|
| `module`, `method` | the PHP class and the method to call |
| `section_id` | the section the box is rendered into |
| `sites` | JSON array of site ids where the box appears, for example `["2","5"]` |
| `shared` | `1` shows the box on every site (banners, footers) |
| `order` | order inside the section |

**Article, static and dynamic content.** WBengine distinguishes by what a box does, not by a flag:

* a static box renders a template only (`StaticBoxes::About` in the skeleton);
* a dynamic box asks a Model for data and passes it to a template (`Blog::LocalStoryBox`);
* framework bundled boxes ship their own template and logic: `Wbengine\Box\Article` (an article from `cms_articles`, with Texy formatting) and `Wbengine\Box\Auth` (the login form with CSRF token and optional reCAPTCHA).

**Supporting classes**

| Class | Purpose |
|-------|---------|
| `Db` | static facade over one lazy MySQL connection, with bound parameters and a query log |
| `Config` | the parsed `Settings.json` |
| `Router`, `Router\Route` | method and path matching with `{param}` placeholders |
| `Url` | the parsed request URL |
| `Session`, `User`, `User\Password` | database backed session, the current user, password hashing |
| `Auth`, `Api\*` | JWT creation and the REST API modules |
| `Renderer` | adapter around Smarty 5 (`SmartyRenderer`) |
| `Vars`, `Registry` | template variable bag, key value store |
| `Debug`, `Error\Handler` | debug bar, error to exception conversion |
| `Application\Path`, `Application\Env\*` | path registry, HTTP and environment helpers |

## Request lifecycle

```
index.php
  new Application()      load Settings.json, install error handler
  $App->run()
    Site::initialize()
      cms_sites row by URL          404 if there is none
      ACL check                     redirect to /login/ if the site needs a user
      menu, meta, template vars
      for each active section:
        cms_boxes rows for this site and section
        for each box: new App\Box\<Section>\<Module>, call <Method>(), collect HTML
    Renderer::dispatch()            render site.tpl with all variables
    Debug bar (if enabled)
```

A typical anonymous page costs roughly eight to ten queries: the site, the sections, the menu, one box query per section, and the session. See the performance notes for how to cut that.

## Writing boxes

A static box:

```php
namespace App\Box\Central;

use Wbengine\Box\WbengineBoxAbstract;

class StaticBoxes extends WbengineBoxAbstract
{
    public function About()
    {
        return $this->getRenderer()->render($this->getBoxTemplatePath()); // View/Central/About.tpl
    }
}
```

A dynamic box with a model:

```php
class Blog extends WbengineBoxAbstract
{
    public function LocalStoryBox()
    {
        $story = $this->getModel('App\Box\Central\Article')->getArticleRow();
        return $this->getRenderer()->render($this->getBoxTemplatePath(), $story);
        // the template reads the data as {$LocalStoryBox->title}
    }
}
```

`getModel('App\Box\Central\Article')` creates `App\Box\Central\Article\Model` once and caches it. A model extends `Wbengine\Model\ModelAbstract` and talks to `Db`.

Template data is assigned under the name of the last part of the template path, so `render('Central/LocalStoryBox', $data)` exposes `$LocalStoryBox`. Template names are resolved inside the application template directory.

To add a page: insert a row into `cms_sites` (and `cms_menu` for navigation), write the box class and template, then insert a `cms_boxes` row that points at them.

## Database layer

`Db` is a static facade over a single lazily opened `mysqli` connection. Values are always passed as bound parameters with `?` placeholders:

```php
use Wbengine\Db;

$row  = Db::fetchAssoc('SELECT * FROM cms_users WHERE email = ? LIMIT 1', [$email]);
$rows = Db::fetchAllAssoc('SELECT * FROM cms_sections WHERE active = ?', [1]);
$obj  = Db::fetchObject('SELECT title FROM cms_articles WHERE id = ?', [$id]);

Db::query('UPDATE cms_articles SET views = views + 1 WHERE id = ?', [$id]);

$id       = Db::insert('cms_sections', ['title' => $title, 'key' => $key]);
$affected = Db::update('cms_sections', ['active' => 0], ['section_id' => $id]);
```

Rules:

* Never concatenate user input into SQL. `?` is for values only.
* `Db::insert` and `Db::update` validate column and table names (letters, digits, underscore) and bind the values, so a column list built from client data cannot inject SQL. Still whitelist the columns you accept, as the API models do.
* A failed query throws `Wbengine\Db\Exception\DbException` with a generic message. The SQL and the server error go to the PHP error log, never to the client.
* Every query is recorded with its time for the debug bar.

Set the connection `charset` in the configuration. Use `utf8mb4` for new databases (`utf8` is the three byte variant and rejects emoji).

## Routing and the REST API

Routes are registered with static methods and match on the HTTP method and the path. A trailing slash is optional and the query string is ignored:

```php
use Wbengine\Router;

Router::get('/api/things/{id}', function (Router\Route $route) {
    return $route->getParams('id');
});
Router::post('/api/things', fn () => /* ... */ null);
```

`{name}` matches letters, digits, underscore and dash.

The REST API lives under `/api/`. It is started from a box that belongs to an API section:

```php
class Restapi extends WbengineBoxAbstract
{
    public function Restapi()
    {
        $api = new \Wbengine\Api();
        $api->Initialize();
        $api->end();
    }
}
```

Modules included in 3.1:

| Method and path | Purpose | Auth |
|-----------------|---------|------|
| `POST /api/auth/login` | exchange e-mail and password for a JWT | no |
| `POST /api/auth/jwt/revalidate` | exchange the refresh cookie for a new access token (rotates the refresh token) | refresh cookie |
| `POST /api/auth/logout` | revoke this device's refresh token and clear the cookie | cookie |
| `POST /api/auth/logout/all` | revoke the refresh tokens of every device | token |
| `POST /api/auth/jwt/identify` | check that a bearer token belongs to the `user_id` in the body | token |
| `GET /api/users` | list users (never returns the password hash) | admin |
| `GET /api/users/{id}` | read a user | admin, or the user themselves |
| `POST /api/users/create` | create a user (e-mail, password, optional profile fields and role) | admin |
| `PATCH /api/users/patch/{id}` | update profile fields or password; `role` only by an admin | admin, or the user themselves (not the role) |
| `DELETE /api/users/{id}` | delete a user (not yourself, not the last admin) | admin |
| `GET/POST /api/sections`, `GET/PUT/DELETE /api/sections/{id}` | sections CRUD | admin |

Conventions:

* Send `Authorization: Bearer <token>`. A missing, malformed, expired or wrongly signed token returns `401`. A valid token without the required role returns `403`.
* Roles are `user`, `editor` and `admin` (`cms_users.role`, class `Wbengine\User\Role`). The role is read from the database on every privileged call, so demoting or deleting a user takes effect immediately, not when their token expires. The token carries the role only so a client can show or hide controls.
* To protect your own action: `$this->isAuthenticated(function ($payload, $actor) { ... }, Role::EDITOR);`
* Writes accept only whitelisted fields. Unknown fields (for example `user_type`) are rejected with `400`, so a client cannot raise its own privileges.
* Success bodies are the plain data, errors are `{"success": false, "error": <code>, "message": "..."}`. Creates return `201` for sections.
* CORS: `Access-Control-Allow-Origin` defaults to `*`. Use `Api::setSingleOrigin()` and `Api::allowHeader('Authorization')` for a browser based admin application, and `Api::setHeaderAllowOredentials(true)` only together with a single explicit origin.

Deployment notes for cookie based refresh (unchanged from earlier versions):

* If cross-domain authentication must work, deploy the API website on the same hostname as the client's API calls.
* Both the client and the API server must run on HTTPS only, with a trusted certificate.

To add a module, extend `Wbengine\Api\WbengineRestapiAbstract`, wrap each action in `$this->isAuthenticated(fn () => ...)`, and add a `Routes` class next to it. Look at `Api/Section.php` and `Api/Section/Routes.php` as the smallest example.

## Authentication, sessions and users

* **Passwords** are stored with `password_hash()`. Legacy unsalted MD5 hashes still work and are upgraded to a modern hash on the user's next successful login, provided `cms_users.password` is at least 60 characters wide (`varchar(255)` is recommended). If the column is still narrow the legacy hash is kept and a notice is written to the error log, so a narrow column can never lock a user out.
* **Web login** (`Box\Auth`) uses a CSRF token, a failed attempt counter and reCAPTCHA after repeated failures. The password is read untouched from the request (`Http::PostRaw`), so special characters work.
* **Login rate limiting** covers the web form, the AJAX login and the API login. Within 15 minutes a client may fail 5 times for one account, 20 times over all accounts, and an account may fail 25 times from all addresses together; after that the answer is `429` with `Retry-After` (an error message on the web form). The strict limit is per IP and account pair, so an attacker cannot lock the real owner out from another address. A successful login clears the pair and account counters, not the IP counter. Counters are files in the temp directory (set `rateLimit.login.dir` to a shared directory with several servers). Behind a reverse proxy or load balancer the limiter sees the proxy's address unless the web server restores the real client IP (`mod_remoteip`, `real_ip_header`), otherwise all clients share one IP counter.
* **Sessions** are stored in `cms_sessions`, bound to the client IP and a hash of the user agent. The session cookie is `HttpOnly` and `SameSite=Lax` (and `Secure` over HTTPS), strict mode is on, and the session id is regenerated on login to prevent fixation. Stored session data is unserialized with `allowed_classes` limited to `stdClass`.
* **Refresh tokens** keep a client logged in without a long lived access token. Login returns a short lived access token (15 minutes by default) and sets the `refresh_token` cookie (`HttpOnly`, `SameSite=Strict`, scoped to `/api/auth/`, `Secure` over HTTPS, 7 days by default; `Api::setJwtTokenExpiration()`, `setCookieExpiration()`, `setCookieDomain()`, `setCookieSameSite()` change this). The cookie holds an opaque random token and the database stores only its SHA-256 hash (table `cms_refresh_tokens`). Each `POST /api/auth/jwt/revalidate` returns a new access token and replaces the cookie with a new token; the old one works no more. If an old token is presented again after a 10 second grace period it was copied, so the whole token family is revoked and both the thief and the owner must log in again. Logout revokes the device, `POST /api/auth/logout/all` every device, and a password change or deleting a user revokes all their tokens. An already issued access token stays valid until it expires, which is why it is short lived. Refresh is cookie based, so the API must be on the same site as the client (see the deployment notes above).
* **JWT** uses HS256 with two secrets (access and refresh). The secrets are read from the configuration or the environment and are never stored in the source. Authentication fails closed if a secret is missing or shorter than 32 characters.
* Access control for pages is the `acl` column of `cms_sites`: a value of `1` or more requires a logged in user.

## Configuration reference

`src/Config/Settings.json`:

| Key | Meaning |
|-----|---------|
| `timeZone` | PHP time zone, for example `Europe/Prague` |
| `codePage` | HTML charset, `utf8` |
| `debug` | `true` shows the debug bar and detailed errors. Never in production |
| `minimizeHtml`, `minimizeCss`, `minimizeJs` | output minification (HTML minification is a whitespace collapse, so avoid it with `<pre>`, `<textarea>` or inline scripts) |
| `cdnPath` | prefix for static assets |
| `cssFiles` | list of CSS files to combine when `minimizeCss` is on |
| `rateLimit` | `login`: `enabled` (default `true`), `maxAttempts` per IP and account (5), `ipMaxAttempts` per IP (20), `accountMaxAttempts` per account (25), `window` in seconds (900), `dir` for the counter files |
| `counters` | `views`: `enabled` (default `false`), `hits` (50) and `seconds` (60) per batch, `dir` for the counter files. Batches the article view counter |
| `cache` | `enabled` (default `false`), `driver` (`apcu` or `file`), `ttl` in seconds (default 300), `dir` for the file driver, `pages`: `{ "enabled": false, "ttl": 60 }` for the whole page cache. See the performance notes |
| `renderer` | `adapterName` (`SmartyRenderer`), `templatesDir`, `compiledDir`, `configDir`, `compileCheck` (default `true`; set `false` in production to skip the template change check, then clear the compiled directory on deploy) |
| `dbAdapterDefinition` | `adapterName` (`Mysqli`), `hostname`, `database`, `username`, `password`, `charset` |
| `jwt` | `accessKey` and `refreshKey`, at least 32 characters each |
| `recaptcha` | `secretKey` for the login form. Empty means captcha checks fail closed |
| `ipAdmins` | list of administrator IP addresses |

Environment variables `WBENGINE_JWT_KEY`, `WBENGINE_JWT_REFRESH_KEY` and `WBENGINE_RECAPTCHA_SECRET` override the matching settings. Generate keys with `openssl rand -hex 32`. Keep `Settings.json` out of version control.

## Debug tooling

With `"debug": true` a bar is appended to the page showing the PHP version, the configuration file, application time, the number of database queries and their total time, and the site, section and box counts. Opening the DB panel lists every query with its duration, HTML escaped. The error handler converts PHP warnings and errors into exceptions so they are not silently ignored; notices and deprecations are logged and do not stop the request.

Debug mode also turns on `display_errors`. It exposes SQL and paths, so it must never be enabled on a public server. With debug off, an uncaught exception shows a generic error page and the details go to the error log.

## Performance notes and roadmap

What the engine does well today: a single connection opened only when needed, no model hydration, compiled Smarty templates, bound parameter queries, and a debug bar that makes the query count visible on every page.

Measured queries per page in the skeleton application (debug bar):

| Page | Before | Cache off, new session handling | Cache on, warm |
|------|--------|-------------------------------|----------------|
| `/` first visit, no cookie | 9 | 6 | 0 |
| `/` returning visitor | 8 | 7 | 1 |
| `/blog/` (article plus view counter) | 10 | 9 | 3 |

Indicative throughput (ApacheBench, 2000 requests, concurrency 4, PHP 8.4 with opcache, Apache and MySQL 8.4 in Docker Desktop, home page of the skeleton): about 540 to 600 requests per second with the cache off against about 725 to 745 with the cache on (cache and `compileCheck: false`), roughly 25 to 30 percent more. The runs were noisy and some were dropped, so treat this as a direction and measure on your own hardware.

What changed:

* **Session:** the row is read once per request, created only when something is stored, written once at the end of the request, expired rows are purged on about 1 in 100 loads, and a request without a session cookie skips the lookup.
* **View counter** (opt-in): `"counters": {"views": {"enabled": true, "hits": 50, "seconds": 60}}` turns the `UPDATE` on every article view into one `UPDATE` per 50 views or per minute. The counts are kept in small locked files, so concurrent requests lose nothing (tested with parallel processes); only increments still pending when the server crashes are lost. The displayed count lags by at most one batch.
* **Cache** (opt-in): the site table, menu, sections and box lists. Enable with `"cache": {"enabled": true, "driver": "apcu", "ttl": 300}` (or `"driver": "file"`). APCu is the fastest and is shared by all PHP workers of a server; the file driver works everywhere and benefits from opcache. The API flushes the cache when sections change. After editing `cms_sites`, `cms_menu`, `cms_sections` or `cms_boxes` directly in the database, call `Wbengine\Cache::flush()` or wait for the TTL. When the cache is on, the site is looked up in PHP on the cached table of visible sites (links are compared case-insensitively, as the database collation does). That table is cached once, so random URLs cannot grow the cache. Very large site tables (tens of thousands of rows) are better served without the cache.

* **Page cache** (opt-in, needs the cache enabled): `"cache": {"enabled": true, "driver": "apcu", "pages": {"enabled": true, "ttl": 60}}`. Anonymous visitors get a stored copy of the page, served before any box runs. A hit costs no database query for a visitor without a session cookie and one session lookup for a returning visitor (to be sure they are not logged in). A page is stored only when it is the same for everybody: a plain `GET` without a query string or `Authorization` header, for a visitor who is not logged in, a `200` HTML response, no write to the session (so the login page with its CSRF token is never stored), the site does not require a login, and the URL is exactly the site's own link (a deeper path that falls back to it is not stored, which also keeps the number of entries bounded by the number of sites). Responses carry `X-WBengine-Cache: HIT`, `MISS-STORED` or `BYPASS`, plus an `ETag` and `304` support. The TTL (60 seconds by default) bounds staleness; the API flushes the pages when sections change, other edits need `Cache::flush()` or the TTL. If a page depends on anything besides the URL and the login state (device type, a language cookie, the time), do not enable the page cache for that site. Indicative throughput of the skeleton home page in the same Docker VM: about 570 requests per second without any cache, about 725 with the data cache, about 1,175 with the page cache (a single completed run, treat it as a direction).

Still open, in the order I recommend:

1. A small template compiler to plain PHP with automatic output escaping and per box fragment caching. Smarty 5 stays available behind `RendererInterface` during migration. This is worth doing after the query count is down, because the queries dominate today.
4. Production setup: opcache with `composer dump-autoload --classmap-authoritative`, `php.ini-production`, and `"compileCheck": false` in the renderer settings (verified: Smarty then stops checking template files on every request).

## Security notes

* Use bound parameters for every value (see the database layer).
* Keep `debug` off in production and `Settings.json` out of git.
* The API authorizes by role (`user`, `editor`, `admin`) checked against the database. Give every new API action an explicit role: `isAuthenticated()` without one only checks that the token is valid.
* Smarty output is not escaped by default. Escape values in templates (`{$value|escape}`) until automatic escaping is added.
* Put TLS in front of the application. The session cookie is marked `Secure` only when the request arrives over HTTPS.
* Report problems privately to the maintainers.

## Development and tests

The test environment is Docker only: PHP 8.4 CLI and MySQL 8.4.

```bash
docker compose run --rm php composer install
docker compose run --rm php composer test          # unit and integration suites
docker compose run --rm php composer test:unit     # no database needed
```

Use `PHP_VERSION=8.3 docker compose build` (and `docker compose run --rm php composer test`) to test another PHP version; 8.3, 8.4 and 8.5 are supported and run in CI on every push (`composer lint` checks the syntax of every file).

The integration tests create their tables from `tests/schema.sql`. They are skipped when the database is not reachable. For new installations use the `utf8mb4` connection charset: the older `utf8` alias is three bytes per character and cannot carry emoji.

## Upgrading from 3.0

* PHP 8.3 or newer is required.
* `firebase/php-jwt` moved from 5 to 7. If you call it yourself, decode with a `Key` object.
* JWT signing keys are no longer built in. Add a `jwt` section to `Settings.json` or set the environment variables. Authentication refuses to start without them. The keys that used to ship with the library are public and must not be used.
* Add a `recaptcha` section if you use the login captcha. The previous built in secret is removed.
* Widen the password column: `ALTER TABLE cms_users MODIFY password varchar(255) NOT NULL DEFAULT '0';`
* Replace string built SQL with bound parameters. `Db::query()` and the fetch helpers accept an array of parameters. `Db::createUpdateQuery()` is removed, use `Db::update()`.
* `SiteModel::getUrlId()` is replaced by `getUrlCandidates()`, which returns an array.
* The `/api/sections` routes now require an admin token, and users and sections accept only whitelisted fields.
* Create the refresh token table (`schemas/migrations/003-add-refresh-tokens.sql` in the skeleton). Without it logging in still works, but refreshing answers 401. Clients must log in again once, the old refresh cookie is not accepted. Check your cookie settings: the defaults changed (access token 15 minutes, refresh token 7 days, cookie path `/api/auth/`, `SameSite=Strict`).
* Add `cms_users.role` (`ALTER TABLE cms_users ADD COLUMN role varchar(20) NOT NULL DEFAULT 'user';`) and promote your administrators, otherwise privileged API calls answer 403.
* API routes accept URLs with or without a trailing slash. If your `.htaccess` adds a trailing slash with a 301 redirect, exclude `/api`, because a redirect turns a POST into a GET.

## License

MIT
