# Analytics Integration into WBengine Admin

> **For implementation:** proceed through the tasks below; for each, first add a regression test, verify that it fails, implement the change and run the test again. Work in separate branches of the respective repositories and keep separate checkpoint commits.

**Goal:** Display aggregate metrics of a selected WBengine site in the private administration and prove by tests that permissions, time periods, remote errors and site separation work safely.

**Architecture:** The administration remains a server-rendered SvelteKit application. A new server-side analytics gateway uses the existing HTTPS client, the connection to the specific site, the MFA session, the user grant and the integration scope `analytics:read`; the sign-in key stays exclusively on the server. The dashboard will live at `/analytics/[site]` and will read a single versioned summary endpoint.

**Technology:** Node.js `>=22.18`, SvelteKit `3.1.0`, Svelte `5.57.2`, TypeScript `6.0.3`. Do not add frontend or chart packages.

**Specification:** [plan-analytics.md](plan-analytics.md). This plan covers the administration integration and its test gate. The API and metric storage must be delivered by the framework according to the same specification.

## Global constraints

- Remote communication uses the existing HTTPS client `src/lib/server/remote.ts`, the target address check and the 1 MiB response limit.
- Private routes require the existing authentication with MFA and the user's permission for the given site.
- The remote key must not be part of the data passed to a component, the HTML, client JavaScript or an error message.
- The only analytics scope is `analytics:read`; the dashboard changes nothing on the remote site.
- Do not persist visitor IP addresses, cookies, User-Agent, referrer or individual visit records in the administration.
- No analytics route may return data from a cache shared between sites; the global hook already sets `Cache-Control: no-store` and this behavior must remain verified.
- A missing scope, an unsupported endpoint, empty data and a site outage are distinct states. They must not all be displayed as zero visits.

## Interface between the API and the administration

The framework will provide:

```text
GET /api/v2/analytics/overview?from=YYYY-MM-DD&to=YYYY-MM-DD&granularity=day|week|month
```

A successful response `data` contains:

```text
timezone: IANA time-zone string
period: { from, to, granularity, complete }
totals: { page_views, article_views, box_appearances }
series: [{ period_start, page_views, article_views, box_appearances }]
top_pages: [{ id, title, views }]
top_articles: [{ id, title, views }]
top_boxes: [{ id, name, appearances }]
```

Counters are non-negative safe integers. The field `complete=false` means that the requested period starts before the first recorded public view. Ranges outside retention are rejected by the API. The API returns at most 10 items in each top list. The endpoint requires `analytics:read` and the parameters must not exceed the 62-day retention.

## File overview

### WBengine Admin, checkout `/Users/roza/Sites/svelte/wbengine-admin`

- `src/lib/server/api-contract.ts`: safe type and serialization of the analytics read request.
- `src/lib/server/analytics.ts`: range parsing, response validation and the scope gateway.
- `src/routes/content/[site]/+page.server.ts` and `+page.svelte`: availability of and link to analytics according to scope/capability.
- `src/routes/analytics/[site]/+page.server.ts` and `+page.svelte`: protected loading and display of the dashboard.
- `src/lib/components/AnalyticsChart.svelte`: a lightweight accessible chart without an extra dependency, with a table alternative.
- `tests/analytics.test.ts`: range validation, permissions, data shape and zero/partial results.
- `tests/analytics-transport.test.ts`: HTTP method, query, bearer scope and secret protection.
- `tests/api-pilot.ts`: a real browser-like scenario against separate PHP API sites.
- `README.md`: scope installation and dashboard usage.

### WBengine framework, checkout `/Users/roza/Sites/php/wbengine`

- `tests/pilot/seed-installation.php`: apply the new analytics migration and deterministic test aggregates for the isolated pilot sites.
- `tests/pilot/ref-pilot.md`: pilot prerequisites and cleanup, if the fixture procedure changes.
- The existing administration `.github/workflows/ci.yml` already runs the two-site PHP API pilot. Keep the test in this job; do not create a parallel non-isolated production test.

## Review Focus

- A user has access to one site and tries a different `site_id`: rejection must happen before the remote call and no foreign data may be displayed. Covered by Task 2 and Task 4.
- The integration key lacks `analytics:read`: the dashboard must explain the missing permission and must not display empty charts as real zeros. Covered by Task 1 and Task 4.
- The remote site returns a malformed, oversized, negative or time-inconsistent response: the dashboard rejects the data without partial rendering and without leaking the key. Covered by Task 1 and Task 3.
- The range exceeds retention, has swapped boundaries or crosses a time zone: the form rejects the range or explicitly marks incompleteness, and never silently widens the data. Covered by Task 1 and Task 3.
- The API site is unavailable or returns 403/404/503: the page shows an understandable state separate from zero traffic, preserves separation of the other sites and sets `no-store`. Covered by Task 3 and Task 4.

---

### Framework prerequisite: Analytics API

- [x] The scope `analytics:read`, `GET /api/v2/analytics/overview`, `lifetime_totals`, `capabilities.analytics.read` and validation of boundaries/retention are implemented in the framework worktree.
- [x] `ApiV2AnalyticsTest` tests: 4 tests, 14 assertions.
- [x] The framework collects anonymous content IDs from the tree and PageCache, stores totals transactionally and offers CLI cleanup.
- [x] The Docker pilot applied the migration and verified separate daily totals of two independent installations.
- [x] Public rendering records anonymous counts including cache metadata; the analytics dashboard loaded data over HTTPS.
- [x] An editor with access to the first site did not receive permission for the second site.

### Task 1: Analytics API contract and safe gateway

Status: implemented; unit tests, transport tests, type check and production build passed.

**Files:**

- Admin Modify: `src/lib/server/api-contract.ts`
- Admin Create: `src/lib/server/analytics.ts`
- Admin Create: `tests/analytics.test.ts`
- Admin Create: `tests/analytics-transport.test.ts`

**Interface:**

- `AnalyticsGranularity = 'day' | 'week' | 'month'`.
- `AnalyticsRange = { from: string; to: string; granularity: AnalyticsGranularity }`.
- `parseAnalyticsRange(params: URLSearchParams, now: Date): AnalyticsRange` uses, when the range is missing, 30 calendar dates ending on the previous UTC day, so that the date is not in the future for any valid time zone of the target site. It rejects an invalid date, `from > to`, an invalid granularity and a range longer than 62 days.
- `validateAnalyticsOverview(value: unknown, expected: AnalyticsRange): AnalyticsOverview` verifies the contract, counts, period boundaries, IANA timezone, at most 10 top items and a match of the requested granularity.
- `canReadAnalytics(role: Role, scopes: string[], capabilities: Capabilities): boolean` returns true only with a role allowed site read, the `analytics:read` scope and `capabilities.analytics.read === true`.
- `createAnalyticsGateway({ role, scopes, capabilities, call }).overview(range): Promise<AnalyticsOverview>` rejects missing permission before the network and calls only the GET overview.
- `ApiCall` gets the operation `analyticsOverview` and required `from`, `to`, `granularity`. `planApiCall()` returns the exact path `/analytics/overview` and a deterministically encoded query string.

- [x] **Step 1: Add regression tests of range and scope**

  In `tests/analytics.test.ts` cover the default of the last 30 days, the 62-day length, ISO date, swapped boundaries, a disallowed granularity, an invalid timezone, a negative/non-integer counter, a missing series, a top list longer than 10, an incomplete range and the ban on calling the remote callback without `analytics:read` or the capability.

- [x] **Step 2: Run the new unit test and confirm failure**

  Run from admin repo: `node --experimental-strip-types --test tests/analytics.test.ts`

  Expected: FAIL, because the analytics helper and operation do not exist yet.

- [x] **Step 3: Add a typed read-only API operation and gateway**

  Implement the types and functions above. Validate parameters before serialization and do not allow the client to pass a path, HTTP method, scope or free-form query parameters.

- [x] **Step 4: Verify the unit test**

  Run: `node --experimental-strip-types --test tests/analytics.test.ts`

  Expected: PASS for all valid and invalid inputs.

- [x] **Step 5: Test the real HTTPS transport with a fixture server**

  In `tests/analytics-transport.test.ts` verify `GET`, the exact query path, `Authorization: Bearer`, the response limit, not accepting the key in a JSON response and that 403/404/503 end as a sanitized `RemoteFailure`.

- [x] **Step 6: Run the transport test and commit**

  Run: `node --experimental-strip-types --test tests/analytics-transport.test.ts`

  Expected: PASS; commit in the admin repository `feat: add analytics API gateway`.

### Task 2: Protected dashboard page and navigation

Status: implemented, permissions stay in the server loader, the HTML does not expose the integration key. The production build passed.

**Files:**

- Admin Modify: `src/routes/content/[site]/+page.server.ts`
- Admin Modify: `src/routes/content/[site]/+page.svelte`
- Admin Create: `src/routes/analytics/[site]/+page.server.ts`
- Admin Create: `src/routes/analytics/[site]/+page.svelte`
- Admin Create: `src/lib/components/AnalyticsChart.svelte`
- Admin Modify: `src/lib/server/analytics.ts`

**Interface:**

- Route URL: `/analytics/{siteId}?from=YYYY-MM-DD&to=YYYY-MM-DD&granularity=day|week|month`.
- The server loader first uses `signedIn(e)`, then `connectContent(session, siteId)` to verify MFA and the site grant. Only after that does it verify scope/capability and ask the remote API.
- The server loader returns only the site name, the normalized range, timezone, aggregate counters and top lists. It never returns `gateway`, a callback or the integration key.
- When analytics support is absent, show the state "Analytics is not enabled for this site". Zero results are shown as an empty state, not as an error.
- The daily/weekly/monthly choice and the from/to dates will be submitted as a GET query. The chart must have an accessible text summary or table.

- [x] **Step 1: Add tests of permissions and the dashboard data model**

  Extend `tests/analytics.test.ts`: a failing grant/scope must not call the remote callback; zero visits is valid; `complete=false` keeps the warning about incomplete history; unsafe names from the API remain plain text.

- [x] **Step 2: Run the unit test and confirm failure**

  Run from admin repo: `node --experimental-strip-types --test tests/analytics.test.ts`

  Expected: FAIL for the new dashboard permissions and partial/empty states.

- [x] **Step 3: Add the server loader and navigation link**

  Use the existing `contentLoad()` for sanitized HTTP errors. The site hub shows the link only when `canReadAnalytics(...)`; opening the route directly without permission ends in 403 or the existing safe error page. Add `Cache-Control: no-store`, even though the global hook sets it.

- [x] **Step 4: Add the accessible display**

  Render a summary of page views, article views and box appearances, a timeline and top lists. Svelte escaping must stay active for names from the API. Draw the chart in native SVG/CSS and make the same values available in table form.

- [x] **Step 5: Verify types and build**

  Run: `npm run check && npm run build`

  Expected: the Svelte/TypeScript check and the production build pass without new dependencies; commit `feat: add per-site analytics dashboard`.

### Task 3: Real two-site PHP API pilot

**Files:**

- Admin Modify: `tests/api-pilot.ts`
- Framework Modify: `tests/pilot/seed-installation.php`
- Framework Modify: `tests/pilot/seed.php` only if a deterministic daily fixture is needed.
- Framework Modify: `tests/pilot/ref-pilot.md` only when the startup or cleanup procedure changes.

**Interface:**

- Both isolated installations get their own `analytics:read` key and a different name of the seeded page, so that the pilot proves data separation. A missing scope is verified by the framework API tests and the Admin gateway test without a remote call.
- The administration tests a real MFA session, site grant, an encrypted key on the server, the real HTTPS API and server-rendered HTML.

- [x] **Step 1: Prepare isolated analytics fixtures in the pilot DB**

  Use a fixed date within retention and create known daily totals for pages, articles and boxes. Deliver the migration before the seed. After cleanup the test must leave both regular DBs unchanged.

- [x] **Step 2: Add end-to-end regression scenarios**

  In `tests/api-pilot.ts`, after MFA sign-in, verify the MFA session, link availability, valid and invalid periods, seeded values, separation of the second site's data, `no-store`, absence of keys in the HTML and rejection of the second site for an editor with a grant only for the first site. A missing scope and an outage are verified in the gateway/transport tests and existing outage scenarios.

- [x] **Step 3: Run all administration tests and the two-site pilot**

  Run from admin repo: `npm test && npm run check && npm run build`.

  Run from framework repo: `sh tests/pilot/pilot.sh start`, `sh tests/pilot/pilot.sh probe`, `sh tests/pilot/pilot.sh consumer /Users/roza/Sites/svelte/wbengine-admin`, `sh tests/pilot/pilot.sh backup-check`, then `sh tests/pilot/pilot.sh reset`.

  Result 2026-10-27: `npm test` passed (43 tests), `npm run check` passed without errors and warnings, `npm run build`, `npm run test:mysql` and `npm audit --audit-level=high` passed; npm audit found no vulnerabilities. The local Docker pilot of both separate HTTPS APIs passed, including the central forms, the analytics dashboard and data separation. After the new table `cms_analytics_meta`, `backup-check` verified 20 tables and 158 rows with matching schema and data hashes; `reset` removed only the pilot containers, network and volume.

  Framework GitHub CI run [40570318253](https://github.com/rrsoftcz/wbengine/actions/runs/40570318253) passed all 7 jobs: PHP 8.3–8.5, MySQL 8.4, MariaDB 10.11/11.4, Texy migration and Smarty compatibility. Admin GitHub CI run [40570331198](https://github.com/rrsoftcz/wbengine-admin/actions/runs/40570331198) passed `test` and the two-site API pilot.

  After the subsequent revision, the CI pilot is pinned to framework commit `351a5e79b0061d69a1e714552ccdd4c07b675db0`, not the old tag `v4.0.0`. This commit contains the new API, migration metadata, measurement start and the public event guard. After the framework is released, the pin is updated to the stable release tag.

  The local checkpoint of the administration change is `34c9f6871c00358c96f04c8eda30055c4cf4bf4e` (`fix: align analytics CI with framework API`).

  A read-only review on 2026-10-27 found, and the following were then fixed: article/box events before the public HTML render and an inaccurate `period.complete` before the start of measurement. The default admin period also ends on the previous UTC day, so that it is not a future date on a site in a western time zone.

- [x] **Step 4: Record evidence and commit both repositories**

  Write both local and remote CI evidence into the plan. Separate commits are on the target branches framework `master` and admin `main`; release and deployment remain.

### Task 4: Guide and readiness for enabling

**Files:**

- Admin Modify: `README.md`
- Framework Modify: `README.md`
- Framework Modify: `plan-analytics.md`
- Framework Modify: `plan-framework-improvements.md`

- [x] **Step 1: Document configuration and site support**

  Explain the new `analytics:read` scope, the 62-day availability of daily rows, permanent lifetime totals, the definition of a view, inclusion of robot requests, the empty/partial state and Docker CLI maintenance.

- [x] **Step 2: Update the decision tracker**

  Record the completed implementation, local test evidence, the migration, retention maintenance and the open deployment. Both CI suites passed. GA4 stays active until a separate operational comparison.

- [x] **Step 3: Verify the documentation changes**

  Run: `git diff --check` in both repositories.

  Result 2026-10-27: `git diff --check` passed in both worktrees; the READMEs state the manual migration and opt-in configuration before enabling.

## Completion boundary

The administration is ready for release when the unit tests, HTTP tests, type-check/build and the two-site actual API pilot pass; a user with a limited grant or a key without the analytics scope gets no data; outages are understandable; and `no-store` and the absence of secrets are verified. GA4 is switched off only in a separate approved operational step after both the framework and the administration are deployed.

Status 2026-10-27: local verification and the GitHub CI release gate passed. The changes are on `wbengine/master` and `wbengine-admin/main`. WBengine Admin stays local on the development Mac. The public Skeleton App was cleanly deployed to the new VPS from commit `fcaf38a`; the new database was created from the current seed files without transferring the old database, and migrations `002`, `005` and `006` were applied. Analytics stays disabled, the public capabilities do not yet contain `analytics.read`, GA4 stays active. Verification of HTTPS, simulated certificate renewal and invalidation of the demo account passed. A limited integration key has not yet been created or handed over, and Admin is not connected to the live site.

Release 2026-10-27: framework `v4.1.0` and the private admin `v0.2.0` were published. The tags point to the CI-verified commits `cdf7b90287825af5d1d0f16445c43965a56c3231` and `6e4a1253c3154ae0d1bfb88bc67ea83ab8e9d74d`. The CI runs for the [framework](https://github.com/rrsoftcz/wbengine/actions/runs/40573908541) and the [admin](https://github.com/rrsoftcz/wbengine-admin/actions/runs/40573888645), including the two-site API pilot, passed. The Skeleton App pin to `v4.1.0` and the smoke fixes are in `main`: `5bcc949`, `49d7646`; the new [CI smoke gate](https://github.com/rrsoftcz/wbengine-skeleton-app/actions/runs/40577284046) passed. The user confirmed that WBengine Admin will run locally on the development Mac and will not be hosted. On 2026-10-27 the public site was cleanly deployed to the new VPS; HTTPS, four public paths, the database containers and rejection of the demo sign-in are verified. The new database uses the current repo seed without restoring old data. Migrations `002`, `005` and `006` are applied, but analytics is disabled and the public capability does not contain `analytics.read`. The old server was neither copied nor modified. The next step is to prepare the local Admin for a safe connection to this site, create a limited key by a trusted path and verify the read-only API. GA4 stays active until this verification is complete.

## Recommended order

First finish the framework analytics API and the isolated pilot fixture. Then follow with Task 1 and Task 2 in the administration. Task 3 and Task 4 form the release gate and should happen before release. Task 5 closes the documentation and the checkpoint.
