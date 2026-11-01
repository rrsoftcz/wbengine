# WBengine

WBengine is a small object oriented PHP framework for database driven websites and backends. A page is not a script: it is a tree of objects assembled from database rows, so a site can be changed by editing data instead of code.

```
Application
  Site                 one row of cms_sites, chosen by the URL
    Section            header, central, right ... (cms_sections)
      Box              one unit of content (cms_boxes): a PHP class + method
        static box     a template, rendered as is
        dynamic box    data from the database through a Model, then a template
```

The framework is built with performance in mind: no ORM, no reflection-heavy container, one lazily created connection, compiled templates, and a built in debug bar that shows what a request really cost. A scoped, audited HTTP API (API v2) lets a central administration application manage a site server to server.

A ready to run example application lives in the companion project [`wbengine-skeleton-app`](https://github.com/rrsoftcz/wbengine-skeleton-app).

**See it running: [www.wbengine.org](https://www.wbengine.org).** That site is the skeleton application itself, so every page you read is built with the feature it describes.

* [Documentation](https://www.wbengine.org/docs/) of every part of the framework, in English and Czech.
* [Blog](https://www.wbengine.org/blog/) and [changelog](https://www.wbengine.org/changelog/) with every release.
* [Performance](https://www.wbengine.org/docs/performance/): how long the core needs to assemble a page, and how that was measured.
* [`wbengine-admin`](https://github.com/rrsoftcz/wbengine-admin): the optional central administration, a separate application that manages several sites through API v2.

## Contents

1. [Requirements](#requirements)
2. [Install](#install)
3. [Core concepts](#core-concepts)
4. [Request lifecycle](#request-lifecycle)
5. [Writing boxes](#writing-boxes)
6. [Template engine](#template-engine)
7. [Internationalisation](#internationalisation)
8. [Database layer](#database-layer)
9. [Routing and the API](#routing-and-the-api)
10. [Authentication, sessions and users](#authentication-sessions-and-users)
11. [Configuration reference](#configuration-reference)
12. [Debug tooling](#debug-tooling)
13. [Performance notes](#performance-notes)
14. [Security notes](#security-notes)
15. [Command line tools](#command-line-tools)
16. [Upgrading](#upgrading)

## Requirements

* PHP 8.3 or newer. PHP 8.4 is the development target. 8.3, 8.4 and 8.5 are tested in CI.
* Extensions: `mysqli`, `json`, `mbstring`
* MySQL 8 or MariaDB 10.6+ (box selection uses `JSON_SEARCH`). Tested on MySQL 8.4, MariaDB 10.11 and MariaDB 11.4.
* A web server that sends every request that is not a file to `index.php`: Apache with `mod_rewrite` or Nginx with PHP-FPM (both tested, the skeleton has the configuration for each)
* No runtime Composer packages. Templates and Markdown use built-in engines; Smarty compatibility and offline Texy migration are optional separate packages.

PHP has no long term support releases. 8.4 receives security fixes until the end of 2028 and 8.3 until the end of 2027, which is why 8.3 is the minimum.

## Install

```bash
composer require wbengine/wbengine
```

The framework is a library: your application provides the entry point, templates, box classes and configuration. The quickest way to see it working is the [skeleton application](https://github.com/rrsoftcz/wbengine-skeleton-app), which has a Docker setup and demo data and runs live at [www.wbengine.org](https://www.wbengine.org).

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
View/Compiled/           Compiled template cache, must be writable
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
* framework bundled boxes ship their own template and logic: `Wbengine\Box\Article` (an article from `cms_articles`, with explicit Markdown or preserved legacy HTML) `Wbengine\Box\Auth` (the login form with CSRF token and optional reCAPTCHA) and `Wbengine\Box\Integrations` (the screen where an administrator issues, rotates and revokes API v2 integration keys, see below).

**Supporting classes**

| Class | Purpose |
|-------|---------|
| `Db` | static facade over one lazy MySQL connection, with bound parameters and a query log |
| `Config` | the parsed `Settings.json` |
| `Router`, `Router\Route` | method and path matching with `{param}` placeholders |
| `Url` | the parsed request URL |
| `Session`, `User`, `User\Password` | database backed session, the current user, password hashing |
| `Api` | API v2 (`Api/V2`): integration keys, scopes, content, users, audit |
| `Renderer` | native compiled templates (`NativeRenderer`); optional Smarty compatibility |
| `Vars`, `Registry` | template variable bag, key value store |
| `Debug`, `Error\Handler` | debug bar, error to exception conversion |
| `Application\Path`, `Application\Env\*` | path registry, HTTP and environment helpers |

## Request lifecycle

```text
new Application()              config, paths, error handler, timezone, early i18n
configure services / variables
register Application routes    registration only, callbacks have not run
$App->run()
  custom routes                first accepted match; true completes the response
  pending canonical redirect   only when no custom route accepted the request
  page-cache gate              after route policies and application configuration
  Site::initialize()           unless central content was supplied explicitly
    site lookup / ACL / menu / metadata
    active sections -> boxes -> collected content
  404 or explicit 410 / debug
  Renderer::dispatch()         at most one layout per Application instance
```

The constructor performs no CMS queries or box execution, does not serve a
page-cache HIT, and does not exit for a canonical-language redirect. I18n still
normalizes the request early so route patterns see the path without a locale prefix.
Redirects are emitted by `run()` after custom routes have had a chance to respond.
A site getter may create an empty Site object; its database resource is loaded later.

`run()` and `display()` share one lifecycle and repeated calls do not render another
layout. `display($content)` also accepts an empty string or `"0"` as explicit central
content. A route may call `display()` directly; ordinary CMS boxes should return their
content through the existing Section/Box tree. Register routes before either entry
point starts handling the request.

Pre-run central content avoids CMS initialization. Changes to application Vars
(including direct `getClassVars()->addValue()` calls) are compared with the bootstrap
snapshot and conservatively bypass page cache. Accessing the renderer before the
cache gate also bypasses it, so direct renderer assignments cannot be skipped by an
old HIT. Custom accepted routes, prior response output or a non-200 status likewise
bypass shared page cache. The existing bypass flag also skips public native
fragments. This can reduce cache HITs for customized requests; use
ordinary CMS boxes for cacheable visitor-independent page generation.

**Migration from 3.x:** construct the Application, configure it and register its
routes, then call `run()` or `display()`. Application route callbacks no longer run
at registration, CMS variables/box side effects are no longer available immediately
after construction, and debug output is prepared during handling. Move code that
requires loaded CMS data into the CMS handling phase or make its model access
explicit. Use one Application instance per request. Clear existing page caches on
deployment so old entries cannot retain policies from the previous lifecycle.

A typical anonymous page costs roughly eight to ten queries: the site, the sections, the menu, one box query per section, and the session. See the performance notes for how to cut that.

### The version of the framework

The layout variable `wbengine_version` holds the running version, for example `6.7.0`, for a header or footer: `{$wbengine_version}`. It comes from Composer and is `dev` when the framework is not an installed release (a checkout or a branch), never a made-up number. `Wbengine\Version::current()` returns the same value in PHP.

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

## Template engine

**NativeRenderer** is the built-in renderer and the default. The optional `wbengine/smarty-compat` package provides the transitional Smarty adapter. The **native engine** is a small compiler that turns a template into plain PHP once and then just includes that file, so with opcache a render is running compiled code. Select it in `Settings.json`:

```json
"renderer": { "adapterName": "NativeRenderer", "autoEscape": true, "compileCheck": false }
```

### Syntax

A subset of Smarty, so existing templates mostly work unchanged:

```smarty
{* a comment *}
<h1>{$title}</h1>                        {* escaped *}
{$article->introtext|raw}                {* trusted HTML, printed as is *}
{$user.address.city}  {$menu[0]->name}   {* array keys, properties *}
{$item->active()}                        {* method call *}
{$name|default:'anonymous'|upper}        {* modifiers *}
{'/blog/'|lurl}                          {* quoted text with a modifier *}

{if $n > 1 && !$hidden}...{elseif $n === 1}...{else}...{/if}

<ul>
{foreach $menu as $m}
  <li><a href="{$m->link}">{$m->name}</a></li>
{foreachelse}
  <li>empty</li>
{/foreach}
</ul>

{set $total = $price * $count}
{include 'Shared/footer.tpl'}
{literal}{$not parsed}{/literal}

{cache 'sidebar' ttl=300}...expensive, rendered once per 5 minutes...{/cache}
{cache 'item-' ~ $id}...key may use variables...{/cache}
```

Operators: `== != === !== < <= > >= && || ! and or not + - * / % ~` (`~` joins text). Modifiers: `raw`, `nofilter`, `escape`, `upper`, `lower`, `trim`, `default`, `truncate`, `nl2br`, `json`, `url`, `length` (`count`), `date`, `join`. A `{` that does not start a tag is plain text, so inline CSS and JavaScript need no `{literal}`. The line break right after a control tag (`{if}`, `{foreach}`, `{/if}` ...) is dropped, as in Smarty.

### Security model

* **Escaping is on by default in both WBengine renderer adapters.** `{$x}` is HTML-escaped. In the native engine the final modifier of the whole expression determines whether output is already escaped or explicitly trusted: `|escape`, `|nl2br`, `|json`, `|url`, `|raw` or `|nofilter`. An earlier safe modifier does not make a later fallback, concatenation operand, method result or array value safe. Put `raw` last only for trusted HTML. `|json` is suitable for a script element, not a quoted HTML attribute.
* **A template cannot run PHP.** Expressions use a fixed grammar: no function calls (only the modifiers above), no assignment, no constants, no `new`. PHP tags written in a template are plain text, and template syntax inside data is never interpreted.
* **A template cannot read arbitrary files.** Names resolve inside the template directory and the framework's own bundled template directories only; `..`, absolute paths elsewhere and symlinks out of the root are refused.
* **Missing things are empty.** An undefined variable, key or property, a method on `null`, or an array printed as text gives an empty string, never a warning (which this framework's error handler would turn into an exception).

### Output contexts and template trust

Template files are trusted application code. The native grammar rejects free PHP
and restricts file includes, but objects passed to templates can expose callable
public methods. Do not let untrusted editors upload templates or pass privileged
application objects to templates intended for them. Database text is data and must
never be parsed again as a template.

Automatic HTML escaping protects ordinary text and values inside quoted HTML
attributes. It does not validate URL schemes: validate full `href`/`src` URLs in the
application, and use `|url` for individual URL components. Do not insert ordinary
variables into inline JavaScript, CSS, event handlers or unquoted attributes.
In the native engine use `|json` for a complete JavaScript value; inside a quoted
HTML attribute use `|json|escape`. The JSON modifier is native-only. For the temporary
Smarty adapter, generate script-safe JSON in application code and mark only that
trusted serialized value as raw. Explicit `raw` is never suitable for user HTML
unless it has been handled by a dedicated safe content renderer.

Place escaping after transformations: `{$value|default:$fallback|escape}`. Escaping
only one operand before concatenating additional data is insufficient; final automatic
escaping may also encode an already escaped operand again. Normalize data first and
escape the final output once. Clear data/page caches and compiled templates when
deploying changed templates or escaping policy. With `compileCheck: true`, native
freshness includes a source checksum and detects same-size edits with unchanged file
timestamps. Production `compileCheck: false` skips this per-render checksum.

### Moving from Smarty

The temporary Smarty adapter also escapes by default. Legacy layouts must
mark trusted section HTML, such as `{$header|raw}`, `{$central|raw}` and
`{$debug|raw}`, explicitly. Ordinary database values remain escaped. A temporary
`autoEscape: false` setting is available while migrating those templates.

1. Inventory each application's templates, direct Smarty calls, plugins and configuration. Run the read-only `tests/tools/template-inventory.php` aid and review its hints. Known unsupported Smarty constructs now throw TemplateException with source and line. Successful compilation alone is still not proof of compatibility: other unknown plugin tags can remain literal text.
2. Convert unsupported constructs explicitly. Native include uses a path expression without `file=`, and set replaces Smarty assign. The 43-scenario legacy common-subset corpus remains covered, but the current skeleton also uses native-only set/t tags. Default with zero, truncate limits and nl2br output differ between adapters; use the new migration corpus to review expected output.
3. Keep automatic escaping enabled, mark only trusted section/formatted HTML with `|raw`, and verify ordinary data in its output context. `autoEscape: false` is a temporary option for controlled legacy migration, not a compatibility guarantee.
4. After comparing real page output and login/CSRF/logout, locale and cache behavior, select `NativeRenderer` and clear compiled, fragment, page and external HTML caches. Keep matching source/configuration backups for rollback; native-only templates cannot simply be switched back to Smarty.

See [the versioned template inventory and migration contract](ref-template-migration.md)
for source identities, adapter API differences, exact expected outputs and the
TPL-02/TPL-03 gates. Smarty is no longer a core dependency. See `compat/smarty/README.md` for the separate compatibility test/install profile.
Smarty plugins, capture/section, config files, custom delimiters, smarty metadata
and inheritance are outside the supported native language; migrate them before
removing the compatibility dependency.

Compiler version 5 prevents reuse of older compiled files even when
`compileCheck` is disabled. Reserved Smarty syntax (such as assign, extends,
block/capture and their closing tags) now reports an explicit error. Ordinary
CSS/JS object keys and loop/declaration forms remain covered by regression tests;
exact syntax collisions, such as JS shorthand `{capture}`, need a literal block.
See the reference for the finite detection rules and remaining ambiguities.
Clear all HTML and compiled caches during deployment. TPL-02 is still in progress.

Native partial paths resolve from templatesDir, including inside nested partials.
Partials inherit current values but local set assignments do not overwrite their
caller or the next fetch. Object references remain shared developer-owned objects.
Renderer and native cache blocks collect ordinary nested output buffers on success
and discard owned buffers on exceptions. Services must not close renderer buffers
or create non-removable buffers; those PHP side effects cannot be fully recovered.

### Performance

The compiled template is a PHP file, so opcache keeps it in memory. Measured on the skeleton layout (6 menu items, about 9.7 KB of output): repeated renders in one process take about 11 microseconds with the native engine and 9 with Smarty (the native engine also escapes). On a cold request, the case that matters for PHP-FPM and Apache, the native engine adds about 2.3 ms over a bare PHP process against 4.5 ms for Smarty, because Smarty's classes are not loaded and initialised. The saving is about 2 ms per request, which is real but small next to a database query; the main reasons to switch are the automatic escaping and the fragment cache. Set `"compileCheck": false` in production (and clear the compiled directory on deploy) so the source files are not checked on every request.

## Markdown content

WBengine now includes its own block/inline Markdown parser and immutable content
tree with a separate safe HTML renderer, without a Markdown package dependency.
Use `(new \Wbengine\Markdown())->render($source)`. It supports CommonMark 0.31.2
and GFM tables, strikethrough and task lists. Raw HTML is escaped and URL schemes
are restricted to http/https/mailto and relative paths. Default limits include
1 MiB input and 8 MiB expanded HTML output; exceeding work/depth/size budgets
throws LengthException. There is no unsafe HTML mode.

See [the Markdown contract and conformance evidence](ref-markdown.md) for all
652 official CommonMark examples, 12 GFM examples, 76 exact safe-output deviations,
source attribution and regression cases discovered beyond the official corpus.
Use `$app->getMarkdown()` for the lazy shared service. NativeRenderer also accepts
`.md` sources directly and inside an HTML layout's include: the file is Markdown
content throughout, so PHP, variables and template loops are never executed.
Requested or canonical `.md` extensions (case-insensitive) select this path, roots
are validated first and reads are bounded to the parser input limit.

Optional `migrations/4.0.0/003-markdown-content.sql` adds explicit article/translation
`content_format` values `legacy_html` and `markdown`. Existing and unmigrated rows
retain their previous trusted HTML bytes. New Markdown fields render through the
safe parser. Partial translations inherit each omitted field's original format;
changing an existing translation format requires both text fields to be replaced
or cleared and rejects stale concurrent format changes. Normal content writes must
still invalidate caches. Texy is removed from the core. The reviewed reversible offline converter is documented in `ref-markdown.md` and `compat/texy-migration/README.md`. The legacy `Renderer::getFormater()->process($source)` entry now processes Markdown. Migrate actual Texy sources explicitly before using it; Texy runtime selection fails with migration instructions.

## Internationalisation

The user interface can be shown in several languages. It is off by default. Switch it on in `Settings.json`:

```json
"i18n": {
  "enabled": true,
  "default": "en",
  "locales": ["en", "cs"]
}
```

### How the language is chosen

For every request the first of these that gives a supported locale wins:

1. **A language prefix in the URL**: `/cs/blog/`. It always wins, and it is removed from the URL before anything else reads it, so sites, boxes and routes are written without it. The default language has one canonical URL without a prefix: `/en/blog/` is redirected to `/blog/` (a `301`, only for `GET` and `HEAD`, the query string is kept).
2. **The cookie** `wb_locale`, which remembers the last explicit choice (set when a prefixed URL is visited).
3. **The `Accept-Language` header** of the browser, with quality values (`cs-CZ,cs;q=0.9,en;q=0.5`). `cs-CZ` matches the supported `cs`.
4. **The default locale.**

A value that is not a supported locale is ignored (a cookie with garbage, a language the site does not have). The response carries `Content-Language` and `Vary: Accept-Language, Cookie`, so proxies and CDNs do not mix languages up. Settings:

| Key | Meaning |
|-----|---------|
| `enabled` | `false` by default |
| `default` | the fallback locale (`en`) |
| `locales` | supported locales, `["en"]` by default. `"pt_BR"` is written `/pt-BR/` in URLs |
| `detect` | the sources and their order, default `["url", "cookie", "header"]` |
| `urlPrefix` | `true`: use `/cs/` prefixes. `false`: only cookie and header decide |
| `prefixDefault` | `true`: the default language has a prefix too (`/en/blog/`) |
| `cookie`, `cookieDays` | name (`wb_locale`) and lifetime (365) of the cookie |
| `dir` | where the application's catalogs are, `/Locale/` under the application directory |
| `baseUrl` | the public address, `"https://example.com"`, used for the absolute `hreflang` links. Without it the address is taken from the request |

### hreflang links

Search engines and Lighthouse require `hreflang` URLs to be absolute. The layout variable `hreflangs` holds them (code to URL, plus `x-default` for the default language):

```smarty
{foreach $hreflangs as $code => $url}<link rel="alternate" hreflang="{$code}" href="{$url}">{/foreach}
```

Set `i18n.baseUrl` in production. Without it the address comes from the `Host` header, which a client controls: it is accepted only when it looks like a host name, and the page cache then keeps one copy per host, so a forged `Host` cannot put its address into the page other visitors get. With `baseUrl` there is one copy and the header is ignored. If neither is known (no `Host`, as on the command line), `hreflangs` is empty. `I18n::origin()` and `I18n::hreflangs($path)` are the PHP side.

### robots.txt and sitemap.xml

`Wbengine\Seo\Crawlers::register($app)` adds `/robots.txt` and `/sitemap.xml`. It is opt-in: call it before `run()`, an application that does not call it changes nothing. Both are generated for each request, are not stored in the page cache and are not counted as page views.

```php
\Wbengine\Seo\Crawlers::register($App);
$App->run();
```

The sitemap lists the pages a visitor can open by their link: visible, public (`acl` 0) and without a hidden article, the same conditions that load a page. A page is listed once per language, with `xhtml:link` alternates and `x-default`, in agreement with the `hreflang` links. The default language is always listed, another language only when the page has a translation. Only the link of a page is listed. A page that is not strict is a dynamic box that receives the rest of the address as parameters, and the framework cannot know which parameter addresses the box accepts. `lastmod` is the date of the newest published article of the page and is left out for a page without an article. `changefreq` and `priority` are never written. The sitemap needs the public address (`i18n.baseUrl` or the request host) and answers 404 without it; it lists up to 50,000 addresses.

`robots.txt` allows everything, lists the paths of `seo.disallow` and names the sitemap. Settings under `seo`:

| Key | Meaning |
|-----|---------|
| `allowIndexing` | `false` closes the whole site (`Disallow: /`, no sitemap), for a staging copy. Default `true` |
| `disallow` | list of paths for `robots.txt`, each starting with `/` |
| `sitemapExclude` | list of page links to leave out of the sitemap, each starting with `/`, for example `["/login/"]` |

### Catalogs

One PHP file per locale in `src/Locale/` (`en.php`, `cs.php`, `pt_BR.php`). PHP arrays are compiled by opcache, so there is nothing to parse per request:

```php
return [
    'greeting' => 'Hello %name%',
    'files'    => ['one' => '%count% file', 'other' => '%count% files'],
];
```

A message is looked up in the locale, then in its language (`pt_BR`, then `pt`), then in the default locale. If it is nowhere, the key itself is shown, so a missing translation is visible on the page. `Wbengine\I18n::translator()->getMissing()` lists them in development. Parameters are `%name%`. Plural forms follow the CLDR rules of the language: Czech and Slovak `one` (1), `few` (2 to 4), `many` (fractions), `other`; Polish and Russian have their own rules; English and German `one` and `other`; French and Portuguese treat 0 and 1 as `one`; Japanese, Chinese and Korean have a single form. The framework's own messages (the login box, the article box, month names) are in `I18n/messages/` and start with `wb.`; an application overrides any of them in its own catalog.

### In templates

```smarty
{t 'greeting' name=$user.name}                  {* Hello Eva *}
{t 'files' count=$n}                            {* 1 file, 5 files, or 5 souborů in Czech *}
{t 'files' count=($a + $b)}                     {* an expression in parentheses *}
{t 'terms' url=$link raw}                       {* the message contains HTML, parameters are still escaped *}
{$label|t}                                      {* the value is the key *}
<a href="{$m->link|lurl}">{$m->name}</a>       {* /blog/ becomes /cs/blog/ in Czech *}
{$price|number:2}  {$date|ldate:'long'}         {* 1 234,50 and 5. října 2026 *}
```

Translated text is HTML-escaped like any output. With `raw` the message is trusted HTML (a link inside a sentence) but each parameter is escaped first, so a hostile value cannot inject markup. `|lurl` leaves external URLs, anchors and `mailto:` alone. The modifiers `t`, `lurl`, `number` and `ldate` also work in Smarty templates; the `{t}` tag is native-engine only.

The layout variables `locale` (`cs`), `lang` (`cs`) and `alternates` (a locale to URL map of the current page) are available for the language switcher and `hreflang` links:

```smarty
<html lang="{$lang}">
{foreach $alternates as $code => $url}<link rel="alternate" hreflang="{$code}" href="{$url}">{/foreach}
<ul>{foreach $alternates as $code => $url}<li><a href="{$url}" hreflang="{$code}">{$code|upper}</a></li>{/foreach}</ul>
```

### In PHP

```php
use Wbengine\I18n;

I18n::t('greeting', ['name' => $name]);      // translate
I18n::t('files', [], $count);                // plural
I18n::locale(); I18n::language();            // "cs_CZ" and "cs"
I18n::url('/blog/');                         // "/cs/blog/" in Czech
I18n::number(1234.5, 1); I18n::date($time, 'long');
I18n::setLocale($user->locale);              // e.g. a saved preference, for the rest of the request
```

Numbers and dates use built-in tables (no `intl` extension needed), for English, Czech, Slovak, Polish, German and French; other languages use the English conventions.

### With the page cache

The page cache key includes the locale, so a page cached in English is never served to a Czech visitor of the same URL, and the ETags differ per language (verified in the running skeleton). A `/cs/blog/` request and a Czech visitor of `/blog/` share one copy.

### Translating database content

Site titles and meta tags, menu names and articles are translated with one table per kind, keyed by the item and the locale. The default language is the content itself (the original columns), so a translation exists only for the other locales and a missing one falls back to the default, field by field: a Czech article with only a translated title still shows the default intro and body.

| Kind | Table | Translatable fields |
|------|-------|---------------------|
| `site` | `cms_site_translations` | `title`, `html_title`, `html_description`, `html_keywords` |
| `menu` | `cms_menu_translations` | `name`, `description` |
| `article` | `cms_article_translations` | `title`, `introtext`, `bodytext` |

The overlay is applied in the models (`SiteModel::loadSiteData`, `getSiteMenu`, `ArticleModel::getArticleRow`), so templates and boxes need no change. Links, ordering, visibility and everything else stay as in the default language. The small tables (sites, menu) are read once per locale and held in the data cache; articles are read by id, so large bodies never fill the cache. In the default language, and when `i18n` is off, no extra query is made. If the translation tables do not exist yet (an upgraded database), the application logs it once and keeps working in the default language; create them with the migration in the skeleton (`schemas/migrations/005_content_translations.sql`).

Editors manage translations through the API (role `editor` or higher):

| Method and path | Purpose |
|-----------------|---------|
| `GET /api/translations/{kind}/{id}` | all translations of one item |
| `PUT /api/translations/{kind}/{id}/{locale}` | store the fields given in the JSON body; fields not given stay, `null` or `""` falls back to the default |
| `DELETE /api/translations/{kind}/{id}/{locale}` | show the item in the default language again |

Unknown kinds and missing items answer `404`; an unsupported locale, the default locale, an unknown field, a non-text value or a text longer than the column answer `400`. Writing flushes the cache. Pages stored by the page cache are keyed by locale, so each language gets its own translated copy.

If you write the tables directly in SQL, call `POST /api/cache/flush` afterwards, as for any other direct edit.

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

### Reading request data

`Http::Post($name)` and `Http::Get($name)` return the corresponding PHP-parsed value
unchanged, including nested arrays. Omit the name to read the whole POST or GET map.
A missing field returns null without a warning; integer/string keys such as `0` and
`"0"` work normally. Other key types return null.

Use `Http::PostString($name)` and `Http::GetString($name)` when the field must be a
string. They return null for arrays and other non-string values, preserving empty
strings and `"0"`. `Http::PostRaw($name)` remains an alias of `PostString`.
`Http::getCookie($name)` likewise returns an unchanged string or null, rejecting
array-shaped cookies. These helpers do not trim, add or remove backslashes, turn
characters into HTML entities, strip tags or silently repair invalid UTF-8.
"Unchanged" means after PHP parsed the HTTP input, not the original wire bytes.

Validate each field's type, encoding, length and meaning separately. A string-only
helper is a shape check, not a sanitizer. For example:

```php
use Wbengine\Application\Env\Http;
use Wbengine\Db;

$title = Http::PostString('title');
if ($title === null || !mb_check_encoding($title, 'UTF-8') || mb_strlen($title, 'UTF-8') > 100) {
    throw new \InvalidArgumentException('Invalid title.');
}
$id = Db::insert('cms_sections', ['title' => $title, 'key' => 'content']);
```

Pass values as SQL parameters and whitelist writable fields; never pass a whole
request map directly to a database write. Render ordinary text with `{$title}`
(automatic escaping) or explicit `|escape`. Do not mark a value as raw just because
it came through a request helper. Keep password bytes unchanged; apply the intended
password policy without HTML encoding or slash manipulation.

**Migration:** `Http::secureClean()` has been removed. Replace
calls with the appropriate input accessor and field validation, and escape at the
output context. Audit direct `echo` calls, custom renderers and templates using
`autoEscape: false`; those no longer receive pre-escaped input. Previously stored
backslashes or HTML entities are not automatically undone: blanket `stripslashes`
or entity decoding would also corrupt legitimate historical content.

### JSON request bodies

`Http::Json()` (or `Http::Json(true)`) reads `php://input` and decodes it once.
JSON objects become associative arrays; arrays and scalar values retain PHP's
normal JSON conversion. `Http::Json(false)` validates the body and returns its
original text, including whitespace. Only boolean `true` selects decoded output;
the legacy behavior of other arguments is preserved.

Invalid JSON, an empty body, invalid UTF-8 or excessive nesting return null without
throwing. A valid JSON `null` also returns null in decoded mode, but leaves
`json_last_error()` as `JSON_ERROR_NONE`. Capture/check that status immediately,
before another JSON operation overwrites it:

```php
$body = Http::Json();
$error = json_last_error();
if ($error !== JSON_ERROR_NONE || !is_array($body)) {
    http_response_code(400);
    return;
}
// Validate allowed fields and permissions before passing data to the model.
```

`Http::isJson($text)` remains a standalone validator, including true for the text
`null`. Default PHP depth (512), number conversion and error codes are unchanged. The
legacy rejection of object keys starting with a NUL character is also preserved,
including `JSON_ERROR_INVALID_PROPERTY_NAME` in both decoded and raw modes.
The helper does not enforce a media type; controller-specific requirements, including
the cookie API JSON/custom-header contract below, still apply. Repeated calls read
and parse the body independently; no request body is retained in a static cache.

### CSRF protection

The session owns a lazy `Wbengine\Session\Csrf` service, available as
`$app->getCsrf()` or `$session->getCsrf()`. Use it explicitly in every custom form
or cookie-authenticated action that changes state. It does not add middleware or
start a session on ordinary public requests. Calling the Application accessor does
obtain the PHP session; request tokens only on routes that need them.

Obtain tokens **before** entering any cached rendering code:

```php
$csrf = $app->getCsrf();
$renderer->assign('saveToken', $csrf->token('article.save'));
```

```html
<form method="post" action="/article/save/">
    <input type="hidden" name="token" value="{$saveToken|escape}">
    <button type="submit">Save</button>
</form>
```

Validate before performing the write, and keep authorization checks separate:

```php
if (!$app->getCsrf()->validateRequest('article.save')) {
    http_response_code(403);
    return;
}
// Check the user's permission, validate fields, then write through the model.
```

`validateRequest()` accepts only POST, PUT, PATCH or DELETE. It reads the `token`
form field or `X-CSRF-Token` header for AJAX/JSON; a present invalid header fails
without falling back to the form. It never reads tokens from URLs. For manually
parsed input, `verify($token, $purpose)` checks the token only: enforce the HTTP
method in the controller. Use a non-empty purpose of at most 128 bytes.

Tokens are bound to the session and purpose. They remain stable across forms and
tabs, and verifying a request does not create a secret. A single random session
secret derives purpose tokens with HMAC-SHA256. Session expiry, logout and session-ID
regeneration invalidate them; fetch fresh forms after logging in. `reset()` explicitly
invalidates all purposes. Per-render login tokens are not accepted.

Issuing or verifying a token marks the response `Cache-Control: private, no-store`
and bypasses page cache and all native fragment scopes for the rest of the request.
An outer fragment that issues a token during rendering is not stored. Acquire the
token before a cached wrapper can return a HIT. Exclude form routes with
`cache.pages.denyPaths` or call `PageCache::bypass()` before Application construction
when a previously cached route becomes personalized. Clear old caches during
migration. Do not put token-bearing responses in an external cache or Smarty output
cache; these are outside the framework's storage controls.

The bundled login form now uses purpose `auth.login`, including its AJAX path.
GET `?a=logout` returns 405 and leaves the session intact. Render
`Box\Auth::getLogoutBox()` for the bundled POST logout form, or submit `a=logout`
and a token for `auth.logout` to `/login/`. POST `?a=logout` is also supported. Missing,
wrong-purpose or foreign-session tokens return 403. Both renderers use the existing
escaped `token` field.

The API (v2) is not browser based: it is called server to server with a bearer
integration key, sets no cookies and starts no session, so it has no CSRF surface.
Browser logins use the form of `Box\Auth` and its CSRF token described above.

### Trusted reverse proxies

Forwarded metadata is disabled by default. `Utils::getUserIp()` and
`Http::getClientIp()` return the validated connection peer, or an empty string
when `REMOTE_ADDR` is missing or invalid. `Client-IP`, `Forwarded`, forwarded host,
port and alternative TLS headers are never used as fallbacks.

For a deployment behind a reverse proxy, configure its actual source addresses
in `Settings.json`. These documentation addresses must be replaced for deployment:

```json
{
  "http": {
    "trustedProxies": ["192.0.2.10", "2001:db8:100::/64"],
    "forwardedHeaders": ["x-forwarded-for", "x-forwarded-proto"]
  }
}
```

Each header is opt-in. Proxy entries accept exact IPv4/IPv6 addresses or numeric
CIDR prefixes. Invalid configuration throws `InvalidArgumentException`. There are
no automatic private-network or provider exceptions; restrict trust to the proxies
you operate. IPv4 and IPv6 ranges are separate families, including IPv4-mapped IPv6
addresses. IPv6 output is canonicalized for consistent session/rate-limit keys.

The proxy must remove untrusted forwarding metadata and generate or correctly
append `X-Forwarded-For` using the real connection address. The resolver walks the
list from the trusted right side and stops at the first untrusted address, ignoring
client-controlled entries farther left. An invalid traversed hop, a header over
8192 bytes or more than 64 hops falls back to the direct peer.

`X-Forwarded-Proto` has a separate contract: the trusted immediate proxy must
**overwrite** it with a single `http` or `https` value representing the original
client connection. Configure every trusted hop to preserve that meaning only from
its own trusted upstreams. Do not append or blindly copy client-supplied values.
Comma-separated values are ignored. Without a valid trusted value, secure detection
uses only the local PHP `HTTPS` value `on` or `1`. A trusted `http` overrides backend
TLS because the browser's original connection determines the cookie transport.

Session cookies, the locale preference cookie and absolute locale links
use this shared detection.
Their HttpOnly and SameSite policies remain in effect. The explicit API
`setCookieIsSecured()` override remains available. A change in resolved IP may
invalidate existing sessions and change rate-limit keys during rollout.

The resolver lives at `Wbengine\Application\Env\RequestContext`.
`$app->getRequestContext()` lazily owns it in the existing Application tree and its
`getParent()` returns that Application. `getClientIp()` and `isSecure()` read current
request metadata. Existing static Http/Utils and standalone API entry points use
the same implementation without requiring Application bootstrap, database queries
or starting a session. Only the compiled proxy policy is cached and it refreshes
when configuration changes.

The trust-chain rules follow the [MDN X-Forwarded-For guidance](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Forwarded-For);
the single-value scheme contract follows [X-Forwarded-Proto](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Forwarded-Proto).

## Routing and the API

Application routes are deferred until `run()` and execute before shared cache or
CMS handling. `get($path, $callable)` is a GET shortcut;
`route($method, $path, $callable)` supports GET, POST, PUT, PATCH, DELETE, HEAD and
OPTIONS. Both return the Application for chaining and validate registration input.
The callback receives the existing `Router\Route` and Application objects:

```php
$App->get('/health/{id}', function ($route, $app) {
    header('Content-Type: application/json');
    echo json_encode(['id' => $route->getParams('id'), 'ok' => true]);
    return true;
});
$App->get('/custom', function ($route, $app) {
    $app->setValue('central', '<p>Trusted page content</p>');
});
$App->run();
```

Return boolean `true` after emitting a complete response, or terminate as before.
Boolean `false` declines the route and tries the next one, then the CMS fallback.
Other return values select the HTML callback model: prepare application variables
and the normal layout is rendered. Return values are not automatically sent as
response bodies. The first accepted route wins. Merely echoing JSON without
returning true does not complete the request. Authorization and cookie CSRF guards
remain the controller's responsibility.

The existing static Router methods retain immediate matching/execution for API
modules and boxes. A trailing slash is optional and the query string is ignored:


```php
use Wbengine\Router;

Router::get('/api/things/{id}', function (Router\Route $route) {
    return $route->getParams('id');
});
Router::post('/api/things', fn () => /* ... */ null);
```

`{name}` matches letters, digits, underscore and dash.

### API v2

The API lives under `/api/v2` and is the only API: server to server, authenticated by integration keys with scopes. Enable it with `"apiV2": {"enabled": true}`; the application dispatches it before CMS handling, so no box or bootstrap code is needed. It needs HTTPS (behind a proxy, list the proxy in `http.trustedProxies`), sets no cookies and has no browser CORS. There is no login through the API: users sign in on the web with `Box\Auth`. A new capability is a new resource and scope of API v2, not a new API version.

| Path | Purpose | Scope |
|------|---------|-------|
| `GET /api/v2`, `/api/v2/capabilities` | public discovery: version, capability flags, languages, box types | none |
| `GET /api/v2/identity` | the calling key: id, name, scopes | `discovery:read` |
| `articles`, `pages`, `menu`, `sections`, `boxes` | list, read, create, update, delete, change history and restore; articles also drafts, revisions, publish and preview | `<resource>:read`, `<resource>:write`, `preview:read`, `publish:write` |
| `translations/{site\|menu\|article}/{id}/{locale}` | read, save and delete a translation | `translations:read`, `translations:write` |
| `images`, `media/{id}` | upload, list and delete images (when an image directory is set); public media | `images:read`, `images:write` |
| `users`, `users/{id}`, `users/{id}/password-reset` | list, create, change role, disable, one-time password link (never a password in or out) | `users:read`, `users:write` |
| `POST cache/flush` | drop cached data and pages | `cache:write` |
| `GET config` | effective settings, read only, secrets shown as set or unset | `config:read` |
| `articles/{id}/hide`, `articles/{id}/unhide` | hide a published article (its page answers 404 and its menu item disappears) or show it again | `articles:write`, `publish:write` |
| `GET changes` | the change feed and rejected writes | `audit:read` |
| `GET analytics/overview` | traffic overview (optional) | `analytics:read` |

Keys are hashed on the website. A logged in local `ADMIN` issues, lists, revokes and rotates them on the "Integrations" screen (`Wbengine\Box\Integrations`); the raw key is shown once. The screen and its template ship with the framework. The box needs no class in the application: a `cms_boxes` row with module `integrations` and method `integrations` on a page with `acl = 1` is enough. `vendor/bin/wbengine.php integrations:install --config=src/Config/Settings.json` previews, and with `--apply` creates, the section, the page (`/integrations/`) and the box record (nothing existing is changed) and lists what still blocks issuing keys. Every change is a POST with a CSRF token and a fresh password confirmation.

When issuing a key the administrator sets one row per permission group to None, Read or Write: Content (articles, pages, menu, translations, previews), Publishing (write only), Structure (sections and boxes), Images, Site administration (read: users and settings without secrets; write: change users, flush the cache), History (the change log, read only) and Analytics (read only, when usable). The row changes its color with the choice: red for none, blue for read, green for write. The default is every group at its highest level, which is 22 scopes. `discovery:read` is always granted, a write level includes the read level, and a key needs at least one group. The Analytics row adds `analytics:read` (23 in total) so a central administration can show the traffic overview; it is enabled only when `analytics.enabled` is `true` and the analytics migration is applied (a form field `analytics=1` is still accepted). The server accepts only the known groups and levels (`Box\Integrations::GROUPS`), never a list of scopes from the client. A key cannot be changed afterwards (except its allowed addresses): issue a new one and replace the old one. Optionally the key is bound to IP addresses or CIDR ranges (`allowed_ips`, up to 20): a call from another address is answered `403 ip_not_allowed`, and the key card shows the address of the last successful call with a field to restrict the key to it. The address is read with the trusted proxy settings (`http.trustedProxies`), so set them correctly behind a reverse proxy. Needs migration `6.1.0/011-integration-ip-restriction.sql`; without it keys work as before, unrestricted. Every write is recorded in the change log with the key and the actor the central administration names. See [API v2 contracts, schema upgrade and audit/transaction rules](ref-api-v2.md).

Database updates for the API, applied by the operator and never by a web request (`vendor/bin/wbengine.php migration:status` and `doctor` report what is missing):

| Migration | Adds |
|-----------|------|
| `4.0.0/002-api-v2-integrations.sql` | integration keys and audit tables |
| `4.0.0/005-api-content.sql` | content API columns, drafts, revisions, change log |
| `4.0.0/006-analytics.sql` | analytics tables (only for analytics) |
| `4.0.0/007-api-integration-last-used.sql` | last use of a key |
| `4.8.0/008-change-log-source.sql` | source and actor columns of the change log |
| `4.10.0/009-remote-users.sql` | disabling accounts and one-time password links |
| `5.0.0/010-drop-refresh-tokens.sql` | removes the unused refresh token table |
| `6.1.0/011-integration-ip-restriction.sql` | allowed addresses and last used address of a key |
| `6.2.0/012-article-hidden.sql` | the `hidden` flag of articles and an index on `cms_articles.site_id` |
| `6.7.0/013-analytics-locale.sql` | the language of a view in the analytics tables (only for analytics) |

First-party aggregate analytics is opt-in. Before enabling it, apply
[`migrations/4.0.0/006-analytics.sql`](migrations/4.0.0/006-analytics.sql),
then set `analytics.enabled` to `true` in `Settings.json`. The default retention is
62 calendar days; set `analytics.timezone` to an IANA timezone when daily boundaries
should follow the website's local time. The first accepted public view stores the
measurement start date. Overview periods beginning earlier are marked incomplete,
so unmeasured days are not presented as confirmed zero views. `analytics:read` exposes the read-only
`/api/v2/analytics/overview` endpoint to the private administration. The tables store
daily counts and lifetime page/box totals; lifetime article counts remain in
`cms_articles.views`. No visitor identifiers or individual request rows are stored.
Run `vendor/bin/wbengine.php analytics:prune --config=/site/src/Config/Settings.json`
regularly to remove at most 1000 expired daily rows per invocation. Lifetime totals
are not pruned.

### MCP server

An MCP (Model Context Protocol) server lets an AI client such as Claude write, preview, publish and hide the articles of one site, manage its pages, menu, boxes and images, and explain to a non-technical editor how the site works. It is a client of API v2 over HTTPS, so the permissions, address restriction and change log of the integration key apply unchanged. It never opens the database. Issue a key on the Integrations page with the preset "Editor with structure and statistics" (or "Agent for content", "Agent, drafts only"), copy the client configuration the page shows once, and run the server with `php wbengine-mcp.phar` (a release asset with a checksum) or `vendor/bin/wbengine-mcp.php`. Configuration is read from `WBENGINE_MCP_URL`, `WBENGINE_MCP_KEY`, `WBENGINE_MCP_MODE` (`full` or `draft`), `WBENGINE_MCP_ACTOR` and `WBENGINE_MCP_ALLOW_INSECURE`. The tools, their inputs, the safety rules and client examples are in [`ref-mcp.md`](ref-mcp.md).

## Authentication, sessions and users

* **Passwords** are stored with `password_hash()`. Legacy unsalted MD5 hashes still work and are upgraded to a modern hash on the user's next successful login, provided `cms_users.password` is at least 60 characters wide (`varchar(255)` is recommended). If the column is still narrow the legacy hash is kept and a notice is written to the error log, so a narrow column can never lock a user out. Accounts can be created and passwords reset without the web or API from the command line, see [Creating and managing users from the CLI](#creating-and-managing-users-from-the-cli).
* **Web login** (`Box\Auth`) uses a CSRF token, a failed attempt counter and reCAPTCHA after repeated failures. The password is read untouched from the request (`Http::PostRaw`), so special characters work.
* **Login rate limiting** covers the web form and the AJAX login. Within 15 minutes a client may fail 5 times for one account, 20 times over all accounts, and an account may fail 25 times from all addresses together; after that the answer is `429` with `Retry-After` (an error message on the web form). The strict limit is per IP and account pair, so an attacker cannot lock the real owner out from another address. A successful login clears the pair and account counters, not the IP counter. Counters are files in the temp directory (set `rateLimit.login.dir` to a shared directory with several servers). Behind a reverse proxy or load balancer the limiter sees the proxy's address unless the web server restores the real client IP (`mod_remoteip`, `real_ip_header`), otherwise all clients share one IP counter.
* **Sessions** are stored in `cms_sessions`, bound to the client IP and a hash of the user agent. The session cookie is `HttpOnly` and `SameSite=Lax` (and `Secure` over HTTPS), strict mode is on, and the session id is regenerated on login to prevent fixation. Stored session data is unserialized with `allowed_classes` limited to `stdClass`.
* Access control for pages is the `acl` column of `cms_sites`: a value of `1` or more requires a logged in user.

For long exports or parallel AJAX requests, finish authentication, session writes
and creation of the CSRF secret first, then call `$app->closeSession()` (or
`$session->close()`). This explicitly loads the session snapshot, persists pending
WBengine database changes while holding the PHP session lock, and then calls
`session_write_close()`. Application does nothing if no session has been created.
The default lifecycle continues to allow writes until request shutdown.

```php
$session = $app->getSession();
$filter = $session->getValue('export_filter');
$app->closeSession();
// Perform slow work using $filter without blocking the next same-session request.
```

After close, the object is sealed against persistence. Reads and CSRF verification
remain available from the snapshot; an existing CSRF secret can still derive
purpose tokens without writing. Creating a missing secret, changing values,
`save()`, `unsetKey()`, ID rotation, logout and reopening through `sessionStart()`
throw `SessionException`. Close is idempotent and never implicitly reopens a session.
Object references returned by getters can still change locally, but a closed
`flush()` never stores them. Treat the snapshot as request-local data: it can be
stale after another request logs out and must not authorize new privileged work.
Complete authorization and state changes before closing.

A failed WBengine database write keeps the lock and pending changes, and close
throws so the caller can handle the failure or retry. Once native closing begins,
the object stays sealed even if a custom save handler throws. Native handler
persistence/error semantics remain those of PHP. Use the WBengine close method
instead of calling native `session_write_close()` directly: the native function
does not flush WBengine's database or seal its object. Closing without the matching
active native session is rejected. Handlers must provide session locking for
cross-request write serialization; custom Redis/database handlers need their own
locking verification.

Reproduce the file-handler comparison with
`docker compose -p wbengine-modernization run --rm php php tests/benchmarks/session-lock.php`.
It uses the test database and independent CLI workers with one cookie, five runs
per mode and 250 ms of simulated remaining work. On PHP 8.4.27 / MySQL 8.4 the
second worker waited about 247 ms with the lock held and about 1 ms after early
close. This measures lock wait in that scenario, not end-to-end website throughput.
The integration suite also proves serialization, preservation of both writers'
changes and absence of logout resurrection after an older closed worker exits.

### Creating and managing users from the CLI

The console tool creates, lists, re-passwords and deletes users directly in the database configured by `--config`. It is the way to get the first administrator, to reset a forgotten password when nobody can log in, and to remove demo accounts. It needs only the database, not a running web server or an API token. Composer installs the tool as `vendor/bin/wbengine.php`; from a framework checkout use `php bin/wbengine.php`.

```sh
php vendor/bin/wbengine.php user:create --email=EMAIL [--username=NAME] [--role=user|editor|admin] [--root=PATH] [--config=PATH]
php vendor/bin/wbengine.php user:password --email=EMAIL [--root=PATH] [--config=PATH]
php vendor/bin/wbengine.php user:list [--root=PATH] [--config=PATH]
php vendor/bin/wbengine.php user:delete --email=EMAIL [--dry-run] [--root=PATH] [--config=PATH]
php vendor/bin/wbengine.php user:delete --example [--dry-run] [--root=PATH] [--config=PATH]
```

Every command prints JSON on standard output and exits with `0`, or with `2` and a message on standard error when it refuses (the message never contains a password, a hash or SQL).

```sh
$ php vendor/bin/wbengine.php user:create --email=owner@corp.example.cz --role=admin --config=/var/www/html/src/Config/Settings.json
Password:
Repeat password:
{"created": true, "id": 2, "email": "owner@corp.example.cz", "username": "owner", "role": "admin"}

$ php vendor/bin/wbengine.php user:password --email=owner@corp.example.cz --config=...
{"updated": true, "id": 2, "email": "owner@corp.example.cz"}

$ php vendor/bin/wbengine.php user:list --config=...
{"users": [{"id": 2, "username": "owner", "email": "owner@corp.example.cz", "role": "admin", "active": true}]}

$ php vendor/bin/wbengine.php user:delete --example --dry-run --config=...
{"deleted": [{"id": 1, "email": "admin@example.com", "role": "admin"}], "dryRun": true}
```

(The JSON is pretty printed in real output.)

* **user:create** validates the e-mail, role and password with the same code as the API v2 users resource (`Api\Model\ApiUserModel`) and stores a `password_hash()` hash. The role defaults to `user`. `--username` defaults to the part of the e-mail before the `@` (characters other than letters, digits and `. _ + -` become `_`, and `-2`, `-3` is appended when the name is taken). An explicit `--username` may contain letters, digits and `. _ + @ -` and is refused when taken. An existing e-mail or username, an unknown role, an unreachable database or a missing configuration is refused and nothing is written. A user created this way can log in with the web form (which signs in by e-mail) and the API immediately.
* **user:password** sets a new password for an existing user and deletes their rows in `cms_sessions`, so every device is logged out.
* **user:list** is read only and prints the id, username, e-mail, role and an `active` flag (the account has a usable password; the placeholder value `0` does not). The anonymous user (id 0) and the password hash are never shown.
* **user:delete** removes one user by e-mail, or with `--example` every user whose e-mail domain is reserved for documentation by RFC 2606 and RFC 6761: `example.com`, `example.org`, `example.net` or a subdomain of them, and the top level domains `.example`, `.test`, `.invalid` and `.localhost`. Domains are matched by whole labels, so `notexample.com` is not touched. The anonymous user is never deleted, and the command refuses when no active administrator would remain, so create the replacement administrator first. The user's refresh tokens and sessions are deleted in the same transaction and the link in `cms_api_audit.local_user_id` is cleared (the audit rows stay). Integration keys created by the user are not changed: revoke them from the integrations screen if they should stop working. `--dry-run` only prints who would be deleted. The output lists the id, e-mail and role of every deleted user.

**Passwords are never an argument.** They would end up in the shell history and in the process list of the machine. A `--password` option (or `--pass`, `--passwd`, `--pwd`, `--pw`) is refused, and the environment is not read either. On a terminal the password is requested twice without echo (the terminal state is restored on errors and on Ctrl+C). Without a terminal one line is read from standard input, which is how to automate it:

```sh
printf '%s\n' "$ADMIN_PASSWORD" | php vendor/bin/wbengine.php user:create --email=owner@corp.example.cz --role=admin --config=...
```

Keep the secret in a protected file or a secret store and avoid `echo` on a shared machine with `set -x`. The password must be 12 to 72 bytes long (the API itself enforces no minimum, the CLI does for the accounts it creates; 72 bytes is the limit of bcrypt). The column `cms_users.password` must be `varchar(255)`.

**Forgotten password.** Run `user:password --email=...` on the server. It does not need the old password, and the previous password and all logins stop working.

**First administrator on a fresh install.** The schema of the skeleton application seeds a demo account `admin@example.com` (user `admin`, role `admin`) with a published demo password, and the anonymous user with id 0. Never keep that account on a reachable site. Replace it in this order: create your own administrator with `user:create --role=admin`, log in with it to check that it works, then run `user:delete --example --dry-run` to see which demo accounts would go and `user:delete --example` to remove them. Alternatively change the demo password with `user:password --email=admin@example.com`, but a real administrator is preferable.

In the skeleton's container the binary is `vendor/bin/wbengine.php` in `/var/www/html`, for example `docker compose exec web php vendor/bin/wbengine.php user:create --email=owner@corp.example.cz --role=admin --config=src/Config/Settings.json` (add `-T` and pipe the password for automation, otherwise the terminal prompt is used).

## Configuration reference

`src/Config/Settings.json`:

| Key | Meaning |
|-----|---------|
| `timeZone` | PHP time zone, for example `Europe/Prague` |
| `debug` | `true` shows the debug bar and detailed errors. Never in production. `WBENGINE_DEBUG` overrides it |
| `dbAdapterDefinition` | `adapterName` (`Mysqli`), `hostname`, `port`, `database`, `username`, `password`, `charset` (use `utf8mb4`) |
| `renderer` | `adapterName` (`NativeRenderer`; `SmartyRenderer` only with the optional compatibility package), `autoEscape` (default `true`), `templatesDir`, `compiledDir`, `configDir`, `compileCheck` (default `true`; set `false` in production to skip the template change check, then clear the compiled directory on deploy) |
| `i18n` | `enabled` (default `false`), `default`, `locales`, `detect`, `urlPrefix`, `prefixDefault`, `cookie`, `cookieDays`, `dir`, `baseUrl` (public origin for absolute hreflang links). See Internationalisation |
| `cache` | `enabled` (default `false`), `driver` (`apcu` or `file`), `ttl` in seconds (default 300), `dir` for the file driver, `pages`: `enabled`, `ttl` (default 60), `allowPaths`, `denyPaths` for the whole page cache. See the performance notes |
| `counters` | `views`: `enabled` (default `false`), `hits` (50) and `seconds` (60) per batch, `dir` for the counter files. Batches the article view counter |
| `rateLimit` | `login`: `enabled` (default `true`), `maxAttempts` per IP and account (5), `ipMaxAttempts` per IP (20), `accountMaxAttempts` per account (25), `window` in seconds (900), `dir` for the counter files |
| `http` | `trustedProxies` (addresses or CIDR ranges of reverse proxies) and `forwardedHeaders` (`x-forwarded-for`, `x-forwarded-proto`). Both default to empty: forwarded headers are never trusted unless listed |
| `recaptcha` | `secretKey` for the login form. Empty means captcha checks fail closed. `WBENGINE_RECAPTCHA_SECRET` overrides it |
| `apiV2` | `enabled` (default `false`) switches on `/api/v2`; `imageDirectory` is the directory for uploaded images (`WBENGINE_IMAGE_DIR` overrides it) |
| `analytics` | `enabled` (default `false`), `timezone` (default `UTC`), `retentionDays` (default `62`, allowed 31 to 365). Identity-free daily aggregates, needs migration `4.0.0/006-analytics.sql` |
| `seo` | `allowIndexing` (default `true`), `disallow` and `sitemapExclude` (lists of paths starting with `/`) for `Seo\Crawlers`, see [robots.txt and sitemap.xml](#robotstxt-and-sitemapxml) |
| `audit` | `retentionDays` (default `365`, allowed 30 to 3650) for `cms_api_audit` and `cms_api_changes`, `revisionRetentionDays` (default `365`, allowed 30 to 3650) for `cms_api_revisions`. Applied by `audit:prune` |

An application may add keys of its own, and a key the framework does not read (for example one left from an older version) is ignored. The environment variables `WBENGINE_DEBUG`, `WBENGINE_RECAPTCHA_SECRET` and `WBENGINE_IMAGE_DIR` override the matching settings. Keep `Settings.json` out of version control.

## Debug tooling

With `"debug": true` a bar is appended to the page showing the PHP version, the configuration file, application time, the number of database queries and their total time, and the site, section and box counts. Opening the DB panel lists every query with its duration, HTML escaped. The error handler converts PHP warnings and errors into exceptions so they are not silently ignored; notices and deprecations are logged and do not stop the request.

Debug mode also turns on `display_errors`. It exposes SQL and paths, so it must never be enabled on a public server. With debug off, an uncaught exception shows a readable, non-sensitive error page with a reference code; the exception class, message, file and line are written to the PHP error log under the same reference code. Configuration failures have a specific checklist for `src/Config/Settings.json` without exposing server paths. During local recovery, `WBENGINE_DEBUG=1` enables the exception detail template even when the configuration file cannot be loaded. Remove that environment variable before exposing the site publicly.

`Site::initialize()` automatically assigns `server`, `dbinfo` and
`phpversion` only when debug is enabled. Production templates no longer receive
these diagnostic values by default. If an internal page explicitly needs them,
call `$app->getSite()->assignDiagnostics()` after site initialization; it assigns
the same template keys and returns that Site instance.

Reading `dbinfo` may open a database connection even though no SQL query is counted.
Production initialization avoids diagnostic-only work. Site selection now uses
one bound SQL lookup for exact database collation, so it can itself open the
connection; diagnostics add no SQL beyond that necessary lookup. Content, navigation and custom boxes can still require database access.
Keep diagnostic output restricted to trusted users and escape the values normally.

### HTML whitespace and transfer compression

`Renderer::dispatch()` emits the rendered template through the selected adapter's
`display()` method. It does not rewrite whitespace in the result. Both adapters
preserve the rendered bytes of trusted HTML, including `pre`, `textarea`, inline
JavaScript/CSS, attribute values and JSON script text. Ordinary template variables
still use the renderer's existing output-escaping rules.

There is no setting that minifies or rewrites the HTML.

Enable gzip or Brotli at the webserver or reverse proxy to reduce transfer size
without changing HTML content. Keep encoding negotiation and `Vary: Accept-Encoding`
correct at that layer. If a project needs source minification, use a build step
that understands its HTML and embedded languages. No minifier dependency or PHP
compression handler is added by the framework.

**Migration:** clear existing page caches and any external HTML caches on deployment.
They may still contain output damaged by the old whitespace collapse; changing
renderer behavior cannot repair an already stored response. Render fresh output
from the original templates/content. Uncompressed bodies can be larger after this
change, so check transfer compression in the deployment configuration.

## Performance notes

For large box tables, the optional migration
[`migrations/4.0.0/001-boxes-section-order.sql`](migrations/4.0.0/001-boxes-section-order.sql)
adds `(section_id, order)`. `Section\Model::getBoxes()` now filters the section
outside the site-membership/shared alternative, allowing this index to provide
ordering on the tested MySQL and MariaDB plans. JSON membership, shared boxes,
returned columns, parameter binding and the existing cache keys are preserved.
The framework never applies this DDL during a web request.

Before deployment, inspect `SHOW INDEX FROM cms_boxes` for an equivalent index
whose first two columns are `section_id, order`, adapt the table prefix, and apply
the SQL file through the installation's normal database migration procedure.
Schedule the DDL for the size and traffic of that installation. Do not add a
redundant index if an equivalent one already exists. Verify the actual query with
`EXPLAIN` and compare result IDs/order before and after. Rollback is
`ALTER TABLE cms_boxes DROP INDEX wb_boxes_section_order`; roll back only the
index created by this migration. The PHP query also works without the index.
Invalidate box/data/page caches after content edits as usual; a schema-only index
change does not alter cached rows.

The repeatable test benchmark is
`docker compose -p wbengine-modernization run --rm php php tests/benchmarks/content-lookup.php`.
With 30,000 boxes over 100 sections and 20 percent shared boxes, the factored query with the index takes about 0.4 ms against 5.4 ms (MySQL 8.4) or 6.6 ms (MariaDB 11.4) without it, and EXPLAIN estimates fall from about 30,000 rows to 300. These are warm database buffer figures with the application cache off. The index adds a little write work (about 0.5 ms per batch of 1,000 inserts), so remeasure for a write-heavy workload.

Site selection always executes one bound SQL query, whether a cache driver is enabled or not: it keeps the real column collation, strict values, visibility and the highest site id for duplicates. `Site::isMenuSelected()` resolves all parent URL prefixes in one statement, so menu rendering does not add a query per menu item.

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
* **Cache** (opt-in): menu, sections, box lists and translation data. Enable with `"cache": {"enabled": true, "driver": "apcu", "ttl": 300}` (or `"driver": "file"`). APCu shares data across PHP workers; the file driver works without APCu. The API flushes affected caches on supported writes. Direct database edits still require cache/page invalidation or TTL expiry. URL selection uses one parameterized database query even with a cache driver, to preserve real collation and row freshness; it never caches arbitrary URL lookups or the full visible-site table.

* **Page cache** (opt-in, needs the cache enabled): `"cache": {"enabled": true, "driver": "apcu", "pages": {"enabled": true, "ttl": 60}}`. Anonymous visitors get a stored copy of the page, served before any box runs. A hit costs no database query for a visitor without a session cookie and one session lookup for a returning visitor (to be sure they are not logged in). A page is stored only when it is the same for everybody: a plain `GET` without a query string or `Authorization` header, for a visitor who is not logged in, a `200` HTML response, no write to the session (so the login page with its CSRF token is never stored), the site does not require a login, and the URL is exactly the site's own link (a deeper path that falls back to it is not stored, which also keeps the number of entries bounded by the number of sites). Responses carry `X-WBengine-Cache: HIT`, `MISS-STORED` or `BYPASS`, plus an `ETag` and `304` support. The TTL (60 seconds by default) bounds staleness; the API flushes the pages when sections change; after editing `cms_sites`, `cms_menu`, `cms_sections`, `cms_boxes` or articles directly in the database, call `POST /api/cache/flush` (admin token), which also reaches an APCu cache, or wait for the TTL. If a page depends on anything besides the URL and the login state (device type, a language cookie, the time), do not enable the page cache for that site. Indicative throughput of the skeleton home page in the same Docker VM: about 570 requests per second without any cache, about 725 with the data cache, about 1,175 with the page cache (a single completed run, treat it as a direction).

Production setup: opcache with `composer dump-autoload --classmap-authoritative`, `php.ini-production`, and `"compileCheck": false` in the renderer settings (clear compiled templates when deploying changed sources).

## Security notes

* Use bound parameters for every value (see the database layer).
* Keep `debug` off in production and `Settings.json` out of git.
* The API authorizes by integration key scope. A key is stored only as a hash, shown once, and every request is checked against the keys table. Roles (`user`, `editor`, `admin`) are read from the database whenever one is needed, never from a session or a form.
* Both WBengine renderer adapters escape output by default. Use `{$value|escape}` and `{$html|raw}` explicitly during migration. Set `renderer.autoEscape: false` only as a temporary legacy compatibility setting, then migrate trusted HTML and re-enable escaping.
* Put TLS in front of the application. The session cookie is marked `Secure` only when the request arrives over HTTPS.
* Report problems privately to the maintainers.

### Page cache controls

Use exact canonical paths to restrict shared page caching:

```json
"cache": {
  "enabled": true,
  "driver": "apcu",
  "pages": {
    "enabled": true,
    "ttl": 60,
    "allowPaths": ["/", "/blog/"],
    "denyPaths": ["/account/"]
  }
}
```

`denyPaths` wins. An omitted allowlist permits other eligible paths; an empty
allowlist disables page caching. Invalid path policies fail closed. Paths are
compared ignoring case and a trailing slash, as for the existing page cache.

For application-specific personal state, call `Wbengine\PageCache::bypass()`
**before constructing Application** to prevent both a cache hit and storage. A box
can call it to prevent storage of the current response, but a box is never executed
on a hit, so a box-only call cannot protect an existing cached page.

Private/no-store/no-cache responses, `Set-Cookie`, unsupported `Vary` and CSP nonces
are not stored. Custom request cookies and anonymous sessions containing personal
data bypass the shared cache. The locale cookie is supported when i18n is enabled.
Security headers are retained on hits and 304 responses; headers already supplied
by the current request take precedence. Cache entries from older versions are
ignored. Clear all cache drivers during deployment when changing content or policy.
Fragment caches include the locale automatically. Public blocks must contain only
content that is independent of the visitor. Use the user scope for personal HTML:

```smarty
{cache 'navigation' ttl=300}...public content...{/cache}
{cache 'profile' scope='user' ttl=60}...personal content...{/cache}
```

User-scoped blocks use the current authenticated session identity, supplied lazily
through Application, Renderer and the native template engine. They are separated
by user ID and locale. Anonymous visitors, invalid identities or unavailable user
context render without storing a personal fragment; there is no public fallback.
Public blocks never load the user just to decide their cache key.

Using a user-scoped block bypasses the whole-page cache, even for anonymous visitors
or when the fragment cache is disabled. A public outer block that renders a personal
inner block is not stored. Once personal scope has been used, public fragment storage
and lookup are conservatively bypassed for the rest of that request. Do not wrap
conditional personal content in a public block: a public cache hit does not execute
its body, so public content must be visitor-independent on every rendering path.

Standalone native engines can configure `setCacheUserResolver(fn () => $authenticatedUserId)`;
return a positive integer ID from trusted authentication state or null for a guest.
The resolver must not use request parameters as identity. Other variants, such as
device or permissions-dependent output, still require explicit keys and suitable TTL
or invalidation. Personal scope separates identity, not every possible role or state.
Fragment keys include compiler version and escaping mode, so safe rendering never
reuses raw HTML cached during legacy migration. Existing compiled templates are
recompiled into a new compiler version and old fragment entries are not reused. Clear compiled templates during deployment when
`compileCheck` is disabled. The legacy `Runtime::cache()` helper remains public-only.

Widen `cms_users.password` to `varchar(255)`. Creating or
changing a password now fails when a secure hash cannot fit. Existing legacy MD5
passwords can still log in and are upgraded once the schema supports it.

## Development and tests

The [modernization plan](plan-framework-improvements.md) records completed work, dependencies and migration procedures. New PHP
features must follow the existing Application/Site/Section/Box object tree,
namespaces, lazy services, model and renderer adapter conventions.

The test environment is Docker only: PHP 8.4 CLI and MySQL 8.4.

```bash
docker compose run --rm php composer install
docker compose run --rm php composer test          # unit and integration suites
docker compose run --rm php composer test:unit     # no database needed
```

Use `PHP_VERSION=8.3 docker compose build` (and `docker compose run --rm php composer test`) to test another PHP version; 8.3, 8.4 and 8.5 are supported and run in CI on every push (`composer lint` checks the syntax of every file).

The integration tests create their tables from `tests/schema.sql`. They are skipped when the database is not reachable. For new installations use the `utf8mb4` connection charset: the older `utf8` alias is three bytes per character and cannot carry emoji.

## Upgrading

### From 6.6 to 6.7

* A site that uses analytics applies `migrations/6.7.0/013-analytics-locale.sql` (safe to rerun). Without it nothing breaks: counting goes on as before and the overview has `per_locale: false`. With it each view is recorded with the language of the request, the totals stay the sum of all languages, and `/api/v2/analytics/overview` adds `by_locale` and an optional `locale` filter. Views counted earlier keep an empty language and are reported as not recorded per language. `doctor` reports the migration when analytics is enabled.

### From 6.5 to 6.6

* No migration. `/robots.txt` and `/sitemap.xml` exist only when the application calls `Wbengine\Seo\Crawlers::register($app)` before `run()`. The settings `seo.*` are optional.

### From 6.4 to 6.5

* No migration and no setting. The MCP server reads statistics when the key has the Analytics choice and analytics is switched on for the site.

### From 6.3 to 6.4

* No migration. The MCP server offers more tools; a site whose agents should attach article boxes registers a box type that renders articles: `BoxRegistry::current()->register('story', 'blog', 'story', [], ['central'], ['renders' => 'article'])`. Images need `apiV2.imageDirectory` or `WBENGINE_IMAGE_DIR`.

### From 6.2 to 6.3

* No migration and no setting. The MCP server is optional and runs outside the site; the Integrations page gained the presets and the client configuration block.

### From 5.x to 6.0

* The settings `codePage`, `cdnPath`, `cssFiles`, `ipAdmins`, `minimizeHtml`, `minimizeCss` and `minimizeJs` and their `Config` getters (`getHtmlHeaderCharset()`, `getCdnPath()`, `getCssCollection()`, `getAdminIpCollection()`, `minimizeHtml()`, `minimizeCss()`, `minimizejs()`) are removed, together with the `minimizeCss` template variable. Nothing in the framework read them. An existing `Settings.json` keeps working, the leftover keys are ignored; delete them. If a template used `{$minimizeCss}`, write the file name yourself.

### From 4.x to 5.0

* API v1 is removed: `/api/auth`, `/api/users`, `/api/sections`, `/api/translations` and `/api/cache/flush`, together with the JWT login and the refresh cookie. The only API is v2 (`/api/v2`) with integration keys; users, sections, translations and the cache flush are v2 resources. There is no login through the API: users sign in on the web with `Box\Auth`. Clients of the old routes must move to API v2 with an integration key.
* The dependency `firebase/php-jwt` is gone: the framework has no runtime Composer package. The `jwt` section of `Settings.json` and the `WBENGINE_JWT_*` variables are ignored; remove them. `doctor` no longer checks them.
* Remove the application code that starts the old API (`new \Wbengine\Api(); $api->Initialize(); $api->end();` in a box or bootstrap) and the API page and box. API v2 is dispatched by the application itself when `apiV2.enabled` is `true`.
* Drop the unused refresh token table with `migrations/5.0.0/010-drop-refresh-tokens.sql`.
* Removed classes and methods: `Wbengine\Auth`, `Api::Initialize()`, `Api::end()`, the CORS and cookie setters of `Api`, `User::getJwtToken()`, `User::useJwt()`, `WbengineRestapiAbstract::isAuthenticated()`, `getSectionModel()` and `ApiSectionModel`.
* Rows of the change log written by the old API keep the source `v1`; new rows use `v2` or `cli`.

### From 3.0

* PHP 8.3 or newer is required.
* Add a `recaptcha` section if you use the login captcha. The previous built in secret is removed.
* Widen the password column: `ALTER TABLE cms_users MODIFY password varchar(255) NOT NULL DEFAULT '0';`
* Replace string built SQL with bound parameters. `Db::query()` and the fetch helpers accept an array of parameters. `Db::createUpdateQuery()` is removed, use `Db::update()`.
* `SiteModel::getUrlId()` is replaced by `getUrlCandidates()`, which returns an array.
* Add `cms_users.role` (`ALTER TABLE cms_users ADD COLUMN role varchar(20) NOT NULL DEFAULT 'user';`) and promote your administrators.
* If your `.htaccess` adds a trailing slash with a 301 redirect, exclude `/api`, because a redirect turns a POST into a GET, and keep the query string in the rewrite rule (`[QSA,L]`), otherwise API filters and dates are lost.

## License

MIT, see [LICENSE.md](LICENSE.md).


## Command line tools

Composer installs `vendor/bin/wbengine.php` and `vendor/bin/migrate-content.php` in applications. From a framework checkout use `php bin/wbengine.php`. Both entry points load the host application autoloader through Composer's bin proxy or anchored installed-package paths. They never search the caller's current directory for executable code.

```sh
vendor/bin/wbengine.php config:check --config=/site/src/Config/Settings.json --root=/site/src
vendor/bin/wbengine.php doctor --config=/site/src/Config/Settings.json --root=/site/src
vendor/bin/wbengine.php migration:status --config=/site/src/Config/Settings.json --root=/site/src
vendor/bin/wbengine.php analytics:prune --config=/site/src/Config/Settings.json
vendor/bin/wbengine.php audit:prune --config=/site/src/Config/Settings.json [--days=N] [--dry-run]
vendor/bin/wbengine.php audit:list --config=/site/src/Config/Settings.json [--limit=N] [--resource=NAME] [--actor=ACTOR]
vendor/bin/wbengine.php user:create --email=owner@example.cz --role=admin --config=/site/src/Config/Settings.json
vendor/bin/wbengine.php user:password --email=owner@example.cz --config=/site/src/Config/Settings.json
vendor/bin/wbengine.php user:list --config=/site/src/Config/Settings.json
vendor/bin/wbengine.php user:delete --email=demo@example.com [--dry-run] --config=/site/src/Config/Settings.json
vendor/bin/wbengine.php integrations:install --root=/site/src --config=/site/src/Config/Settings.json [--apply]
vendor/bin/wbengine.php generate:box --root=/site/src --namespace=App --section=Main --module=Welcome --method=index
```

`config:check` does not contact the DB. Doctor and migration status inspect schema read-only, never run DDL. Doctor prints fixed actions without secret values and checks production Composer/OPcache, renderer, cache, proxies and optional API schema. CLI permissions and OPcache may differ from the web worker; verify that environment separately. Exit0 is success,1 failed checks,2 invalid input.

The generator defaults to a JSON preview with class, template and reviewable SQL. `--write` atomically creates its files without overwriting existing paths. Inspect SQL placeholders and unpublished page metadata, choose a real page link, then apply it separately after review. Generated classes extend the existing Box tree; no new router or component framework is introduced.

HTTP benchmarking is opt-in: `php tests/benchmarks/request-matrix.php /private/manifest.json 20 4`. Copy the documented fixture manifest and describe the real server, data and OPcache; supply your own isolated URLs and cookies. It measures off/data-warm/page-warm with anonymous, anonymous-session, logged, custom API and parallel same-session requests. Cookies are never printed. p50/p95, throughput and HTTP errors are measured; SQL, connections and server memory are null unless test-only response headers provide them. Client memory is never substituted for server memory. No benchmark code is attached to production requests.


The isolated two-website administration acceptance pilot is documented in
[tests/pilot/ref-pilot.md](tests/pilot/ref-pilot.md). It verifies real central forms
through certificate-verified PHP APIs, separate keys/databases, outage recovery,
revocation and a real database dump/restore. See
[book-wbengine-4-upgrade.md](book-wbengine-4-upgrade.md) for the operator upgrade
and rollback procedure. Test success does not mean production data were migrated.
