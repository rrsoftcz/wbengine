# Design of the personal data erasure tool

Status: draft for comments, before implementation. The analytics principles were approved on 2026-10-27.
Date: 2026-10-27.

## Purpose

Add a safe CLI procedure to WBengine that shows the administrator the personal data found in the core and in registered modules, prepares a proposal for removing or anonymizing it, and carries out the approved local steps. Later the administration API can use the same application interface. The tool must not decide on its own whether a given request meets the legal conditions.

## Approaches considered

1. **CLI first:** the administrator performs the review and gives explicit confirmation directly on the server. This is the chosen approach because it adds no public endpoint and uses the existing `bin/wbe` and `Wbengine\\Console`.
2. **Admin API and interface first:** more convenient remote control, but it needs further permissions, authentication, audit and protection against a faulty or abused request. Postponed to a later phase.

## Architecture

- The coordinator will be part of `Wbengine\\Privacy` and will obtain its dependencies through the current application and model tree. The CLI entry point stays in `Wbengine\\Console`; no parallel script outside the framework will be created.
- The core provides a registry of personal data handlers. A handler must first be able to look up records and return an overview of the source, the count and the proposed action. Application boxes and modules can add their own handler in the application namespace.
- The first handlers should cover the user account `cms_users`, the sessions `cms_sessions`, the refresh tokens `cms_refresh_tokens`, the login limit files and personal identifiers in the API audit. Content history, free-text author fields and data in custom models need concrete rules defined by the given application.
- Each handler must distinguish removal, anonymization and retention with an explained reason. Audit events must not be deleted wholesale without thought if the link to the person can be removed while keeping the necessary operational record.

## CLI workflow

1. `privacy:inspect` only reads and prints a redacted overview of the sources found, the proposed actions, unregistered stores and external steps.
2. The administrator verifies the requester's identity, the legitimacy of the request, any legal grounds for retaining the data and the correctness of the scope, outside the CLI.
3. `privacy:erase` only simulates the steps by default. Writing requires an explicit confirmation bound to a specific preview. Before executing, it checks again that the target records have not changed since the preview.
4. The result lists successful, skipped and failed handlers without printing an email, tokens or record contents. Repeating after a partial failure must be safe.

The option names, passing the identifier without leaking it into the shell history and the way of confirmation will be worked out before the command is implemented.

## Scope and limits

- The local database and the files managed by the application can be handled by registered handlers. Erasure must not delete the data of other people or content that must not be removed.
- Aggregate analytics metrics with no link to a specific person, for example the lifetime view count of an article or page, are not a target of user account erasure. The design of our own analytics must not create identifiers by which these aggregates could be attributed back to a visitor. Keeping aggregates is not a legal assessment of a specific request.
- The goal of our own analytics is to measure aggregate views and trends, not to identify individual people. IP addresses, analytics cookies, user identifiers and individual visit profiles should not be stored. Lifetime totals are kept; any temporary technical data will have a separately determined necessary retention period.
- A view count is not a count of unique visitors. Unique visitors cannot be deduplicated exactly without an identifier; any estimate must be explicitly marked as inexact.
- GA4 is a separate service. WBengine currently sends no logged-in user identifier to Google Analytics and keeps no link to the GA `clientId`. An erasure request in GA4 therefore cannot be assembled automatically from the email in `cms_users`. The first version reports this step as an external task. A possible Admin API integration will be a separate optional handler and will require an identifier recognizable by Google and separate credentials.
- VPS access and error logs, database logs and backups are outside the application runtime. The first version does not delete them; it prints the checks to perform according to the operational configuration. The backup restore procedure must prevent already removed data from being reintroduced, or ensure the erasure is repeated.
- The default GA4 retention settings are not known. A long history of aggregate reports does not by itself show how long Google keeps user and event data. GA4 retention and the erasure request remain a separate operational responsibility until a connector is approved and verified.
- In the first phase the tool will not be available over HTTP and will not delete on a normal logout, an account deactivation or an API request to delete a user.

## Acceptance criteria

- The preview performs no writes and shows all registered handlers and the gaps in coverage.
- Execution requires an administrator review, confirmation of a specific preview and a check for changes since it was created.
- Integration verification shows the removal or anonymization of the user, all of their sessions and refresh tokens, without touching another account.
- A failure of one handler does not silently claim that a complete erasure finished. The result allows safe continuation without repeated damage.
- Reports and logs of the tool contain no unnecessary personal data. The coverage of custom models and external systems is stated explicitly.
- The CLI guide and the application documentation explain requester verification, backups, external stores and the limits of erasure.

## Next step

After the design is approved, an implementation plan will be prepared in stages: the handler interface and simulation, core database handlers, explicit execution and recovery after a partial failure, integration verification in an isolated database, and operational documentation. The changes go into a separate working branch and are checked on a test site before deployment.
