# Vlastní Markdown parser WBengine 4.0

Stav 2026-10-26: MD-01 a MD-02 dokončeny; konverze obsahu pokračuje v MD-03. Parser nemá závislost na dalším Markdown balíčku.

## Rozhraní a strom

`Wbengine\Markdown` skládá `Markdown\Parser` a `Markdown\HtmlRenderer`. Block a inline parser sdílejí rozpočet práce a vytvářejí neměnný strom `Markdown\Node`; HTML renderer používá konečnou množinu elementů a escapuje jejich obsah a atributy. Vnitřní strom není Smarty ani PHP program.

```php
$html = (new \Wbengine\Markdown())->render("# Titulek\n\nBěžný **text**.");
$tree = (new \Wbengine\Markdown\Parser())->parse($source);
$html = (new \Wbengine\Markdown\HtmlRenderer())->render($tree);
```

Markdown zůstává obsahem: proměnné šablon ani PHP se nevyhodnocují. Přímé `.md` zdroje v layoutu, Application lazy služba a explicitní DB formáty jsou zapojeny podle následující části. Tato etapa nepřepíná staré články a neodstraňuje Texy.

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


## MD-02: zdroje, layout a uložený formát

Application poskytuje lazy `getMarkdown()`; callback se předává přes současný Renderer/Adapter/NativeRenderer do Engine. Parser nevzniká při běžném ne-Markdown renderu. Samostatný NativeRenderer má bezpečný vlastní lazy fallback. Engine nejprve ověří povolené kořeny a potom vyhodnotí příponu požadované i kanonické cesty, case-insensitive. `.md` nikdy nejde do Compileru, ani jako symlinkový alias. Soubor se načte s pevnou mezí 1MiB plus detekční bajt; nelze tak před kontrolou spolknout obrovský zdroj.

`{include 'content.md'}` vloží bezpečně zformátovaný obsah do HTML layoutu. Text `{$variable}`, `{if ...}` a PHP zůstávají obsahem, i v důvěryhodném souboru. Stejný parser obsluhuje DB hodnoty.

Volitelná migrace `003-markdown-content.sql` přidává `content_format` článkům i překladům. Výchozí `legacy_html` zachová současné důvěryhodné HTML přesně; `markdown` výslovně zapíná nový renderer. Staré schéma je stále čitelné: používá SELECT * a legacy fallback, bez runtime DDL nebo schema-probe dotazů. Před novými formátovanými zápisy migraci aplikovat ručně běžným migračním postupem. Neznámý formát se odmítá.

Článek může kombinovat přeložené Markdown body s původním legacy intro. Overlay proto předává formát samostatně pro každý vybraný field, nikoli slepě jeden formát poslední řádky. Null znamená fallback, prázdný text výslovně prázdný field. Změna formátu existujícího překladu vyžaduje předat oba textové fieldy k nahrazení či fallbacku. Podmíněný UPDATE navíc hlídá formát skutečně načtené verze; jinak souběžný save mohl vrátit starý formát nad novým textem. Stale zápis i kolize prvního INSERT jsou odmítnuty, legitimní no-op zůstává povolen. Metoda nezačíná ani neukončuje cizí transakci.

Deterministický RED/GREEN souběhový test použil dva procesy/spojení a row lock v izolované testovací DB. Druhý writer změnil oba texty i formát, zatímco první čekal; původní implementace data smíchala a nová ji odmítne. Temporary migrační testy pokrývají nový sloupec, legacy zachování i mixed fallback; běžné ContentTranslations/ContentReadPath regrese zůstávají zelené. Souběhová fixture po sobě odstraňuje vlastní řádky a pouze svůj přidaný testovací sloupec.


## Řízená migrace starého obsahu (MD-03)

Články dříve předávaly již uložené HTML. Zdroj proto vždy určete explicitně: `html` nebo `texy`. Nedochází k automatickému odhadu ani hromadnému přepsání. Konvertor HTML používá DOM bez síťového přístupu, konečný seznam podporovaných uzlů a srovnání významu po bezpečném renderu. Nejednoznačné, nepodporované nebo ztrátové konstrukce mají manual issues a nelze je automaticky schválit. Vstup nejvýše1MiB, agregovaný plán8MiB, report16MiB, nejvýše256 locale. Report obsahuje původní texty a musí se chránit jako databázová záloha.

Nejdříve na kopii databáze aplikujte volitelné SQL003 a004, zálohujte DB, ověřte InnoDB a write oprávnění důvěryhodného provozního CLI. Žádné runtimeDDL. Suchý běh nic nemění:

```sh
php bin/migrate-content.php --config=/site/Config/Settings.json --article=42 --source-format=html > /secure/report.json
```

Report zahrnuje článek i překlady; `--locale=cs` vybere jediný překlad. Ručně porovnejte původní a výsledný obsah a render. Každý bezpečný záznam má vlastní approval_hash:

```sh
php bin/migrate-content.php --config=/site/Config/Settings.json --apply=/secure/report.json --approve=SHA256
php bin/migrate-content.php --config=/site/Config/Settings.json --rollback=SHA256
```

Apply schválí pouze jeden konkrétní plán, znovu ověří zdroj, konvertor, scopeDB a aktuální uzamčený řádek. Obsah i původní bajty v ledgeru zapisuje atomicky. Kontroluje skutečné post-write hashe, takže ani permissive MySQL nesmí tiše zkrátit obsah či zálohu. Opakování je idempotentní; změněný zdroj, cizí transakce nebo konfliktní rollback se odmítne. Rollback obnoví přesné původní hodnoty včetně formátu a null fallbacků; po pozdější úpravě nic nepřepíše. Po úspěšném commitu se invaliduje WBengine cache. Externí CDN cache musí provoz vyčistit zvlášť.

Pro `--source-format=texy` je nutný volitelný `compat/texy-migration` se závislostí Texy. `--texy-autoload=/trusted/vendor/autoload.php` smí ukazovat jen na důvěryhodný lokální autoload. Převod jde přes stejnou bezpečnou HTML bránu, nemá nebezpečný automatický fallback. Balíček je v checkoutu, před Composer instalací potřebuje důvěryhodný repository nebo publikování.
