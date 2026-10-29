# WBengine 4.0.0

Released 2026-10-26. The v4.0.0 tag points to merge commit cf710e5adbc25809868a864e615f8b9fc62a7489. Production data was not migrated.

## Changes

- Own native compiled templates as the foundation, Smarty only as an optional compatibility package. Default escaping, diagnostics for unsupported syntax and correct ownership of output buffers.
- Own restricted Markdown parser, AST and safe HTML renderer, .md files and explicit DB formats. Texy only as an optional offline converter. Dry run, approved hash, exact backups and conflict-checked rollback of articles and translations.
- Deferred CMS, terminal routes before the cache, personalized cache scopes, safer sessions, inputs, CSRF, proxy and authentication. Parameterized menu lookups and a measured boxes index.
- API v2 with restricted keys/scopes, audit and limits, content CRUD, registered boxes, actually available settings of an executed box, drafts/revisions/publishing, translations, structure recovery, ETag and public raster media.
- Separate central SvelteKit administration: invitations, MFA, per-site roles, encrypted keys, verified pinned HTTPS, editor, preview/publish/recovery and preservation of text being edited on conflict and outage.
- Installed Composer CLI for diagnostics, read-only schema and a safe scaffold; a repeatable real CMS benchmark matrix.

The PHP core keeps Application/Site/Section/Box, namespaces, lazy services and the model/adapter patterns. No parallel PHP framework was created. A URL map is deliberately not introduced after measurement: a real indexed SQL lookup preserves the DB collation and has no needless full-table/memory costs.

## Verification

- Core: 1989 tests, 5666 assertions on PHP 8.4.27/MySQL 8.4; audit and lint without errors. CI on PHP 8.3/8.4/8.5 and MariaDB 10.11/11.4.
- Optional Smarty 86/123 and offline Texy 1/5, separate CI profiles. The core does not install them; the only runtime Composer dependency remains firebase/php-jwt.
- Sample site: 20 real HTTP checks, fresh no-dev optimized strict PSR autoload without Smarty/Texy; 15-point CMS matrix, 300 requests, 0 errors. Public page HIT 0 SQL/0 connections, personal pages are not a shared HIT. This is a small localhost fixture, not a production capacity guarantee.
- Administration: 34 unit/transport/action/component tests, 5 MySQL scenarios and an HTTP auth smoke test, check 0 errors/0 warnings and a production Docker build. The component test compiles a real Svelte form.
- Two independent HTTPS sites: cross-key 401, separate changes/databases, restricted editor, actual HTML draft/preview/publish/revisions/conflict/recovery/translations/media, key revocation and outage/manual retry. Public sites stayed available after the central administration was shut down.
- Real DB dump/restore of 17 tables with identical definitions and SHA256 of data, helper artifacts cleaned up. The shared automatic admin CI pilot passed, including this restore. The Node HTTP/SSR test does not replace graphical Chromium automation.

## Migration impacts

This is a new major version. Check direct Smarty/Texy APIs and plugins, raw versus escaped output, unmodified HTTP inputs, POST logout and purpose-bound CSRF, the deferred lifecycle and the production cache. Old HTML is not automatically labelled Texy or Markdown. The new API connector requires explicit SQL002/003/005 and InnoDB; content migrations also 004. The schema does not change by itself during a request.

The compatibility Composer packages in compat/ are not yet published separately. For a local path repository, use the documented mirror setting symlink=false. Preview is private; uploaded media are public and the storage must be permanent and outside the document root. An external CDN is not purged automatically.

Exact procedure and rollback: book-wbengine-4-upgrade.md. Current tasks and history: plan-framework-improvements.md. API, Markdown and templates have separate ref documents. The operations administrator must verify their own backup, the specific content and the manual migration issues before deployment.

## Merged changes

- Core: https://github.com/rrsoftcz/wbengine/pull/2
- Sample application: https://github.com/rrsoftcz/wbengine-skeleton-app/pull/1
- Private central administration: https://github.com/rrsoftcz/wbengine-admin/pull/1

GitHub release: https://github.com/rrsoftcz/wbengine/releases/tag/v4.0.0. Production deployment and the conversion of operational data are separate controlled operations according to the upgrade guide.
