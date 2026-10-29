# Optional offline Texy conversion

The package `wbengine/texy-migration` serves only for the controlled conversion of existing Texy sources through the safe HTML migration gate. It is not a runtime formatter of the site and the core does not install Texy. Old DB rows with legacy_html remain the original HTML and are not translated automatically.

## Installation in an isolated migration project

Until the package is published in the Composer registry, use a trusted local checkout:

```json
{
  "repositories": [
    {"type":"path","url":"/trusted/wbengine/compat/texy-migration","options":{"symlink":false,"versions":{"wbengine/texy-migration":"4.0.x-dev"}}},
    {"type":"path","url":"/trusted/wbengine","options":{"symlink":false,"versions":{"wbengine/wbengine":"4.0.x-dev"}}}
  ],
  "require":{"wbengine/texy-migration":"4.0.x-dev"},
  "minimum-stability":"dev",
  "prefer-stable":true
}
```

After `composer install`, use the trusted autoload as `--texy-autoload=/migration-project/vendor/autoload.php`. To run the CLI from the installed library, use its Composer bin proxy. Explicit dry run:

```sh
php /trusted/wbengine/bin/migrate-content.php --config=/site/Config/Settings.json --article=42 --source-format=texy --texy-autoload=/migration-project/vendor/autoload.php
```

Then follow ref-markdown.md in the core: a protected report, a check of the original texts and the resulting render, a single approved hash, a backup and a conflict-checked rollback. Manual issues must not be bypassed. Do not assume an automatic lossless conversion of all Texy extensions.

Standalone testing in the checkout: `sh compat/texy-migration/bin/test`. The runner has a temporary vendor outside the checkout, its own mirror of the core and removes only its own dependencies afterwards.
