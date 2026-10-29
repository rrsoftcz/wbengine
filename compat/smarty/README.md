# Optional Smarty compatibility

A transitional package `wbengine/smarty-compat` for WBengine 4.0. It preserves `Wbengine\Renderer\Adapter\SmartyRenderer` and the default escaping. The core requires neither this package nor Smarty.

Once the package is available in your trusted Composer repository, use `composer require wbengine/smarty-compat:^4.0` and set `renderer.adapterName` to `SmartyRenderer`. Until then, a local project can add a Composer path repository pointing to this directory with an explicit package version 4.0.x-dev and `options.symlink: false`. The local compatibility package must be copied into its own vendor directory so that the core's classmap exclusion for /compat/ does not drop it in a shared symlink checkout. Do not copy the adapter files into the core.

For the target site, inventory the plugins and direct Smarty APIs. The native set/t syntax is not compatible with Smarty. A rollback requires the matching original templates, configuration and clearing of the compiled, fragment, page and external HTML cache.

Test from the core root with `sh compat/smarty/bin/test`. The runner installs its own temporary vendor outside the checkout, runs the separate tests and removes only its own directory. It requires Composer, PHP 8.3+ and access to a Composer repository. Do not run a vendor installation inside this directory with a recursive path symlink to the core.
