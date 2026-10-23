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

* PHP 8.3 or newer. PHP 8.4 is the development target and is covered by the checks below.
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
| `POST /api/auth/logout`, `/jwt/identify`, `/jwt/revalidate` | session and token handling | cookie or token |
| `GET /api/users`, `GET /api/users/{id}` | list and read users (never returns the password hash) | token |
| `POST /api/users/create` | create a user (e-mail, password, optional profile fields) | token |
| `PATCH /api/users/patch/{id}` | update profile fields or password | token |
| `DELETE /api/users/{id}` | delete a user | token |
| `GET/POST /api/sections`, `GET/PUT/DELETE /api/sections/{id}` | sections CRUD | token |

Conventions:

* Send `Authorization: Bearer <token>`. A missing, malformed, expired or wrongly signed token returns `401`.
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
* **Sessions** are stored in `cms_sessions`, bound to the client IP and a hash of the user agent. The session cookie is `HttpOnly` and `SameSite=Lax` (and `Secure` over HTTPS), strict mode is on, and the session id is regenerated on login to prevent fixation. Stored session data is unserialized with `allowed_classes` limited to `stdClass`.
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
| `cache` | `enabled` (default `false`), `driver` (`apcu` or `file`), `ttl` in seconds (default 300), `dir` for the file driver. See the performance notes |
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
* **Cache** (opt-in): the site table, menu, sections and box lists. Enable with `"cache": {"enabled": true, "driver": "apcu", "ttl": 300}` (or `"driver": "file"`). APCu is the fastest and is shared by all PHP workers of a server; the file driver works everywhere and benefits from opcache. The API flushes the cache when sections change. After editing `cms_sites`, `cms_menu`, `cms_sections` or `cms_boxes` directly in the database, call `Wbengine\Cache::flush()` or wait for the TTL. When the cache is on, the site is looked up in PHP on the cached table of visible sites (links are compared case-insensitively, as the database collation does). That table is cached once, so random URLs cannot grow the cache. Very large site tables (tens of thousands of rows) are better served without the cache.

Still open, in the order I recommend:

1. Count article views in batches instead of an `UPDATE` on every view.
2. Cache whole HTML responses for anonymous GET requests.
3. A small template compiler to plain PHP with automatic output escaping and per box fragment caching. Smarty 5 stays available behind `RendererInterface` during migration. This is worth doing after the query count is down, because the queries dominate today.
4. Production setup: opcache with `composer dump-autoload --classmap-authoritative`, `php.ini-production`, and `"compileCheck": false` in the renderer settings (verified: Smarty then stops checking template files on every request).

## Security notes

* Use bound parameters for every value (see the database layer).
* Keep `debug` off in production and `Settings.json` out of git.
* The API currently authorizes by "has a valid token". Before exposing it beyond administrators, add a role claim to the token and check it in each module.
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

The integration tests create their tables from `tests/schema.sql`. They are skipped when the database is not reachable. For new installations use the `utf8mb4` connection charset: the older `utf8` alias is three bytes per character and cannot carry emoji.

## Upgrading from 3.0

* PHP 8.3 or newer is required.
* `firebase/php-jwt` moved from 5 to 7. If you call it yourself, decode with a `Key` object.
* JWT signing keys are no longer built in. Add a `jwt` section to `Settings.json` or set the environment variables. Authentication refuses to start without them. The keys that used to ship with the library are public and must not be used.
* Add a `recaptcha` section if you use the login captcha. The previous built in secret is removed.
* Widen the password column: `ALTER TABLE cms_users MODIFY password varchar(255) NOT NULL DEFAULT '0';`
* Replace string built SQL with bound parameters. `Db::query()` and the fetch helpers accept an array of parameters. `Db::createUpdateQuery()` is removed, use `Db::update()`.
* `SiteModel::getUrlId()` is replaced by `getUrlCandidates()`, which returns an array.
* The `/api/sections` routes now require a token, and users and sections accept only whitelisted fields.
* API routes accept URLs with or without a trailing slash. If your `.htaccess` adds a trailing slash with a 301 redirect, exclude `/api`, because a redirect turns a POST into a GET.

## License

MIT
