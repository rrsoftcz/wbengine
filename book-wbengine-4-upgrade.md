# WBengine 4.0: upgrade, verification and rollback

Status: release candidate, 2026-10-26. This procedure deploys nothing by itself. Production changes are made by the administrator of the specific installation after verifying its backup and content. Working registers: plan-framework-improvements.md and the separate plan-admin-implementation.md in the administration.

## Before the change

1. Record the current application commit/tag, the library, the Composer manifest/lock, the configuration, the PHP/DB versions and the available extensions. Choose a maintenance window. The PHP core requires PHP 8.3+, a DB and mbstring depending on the features in use; the offline HTML migration also needs DOM. The administration requires Node 22.18+ or its Node 24 image and its own MySQL.
2. Back up the sources, the private configuration, the database and the media outside the public directory. First restore the backup into a separate schema and verify login and representative content. Do not put passwords and keys into shell arguments, Git reports or tickets. For the dump, use a private defaults-extra-file of your DB client and single-transaction only over InnoDB.
3. Back up the central administration's database and its MASTER_KEY from the secret store separately. A restored encrypted DB is not enough without the matching key. Keep the backup MFA codes in a secure channel.
4. Run the whole procedure on a copy of the target site first. The existing test pilot does not replace an inventory of your plugins, direct Smarty APIs or real Texy/HTML data.

## Application and templates

1. Prepare the application for the deferred lifecycle: the constructor performs no CMS queries or boxes. Prepare layout data/session in the correct Site/Section/Box step, and register custom terminal routes before run(). The sample application has this procedure in its own PR.
2. Inventory the trusted templates with tests/tools/template-inventory.php and the rules in ref-template-migration.md. Convert assign/include/inheritance/plugins according to the language that is actually supported. Native set/t cannot be switched back to Smarty automatically.
3. Set NativeRenderer and keep autoEscape=true. Raw belongs only to HTML that is already safely processed or otherwise trusted. Templates have access to assigned objects; they are not a sandbox for foreign authors. A separate .md source, by contrast, executes no PHP or template logic.
4. Direct users of Http::secureClean must move to unmodified inputs, validation, parameterized SQL and context-aware output escaping. Empty text is not the same as a missing JSON key. The JWT iat is no longer the issuer.
5. Login forms use purpose-bound CSRF tokens. Logout is a POST with auth.logout; change the old GET link. Check your own forms, cookies, session.close and the trusted proxy settings according to the README.

## Schema and content

1. Stop mutating requests and operational jobs during DDL. Before the change, verify the table engines, the specific schema and the stored backup. The framework does not run runtime DDL.
2. Check the width of cms_users.password: new secure hashes need varchar 255. Reading old MD5 hashes remains for migration only; new short writes are rejected. Run the specific ALTER according to your existing schema, not by blind copying.
3. The optional SQL001 adds a measured index boxes(section_id,order). Check the existing indexes beforehand. A relational rebuild of the table is not required.
4. For the API, apply SQL002, 003, 005 in migrations/4.0.0. SQL003 introduces explicit legacy_html/markdown; the original bytes remain HTML. SQL005 adds drafts/revisions/recovery/media and api_settings. Do not re-run an ALTER that already exists without checking. Doctor/migration:status are read-only helpers, not a migration runner.
5. SQL004 is only for the controlled conversion of old content. The original Article getter passed HTML through, so not all old data can be labelled Texy. Create an explicit dry run with html or texy, protect the report like a backup, and check the resulting render and all translations. Resolve manual issues by hand and create a new report. Do not approve an unsafe or lossy conversion.
6. Apply is approved by a single approval_hash. It stores the exact originals and the new content in one transaction; it rejects a stale source or damaged storage. Verify the real rollback on a copy and idempotence. Detailed commands: ref-markdown.md. The required Texy is not part of the core, only of the optional offline package. Renderer::getFormater()->process now means Markdown; convert direct Texy sources first.

## API and central administration

1. Register the permitted box types, setting enums and lowercase section keys in trusted startup code before run(). The API does not allow a remote PHP class, method or template editor. A box reads getSettings/getSetting and does not execute code from settings.
2. Enable apiV2.enabled=true only after the schema and TLS are ready. The connector is independent of the CMS API page. For public media, configure a permanent absolute imageDirectory outside the document root; media are public even when used in a draft.
3. A trusted local administrator issues a restricted integration key via Api\V2\Model::provision. The key is shown once and the site stores only its hash. In the central administration, pass it to site-add through secure stdin from the secret store. Keys never belong in the browser.
4. Run the explicit migration of the central administration. Create the first account with a CLI invitation, complete MFA and store the backup codes. A global administrator assigns access to specific sites. An editor has no permission to publish or change the structure.
5. Set the exact HTTPS ORIGIN of the central administration, a trusted reverse proxy and network egress. Allow the intranet only through a specific operational IP allowlist. Turning off TLS or a global allowPrivate are not solutions.
6. Integration key rotation: create a new one, switch the central administration, verify the connection, revoke the old one and verify that it is rejected. Do not automatically delete history needed for recovery. Operations schedules the bounded pruneAudit and the cleanup of expired central sessions/tokens.

## Verification gate

1. Composer install --no-dev --optimize-autoloader, validation and audit, web OPcache and directory permissions under the real web user. Production compileCheck=false requires controlled cleanup of compiled templates on every deploy. The core has a single runtime Composer dependency, firebase/php-jwt; Smarty/Texy are not needed.
2. Verify anonymous and logged-in pages, the active menu, locale, 404, login/CSRF/POST logout and your own API routes. Private responses and previews must not be a shared HIT.
3. From the central administration, verify two connected sites and a user restricted to just one. Saving a draft must not change the public article. Preview is private, publish is explicit. A concurrent write returns 412 and keeps the text being edited; merge itself writes nothing.
4. Verify translations, revision to draft, deletion and structure recovery including translations, the settings of an actually executed box, and the public MIME/hash/nosniff of an image. Verify a revoked key, an unavailable site and the return of the service. An unavailable central administration must not take down a public site.
5. After every changed artifact, clear the compiled, data, fragment and page cache as well as the external CDN/HTML cache. API and content migrations flush the WBengine cache after commit; external direct SQL/CDN require their own purge.
6. Record the exact commits, results and the responsible person. Only after a green gate can the merge and publication of v4.0.0 be decided. A draft PR and the local pilot are not a production deployment by themselves.

## Rollback on a problem

1. Block mutating requests and publishing in the central administration. Keep diagnostics without keys and sensitive bodies.
2. For a single content conversion, use its conflict-checked rollback. If the content has been edited in the meantime, the rollback must not overwrite it; decide manually over the stored original and the new change.
3. For a rollback of the whole version, restore the matching sources, Composer lock/vendor, configuration, media and a verified DB snapshot. Merely removing the new columns will not bring back the original content or lost hashes. Restore legacy templates together with the old adapter.
4. When restoring the central administration, use the matching MASTER_KEY, revoke old sessions as appropriate and verify MFA/roles and remote keys. Do not fix an outage of the central administration by disabling authentication on the target sites.
5. Clear all caches again, run a representative smoke test and only then re-enable writes. Record the cause, the exact restored commit and the state of the data in the plan.
