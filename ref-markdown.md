# WBengine 4.0 own Markdown parser

Status 2026-10-26: MD-01 and MD-02 complete; content conversion continues in MD-03. The parser has no dependency on another Markdown package.

## Interface and tree

`Wbengine\Markdown` composes `Markdown\Parser` and `Markdown\HtmlRenderer`. The block and inline parsers share a work budget and produce an immutable `Markdown\Node` tree; the HTML renderer uses a finite set of elements and escapes their content and attributes. The internal tree is neither Smarty nor a PHP program.

```php
$html = (new \Wbengine\Markdown())->render("# Title\n\nPlain **text**.");
$tree = (new \Wbengine\Markdown\Parser())->parse($source);
$html = (new \Wbengine\Markdown\HtmlRenderer())->render($tree);
```

Markdown stays content: template variables and PHP are not evaluated. Direct `.md` sources in the layout, the Application lazy service and explicit DB formats are wired in as described in the following section. This stage does not switch old articles and does not remove Texy.

## Supported format and safe output

The target is CommonMark 0.31.2 plus GFM tables, strikethrough and task lists. No other GFM extension is promised by this contract. Embedded HTML is always escaped. URLs may use http, https, mailto or a relative path; other schemes get an empty target. The output contains no user PHP/template code. There is no production switch for an unsafe HTML mode.

Default limits: input 1 MiB, parser depth 64, 8 million work units, 100000 node allocations, renderer depth 128 and output 8 MiB. Exceeding them raises `LengthException`, not partial HTML. The counters are reset between documents. The limits can be set through the Parser/HtmlRenderer constructors; raising them must match the trustworthiness of the content and the memory profile of the installation. The output limit includes repeated expansion of reference URLs.

## Verification and known differences

`tests/fixtures/markdown/sources.json` lists the sources, authors, licenses, versions and SHA-256 of the corpora. The complete official CommonMark JSON has 652 examples, the selected official GFM examples 12. The local MD-01 suite: 695 tests / 1826 assertions on PHP 8.4.27.

For every CommonMark example, the exact syntactic structure of the resulting HTML is compared through a test-only AST projection, and at the same time the exact actual safe output. The test-only projection returns raw HTML nodes and forbidden schemes so that the security profile does not mask errors in the rest of the syntax; it is not accessible to the production renderer. The registry `safe-deviations.json` contains 76 individual expected results: 72 due to HTML escaping and 4 due to the URL allowlist. No whole group of HTML examples is skipped. Both outputs are verified for all 652 cases.

The official corpus is not a mathematical proof of conformance for all inputs. Independent review found further cases outside it: escaped delimiters in the target and the reference label, parenthesized titles, Unicode label length, invalid UTF-8 and the multiplication of reference URLs into large HTML. Each case has its own RED/GREEN regression. An amplification of a 24 kB source to roughly 20 MB of HTML was replaced by a controlled rejection at the configured limit. Meanwhile, an ordinary 1000 links/code spans passes the work budget.

A supplementary deterministic smoke test over 1000 short corrupted inputs raised no PHP warnings. It is not a penetration test or a guarantee that no other parser bugs exist. Future findings extend this corpus, not exceptions to the requirements.

```sh
docker compose -p wbengine-modernization run --rm --no-deps php php vendor/bin/phpunit tests/Unit/MarkdownTest.php tests/Unit/MarkdownConformanceTest.php tests/Unit/MarkdownGfmTest.php
```

The corpora are adopted test materials with their own attribution and license according to the manifest; the implementation is our own. The production core adds no Composer dependency for Markdown.


## MD-02: sources, layout and stored format

Application provides a lazy `getMarkdown()`; the callback is passed through the current Renderer/Adapter/NativeRenderer into the Engine. The parser is not created during an ordinary non-Markdown render. A standalone NativeRenderer has a safe lazy fallback of its own. The Engine first verifies the allowed roots and then evaluates the extension of both the requested and the canonical path, case-insensitively. `.md` never goes into the Compiler, not even as a symlink alias. The file is read with a fixed limit of 1 MiB plus a detection byte; a huge source therefore cannot be swallowed before the check.

`{include 'content.md'}` inserts safely formatted content into the HTML layout. The text `{$variable}`, `{if ...}` and PHP remain content, even in a trusted file. The same parser serves DB values.

The optional migration `003-markdown-content.sql` adds `content_format` to articles and translations. The default `legacy_html` preserves the current trusted HTML exactly; `markdown` explicitly turns on the new renderer. The old schema is still readable: it uses SELECT * and a legacy fallback, with no runtime DDL or schema-probe queries. Before new formatted writes, apply the migration manually through the ordinary migration procedure. An unknown format is rejected.

An article can combine a translated Markdown body with the original legacy intro. The overlay therefore passes the format separately for each selected field, rather than blindly one format of the last row. Null means fallback, an empty text an explicitly empty field. Changing the format of an existing translation requires passing both text fields for replacement or fallback. A conditional UPDATE also guards the format of the version actually loaded; otherwise a concurrent save could have returned the old format over the new text. A stale write and a collision of the first INSERT are rejected, a legitimate no-op remains allowed. The method neither begins nor ends a foreign transaction.

A deterministic RED/GREEN concurrency test used two processes/connections and a row lock in an isolated test DB. The second writer changed both texts and the format while the first was waiting; the original implementation mixed the data and the new one rejects it. Temporary migration tests cover the new column, legacy preservation and mixed fallback; the ordinary ContentTranslations/ContentReadPath regressions stay green. The concurrency fixture removes its own rows afterwards and only its added test column.


## Controlled migration of old content (MD-03)

Articles previously passed through already stored HTML. Always specify the source explicitly: `html` or `texy`. There is no automatic guessing or bulk overwrite. The HTML converter uses DOM with no network access, a finite list of supported nodes and a comparison of meaning after a safe render. Ambiguous, unsupported or lossy constructs produce manual issues and cannot be approved automatically. Input at most 1 MiB, aggregated plan 8 MiB, report 16 MiB, at most 256 locales. The report contains the original texts and must be protected like a database backup.

First apply the optional SQL003 and 004 on a copy of the database, back up the DB, verify InnoDB and the write permissions of the trusted operational CLI. No runtime DDL. A dry run changes nothing:

```sh
php bin/migrate-content.php --config=/site/Config/Settings.json --article=42 --source-format=html > /secure/report.json
```

The report covers the article and its translations; `--locale=cs` selects a single translation. Manually compare the original and resulting content and render. Each safe record has its own approval_hash:

```sh
php bin/migrate-content.php --config=/site/Config/Settings.json --apply=/secure/report.json --approve=SHA256
php bin/migrate-content.php --config=/site/Config/Settings.json --rollback=SHA256
```

Apply approves only one specific plan and re-verifies the source, the converter, the DB scope and the current locked row. It writes the content and the original bytes in the ledger atomically. It checks the actual post-write hashes, so even a permissive MySQL must not silently truncate the content or the backup. Repetition is idempotent; a changed source, a foreign transaction or a conflicting rollback is rejected. Rollback restores the exact original values including the format and null fallbacks; after a later edit it overwrites nothing. After a successful commit, the WBengine cache is invalidated. The external CDN cache must be purged separately by operations.

For `--source-format=texy`, the optional `compat/texy-migration` with the Texy dependency is required. `--texy-autoload=/trusted/vendor/autoload.php` may point only to a trusted local autoload. The conversion goes through the same safe HTML gate and has no unsafe automatic fallback. The package is in the checkout; before the Composer install it needs a trusted repository or publishing.
