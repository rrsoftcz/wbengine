# Vlastní Markdown parser WBengine 4.0

Stav 2026-10-26: MD-01 dokončen, MD-02 integrace zdrojů a DB rozpracována. Parser nemá závislost na dalším Markdown balíčku.

## Rozhraní a strom

`Wbengine\Markdown` skládá `Markdown\Parser` a `Markdown\HtmlRenderer`. Block a inline parser sdílejí rozpočet práce a vytvářejí neměnný strom `Markdown\Node`; HTML renderer používá konečnou množinu elementů a escapuje jejich obsah a atributy. Vnitřní strom není Smarty ani PHP program.

```php
$html = (new \Wbengine\Markdown())->render("# Titulek\n\nBěžný **text**.");
$tree = (new \Wbengine\Markdown\Parser())->parse($source);
$html = (new \Wbengine\Markdown\HtmlRenderer())->render($tree);
```

Markdown zůstává obsahem: proměnné šablon ani PHP se nevyhodnocují. Přímé `.md` zdroje v layoutu, Application lazy služba a explicitní DB formáty vznikají až v MD-02. Tato etapa nepřepíná staré články a neodstraňuje Texy.

## Podporovaný formát a bezpečný výstup

Cíl je CommonMark 0.31.2 plus GFM tabulky, přeškrtnutí a seznamy úkolů. Jiné GFM rozšíření není touto smlouvou přislíbeno. Vložené HTML se vždy escapuje. URL mohou používat http, https, mailto nebo relativní cestu; ostatní schémata dostanou prázdný cíl. Výstup neobsahuje uživatelský PHP/template kód. Neexistuje produkční přepínač pro nebezpečný HTML režim.

Výchozí limity: vstup 1 MiB, parser depth 64, 8 milionů pracovních jednotek, 100000 alokací uzlů, renderer depth 128 a výstup 8 MiB. Překročení vyvolá `LengthException`, ne částečné HTML. Čítače se obnovují mezi dokumenty. Limity lze nastavit konstruktory Parseru/HtmlRendereru; zvyšování musí odpovídat důvěryhodnosti obsahu a paměťovému profilu instalace. Výstupní limit zahrnuje opakované rozbalování referenčních URL.

## Ověření a známé rozdíly

`tests/fixtures/markdown/sources.json` uvádí zdroje, autory, licence, verze a SHA-256 korpusů. Kompletní oficiální CommonMark JSON má 652 příkladů, vybrané oficiální GFM příklady 12. Lokální sada MD-01: 695 testů / 1826 assertions na PHP 8.4.27.

U každého CommonMark příkladu se porovnává přesná syntaktická struktura výsledného HTML přes testovou projekci AST a současně přesný skutečný bezpečný výstup. Projekce pouze v testech vrací raw HTML uzly a zakázaná schémata, aby bezpečnostní profil nezakryl chyby ostatní syntaxe; není přístupná produkčnímu rendereru. Registr `safe-deviations.json` obsahuje 76 jednotlivých očekávaných výsledků: 72 kvůli escapování HTML a 4 kvůli URL allowlistu. Žádná celá skupina HTML příkladů se nepřeskakuje. Oba výstupy se ověřují pro všech 652 případů.

Oficiální corpus není matematický důkaz shody pro všechny vstupy. Nezávislé review našlo další případy mimo něj: escapované delimitery v cíli a reference labelu, parentetické tituly, Unicode délku labelu, neplatné UTF-8 a násobení referenčních URL do velkého HTML. Každý případ má samostatnou RED/GREEN regresi. Amplifikace 24 kB zdroje na přibližně 20 MB HTML byla nahrazena kontrolovaným odmítnutím při nastaveném limitu. Běžných 1000 odkazů/code spans přitom projde pracovním rozpočtem.

Doplňkový deterministický smoke nad 1000 krátkými poškozenými vstupy nevyvolal PHP warnings. Není to penetrační test ani záruka neexistence dalších parserových chyb. Budoucí nálezy rozšiřují tento corpus, nikoli výjimky z požadavků.

```sh
docker compose -p wbengine-modernization run --rm --no-deps php php vendor/bin/phpunit tests/Unit/MarkdownTest.php tests/Unit/MarkdownConformanceTest.php tests/Unit/MarkdownGfmTest.php
```

Korpusy jsou převzaté testovací podklady s vlastní atribucí a licencí podle manifestu; implementace je vlastní. Produkční jádro pro Markdown nepřidává Composer závislost.
