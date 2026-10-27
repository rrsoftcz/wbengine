# Inventura a migrační smlouva šablon WBengine 4.0

Stav k 2026-10-26: TPL-01 a TPL-02 dokončeny, TPL-03 ověřuje oddělený kompatibilní balíček. Cílem je vlastní kompilovaný renderer ve stávajícím stromu WBengine. TPL-01 zdokumentoval podporu a rozdíly, přidal corpus a inventurní nástroj. První část TPL-02 doplňuje diagnostiku v existujícím Compileru; výchozí adaptér ani Composer závislosti se nemění. TPL-02 dokončí migraci, TPL-03 odstraní Smarty z jádra.

## Rozsah a důkazy

Framework: větev `codex/wbengine-4.0`, výchozí commit `b7cf3d4`. Vzorová aplikace: lokální `wbengine-skeleton-app`, čistý commit `d22281fc86e9efd63a333ac6668b9261f22777e9`, zdroje `src/View`. Aplikace byla pouze čtena; její konfigurace již vybírá NativeRenderer. Kompilované soubory, vendor, node_modules a testovací fixtures nejsou zdrojovou inventurou.

Snapshot cest, SHA-256 a kompilace je v [inventory.json](../tests/fixtures/template-migration/inventory.json). Jsou to identity zdrojů v okamžiku inventury, nikoli kopie externí aplikace ani náhrada jejího checkoutu. CI na externím adresáři nezávisí. [corpus.json](../tests/fixtures/template-migration/corpus.json) obsahuje 17 pojmenovaných scénářů a 31 očekávaných výsledků podle adaptéru: 30 výstupů a jednu diagnostiku nepodporovaného tagu. Dalších pět testů porovnává skutečné frameworkové šablony, včetně obou větví CAPTCHA. Při místním ověření: PHP 8.4.27 a Smarty 5.8.5, požadavek Composeru zůstává `^5.5.2`.

Knihovna obsahuje sedm `.tpl` souborů:

- `Application/error.tpl`: renderer, data chyby a překlady.
- `Box/Article/View/Article.tpl`: renderer, článek, explicitně důvěryhodné HTML z obsahového formátovače.
- `Box/Login/View/Login.tpl`: renderer, formulář, CSRF a CAPTCHA.
- `Box/Login/View/Logout.tpl`: renderer, POST odhlášení a CSRF.
- `Application/exception.tpl`, `Exception/exception.tpl`, `Box/exception.tpl`: formátovací text pro `sprintf`, nikoli Smarty šablony. Zachovat jejich spotřebitele odděleně.

Všechny čtyři rendererové zdroje se kompilují a mají při dodaných testovacích datech shodný výstup obou adaptérů s escapováním zapnutým. Corpus ověřuje escapování uživatelských hodnot i zachování důvěryhodného HTML článku. Toto není audit obsahového formátovače ani důkaz kompatibility pro libovolná data.

Vzorová aplikace obsahuje 13 zdrojů: `site.tpl`, `404.tpl`, `Header/Jumbotron.tpl`, `Central/About.tpl`, `Central/Api.tpl`, `Central/Caching.tpl`, `Central/Features.tpl`, `Central/Languages.tpl`, `Central/LocalStoryBox.tpl`, `Central/Page.tpl`, `Central/Profile.tpl`, `Central/Structure.tpl`, `Central/Templates.tpl`. Všech 13 se nativně kompiluje. Toto samo nedokazuje vykreslení celého webu; služby, překlady, session a runtime data pokryje TPL-02 HTTP smoke test.

## Používaná syntaxe a hranice kompatibility

Frameworkové šablony používají výstup proměnných, pole přes tečku, vlastnosti objektů, if/else, komentáře a modifikátory escape, raw, default. Vzorová aplikace navíc používá foreach s klíčem, metody objektů, quoted literal s modifikátorem, set, t tag a modifikátory lurl, upper, number, ldate, t, truncate, join. Zdroj obsahuje také literálové ukázky syntaxe, které nejsou vykonávanými tagy.

Nativní Compiler a ExpressionParser již zpracovávají:

- Výstup, proměnné, pole, vlastnosti a volání metod přiřazených objektů; základní aritmetiku, porovnání, boolean operace a spojování pomocí `~`.
- if/elseif/else, foreach a foreachelse, include jako výraz cesty, set jako přiřazení výrazu, komentáře a literal.
- Nativní t tag, explicitní fragmenty cache s public/user scope a modifikátory uvedené v `ExpressionParser::MODIFIERS`.
- HTML escapování ve výchozím stavu, raw/nofilter pro explicitně důvěryhodný celek, povolené kořeny souborů, omezenou hloubku include, kontrolu kompilované cache a oddělení politik escapování.

To je omezený jazyk WBengine. Nejde o úplný Smarty ani bezpečnostní sandbox pro cizí autory: přiřazené objekty mohou mít metody s vedlejšími účinky. Šablony zůstávají důvěryhodným kódem vývojáře. Markdown bude samostatný obsahový formát bez této logiky, podle MD-01/02.

Corpus zachycuje konkrétní migrační pravidla:

1. `{assign var="name" value=$title}` převést na `{set $name = $title}`. Smarty attribute syntax se do nativního set nekopíruje.
2. `{include file="partial.tpl"}` převést na `{include 'partial.tpl'}`. Pojmenované argumenty, assign a Smarty resource prefixy nejsou tímto podporovány. Rozdělení layoutu řešit explicitními partials a daty z Application/Site/Section/Box.
3. Nativní `{t 'key'}` není funkcí registrovanou Smarty adaptérem. Společný je modifikátor `|t`; aplikace se set/t tagy již není obecně přepínatelná zpět na Smarty.
4. Nativní default používá `empty()`, takže také nula dostane fallback. Smarty v měřeném scénáři nulu zachová. Data, kde je nula významná, připravit explicitně v PHP modelu/prezentačním objektu; nepřepínat slepě podle stejného názvu modifikátoru.
5. Nativní truncate omezuje počet ponechaných znaků před suffixem. Smarty do limitu započítává suffix a může řešit hranice slov jinak. Corpus pro `abcdefgh|truncate:5` čeká `abcde...` nativně a `ab...` ve Smarty. Kontrolovat skutečné požadavky textového výstupu.
6. Nativní nl2br escapuje vstup a vrátí HTML s `<br>`. Smarty při zapnutém automatickém escapování v tomto scénáři escapuje i vložené `<br />`. Nekopírovat raw na nedůvěryhodný vstup jako automatickou opravu; zkontrolovat celý pipeline.
7. Výchozí escapování zachovat. Raw používat pro důvěryhodné sekce a obsah již zpracovaný bezpečným formátovačem, nikoli běžná data. Escapování HTML nevaliduje URL schémata ani neřeší libovolný JavaScript/CSS kontext.

## Nastavení a přímá API

Cesta zůstává `Application -> Renderer -> Adapter -> NativeRenderer -> Template\Engine -> Compiler/ExpressionParser/Runtime`; fragmenty obsluhuje stávající FragmentCache. Nevznikne druhý template framework, globální preprocesor ani runtime shell skript.

`assign`, `registerObject`, fetch/display, adresář šablon, kompilátů, compileCheck a autoEscape mají existující nativní implementaci. Přístup přes `getAdapter()` vrací Engine místo instance Smarty. Přímá volání Smarty API a vlastní pluginy je nutné inventarizovat v každé konkrétní aplikaci; generický seznam názvů funkcí není důkaz jejich absence.

Aktuální rozdíly adaptéru musí migrace řešit explicitně:

- `setConfigDir`, `setCacheDir`, `enableCache`, `disableCache` jsou v NativeRenderer bez účinku. Smarty config soubory a jeho vlastní output cache nenahrazují nativní fragmenty a PageCache automaticky.
- Parametry cache_id/compile_id u fetch/display a nocache u assign nativní adaptér nepoužívá. Cache klíče mají vznikat podle smlouvy WBengine, s identitou uživatele/locale a správnou invalidací.
- Vlastní delimitery nativní adaptér odmítá výjimkou. Nezapínat je jako součást plošného přepnutí konfigurace.
- Smarty inheritance, block/extends, capture, section, plugin functions, config_load a libovolné PHP modifikátory nejsou schváleným jazykem WBengine. V prohlédnutých aktivních zdrojích nebyly nalezeny. Externí instalace vyžadují vlastní kontrolu.

## Opakování inventury

Pouze framework, bez DB a bez renderování:

```sh
docker compose -p wbengine-modernization run --rm --no-deps php php tests/tools/template-inventory.php
```

S read-only připojenou vzorovou aplikací:

```sh
docker compose -p wbengine-modernization run --rm --no-deps \
  -v /Users/roza/Sites/php/wbengine-skeleton-app/src/View:/inventory-skeleton:ro \
  php php tests/tools/template-inventory.php /inventory-skeleton
```

Nástroj načte zdroje a sestaví PHP řetězec pomocí stávajícího Compileru; zdroje nevykonává a nezapisuje kompiláty. Nekonfiguruje aplikaci ani session/DB. Vypíše JSON na stdout, SHA-256, spotřebitele, výsledek kompilace a heuristické tag/modifier hints. Neprochází symlinky a vynechává vendor, node_modules, .git, Compiled a tests.

Status `compiled-only` znamená pouze úspěšnou kompilaci. Známé vyhrazené Smarty konstrukce, například assign, nyní vyvolají výjimku; jiné neznámé nebo dynamické pluginové tagy mohou stále zůstat textem. Status `review` a reviewHints vyžadují kontrolu člověkem. Hints ignorují comment/literal těla, ale nejsou plným lexerem, mohou zachytit text uvnitř řetězců a neodhalí všechny dynamické konstrukce, pluginy nebo externí resources. Nástroj není runtime bezpečnostní filtr ani automatický migrátor.

```sh
docker compose -p wbengine-modernization run --rm --no-deps php php vendor/bin/phpunit --filter 'TemplateMigrationCorpusTest|TemplateInventoryTest'
```

## Navazující provedení TPL-02 a brána TPL-03

1. První část dokončena: existující Compiler diagnostikuje níže vyjmenované vyhrazené Smarty konstrukce a uzavírací tagy s cestou a řádkem. Corpus assign-as-text byl záměrně změněn na test výjimky. Inventurní regex hints zůstávají pomocnou heuristikou, nikoli parserem.
2. Rozhodnout a otestovat přesnou podporovanou smlouvu include/set a modifikátorů, zachovat explicitní rozdíly a ukázky přípravy dat. Přidat chybnou syntaxi, chybějící partial, traversal/symlink, výjimku v metodě a obnovu output bufferů. Využít stávající bezpečnostní a cache regresní sady.
3. Provést celý vzorový web na NativeRenderer: menu a aktivní odkazy, oba stavy uživatele, login/CSRF/POST logout, 404, článek, locale, native fragmenty a page-cache HIT/BYPASS. Zkontrolovat odkazy GET logout ve vzorovém layoutu, produkční diagnostické proměnné a raw obsah; samotná kompilace je nezachytí. Změny oddělené aplikace verzovat v jejím vlastním repozitáři.
4. Pro každou cílovou instalaci zazálohovat zdroje a konfiguraci, inventarizovat rozšíření, převést šablony po částech, porovnat výstupy a potom změnit adapterName. Vyčistit kompiláty, fragmenty, stránky i externí HTML cache. Při rollbacku obnovit konfiguraci, kompatibilní zdroje a znovu vyčistit cache; native-only set/t vyžadují obnovení původní verze šablon.
5. Až po této bráně v TPL-03 změnit výchozí příklady a testovací infrastrukturu, určit podobu případného samostatného kompatibilního balíčku a odstranit Smarty adaptér/závislost z jádra. Paritní corpus přesunout na nativní očekávané výstupy a historické migrační fixtures, aby čisté jádro nevyžadovalo Smarty ani pro testy. Ověřit instalaci bez balíčku a celý release gate. Texy se řeší až v MD-01 až MD-04.


## TPL-02, první kontrolní bod: diagnostika

Compiler verze 4 vrací nepodporované tagy jako běžné tokeny a chybu vyvolá po započtení předcházejícího textu. Výjimka `TemplateException` obsahuje název zdroje, řádek a doporučení převést konstrukci nebo textový příklad obalit literal. Nejde o další preprocesor nebo změnu stromu služeb. CRLF se počítá po stávající normalizaci na LF.

Vyhrazené tvary zahrnují assign, append, extends, block, capture, section/sectionelse, config_load, eval, fetch, insert, php/include_php, strip, nocache, ldelim/rdelim, call a forelse; rozeznávají se také jejich uzavírací tvary. Názvy jsou case-sensitive, za jménem musí být whitespace nebo `}`, takže například `{blockade}` ani CSS `block-size` není tento tag. Komentáře a literal těla se netestují jako vykonávaná syntaxe.

U function, for a while se rozpoznává Smarty podoba hlavičky: function s atributem `name=` či jiným pojmenovaným atributem, for s `$` proměnnou, while s neparentetickým výrazem. Zachovávají se běžné JavaScriptové deklarace funkcí, `for (...)`, `while (ready)` i `while ($ready)` a objektové vlastnosti s dvojtečkou. Není to obecná detekce celého Smarty jazyka: while s libovolným parentetickým výrazem může zůstat textem, protože se jeho hlavička shoduje s JavaScriptem. Další dynamické konstrukce vyžadují inventuru a ruční převod.

Přesné kolize syntaxe jsou vyhrazené šablonovému jazyku: například JS objekt shorthand `{capture}` má stejný zápis jako detekovaná Smarty konstrukce. Pro takový doslovný CSS/JS/textový úsek použít `{literal}...{/literal}`. Kompilátor neparsuje HTML/JS kontexty a neslibuje automatické rozlišení totožných bajtů. Neznámý plugin může stále projít jako text; úspěch kompilace není obecná migrační záruka.

Verze kompilátů se mění z 3 na 4. Starý soubor s verze-3 identitou se nepoužije ani s `compileCheck: false`; test to ověřuje se skutečně předpřipraveným starým kompilátem. Při nasazení nadále vyčistit page/fragment/kompilované a externí HTML cache podle migračního postupu, aby stará uložená odpověď nezakryla novou diagnostiku.

TPL-02 zůstává rozpracován: následuje dokončení smlouvy include/set/modifikátorů a HTTP smoke test vzorového webu s jeho aplikačními daty a autentizací. Tento checkpoint nedeklaruje odstranění Smarty ani kompletní migraci externí aplikace.


## TPL-02B: include/set a vlastnictví výstupu

Ověřená smlouva: relativní include se vyhodnocuje od templatesDir, nikoli od adresáře právě vykreslované partial. Dynamická cesta zůstává pod kontrolou povolených kořenů. Partial dědí aktuální hodnoty včetně set; její lokální set nezmění volající šablonu ani přiřazené hodnoty příštího fetch. Pole jsou lokální kopie, reference objektů zůstávají sdílené a jejich metody jsou důvěryhodný aplikační kód. Existující testy odmítají neplatnou syntaxi, chybějící soubory, traversal, symlink mimo root a příliš hluboké include.

Nové regrese prokázaly ztrátu prefixu a únik bufferu, pokud metoda služby otevřela vlastní buffer. Engine nyní používá společné `capture()` pro celý render, partials i callbacky cache bloků. Na úspěchu se vnořené odstranitelné buffery sesbírají ve správném pořadí, při výjimce se zahodí až k původní hranici. Hloubka include se obnoví i po chybě. Regrese cache ověřuje celý cold i warm fragment a jedinou invokaci služby. Výjimka nepersistuje částečný fragment.

Generované cache callbacky se změnily, proto Compiler verze 5 odděluje jejich identitu od verzí 3/4. Po nasazení stále platí čištění všech výstupních cache. Metody služeb nesmějí rušit buffery rendereru nebo otevírat neodstranitelné buffery: PHP tyto vedlejší účinky plně napravit neumí; ochranné smyčky v takovém případě alespoň neuvíznou. Nejde o sandbox nedůvěryhodného kódu.

TPL-02C: vzorová aplikace převedena v samostatném PR rrsoftcz/wbengine-skeleton-app#1, commit 35d2d54. Všech 20 HTTP kontrol a její CI prošlo. Čistá instalace po odstranění Smarty také prošla všemi 20 kontrolami. Historické záznamy výše zachycují původní stav inventury.


## TPL-03: samostatný kompatibilní profil

Jádro neinstaluje Smarty a neobsahuje jeho adaptér. NativeRenderer zůstává ve stávajícím stromu. Historické Smarty očekávané výsledky v JSON slouží jako reference; základní testy vykonávají jen nativní očekávání. Volitelný balíček v `compat/smarty` zachovává původní FQCN adaptéru přes Composer classmap a obsahuje oddělené testy parity. Chybějící balíček při výběru SmartyRenderer vyvolá konkrétní instalační doporučení.

Testovací runner kompatibility instaluje vendor do vlastního dočasného adresáře mimo checkout, aby path repository nevytvořila rekurzivní vendor strom při optimalizaci autoloadu. CI jej spouští samostatně; základní pětice PHP/DB kontrol běží bez Smarty. Před vydáním je nutné balíček zveřejnit v Composer registru nebo připojit důvěryhodný vlastní Composer repository. Samotný adresář v checkoutu jej automaticky neinstaluje. U lokální path instalace nastavte options.symlink=false, aby classmap exclusion jádra pro /compat/ neodstranilo symlinkovaný volitelný adaptér.
