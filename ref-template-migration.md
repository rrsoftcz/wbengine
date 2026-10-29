# WBengine 4.0 template inventory and migration contract

Status as of 2026-10-26: TPL-01 and TPL-02 are complete, TPL-03 verifies the separate compatibility package. The goal is an own compiled renderer in the existing WBengine tree. TPL-01 documented the support and the differences, and added a corpus and an inventory tool. The first part of TPL-02 adds diagnostics to the existing Compiler; the default adapter and the Composer dependencies do not change. TPL-02 completes the migration, TPL-03 removes Smarty from the core.

## Scope and evidence

Framework: branch `codex/wbengine-4.0`, base commit `b7cf3d4`. Sample application: local `wbengine-skeleton-app`, clean commit `d22281fc86e9efd63a333ac6668b9261f22777e9`, sources `src/View`. The application was only read; its configuration already selects NativeRenderer. Compiled files, vendor, node_modules and test fixtures are not the source inventory.

The snapshot of paths, SHA-256 and compilation is in [inventory.json](tests/fixtures/template-migration/inventory.json). These are the identities of the sources at the moment of the inventory, not a copy of the external application nor a replacement for its checkout. CI does not depend on the external directory. [corpus.json](tests/fixtures/template-migration/corpus.json) contains 17 named scenarios and 31 expected results by adapter: 30 outputs and one diagnostic of an unsupported tag. Another five tests compare the real framework templates, including both CAPTCHA branches. In the local verification: PHP 8.4.27 and Smarty 5.8.5, the Composer requirement stays `^5.5.2`.

The library contains seven `.tpl` files:

- `Application/error.tpl`: renderer, error data and translations.
- `Box/Article/View/Article.tpl`: renderer, article, explicitly trusted HTML from the content formatter.
- `Box/Login/View/Login.tpl`: renderer, form, CSRF and CAPTCHA.
- `Box/Login/View/Logout.tpl`: renderer, POST logout and CSRF.
- `Application/exception.tpl`, `Exception/exception.tpl`, `Box/exception.tpl`: format text for `sprintf`, not Smarty templates. Keep their consumers separate.

All four renderer sources compile and, with the supplied test data, have an identical output in both adapters with escaping on. The corpus verifies the escaping of user values and the preservation of the trusted article HTML. This is not an audit of the content formatter nor proof of compatibility for arbitrary data.

The sample application contains 13 sources: `site.tpl`, `404.tpl`, `Header/Jumbotron.tpl`, `Central/About.tpl`, `Central/Api.tpl`, `Central/Caching.tpl`, `Central/Features.tpl`, `Central/Languages.tpl`, `Central/LocalStoryBox.tpl`, `Central/Page.tpl`, `Central/Profile.tpl`, `Central/Structure.tpl`, `Central/Templates.tpl`. All 13 compile natively. This alone does not prove the rendering of the whole site; services, translations, session and runtime data will be covered by the TPL-02 HTTP smoke test.

## Syntax in use and compatibility limits

The framework templates use variable output, dot-notation arrays, object properties, if/else, comments and the modifiers escape, raw, default. The sample application additionally uses foreach with a key, object methods, a quoted literal with a modifier, set, the t tag and the modifiers lurl, upper, number, ldate, t, truncate, join. The source also contains literal syntax examples that are not executed tags.

The native Compiler and ExpressionParser already handle:

- Output, variables, arrays, properties and method calls of assigned objects; basic arithmetic, comparison, boolean operations and concatenation with `~`.
- if/elseif/else, foreach and foreachelse, include as a path expression, set as an expression assignment, comments and literal.
- The native t tag, explicit cache fragments with public/user scope and the modifiers listed in `ExpressionParser::MODIFIERS`.
- HTML escaping by default, raw/nofilter for an explicitly trusted whole, allowed file roots, limited include depth, a compiled cache check and the separation of escaping policies.

This is a restricted WBengine language. It is not full Smarty nor a security sandbox for foreign authors: assigned objects can have methods with side effects. Templates remain the developer's trusted code. Markdown will be a separate content format without this logic, according to MD-01/02.

The corpus captures concrete migration rules:

1. Convert `{assign var="name" value=$title}` to `{set $name = $title}`. The Smarty attribute syntax is not copied into the native set.
2. Convert `{include file="partial.tpl"}` to `{include 'partial.tpl'}`. Named arguments, assign and Smarty resource prefixes are not supported by this. Solve the layout split with explicit partials and data from Application/Site/Section/Box.
3. The native `{t 'key'}` is not a function registered by the Smarty adapter. The `|t` modifier is common; an application with set/t tags can no longer generally be switched back to Smarty.
4. The native default uses `empty()`, so zero also gets the fallback. Smarty keeps zero in the measured scenario. Prepare data where zero is meaningful explicitly in the PHP model/presentation object; do not switch blindly based on the same modifier name.
5. The native truncate limits the number of characters kept before the suffix. Smarty counts the suffix into the limit and may handle word boundaries differently. For `abcdefgh|truncate:5`, the corpus expects `abcde...` natively and `ab...` in Smarty. Check the actual requirements of the text output.
6. The native nl2br escapes the input and returns HTML with `<br>`. With automatic escaping on, Smarty in this scenario also escapes the inserted `<br />`. Do not copy raw onto untrusted input as an automatic fix; check the whole pipeline.
7. Keep the default escaping. Use raw for trusted sections and content already processed by a safe formatter, not for ordinary data. HTML escaping does not validate URL schemes nor address an arbitrary JavaScript/CSS context.

## Settings and direct APIs

The path stays `Application -> Renderer -> Adapter -> NativeRenderer -> Template\Engine -> Compiler/ExpressionParser/Runtime`; fragments are handled by the existing FragmentCache. No second template framework, global preprocessor or runtime shell script is created.

`assign`, `registerObject`, fetch/display, the template directory, the compiled directory, compileCheck and autoEscape have an existing native implementation. Access through `getAdapter()` returns the Engine instead of a Smarty instance. Direct Smarty API calls and custom plugins must be inventoried in every specific application; a generic list of function names is not proof of their absence.

The migration must explicitly address the current differences of the adapter:

- `setConfigDir`, `setCacheDir`, `enableCache`, `disableCache` have no effect in NativeRenderer. Smarty config files and its own output cache do not automatically replace the native fragments and PageCache.
- The cache_id/compile_id parameters of fetch/display and nocache of assign are not used by the native adapter. Cache keys should be created according to the WBengine contract, with the user/locale identity and correct invalidation.
- The native adapter rejects custom delimiters with an exception. Do not enable them as part of a blanket configuration switch.
- Smarty inheritance, block/extends, capture, section, plugin functions, config_load and arbitrary PHP modifiers are not an approved WBengine language. They were not found in the inspected active sources. External installations require their own check.

## Repeating the inventory

Framework only, without DB and without rendering:

```sh
docker compose -p wbengine-modernization run --rm --no-deps php php tests/tools/template-inventory.php
```

With the sample application attached read-only:

```sh
docker compose -p wbengine-modernization run --rm --no-deps \
  -v /Users/roza/Sites/php/wbengine-skeleton-app/src/View:/inventory-skeleton:ro \
  php php tests/tools/template-inventory.php /inventory-skeleton
```

The tool loads the sources and builds a PHP string with the existing Compiler; it does not execute the sources and does not write compiled files. It configures neither the application nor the session/DB. It prints JSON to stdout, SHA-256, consumers, the compilation result and heuristic tag/modifier hints. It does not follow symlinks and skips vendor, node_modules, .git, Compiled and tests.

The status `compiled-only` means only a successful compilation. Known reserved Smarty constructs, for example assign, now raise an exception; other unknown or dynamic plugin tags may still remain text. The status `review` and reviewHints require human review. The hints ignore comment/literal bodies, but are not a full lexer, may catch text inside strings and will not reveal all dynamic constructs, plugins or external resources. The tool is neither a runtime security filter nor an automatic migrator.

```sh
docker compose -p wbengine-modernization run --rm --no-deps php php vendor/bin/phpunit --filter 'TemplateMigrationCorpusTest|TemplateInventoryTest'
```

## Follow-up execution of TPL-02 and the TPL-03 gate

1. First part complete: the existing Compiler diagnoses the reserved Smarty constructs listed below and closing tags with a path and line. The assign-as-text corpus was deliberately changed into an exception test. The inventory regex hints remain an auxiliary heuristic, not a parser.
2. Decide and test the exact supported contract of include/set and modifiers, keep the explicit differences and examples of data preparation. Add invalid syntax, a missing partial, traversal/symlink, an exception in a method and output buffer recovery. Use the existing security and cache regression suites.
3. Run the whole sample site on NativeRenderer: menu and active links, both user states, login/CSRF/POST logout, 404, article, locale, native fragments and page-cache HIT/BYPASS. Check the GET logout links in the sample layout, production diagnostic variables and raw content; compilation alone will not catch them. Version the changes of the separate application in its own repository.
4. For each target installation, back up the sources and configuration, inventory the extensions, convert the templates in parts, compare the outputs and only then change adapterName. Clear the compiled files, fragments, pages and the external HTML cache. On rollback, restore the configuration, the compatible sources and clear the cache again; native-only set/t require restoring the original version of the templates.
5. Only after this gate, in TPL-03, change the default examples and the test infrastructure, decide the form of a possible separate compatibility package and remove the Smarty adapter/dependency from the core. Move the parity corpus to native expected outputs and historical migration fixtures so that the clean core requires Smarty not even for tests. Verify the installation without the package and the entire release gate. Texy is dealt with only in MD-01 to MD-04.


## TPL-02, first checkpoint: diagnostics

The version 4 Compiler returns unsupported tags as ordinary tokens and raises the error after accounting for the preceding text. The `TemplateException` contains the source name, the line and a recommendation to convert the construct or wrap a text example in literal. It is not another preprocessor or a change of the service tree. CRLF is counted after the existing normalization to LF.

The reserved forms include assign, append, extends, block, capture, section/sectionelse, config_load, eval, fetch, insert, php/include_php, strip, nocache, ldelim/rdelim, call and forelse; their closing forms are recognized as well. The names are case-sensitive and the name must be followed by whitespace or `}`, so for example `{blockade}` or the CSS `block-size` is not this tag. Comment and literal bodies are not tested as executed syntax.

For function, for and while, the Smarty form of the header is recognized: function with a `name=` attribute or another named attribute, for with a `$` variable, while with a non-parenthesized expression. Ordinary JavaScript function declarations, `for (...)`, `while (ready)` and `while ($ready)` and object properties with a colon are preserved. This is not a general detection of the whole Smarty language: a while with an arbitrary parenthesized expression may remain text because its header matches JavaScript. Other dynamic constructs require an inventory and manual conversion.

Exact syntax collisions are reserved to the template language: for example the JS object shorthand `{capture}` has the same notation as a detected Smarty construct. For such a literal CSS/JS/text section, use `{literal}...{/literal}`. The compiler does not parse HTML/JS contexts and does not promise automatic distinction of identical bytes. An unknown plugin may still pass as text; compilation success is not a general migration guarantee.

The compiled file version changes from 3 to 4. An old file with a version-3 identity is not used even with `compileCheck: false`; a test verifies this with a really pre-prepared old compiled file. On deployment, keep clearing the page/fragment/compiled and external HTML cache according to the migration procedure so that an old stored response does not mask the new diagnostics.

TPL-02 remains in progress: the completion of the include/set/modifier contract and an HTTP smoke test of the sample site with its application data and authentication follow. This checkpoint declares neither the removal of Smarty nor a complete migration of the external application.


## TPL-02B: include/set and output ownership

Verified contract: a relative include is evaluated from templatesDir, not from the directory of the partial being rendered. A dynamic path stays under the control of the allowed roots. A partial inherits the current values including set; its local set changes neither the calling template nor the assigned values of the next fetch. Arrays are local copies, object references stay shared and their methods are trusted application code. Existing tests reject invalid syntax, missing files, traversal, a symlink outside the root and an excessively deep include.

New regressions proved a loss of prefix and a buffer leak when a service method opened its own buffer. The Engine now uses a common `capture()` for the whole render, partials and cache block callbacks. On success, nested removable buffers are collected in the correct order; on an exception they are discarded down to the original boundary. The include depth is restored even after an error. A cache regression verifies the whole cold and warm fragment and a single invocation of the service. An exception does not persist a partial fragment.

The generated cache callbacks changed, so Compiler version 5 separates their identity from versions 3/4. After deployment, clearing all output caches still applies. Service methods must not cancel the renderer's buffers or open non-removable buffers: PHP cannot fully remedy these side effects; the protective loops at least do not get stuck in such a case. This is not a sandbox for untrusted code.

TPL-02C: the sample application was converted in a separate PR rrsoftcz/wbengine-skeleton-app#1, commit 35d2d54. All 20 HTTP checks and its CI passed. A clean installation after removing Smarty also passed all 20 checks. The historical records above capture the original state of the inventory.


## TPL-03: separate compatibility profile

The core does not install Smarty and does not contain its adapter. NativeRenderer stays in the existing tree. The historical Smarty expected results in JSON serve as a reference; the base tests execute only the native expectations. The optional package in `compat/smarty` preserves the original adapter FQCN through a Composer classmap and contains separate parity tests. A missing package, when SmartyRenderer is selected, raises a concrete installation recommendation.

The compatibility test runner installs vendor into its own temporary directory outside the checkout so that the path repository does not create a recursive vendor tree when optimizing the autoloader. CI runs it separately; the basic five PHP/DB checks run without Smarty. Before release, the package must be published in the Composer registry or a trusted own Composer repository attached. The directory in the checkout alone does not install it automatically. For a local path installation, set options.symlink=false so that the core's classmap exclusion for /compat/ does not remove the symlinked optional adapter.
