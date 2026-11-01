# Plan: sitemap.xml and robots.txt (6.6.0)

Status: planned. Release target: 6.6.0 (a new opt-in feature, no database change).

## Why

The first real use of the MCP server (a new article published through it) showed that the
skeleton site gives crawlers three signals only partly: `hreflang` links existed, `canonical`
was added to the skeleton template on 2026-11-01, and `/sitemap.xml` and `/robots.txt` still
return 404. An editor who publishes through the MCP server cannot fix this, so the framework
has to provide it, and the skeleton must stay generic (it is a template used by other sites,
so no site address may be written into a static file).

## Facts found in the code

- `Application::get($path, $callable)` and `route(...)` register deferred handlers that run
  before the page cache and CMS handling. A handler that echoes and returns `true` finishes
  the request.
- `cms_sites` holds the pages: `visible`, `link`, `acl` (access level, 0 is public), `strict`,
  and `cms_site_translations` holds the per-locale texts. Languages come from `I18n`
  (`alternates($path)`, `url($path, $locale)`, `origin()`, `hreflangs($path)`).
- The public origin is `i18n.baseUrl` when set, otherwise the request host. The same rule
  must be used here, because a sitemap with wrong hosts is worse than none.
- API v2 hides a page by clearing `visible`, so the sitemap must follow `visible`.

## Decisions

1. The framework provides a class `Wbengine\Seo\Crawlers` with `register(Application $app)`.
   It adds the two routes. It is opt-in: the skeleton calls it in `src/Application.php`,
   other applications do nothing and change nothing.
2. `robots.txt` is generated per request: `User-agent: *`, `Disallow` lines for the paths the
   application lists (default: none), and `Sitemap: <origin>/sitemap.xml`. A site can turn
   crawling off completely with a setting, for staging copies.
3. `sitemap.xml` lists every page that can be requested by its link (conditions in the
   answers below) once per language with `xhtml:link` alternates for the
   other languages, so it agrees with the `hreflang` links already in the page head.
4. `lastmod` is written only when a real date exists (the newest published article of the page). `changefreq` and
   `priority` are not written: crawlers ignore them and a wrong value is worse than none.
5. Both responses carry a short public cache header and are not stored in the page cache
   (`PageCache::bypass()`), so a newly published article appears at once.
6. One sitemap file up to 50,000 addresses (the protocol limit). A site above it gets a
   clear log line and the first 50,000 entries, an index of several files is out of scope.

## Answers to the open questions (verified in the code and by the owner)

- `strict` (`SiteModel::getUrlCandidates`): a strict page answers only its exact link, every
  other address is a 404. A page that is not strict is dynamic: the box behind `/addons/`
  also receives `v21-2` and `server-1` of `/addons/v21-2/server-1/` as parameters and the
  box or site decides which parameters are valid (and returns the 404 itself). Consequence
  for the sitemap: the framework lists only the page link, never a parameter address, because
  it cannot know which addresses a dynamic box accepts. The base link is listed for both
  kinds. A box that can list its own addresses is a later extension (see out of scope).
- The sitemap uses the same conditions as loading a page (`SiteModel::loadSiteData`):
  `visible = 1` and no hidden article (`ArticleVisibility::pageCondition`), plus `acl = 0`,
  because `acl >= 1` sends a visitor to `/login/`. A page that cannot be requested is never
  listed.
- Dates: `cms_sites` has no date column, only articles have `published` (a timestamp). So
  `lastmod` of a page is the newest published, not hidden article of that page, and it is
  left out for pages without an article.
- The tracker (`Analytics\Tracker`) is started only by the CMS page render
  (`beginPublicPage`), after the custom routes. The crawler routes are therefore not counted
  as page views, nothing to change. A language prefix does not change the counted page: all
  languages count to the same page.

## Analytics per language (6.7.0, separate from 6.6.0)

The owner wants the views of each language version recorded separately (useful for
marketing) and summed in the totals. The daily and lifetime tables key on page and
entity only, so this needs a `locale` column and a migration, which does not belong in the
6.6.0 crawler release. Outline: add `locale` to `cms_analytics_daily` and
`cms_analytics_totals` keys (existing rows get the default locale), record the request locale
in `Tracker`, keep `/analytics/overview` totals as the sum and add a per-locale breakdown, then
show it in the MCP report and in the Admin. To be planned in its own file once 6.6.0 is out.

## Breakdown

- [x] Read `Site`, `SiteModel`, the router and the tracker to answer the open questions; the
      answers are above.
- [x] `Seo\Crawlers::register`, `Seo\Sitemap` (query and XML writer with escaping) and
      `Seo\Robots`, no output code mixed with the query code.
- [x] Settings `seo.allowIndexing`, `seo.disallow`, `seo.sitemapExclude`,
      validated like the other settings, documented in the settings reference.
- [x] Unit tests: XML escaping, hidden and login pages left out, alternates per language,
      origin from `i18n.baseUrl` and from the host, the 50,000 limit, robots with and without
      the disallow list and with indexing off.
- [x] Integration test against the test database: a visible page, a hidden page, a page with
      `acl`, a translated page.
- [ ] HTTP check in the skeleton smoke test: `/sitemap.xml` is XML and lists the home page in
      both languages, `/robots.txt` names the sitemap, a hidden page is absent.
- [x] `ref-*` and README sections, CHANGELOG 6.6.0.
- [ ] Skeleton: bump to `^6.6`, register the routes, content migration for the documentation
      (mirrored in the content dump), changelog, smoke ref.
- [ ] Release v6.6.0, deploy to contabo, check `/sitemap.xml` and `/robots.txt` on the live
      site and both languages of the Apollo 11 article in the sitemap.
- [x] MCP: a section in the `addresses_and_seo` guide topic. A `check_article` advice that
      reads `/sitemap.xml` was dropped: the MCP client only calls the API, the editor cannot
      change the setting, and the guide text is enough.

## Out of scope

- Sitemap index files, image and news sitemaps.
- `noindex` per page (needs a page field, a separate decision).
- Submitting the sitemap to search engines.

## Verification at the end

Same bar as earlier releases: unit and integration tests, green CI on the supported PHP and
database matrix, then live checks with real requests (status, content type, content) and a
statement of what was and was not verified.
