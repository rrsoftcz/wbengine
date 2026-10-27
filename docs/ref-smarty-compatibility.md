# Volitelná kompatibilita Smarty

Přechodný balíček `wbengine/smarty-compat` pro WBengine 4.0. Zachovává `Wbengine\Renderer\Adapter\SmartyRenderer` a výchozí escapování. Jádro tento balíček ani Smarty nevyžaduje.

Po zpřístupnění balíčku ve vašem důvěryhodném Composer repository použijte `composer require wbengine/smarty-compat:^4.0` a nastavte `renderer.adapterName` na `SmartyRenderer`. Do té doby lze v lokálním projektu přidat Composer path repository ukazující na tento adresář s explicitní verzí balíčku 4.0.x-dev a `options.symlink: false`. Lokální kompatibilní balíček musí být zkopírovaný do vlastního vendor adresáře, aby jej exclusion classmap jádra pro /compat/ nevyřadilo při sdíleném symlink checkoutu. Nekopírujte soubory adaptéru do jádra.

Pro cílový web inventarizujte pluginy a přímá Smarty API. Nativní set/t syntax není kompatibilní se Smarty. Návrat vyžaduje odpovídající původní šablony, konfiguraci a vyčištění kompilované, fragmentové, page i externí HTML cache.

Testujte z kořene jádra příkazem `sh compat/smarty/bin/test`. Runner instaluje vlastní dočasný vendor mimo checkout, spustí oddělené testy a odstraní pouze svůj adresář. Vyžaduje Composer, PHP 8.3+ a přístup k Composer repository. Nepouštějte instalaci vendor uvnitř tohoto adresáře s rekurzivním path symlinkem do jádra.
