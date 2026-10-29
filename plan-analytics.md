# WBengine In-House Analytics Design

Status as of 2026-10-27: approved plan; the analytics API and storage are part of framework `v4.1.0`. A fresh production database has migration 006, but collection is switched off. Connecting the local administration and enabling measurement remain open.
Date: 2026-10-27.

## Purpose

Replace Google Analytics for a basic overview of site traffic with WBengine's own analytics, displayed by a separate private administration. Measure content traffic and trends, not the identity or behavior of individual visitors.

## Decisions and boundaries

- Lifetime article view counts are kept permanently. Existing `cms_articles.views` values are not discarded when this is introduced.
- Daily article views will be available for at least one month. Weekly and monthly figures will be computed from daily aggregates. Older daily aggregates may be removed; lifetime totals remain.
- The analytics storage will not record IP addresses, cookies, user IDs, User-Agent, URL query parameters, referrer or individual visits.
- Without an identifier, the number of unique people cannot be determined exactly. The interface will therefore use the term "views", not "unique visitors".
- A box counter means that the box generated content included in the public response. It does not prove that the visitor actually saw the content on screen.
- Summaries will not be deleted when a user account is deleted, as long as they cannot be linked to a specific person.
- Until this feature is finished and deployed, any measurement through GA4 remains a separate operational matter. Switching it off or removing historical reports is not part of this phase.

## Approaches considered

1. **Aggregation in WBengine with batched writes:** the framework records only counters and daily totals. The existing `Wbengine\\Counter\\BatchCounter` limits the number of database writes. It requires adding handling of cache HITs and reliable flushing of low-volume counters.
2. **Writing every view directly to the database:** simple flow and current data, but it adds a database operation to every view and box.
3. **Analysis of web server logs:** captures cache HITs without touching rendering, but introduces a dependency on the log format and rotation. Logs may contain personal or identifying data and do not provide a reliable link between a request and boxes.

The recommended direction is the first approach, because it matches the WBengine tree architecture and limits the cost per request. Its implementation must include regular flushing of batches. Merely waiting for the next visit to the same content could postpone the write for too long on rarely visited pages.

## Proposed MVP metrics

- Public page views by `site_id`, per day and as a lifetime total.
- Article views by `article_id`, per day, per week, per calendar month and as a lifetime total.
- Box appearances by `box_id`, per day and as a lifetime total.
- Total site traffic by day, week and month.
- Ranking of the most visited pages, articles and boxes for a selected period.

The MVP will not report unique visitors, time on page, paths between pages, demographics, campaigns or devices. Metrics will not be split by language until it is determined how to map a language to a content ID safely and consistently.

## Counting rule

- Count successful public `GET` requests that return the HTML of a specific site. A cache HIT is counted the same as a dynamically rendered response. A 304 response counts as a view of the requested content.
- Do not count the API, administration, signed-in or non-public pages, previews, static files, redirects and error responses.
- One request may increase the page and article view counts and, at the same time, the appearance counts of the rendered boxes. Each dimension will be stored separately.
- The content ID is obtained in the existing layers of the tree: the site in `Wbengine\\Site`, boxes in `Wbengine\\Section` and the article in its box/model. Do not create a parallel route resolver or a separate application architecture.
- By default, counting will also include unknown robot requests, because without identification a real human cannot be determined safely. Excluding known robots may be added as a separate optional feature and will not store the User-Agent.

## Daily period and retention

- Data totals will be stored per calendar day in the time zone configured for the site. The proposed configuration key is `analytics.timezone`, with the default value `UTC` and validation of the IANA name.
- The recommended retention of daily rows is 62 days. This makes it possible to display the current and previous calendar month including the daily course. These are aggregate counters without visitor identifiers.
- The weekly total is computed per ISO week from Monday to Sunday. The monthly total means a calendar month; the API must report when the requested period exceeds the retained daily history.
- After retention expires, daily rows are removed. Lifetime totals are kept.
- A regular CLI command performs a safe batched flush of pending counters and pruning of daily rows past retention. The operations guide will define running it through the container scheduler. The exact command name will be determined during implementation according to the command registration in `bin/wbe`.

## Storage and performance

- New models will live in the `Wbengine\\Analytics` namespace; the service will be available through the current lazy Application access and tied to Site/Section/Box where the content ID arises.
- The proposed database storage separates short-term daily totals from permanent totals. The daily key will contain the date, site, metric type and content ID. The permanent table will contain only these dimensions and the lifetime count.
- For articles, compatibility with the existing `cms_articles.views` will be preserved. The historical value will be the default lifetime base; the daily analytics series will start on the date the new feature is enabled and will not pretend to contain retroactively obtained historical data.
- The batch counter will distinguish the daily key so that a later flush is not credited to the wrong day. The current `BatchCounter` stores only the key hash and its callback lives only within the request process, so it cannot itself enumerate pending low-volume totals for a later CLI flush. Analytics therefore needs a recoverable list of pending aggregation keys or its own queue with safe, bounded dimensions. Pruning daily totals must not subtract from the lifetime count.
- `PageCache` now serves a HIT before Site/Section/Box run. The cached object will therefore need bounded metadata of content IDs and, after a HIT, pass only these IDs to the aggregator. The metadata will not contain URLs, cookies or visitor data.
- A request must not perform one synchronous SQL write per box. Acceptance measurement will set an upper bound on work for both MISS and HIT and verify that handling a HIT does not trigger loading the CMS database cache.
- A failure of analytics must not cause an error on the public page. Errors are logged operationally without IP, URL query, User-Agent or request content; the rate of lost pending counters on a process crash will be documented.

## API and administration

- Extend `Wbengine\\Api\\V2` with a read-only scope `analytics:read`. An integration without this scope must not obtain any analytics data.
- The recommended first endpoint is `GET /api/v2/analytics/overview?from=YYYY-MM-DD&to=YYYY-MM-DD&granularity=day|week|month`. It returns a site summary and bounded lists of the most visited pages, articles and boxes. Detailed per-entity endpoints may follow if they prove necessary.
- The overview response will have `timezone`, `period` (`from`, `to`, `granularity`, `complete`), `totals` (`page_views`, `article_views`, `box_appearances`), `lifetime_totals` with the same keys, and `series` (`period_start` and the same counters), plus `top_pages`, `top_articles`, `top_boxes`. A top list record contains a stable ID, a safe display name and a count. `complete=false` marks a period that starts before the first recorded public view.
- The API accepts only a valid from/to date in the supported range and the granularity `day`, `week` or `month`; the maximum period is limited by retention (by default at most 62 calendar days including today). The response states the time zone, the exact boundaries and information about a missing or partial range. It does not accept arbitrary SQL, a table name or an arbitrary metric from the client.
- The private SvelteKit administration will display a trend overview, the most visited content and boxes, and the option to switch between daily, weekly and monthly summaries. Do not introduce client-side measurement of web visitors into the administration.
- The change requires a coordinated release of the framework, migration of the sample application and changes to the administration client. Public analytics endpoints and a public dashboard are not goals.

## Transition from GA4

1. Implement aggregation and the API behind a disabled configuration.
2. Verify counting on the public site, cache MISS/HIT, day boundaries, batching, retention and permissions in a test application.
3. Display the metrics in the management administration and, for a limited time, compare overall trends with GA4. The numbers need not match, because definitions, consent, robots and cache capture differ.
4. After confirming it works, switch off the GA tag and remove the consent storage used exclusively for GA. Update the privacy and cookie policies. Verify whether the site uses any cookies other than the necessary ones.
5. Historical GA reports may be kept for reading or a separate request for their deletion may be made. Switching off the tag does not delete them by itself.

## Acceptance conditions

- Counts for an article are correct for a single day, a seven-day interval, a calendar month and the lifetime period; weekly and monthly totals match the daily data.
- Lifetime `cms_articles.views` values before migration are preserved and daily history before enabling is not invented.
- One cache MISS and the following HIT requests increase all corresponding aggregates exactly once per request. 304, error responses, the API, static files and non-public pages follow the stated rules.
- After 62 days only old daily rows disappear; lifetime counts remain unchanged.
- The database and analytics batch files contain no IP addresses, cookies, User-Agent, query parameters, referrer or per-visit records.
- A missing or insufficient `analytics:read` scope returns the standard API rejection; existing API integrations without the new scope keep working.
- The public page stays available even when the analytics storage fails. Performance measurement covers MISS, HIT, a page with many boxes and batch flushing.
- CLI maintenance is repeatable, batched, safe under concurrency with web requests and documented for Docker deployment.

## Implementation decisions

- The user approved as the basis of implementation our own batched aggregation, 62-day daily history, permanent lifetime totals and counting of unknown robot requests. The view count will not be labeled as a count of unique people.
- Before implementation, determine the concrete safe format of the box ID list in the cache metadata, its limit and the behavior when the limit is exceeded.
- Adhere to the proposed endpoint and JSON shape in `Api/V2`; coordinate any contract change simultaneously in the framework and the administration.
- A legal and operational review must, before enabling, confirm the notice about aggregate analytics, the GA transition, the retention settings and the handling of server logs. This design is not a legal opinion.

## Implementation status

- [x] The user approved own aggregated analytics, 62-day retention, permanent counts and continuing with administration testing.
- [x] Added the `analytics:read` scope and the endpoint `GET /api/v2/analytics/overview`.
- [x] The endpoint returns a daily/weekly/monthly series, top content, period totals and lifetime totals; pagination is not required yet.
- [x] Added migration `006-analytics.sql` for daily totals and permanent page/box counts. The migration must be applied manually before enabling.
- [x] Added validation of settings and range; analytics is disabled by default.
- [x] Integration tests of scope, response, lifetime totals and invalid periods pass.
- [x] Capture of public GET renders, boxes with non-empty output and articles; cache metadata version 3 counts HIT and 304 without visitor data.
- [x] One request-local batch is stored transactionally in a bounded number of SQL operations; the CLI `analytics:prune` deletes at most 1000 daily rows per run. A persistent file queue is not used; a crash during a response being processed may lose its batch.
- [x] Recording articles and boxes requires a confirmed anonymous public HTML request. Cache metadata without a valid public page event are ignored.
- [x] `cms_analytics_meta` stores the date of the first measured public view; the API thereby distinguishes unmeasured history from zero traffic.
- [x] A local two-site Docker API pilot verified separation of installations, the analytics dashboard, MFA/grant restrictions and an independent HTTPS API. After adding the measurement metadata, the backup check verified 20 tables and 158 rows with matching hashes.
- [x] The README and the administration guide describe the opt-in configuration, migration `006`, `analytics:read`, the 62-day daily history, lifetime totals and CLI maintenance.
- [x] Framework PHPUnit: 2007 tests, 5732 assertions; `composer lint` passed and `composer audit` found no security advisories.
- [x] Framework GitHub CI run [40570318253](https://github.com/rrsoftcz/wbengine/actions/runs/40570318253) passed all seven jobs. Admin GitHub CI run [40570331198](https://github.com/rrsoftcz/wbengine-admin/actions/runs/40570331198) passed the tests and the two-site API pilot.
- [x] Local checkpoints are pushed to `wbengine/master` and `wbengine-admin/main`: framework `4e46bc3`, `351a5e7`, `bc4b1f9`, `2ad8b78`; administration `50c7487`, `34c9f68`.
- [x] Framework `v4.1.0` and the private administration `v0.2.0` are published as GitHub Releases. The tags point to the tested commits `cdf7b902` and `6e4a1253`.
- [x] Skeleton App `main` locks `wbengine/wbengine` to `v4.1.0` (`5bcc949`). The smoke test now uses framework 4.1, verifies the current 10 articles and the language switcher; local Docker passed and GitHub CI [40577284046](https://github.com/rrsoftcz/wbengine-skeleton-app/actions/runs/40577284046) passed.
- [ ] Deploy only the public Skeleton App to the VPS; the central administration stays local on the development Mac. The VPS has SSH as root and Docker Engine 29.9.2 with Compose 5.6.1, verified via `hello-world`. DNS `www.wbengine.org` now points to this VPS, but ports 80/443 are not listening yet and the site is not available. The apex still redirects to www from the old host. The old site is readable at the original IP and returns API 2.0 without `analytics.read`. Before creating the database on the new host, it must be decided whether the existing DB is transferred or initialized with the current seed.
- [ ] Switching off GA4 remains a separate step after comparing trends and updating the operational/privacy information.
