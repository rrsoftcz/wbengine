# Plan: release 6.7.0 and the skeleton that follows it

Status: framework 6.7.0 released and live on 2026-11-01. Skeleton: version in the header, `/changelog/`, the blog overview with the rewritten demo post, and the MCP pages are done. Still open: the remaining documentation pages of the audit (SEO, statistics, API, Boxes, Caching and others). Written 2026-11-01 from the owner's requests after 6.6.0.

Two repositories are involved. The framework (`wbengine`) gets 6.7.0. The skeleton
(`wbengine-skeleton-app`) gets a release that uses it. The skeleton is also the public
documentation of the framework, so documentation work is part of the plan, not an afterthought.

## Goals

1. Views are recorded per language and the totals stay the sum of all languages.
2. The current WBengine version is visible on the site, in the header, in a small font.
3. A page `/changelog/` in the menu lists the releases with their fixes and changes.
4. `/blog/` becomes an overview of the posts: the newest ones with excerpts, the most read
   ones, and a list of all of them.
5. The documentation of the skeleton covers every mechanism and feature added up to now,
   including how to run the MCP client on macOS, Linux and Windows.
6. The MCP server is tested on all three platforms in CI, so the documentation of each
   platform is backed by a run, not by assumption.

## Facts found in the code and in the live content (2026-11-01)

- Analytics tables `cms_analytics_daily` and `cms_analytics_totals` are keyed by page and
  entity only. `Analytics\Tracker` records the page of a request and knows no language, so all
  languages already count to the same page and the totals are correct, but there is no split.
- The skeleton content has 15 documentation pages. Only `/docs/administration/` mentions MCP,
  `/docs/settings/` is the only page that mentions the sitemap and canonical link. The pages
  Boxes (757 characters), Languages (842), Security (883), Caching (1,122) and API (1,617) are
  short summaries and were written before framework 6.x.
- `/blog/` is a strict page with the `story` box and shows one article: a demo about the
  bundled box from 2017 with 83 views. Posts created through the MCP server are separate pages
  under `/blog/<address>/`, each with its own article. There is no list of them anywhere.
  Articles have no image field, only title, introduction, body, author, source, publish date
  and a view counter (`cms_articles.views`).
- `CHANGELOG.md` is shipped inside the Composer package. Its structure is not uniform: up to
  4.0.2 each release is a section `## [x.y.z] - date`, from 5.0.0 on the releases are sub
  headings such as `### Added (6.6.0)` inside one `## [Unreleased]` section, without dates and
  with one release split into several headings. A page that lists releases needs one
  structure first.
- The framework version is available at run time from Composer
  (`Composer\InstalledVersions::getPrettyVersion('wbengine/wbengine')`) when the framework is
  installed with Composer, which is how the skeleton uses it.
- The Integrations page shows a client configuration with the placeholder
  `/path/to/wbengine-mcp.phar` and a `claude mcp add` command. That placeholder already cost a
  failed start in practice (a configuration pointing to the placeholder path). A Windows path
  looks different and the page says nothing about it.
- The MCP server stopped by itself after 60 seconds on a client that starts it over a socket
  pair (fixed in 6.5.4). It was found only by reproducing with the client's own runtime, so
  every platform needs a test that starts the phar from Node the way a client does.

## A. Views per language (framework, 6.7.0)

Why: the owner wants the views of each language version for marketing, and in the statistics
as a sum.

Decisions:
1. Add a `locale` column to both analytics tables and to their primary keys. Rows recorded
   before the migration get an empty locale, which means "not recorded per language". They are
   reported as such and never assigned to a language by guess.
2. `Tracker` records the locale of the request (`I18n::locale()`; empty when the site has no
   languages). The page cache hit path records it too, which has to be checked in the code
   before the change because cached hits are counted from stored metadata.
3. `/api/v2/analytics/overview` keeps its totals as the sum over all languages and adds a
   breakdown by locale. A `locale` filter selects one language. Existing clients keep working
   because no existing field changes.
4. The MCP tools `get_statistics` and `site_report` show the language split when it exists and
   say how much of the period has no split. The central Admin shows the same.
5. The migration is a framework migration (`migrations/6.7.0/`), idempotent (checks the column
   before it adds it), tested on MySQL 8.4 and MariaDB 10.11 and 11.4 like the other ones.

Breakdown:
- [x] Check how the cached-hit path stores events and where the locale is known there.
- [x] Migration with a rerun test, `Tracker` change, `Overview` change (breakdown and filter).
- [x] Unit and integration tests: two languages on one page, totals equal the sum, old rows
      without a locale, a site without languages, retention pruning with the new key.
- [x] `ref-api-v2.md`, README and CHANGELOG, upgrade note (a migration is needed).
- [x] MCP: statistics and report with the split, tests, guide topic `statistics`.
- [x] Admin: the per language view where the overview is shown, `npm test` and `npm run check`.

## B. The version in the header (framework helper, skeleton display)

Decisions:
1. The framework offers `Wbengine\Version::current()`: the Composer version of the package,
   and `dev` when it is not installed through Composer (a checkout). It is exposed as the
   layout variable `wbengine_version` next to the other layout variables.
2. The skeleton header shows `WBengine 6.7.0` in a small, muted font beside the site name.
   Versions that are not a release (`dev`) are shown as given, never as a made-up number.
3. The value is read once per process, not per request through the file system.

Breakdown:
- [x] `Version` class and layout variable, tests with and without Composer data.
- [x] Skeleton `site.tpl` header, a smoke check that the shown version equals the locked one.

## C. The page /changelog/ (skeleton, with a framework CHANGELOG cleanup)

Decisions:
1. First restructure the framework `CHANGELOG.md` into one section per release in the form
   `## [x.y.z] - YYYY-MM-DD`, newest first, with the dates taken from the git tags. `Unreleased`
   stays at the top and is empty after a release. This is a content change only and keeps every
   line of text. The headings `### Added (6.6.0)` split by version become the sub headings
   Added, Changed, Fixed and so on under their release.
2. The skeleton gets a box `changelog` registered in `Application::registerBoxTypes` that
   reads `CHANGELOG.md` from the installed framework package, renders it with the Markdown
   renderer the framework already has, and caches the result per version of the package. A
   release the file does not know is simply absent. There is no network call.
3. The page `/changelog/` is added by a guarded content migration with a menu entry named
   Changelog and a Czech name. The release notes stay in English in both languages, because
   `CHANGELOG.md` is written in English. The headings and the introduction are translated.
4. The page lists the newest release first, shows the version and date, links each release to
   its GitHub release when the address is known, and puts the older releases (before 5.0.0)
   behind a collapsed block so the page stays readable.
5. The header version of B links to `/changelog/`.

Breakdown:
- [x] Restructure `CHANGELOG.md` (script to move and date the sections, a test that every
      heading has a version and a date and that no line was lost).
- [x] The parser and the box, with unit tests on a sample file and on the real file.
- [x] Migration 038 (page, menu entry, translations) mirrored at the end of the content dump,
      checked on a scratch database like 037.
- [x] Smoke test: `/changelog/` returns 200 in both languages and lists the installed version.

## D. The overview of /blog/ (skeleton)

Decisions:
1. A new local box `blogindex` replaces the `story` box on the page `/blog/`. It lists the
   pages under `/blog/` that have a published article, using the same conditions as the sitemap
   (visible, public, no hidden article), in the language of the request with the article
   translations applied.
2. Three blocks. Newest: the latest posts as cards with title, date, author and an excerpt
   (the introduction, Markdown turned into plain text, cut at a word). Most read: the top three
   by `cms_articles.views`, shown only when at least one article has views. All posts: a plain
   list by date, with a page size so a site with many posts stays fast.
3. The demo article that is now the content of `/blog/` moves to its own page
   `/blog/articles-with-the-bundled-box/`, so it appears in the list like any other post. It is
   rewritten at the same time: the present text is about the bundled box and a 2017 example;
   the new text describes what the framework really gives a site (pages and boxes from the
   database, languages, API v2 and keys, MCP for editors, the page cache, native analytics,
   the sitemap), with only claims that the documentation backs.
   This changes what `/blog/` shows, not the address of any other page. Done by a guarded
   migration and documented.
4. The MCP default for a new article stays under `/blog/`. `list_pages` then no longer marks
   `/blog/` as showing an article, which is correct, and `plan_article_page` still finds the
   blog page by its address. Both are checked after the change.
5. Excerpts only for now. Articles have no image field, so image previews need a new field in
   the article model and the API. That is a separate, later decision (see questions).
6. The page stays cacheable for anonymous visitors. View counts may be up to the page cache
   lifetime old, which is acceptable for a ranking.

Breakdown:
- [x] The box, its query (one query with the translation overlay, no per post query), the
      excerpt helper with unit tests (Markdown, short text, long text, empty introduction).
- [x] Template for the three blocks, responsive, both languages, tested in the smoke test.
- [x] Migration 039: move the demo article, switch the `/blog/` box, mirrored in the dump.
- [x] Check `list_pages` and `plan_article_page` against the new `/blog/`.

## E. Documentation audit and new pages (skeleton)

Method: first an audit table, then the writing. The table lists every feature of releases
4.0.0 to 6.7.0 (taken from `CHANGELOG.md`), the page that documents it, and one of: complete,
thin, missing. The owner reviews the table before the pages are written. The documentation
rules stay: plain facts, no information about how the site was made, English and Czech.

Known gaps already found:
- MCP server: no page. Needed: what it is, which key and which preset to issue (Editor,
  Agent for content, Read only), what the tools do in plain words, the guide, prompts and
  resources, what it never does (nothing is deleted, publishing only on request), how a client
  connects, how to update the phar, troubleshooting (a server that does not start, the
  placeholder path, a key that is restricted to other addresses).
- Per platform client setup, see F.
- API v2: hiding and showing articles, the change log feed with its filters, analytics with
  the language split, images and their limits, the key presets and the address restriction.
- Sitemap, robots.txt, canonical link and hreflang: one page that explains the whole set and
  the `seo` settings, with the rule for strict and dynamic pages.
- The short pages Boxes, Languages, Security and Caching: check against the current framework
  and extend where behaviour changed (dynamic boxes and `strict`, translated content, address
  restrictions, page cache controls).
- Statistics: how the numbers are counted, what they do not contain, the retention, the report.

Breakdown:
- [x] The audit table (a file in the repository, reviewed with the owner).
- [ ] New and extended pages in English and Czech through guarded content migrations, each
      mirrored in the dump and verified on a scratch database.
- [ ] A check that every page has both languages and that no page links to a missing page.
- [ ] Menu and docs index updated.

## F. MCP on macOS, Linux and Windows (framework CI, skeleton documentation)

What a user needs per platform: PHP 8.3 or newer on the PATH, the downloaded
`wbengine-mcp.phar` and its checksum, the client configuration with the three environment
variables, and the command to add it to the client. The differences to document and verify:

- macOS and Linux: `php` from Homebrew or the package manager, a path such as
  `/home/name/wbengine-mcp.phar`, `claude mcp add` with the environment passed by `--env`.
- Windows, recommended: run Claude Code and the server inside WSL2. Then it is the Linux case
  below and needs nothing special. The documentation leads with this.
- Windows, native (second option): `php.exe` from the PHP download or a package manager, a path with a drive letter
  and backslashes (escaped in JSON), the quoting rules of the shell used (PowerShell and cmd
  differ), and the `phar` extension enabled in `php.ini`. Which of these needs a wrapper has to
  be found by running it, not by reading documentation.
- Other clients (Claude Desktop and similar) use their own configuration file, in a different
  place per platform. Only what was verified is written down, the rest is marked as not
  verified.

Verification:
1. A CI matrix job on `ubuntu-latest`, `macos-latest` and `windows-latest` builds the phar
   (or downloads the release asset) and starts it from a Node script the way a client does:
   initialize, a tool list, a request after a quiet period longer than PHP's 60 second socket
   timeout, then a clean stop. This is the test that would have caught 6.5.4 on any platform.
2. A real Claude Code run exists only on macOS. For Linux and Windows the documentation says
   what the CI run proved (the server starts, talks the protocol and survives a quiet minute)
   and what it did not (a real client session on that platform).
3. The Integrations page (framework) shows the configuration with a hint for the path on each
   platform instead of the single placeholder, and says which part to replace.

Breakdown:
- [x] CI job and the Node client script in the repository, green on all three platforms, with
      any platform-specific fix found by the run.
- [x] Integrations page: per platform path hints and tests of the generated text.
- [x] Documentation page for the clients, English and Czech, with a "verified" line per
      platform that states what was proven (`/docs/mcp-clients/`, skeleton PR 54).

## Order of work and releases

1. Framework 6.7.0: A (views per language), B (`Version`), the `CHANGELOG.md` restructure of C,
   the CI matrix and the Integrations hints of F. One release, one migration.
2. Skeleton: bump to `^6.7`, header version (B), `/changelog/` (C), the blog overview (D),
   the documentation (E and the client page of F), in separate pull requests, each deployed
   and checked on the live site before the next.
3. After each release: update the plan boxes, the memory notes and the live checks.

## Answers of the owner (2026-11-01)

1. Blog previews: excerpts now, image previews later. Yes.
2. Changelog: the framework only, English. Yes.
3. The demo article moves to its own address, and it is rewritten so that it describes the real advantages of the framework (see D).
4. Windows: the recommended way is to run Claude Code and the MCP server inside WSL2, where the Linux instructions apply. Native Windows stays documented as a second option, CI-verified. Running Claude Code on Windows with the server in WSL2 through `wsl.exe` is documented only if it is verified.

## Open questions (answered above, kept for the record)

1. Blog previews: excerpts only now, image previews later with a new article field.
   Recommended: yes, excerpts now. An image field changes the article model and API v2.
2. Changelog content: the framework's `CHANGELOG.md` only, in English in both languages.
   Recommended: yes. The skeleton's own `docs/CHANGELOG.md` is deployment detail and would
   confuse a visitor.
3. The 2017 demo article moves from `/blog/` to its own address so the overview can list it.
   Recommended: yes.
4. Windows: CI proves the server runs there, a real Claude Code session on Windows is not
   something that can be run here. The documentation will say so. Recommended: accept that.

## Out of scope

- Image fields for articles and thumbnails.
- Comments, tags, categories and search on the blog.
- A hosted multi-site MCP server in the central Admin (later, own plan).
- Translating the release notes.

## Verification at the end

Each step: unit and integration tests, green CI on the supported PHP and database matrix, a
scratch database run for every content migration (applies once, safe to rerun, equals a fresh
install), then live checks with real requests and a statement of what was and was not
verified.
