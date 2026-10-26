# Plán zlepšení WBengine

Datum: 2026-10-25

## Účel a stav

Cílové vydání: **4.0.0**, nová hlavní verze po 3.5.0. Příprava na GitHubu bude probíhat v samostatné větvi a draft PR; tag `v4.0.0` a release až po dokončení release gate.

Trvalý společný seznam návrhů z rozboru frameworku a jejich následného doplnění uživatelem. Uživatel chce návrhy realizovat. Tento dokument zachycuje směr, priority a podmínky ověření; implementace byla zahájena v odděleném worktree `/private/tmp/wbengine-modernization`. Konkrétní návrh rozhraní a migrační postup se dopracují před jednotlivými zásahy.

Hlavní cíl: zachovat snadnou tvorbu webů a rychlé zpracování požadavků. Zachovat model Application, Site, Section a Box, malé komponenty, odloženou inicializaci služeb, parametrizované SQL a kompilované šablony. Každá nová abstrakce musí mít konkrétní přínos.

Závazné architektonické pravidlo uživatele: nové funkce PHP jádra musí zachovat stromovou OOP architekturu WBengine, odpovídající namespaces, parent vazby a současné modelové/adapterové vzory. Služby zapojovat přes stávající lazy přístup aplikace, obsah přes Site/Section/Box, databázové operace přes modely a Db, API moduly přes současné namespace a route/controller vzory. Nevytvářet paralelní framework ani volné produkční skripty obcházející strom. Existující statické fasády lze zachovat jako vstupní rozhraní. Samostatná SvelteKit administrace zůstává odděleným API klientem podle schváleného návrhu; PHP konektor respektuje architekturu WBengine.

Priority níže jsou doporučením z rozboru. P1 znamená bezpečnost a správnost, P2 běžný výkon a předvídatelnost, P3 pohodlí vývoje nebo optimalizaci podmíněnou měřením. Stav jednotlivých položek je uveden v registru níže. Při navázání práce vždy nejprve přečíst tento registr a záznam postupu.

## Pracovní registr

Aktuální etapa: CACHE-01, SEC-02 až SEC-06, PERF-01, PERF-02, PERF-06, PERF-07, ARCH-01, PERF-03 a PERF-05 dokončeny. PERF-04 změřen, implementace URL mapy odložena; neshoda collation FIX-02 opravena jednotným SQL výběrem. TPL-01 dokončen, TPL-02 rozpracován: diagnostika a smlouva include/set s opravou bufferů hotové, pokračuje HTTP ověření aplikace. SEC-01 zůstává otevřený pro invalidaci budoucích obsahových zápisů a API publish. Odpovědnost rozpracovaných úkolů: Codex. Další etapy navazují podle závislostí. Žádný z nových subsystémů není označen jako hotový bez implementace a testů.

Stavy: `čeká`, `připraveno`, `rozpracováno`, `blokováno`, `hotovo`, `odloženo`.

Každý úkol musí mít účel, odpovědnost, závislosti, postup, migrační dopad, akceptační testy, datum poslední změny a důkazy dokončení. `Připraveno` znamená vyřešené rozhraní a testovací scénáře. `Hotovo` znamená implementováno, ověřeno a zdokumentováno. Rozpracovaná práce musí uvádět další konkrétní krok. Důkazy a commity jsou v záznamu postupu.

### Závazné verzování a bezpečné pokračování

Po každé větší samostatné změně a na konci každé fáze vytvořit kontrolní bod:

1. Spustit kontroly odpovídající změně a zaznamenat skutečný výsledek.
2. Aktualizovat tento plán: hotové a rozpracované úkoly, zbývající práci, rozhodnutí, případné blokace, migrace a nejbližší konkrétní krok.
3. Commitnout související kód společně s aktuálním stavem plánu. Čistě dokumentační změny mohou mít samostatný commit.
4. Provést push do pracovní větve `codex/wbengine-4.0` a ověřit, že vzdálená větev obsahuje vytvořený commit. Aktuální PR musí odpovídat skutečnému rozsahu změn.
5. Teprve potom pokračovat další větší změnou nebo fází. Odeslání commitu není samo o sobě merge, tag ani vydání nové verze.

Při přerušení uprostřed změny uložit dosažený stav jako výslovně rozpracovaný kontrolní bod, uvést neúspěšné či neprovedené kontroly a přesný další krok. Takový commit se nesmí vydávat za dokončený nebo připravený k nasazení. Pokud push selže, výslovně uvést, že kontrolní bod je pouze lokální, a vzdálené uložení dokončit před další větší změnou.

Při navázání nejprve přečíst tento plán, ověřit větev, poslední commity, pracovní změny a stav vzdálené větve. Dokončené kroky neopakovat; pokračovat od zaznamenaného dalšího kroku a zachovat cizí změny.

- [ ] SEC-01: rozpracováno (Codex, 2026-10-25). Pravidla odpovědí, personalizace, hlavičky HIT/304 a řízení cest/bypass hotové; zbývá sjednotit invalidaci všech obsahových zápisů a nové API publish.
- [x] SEC-02: hotovo (Codex, 2026-10-25). Žádný nový MD5 fallback, kontrola skutečné délky hashe, ověřeno i na reálném varchar(32) se zachovaným legacy přihlášením.
- [x] SEC-03: hotovo (Codex, 2026-10-25). Oba adaptéry escapují defaultně, native metadata a fallbacky opraveny, režimy cache odděleny, migrační a kontextová pravidla zdokumentována.
- [x] SEC-04: hotovo (Codex, 2026-10-25), nezměněné vstupy, řetězcové helpery, validace autentizačních polí a migrační dokumentace.
- [x] SEC-05: hotovo (Codex, 2026-10-25), explicitní důvěryhodné proxy IPv4/IPv6, společná detekce IP/HTTPS a cookie flags včetně I18n.
- [x] SEC-06: hotovo (Codex, 2026-10-25), session CSRF služba, účely formulářů, POST logout, ochrana cache a stateless cookie API hranice.
- [x] FIX-01: hotovo (Codex, 2026-10-25). iat setter zachovává iss, regresní test RED a GREEN.
- [x] FIX-02: hotovo (Codex, 2026-10-26), jednotný parametrizovaný SQL výběr i při cache, ověřena skutečná collation/padding/strict/visible/duplicity na MySQL a MariaDB.
- [x] CACHE-01: hotovo (Codex, 2026-10-25). Veřejný/osobní scope, lazy identita, locale, anonymní bypass a ochrana public obalů ověřeny včetně HTTP. Jiné obsahové varianty vyžadují explicitní klíče.
- [x] PERF-01: hotovo (Codex, 2026-10-25), diagnostika pouze v debug nebo přes Site::assignDiagnostics; ověřeno odstranění jednoho zbytečného spojení při cache HIT.
- [x] PERF-02: hotovo (Codex, 2026-10-25), requestová množina aktivních ID a jeden dávkový SQL statement pro prefixy, zachované hidden/strict/collation a opravené koncové lomítko rodičů.
- [x] PERF-03: hotovo (Codex, 2026-10-25), explicitní Session::close/Application::closeSession, zápis pod zámkem a následný snapshot bez dalších persistentních změn, měření a testy nezávislých procesů.
- [ ] PERF-04: odloženo po měření (Codex, 2026-10-25). Mapa nenabízí doložený přínos pro jediný lookup bez dalších nákladů a musí nejprve vyřešit FIX-02; data a postup níže.
- [x] PERF-05: hotovo (Codex, 2026-10-25), měření MySQL/MariaDB, společná section podmínka a volitelná migrace indexu (section_id, order), zachování výsledků a rollback ověřeny. Relační redesign odložen bez doložené potřeby.
- [x] PERF-06: hotovo (Codex, 2026-10-25), jeden průchod parserem na volání Json, raw/decoded kontrakt a nativní chyby zachovány včetně NUL-leading klíčů.
- [x] PERF-07: hotovo (Codex, 2026-10-25), odstraněn globální whitespace regex, dispatch zachovává výstup obou adaptérů a ignoruje legacy minimizeHtml.
- [x] ARCH-01: hotovo (Codex, 2026-10-25), constructor bez CMS, deferred Application routy před cache/CMS, jediné vykreslení a migrační smlouva.
- [ ] DX-01: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] DX-02: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] DX-03: čeká. Postup a akceptace jsou v odpovídající části níže.

- [x] PLAN-01: hotovo, schválená rozhodnutí a etapový plán zapsány, 2026-10-25.
- [x] BASE-01: hotovo, výchozí sada 802 testů / 1534 assertions v odděleném Docker prostředí, 2026-10-25.
- [x] TPL-01: hotovo (Codex, 2026-10-26), inventura 7 frameworkových a 13 vzorových zdrojů, read-only compile report, corpus očekávaných výstupů a rozdílů a ref-template-migration.md.
- [ ] TPL-02: rozpracováno (Codex, 2026-10-26), TPL-02A diagnostika a TPL-02B include/set/buffery dokončeny; zbývá aplikační migrace a HTTP smoke. Závisí na TPL-01 a SEC-03.
- [ ] TPL-03: čeká, volitelná kompatibilní vrstva, odstranění Smarty z jádra; závisí na TPL-02.
- [x] MD-01: hotovo (Codex, 2026-10-26), vlastní AST parser a bezpečný renderer, všechny oficiální CommonMark/GFM příklady a explicitní bezpečnostní odchylky, limity práce/vstupu/výstupu.
- [ ] MD-02: rozpracováno (Codex, 2026-10-26), přímé .md zdroje a explicitní DB formáty s děděním formátu při překladech; MD-01 dokončen.
- [ ] MD-03: čeká, převod Texy včetně překladů a záloh; závisí na MD-02.
- [ ] MD-04: čeká, odstranění Texy po migrační bráně; závisí na MD-03.
- [ ] API-01: čeká, kontrakt a capability discovery API v2; závisí na ARCH-01.
- [ ] API-02: čeká, CRUD obsahu a registrované typy boxů; závisí na API-01 a MD-02.
- [ ] API-03: čeká, koncepty, revize, publikování a náhled; závisí na API-02.
- [ ] API-04: čeká, integrační klíče, scopes, audit a invalidace; závisí na API-01.
- [ ] ADM-01: čeká, samostatný SvelteKit projekt a MySQL schéma.
- [ ] ADM-02: čeká, účty na pozvánku, MFA, oprávnění po webech; závisí na ADM-01.
- [ ] ADM-03: čeká, registr webů, šifrované klíče, bezpečný API klient; závisí na ADM-02 a API-04.
- [ ] ADM-04: čeká, obsahový editor a správa struktury; závisí na ADM-03 a API-02.
- [ ] ADM-05: čeká, náhledy, publikování, revize a konflikty; závisí na ADM-04 a API-03.
- [ ] REL-01: čeká, pilot, migrace a návrat, GitHub PR/tag/release v4.0.0; závisí na všech vydávaných funkcích.

## Výchozí ověření

- Při předchozím rozboru prošlo v Dockeru na PHP 8.4.27 všech 802 testů s 1 534 assertions. Composer audit nenahlásil známé zranitelnosti nainstalovaných závislostí. Jde o stav při tomto rozboru, nikoli záruku budoucího stavu.
- Doplňkový izolovaný skript ověřil ukládání stránky s `Cache-Control: no-store`, `Cache-Control: private` i `Set-Cookie`, sdílení fragmentu mezi českým a anglickým locale a tvorbu MD5 při úzkém sloupci pro heslo.
- Ostatní níže uvedená zjištění vycházejí ze čtení aktuálního kódu, pokud není uvedeno jinak. Nové měření propustnosti ani produkční penetrační test nebyly provedeny.
- Existující `ref-inventory.md` a README obsahují historická měření a nálezy; jejich seznamy otevřených úkolů se částečně rozcházejí s aktuálním kódem. Před realizací ověřit skutečný stav.

## Bezpečnost a správnost

### SEC-01: Pravidla a řízení cache celých stránek, P1

Zdroj: `PageCache.php`, zejména `_mayStore()`, `capture()` a `_serve()`.

- Respektovat `Cache-Control: private` a `no-store`; stanovit chování pro `no-cache` a `Vary`.
- Výchozí bezpečná politika má obejít ukládání při `Set-Cookie`, s případnými explicitně podporovanými výjimkami. Samotné `Set-Cookie` není podle HTTP automatickým zákazem cache.
- Přidat snadné řízení cache po stránkách a možnost explicitního bypassu z aplikačního kódu. Rozhodnutí o bypassu musí ovlivnit i čtení staré položky, nejen uložení nové.
- Zohlednit vlastní cookies, varianty zařízení a jiné zdroje personalizace. Locale již cache celé stránky odděluje; tuto ochranu zachovat.
- Při zásahu zachovat potřebné bezpečnostní a obsahové hlavičky, včetně konzistence CSP a případných nonce. Neukládat ani nepřehrávat cizí cookies.
- Zpřehlednit invalidaci při změně článků, stránek, menu, sekcí a boxů. Existující invalidaci sekcí a překladů zachovat; pro přímé SQL změny popsat explicitní invalidaci nebo TTL.

Ověření: osobní odpověď se nesdílí mezi návštěvníky, zákaz ukládání funguje, hlavičky jsou konzistentní při MISS i HIT, změny obsahu nevracejí nepřiměřeně stará data.

### SEC-02: Zrušit tvorbu nových MD5 hesel, P1

Zdroj: `User/Password.php::hash()` a `canStoreModernHash()`.

- Zachovat dočasné ověřování starých MD5 a převod po úspěšném přihlášení.
- Pro nová a měněná hesla používat moderní hash; při neprovedené migraci schématu vrátit bezpečnou a srozumitelnou chybu místo fallbacku na MD5.
- Požadovat sloupec odpovídající doporučenému `varchar(255)` a kontrolovat jej při instalaci či nasazení.

Ověření: nově uložené heslo nikdy není MD5, nedojde ke zkrácení hashe a migrace starých účtů zůstává funkční.

### SEC-03: Bezpečné výchozí escapování a důvěra šablon, P1

Zdroj: `Renderer/Adapter/SmartyRenderer.php`, `Renderer/Adapter.php`, `Template/Runtime.php`.

- Pro nové weby zavést automatické HTML escapování. NativeRenderer ho již podporuje, Smarty adaptér ho sám nezapíná.
- Migrovat existující šablony s rozlišením důvěryhodného HTML a běžných dat, zabránit dvojímu escapování a zachovat obsah sekcí.
- Dokumentovat kontexty HTML, atributů, URL a JavaScriptu; obecné HTML escapování neřeší všechny kontexty ani povolená URL schémata.
- Dokumentovat, že šablony jsou důvěryhodný kód: NativeRenderer dovoluje volat veřejné metody předaných objektů. Před případným zpřístupněním šablon nedůvěryhodným editorům navrhnout omezené datové rozhraní.

Ověření: XSS payloady v podporovaných kontextech, kompatibilita obou rendererů a vědomé použití `raw` pro důvěryhodný obsah.

### SEC-04: Nahradit předběžné čištění vstupů, P1

Zdroj: `Application/Env/Http.php::secureClean()`, `Post()`, `Get()` a související helpery.

- Oddělit nezměněné vstupní hodnoty, validaci typu a významu, SQL parametry a escapování výstupu.
- Odstranit závislost nového kódu na kombinaci `stripslashes`, `htmlspecialchars`, `strip_tags` a `addslashes`.
- Změnu existujících helperů zavést s migračním postupem a kontrolou volajících. Prosté odstranění čištění může odhalit dosud maskované XSS ve starých šablonách.
- Definovat chybějící hodnotu, pole místo řetězce a neplatné kódování. Prověřit také `getCookie()`, které dnes HTML escapuje už při čtení.

Ověření: apostrofy, zpětná lomítka, HTML a hesla se uchovají beze změny; databázové operace používají parametry a výstup se escapuje v odpovídajícím kontextu.

### SEC-05: Důvěryhodné proxy a detekce HTTPS, P1

Zdroj: `Application/Env/Stac/Utils.php::getUserIp()`, nastavení session cookies a API cookies.

- Zavést explicitní seznam důvěryhodných proxy IP nebo CIDR a podporovaných hlaviček. Ve výchozím stavu věřit pouze přímému spojení.
- Předané adresy přijímat jen od důvěryhodného bezprostředního proxy; řetězec adres vyhodnotit od důvěryhodného konce, validovat IPv4 i IPv6.
- Odstranit nedůvěryhodný fallback na `X-Forwarded-For` a `Client-IP` při chybějícím `REMOTE_ADDR`.
- Sjednotit také detekci původního HTTPS pro `Secure` cookies za TLS terminací. Podvržené forwarded hlavičky nesmějí měnit IP ani schéma.
- Současný kód upřednostňuje `REMOTE_ADDR`; běžný klient jej tedy samotným podvrženým `X-Forwarded-For` nepřepíše. Problém za proxy je především společná IP pro rate limit a sessions.

Ověření: přímé spojení, jeden i více proxy, podvržený levý konec řetězce, IPv6, neplatné hodnoty a správné cookie flags za TLS terminací.

### SEC-06: Obecná CSRF ochrana, P1

Zdroj: privátní tokenová ochrana v `Box/Auth.php`.

- Vytvořit malý znovupoužitelný helper pro vydání a ověření tokenu, formuláře a AJAX, navázaný na session a případně účel formuláře.
- Chránit operace měnící stav autentizované cookies, včetně POST, PUT, PATCH a DELETE podle použití. API s explicitním bearer tokenem posuzovat podle jeho skutečného autentizačního mechanismu.
- Převést webové odhlášení přes GET parametr `a=logout` na operaci měnící stav s odpovídající ochranou.
- Nezakládat session globálně při každém veřejném GET. Stránky s tokeny nesmějí sdílet osobní token přes page cache ani fragmentovou cache.
- Zajistit funkčnost více formulářů a otevřených panelů. Samotné SameSite nebo captcha nejsou náhradou obecného řešení.

Ověření: chybějící, chybný a cizí token se odmítne, legitimní formuláře fungují, veřejná stránka bez formuláře nezíská nový session zápis.

### FIX-01: Opravit JWT issued-at setter, P1

Zdroj: `Auth.php::setIssuedTime()` zapisuje do `iss` místo `iat`.

- Zapisovat čas do `iat`, zachovat issuer a otestovat význam jednotlivých setterů. Konstruktor již počáteční `iat` nastavuje správně; jde konkrétně o setter.
- Samotná chyba setteru nedokazuje obejití autentizace. Popsat ji jako chybu správnosti JWT s možným dopadem na integrace a časovou validaci.

Ověření: dekódovaný token obsahuje požadované `iat`, nezměněné `iss` a správné `exp`.

## Cache a výkon

### CACHE-01: Oddělit fragmenty podle locale a rozsahu sdílení, P1

Zdroj: `Template/Runtime.php::cache()`.

- Automaticky oddělit locale. Definovat veřejný a osobní rozsah fragmentu a pravidla klíčů pro ostatní varianty.
- Zabránit tomu, aby autor musel na locale pamatovat u každého běžného překládáného fragmentu.

Ověření: stejný název fragmentu vrací správný český i anglický obsah; případné osobní fragmenty se nesdílejí mezi uživateli.

### PERF-01: Diagnostické údaje pouze podle potřeby, P2

Zdroj: `Site.php::initialize()` a `Site/SiteModel.php::getDBVersion()`.

- Připravovat verze DB, PHP a serveru pouze pro debug nebo explicitní požadavek.
- Odstranit otevření DB spojení pouze kvůli `dbinfo` na stránce obsloužené datovou cache.

Ověření: zahřátá veřejná stránka pokrytá datovou cache nepotřebuje spojení jen pro diagnostiku; měřit spojení i SQL dotazy zvlášť.

### PERF-02: Odstranit N+1 dotazy menu, P2

Zdroj: `Site.php::isMenuSelected()`, `getUrlPairsWithSiteId()`, `Site/SiteModel.php::getSiteIdByUrl()`.

- Aktivní množinu ID spočítat jednou na požadavek a opakovaně používat.
- Využít mapu již načtených stránek nebo jediný dávkový dotaz při vypnuté cache.
- Prověřit sémantiku: cache `sites:visible` obsahuje jen viditelné stránky, zatímco současný lookup podle URL viditelností nefiltruje. Zachovat nebo vědomě sjednotit práci se skrytými stránkami, duplicitními URL a collation.

Ověření: počet lookup dotazů neroste s počtem položek menu; aktivní položky a rodiče odpovídají původnímu zamýšlenému chování.

### PERF-03: Délka zámku sessions, P2

Zdroj: `Session/SessionAbstract.php::sessionStart()`, `Session.php::flush()` a regenerace ID.

- Změřit paralelní požadavky se stejnou cookie při používaném session handleru.
- Navrhnout dřívější bezpečné uvolnění PHP session zámku. Zachovat regeneraci ID a správnost odložených databázových zápisů; zabránit ztraceným aktualizacím a obnovení odhlášené session.

Ověření: porovnat paralelní latence a souběžné zápisy, přihlášení i odhlášení. Bez měření neslibovat konkrétní zrychlení.

### PERF-04: Index URL pro rozsáhlejší weby, P3

Zdroj: `Site/SiteModel.php::_findVisibleSite()`.

- Zvážit mapu podle normalizované URL místo procházení všech viditelných stránek.
- Zachovat striktní a prefixové hledání, současnou prioritu výsledků a kompatibilitu s databázovým porovnáváním.

Ověření: shoda cache a DB větve a náklady při rostoucím počtu stránek; u malých webů nepřidávat složitost bez přínosu.

### PERF-05: Indexy boxů a případná relační vazba, P3

Zdroj: `Section/Model.php::getBoxes()`, tabulka `cms_boxes`.

- Kandidáta `(section_id, shared, order)` porovnat zejména s `(section_id, order)` a se skutečnými existujícími indexy cílového schématu.
- Podmínku přepsat podle potřeby do tvaru se společným `section_id`, při zachování výsledků a parametrů.
- Současná kombinace `JSON_SEARCH` a `OR` neumožňuje předem zaručit odstranění skenu nebo řazení. Kandidát s `shared` mezi sekcí a pořadím nemusí pokrýt globální řazení obou větví.
- Pro rozsáhlejší instalace zvážit relační vazební tabulku boxů a stránek s indexy. Zachovat pohodlné ovládání při tvorbě webu.

Ověření: plány pomocí `EXPLAIN` a běhových statistik podle možností MySQL a MariaDB, realistický počet boxů a poměr shared, studená i zahřátá cache, dopad na zápisy a shoda výsledků.

### PERF-06: Jediné dekódování JSON, P2

Zdroj: `Application/Env/Http.php::Json()` a `isJson()`.

- Při `Json(true)` dekódovat jednou a vyhodnotit chybu stejného dekódování. Dnes `isJson()` dekóduje a `Json()` dekóduje znovu.
- Zachovat zdokumentovanou smlouvu `Json(false)` a určit rozdíl mezi neplatným JSON a platným JSON `null`.
- Otestovat skaláry, prázdné tělo, pole, objekty, neplatné kódování a limit hloubky. Nové chování chyb zavádět vědomě vůči existujícím controllerům.

Ověření: jedno parsování dekódovaného vstupu, regresní testy návratových hodnot. Přínos považovat za malou lokální optimalizaci, ne hlavní zrychlení webu.

### PERF-07: Bezpečná práce s HTML a přenosovou kompresí, P2

Zdroj: `Renderer.php::dispatch()`.

- Vypnout nebo nahradit globální slučování bílých znaků regulárním výrazem.
- Pro přenos využít kompresi webserveru; případnou minifikaci obsahu provádět nástrojem rozumějícím jeho syntaxi.

Ověření: beze změny významu `pre`, `textarea`, inline JavaScriptu, CSS a textových mezer.

## Architektura a pohodlí vývoje

### ARCH-01: Odložit zpracování požadavku, P2

Zdroj: `Application/Application.php::__construct()`, `get()`, `run()` a integrace API boxů.

- Oddělit přípravu aplikace, registraci a vyhodnocení rout, inicializaci CMS a vykreslení.
- Přesunout vykonávání boxů ze stavby objektu do explicitní fáze obsluhy požadavku. Umožnit nastavení služeb a proměnných před renderováním.
- Ukončená vlastní API route má obejít CMS dotazy, sekce a boxy. Samotný přesun `initialize()` bez definice pořadí rout a cache nestačí.
- Page cache nesmí ukončit požadavek před vlastními routami a jejich politikami. Zachovat časnou i18n normalizaci potřebnou pro routy.
- Zajistit migraci existujících aplikací a API registrace uvnitř boxů, správné 404 a jediné vykreslení.

Ověření: vlastní route funguje bez CMS inicializace, běžné stránky zachovávají chování, HIT nepřeskočí požadované aplikační nastavení.

### DX-01: Vytvoření stránky a boxu jedním příkazem, P3

- Navrhnout malý generátor třídy, šablony a potřebné konfigurace nebo databázové migrace.
- Respektovat namespace a adresářové konvence aplikace, nepřepisovat existující práci a nabídnout náhled změn.

Ověření: nový jednoduchý webový obsah vznikne krátkým a opakovatelným postupem bez ručního hledání vazeb.

### DX-02: Kontrola instalace a produkční profil, P2

- Přidat příkaz pro kontrolu konfigurace, migrací, zapisovatelných adresářů, tajných klíčů a dostupnosti zvoleného cache driveru.
- Doplnit doporučené produkční nastavení: debug vypnutý, správná proxy a HTTPS konfigurace, OPcache, optimalizovaný Composer autoload, vypnutá kontrola změn šablon a jasné čištění kompilátové cache při deployi.
- Kontrola má být explicitní při setupu nebo deployi, nikoli drahá kontrola na každém požadavku.

Ověření: vadná instalace má konkrétní návod k opravě; zdravý produkční požadavek nenese dodatečnou režii kontrol.

### DX-03: Opakovatelné měření výkonu, P2

- Odděleně měřit cache vypnutou, zahřátou datovou cache a cache celé stránky.
- Pokrýt návštěvníka bez cookie, anonymního se session, přihlášeného a vlastní API route; měřit i paralelní požadavky stejné session.
- Zaznamenat p50 a p95 latenci, propustnost, chyby, paměť, SQL dotazy a připojení k DB. Uvést PHP, server, DB, OPcache, data a souběžnost.
- Tvrzení o téměř okamžité odpovědi nebo minimální paměti považovat za hypotézu do měření. Historické výsledky nepřevádět na garance.

## Doporučené pořadí a závislosti

1. Opravit pravidla page cache, fallback MD5, locale fragmentů a JWT setter. Jde o potvrzené a poměrně ohraničené problémy.
2. Navrhnout a realizovat společně bezpečné šablony a migraci vstupních helperů; přidat obecnou CSRF ochranu a důvěryhodné proxy.
3. Odstranit diagnostické DB připojení a opakované menu lookupy, zjednodušit JSON a vyřadit nebezpečnou minifikaci.
4. Refaktorovat životní cyklus aplikace a session locking s integračními testy a migračním postupem.
5. Změřit výkon; podle výsledků vybrat indexy boxů, mapu URL a případnou relační vazbu. Doplnit produkční kontrolu a generátor.

Časový odhad zatím není stanoven. Změny dělit na malé kontrolovatelné celky; bezpečnostní změny doplnit regresními testy konkrétního scénáře. Rozsáhlejší změny zachovat kompatibilní nebo opatřit jasnou migrační cestou. Před každým celkem ověřit aktuální stav kódu a testů.

## Referenční zdroje

- [RFC 9111: HTTP caching](https://www.rfc-editor.org/rfc/rfc9111.html)
- [OWASP: Password storage](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)
- [OWASP: CSRF prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [Symfony: Trusted proxies](https://symfony.com/doc/current/deployment/proxies.html), referenční princip důvěry v proxy, bez požadavku přidávat závislost.
- [MySQL 8.4: Multiple-column indexes](https://dev.mysql.com/doc/refman/8.4/en/multiple-column-indexes.html)
- [MySQL 8.4: ORDER BY optimization](https://dev.mysql.com/doc/refman/8.4/en/order-by-optimization.html)
- [RFC 7519: JWT claims](https://www.rfc-editor.org/rfc/rfc7519.html#section-4.1.6)
- [PHP: session_write_close](https://www.php.net/session-write-close)

## Schválená cílová architektura 4.0.0

### Vlastní šablony a Markdown

Rozvíjet existující NativeRenderer s kompilací do PHP a OPcache. Zachovat podporovanou syntaxi; neslibovat úplnou náhradu všech konstrukcí Smarty. Automatické escapování běžných dat a explicitní důvěryhodné HTML. Smarty zůstává pouze dočasnou kompatibilní vrstvou během migrace.

Vlastní parser: CommonMark 0.31.2 plus tabulky, přeškrtnutí a seznamy úkolů podle příslušných GFM pravidel. Blokový a inline parser s interním stromem, oddělený bezpečný HTML renderer, limity velikosti a vnoření, kontrola URL schémat. Vložené HTML se v bezpečném profilu escapuje. Parser musí projít standardními příklady; vědomé rozdíly bezpečného renderu se evidují.

Stejný parser pro DB i .md soubory v povoleném obsahovém adresáři. Markdown je obsah vložený do běžného HTML layoutu; žádné proměnné, PHP, cykly ani šablonová logika, a to ani v důvěryhodných .md souborech. Náhled používá parser cílového webu. Formát uloženého textu je explicitní, neodhadovaný. Migrace Texy má dry-run, originály, překlady, report ručních oprav a kontrolu výstupů. Zachovat firebase/php-jwt a vývojové nástroje.

### Samostatná centrální administrace

Samostatný projekt `wbengine-admin`, SvelteKit + TypeScript se serverem, vlastní MySQL 8.4 a Docker. Prohlížeč komunikuje jen se serverem centrály; ten po kontrole oprávnění volá API webu. Integrační klíče neputují do prohlížeče. Weby vlastní obsah, koncepty a revize; centrála vlastní účty, připojení, přístupová práva a audit. Výpadek centrály nesmí vyřadit weby. Žádný přímý přístup do vzdálených DB/FTP a žádná automatická aktualizace webu.

Lokální účty na pozvánku, obnova přístupu, TOTP a záložní kódy; MFA povinné pro správce. Globální správce připojuje weby a přiděluje práva, editor spravuje obsah přidělených webů, správce webu i jeho strukturu. Každý web vydá odvolatelný integrační klíč s omezenými scopes, hash klíče na webu a šifrovaný klíč v centrále se šifrovacím tajemstvím mimo DB. Audit rozlišuje integrační identitu a uživatele centrály.

Veřejné HTTPS weby standardně; intranet/VPN jen s provozním allowlistem a dostupnou síťovou cestou. Ochrana proti SSRF, přesměrováním a DNS rebindingu. První verze spravuje články, stránky, menu, sekce, registrované boxy, překlady a obrázky, náhled a publikování. Žádná vzdálená editace PHP ani šablon, žádné multi-organizační SaaS, schvalovací workflow nebo plánované publikování v první verzi.

### API v2 a publikování

`/api/v2` vedle zachovaného API v1. Capability discovery, verze API a katalog povolených boxů s validovaným nastavením. CRUD zdrojů, jednotné chyby a stránkování, ETag/If-Match pro ochranu proti přepsání cizí změny. Zastaralý zápis odmítnout, po commit invalidovat související cache. API nepřijímá libovolnou PHP třídu/metodu. Starší instalace vyžaduje explicitní upgrade API a schématu před připojením.

Koncepty a revize se týkají článků a jejich překladů. Uložení konceptu nemění publikovaný obsah; obnova revize vytvoří koncept. Struktura se mění přímo po explicitním potvrzení, s auditem a obnovou. Atomické publikování celého webu není součástí první verze. Osobní náhledy nesmějí do veřejné cache.

## Etapy a postup realizace

1. Etapa 0: uložit tracker a rozhodnutí; zaznamenat výchozí testy; připravit DX-03 opakovatelný benchmark. Baseline testů je hotový, benchmark zůstává otevřený.
2. Etapa 1: SEC-01, SEC-02, CACHE-01, FIX-01, každá chyba s regresním scénářem před opravou.
3. Etapa 2: SEC-03 až SEC-06 společně s migračním návodem vstupů a šablon.
4. Etapa 3: PERF-01, PERF-02, PERF-06, PERF-07, ARCH-01 a PERF-03; PERF-04/PERF-05 pouze podle měření.
5. Etapa 4: TPL-01 až TPL-03, inventura, kompatibilita, migrace a oddělení Smarty.
6. Etapa 5: MD-01 až MD-04, parser, .md/DB, převod a odstranění Texy.
7. Etapa 6: API-01 až API-04, kontrakt, zdroje, publikování, klíče a audit.
8. Etapa 7: ADM-01 až ADM-05, centrální server, identity, weby, editor a publikování.
9. Etapa 8: DX-01, DX-02, REL-01, pilot a postupné nasazení 4.0.0.

Před každým úkolem: ověřit stav, dopsat konkrétní rozhraní a migrační postup, určit regresní testy, převzít odpovědnost. Pak test selhávající před změnou, implementace, cílené testy, celá potřebná sada a dokumentace. Po dokončení zapsat skutečné výsledky a commit, aktualizovat registr. Neoznačovat celý SEC-01 jako hotový, pokud je dokončena jen část pravidel cache.

Release gate: vzorová aplikace a jeden pilot, funkční záloha a návrat, ověřené originály obsahu, dokončené ruční migrační opravy, žádná povinná Smarty/Texy závislost, koncové testy dvou webů a omezeného uživatele, souběžné editace, odvolaný klíč, výpadky webu/centrály, bezpečné preview, SSRF a obnovy. Tag a GitHub release až po splnění brány; průběžný draft PR není hotový release.

## Záznam postupu

### 2026-10-25: Zahájení 4.0.0

- Worktree `/private/tmp/wbengine-modernization`, větev `codex/wbengine-4.0`, výchozí commit `9149fef`, poslední vydání `v3.5.0`.
- Výchozí ověření: `docker compose -p wbengine-modernization run --rm php composer test`: 802 testů, 1534 assertions, úspěch na PHP 8.4.27.
- Rozhodnutí: nová major verze 4.0.0, kompatibilita se starými instalacemi přes explicitní migraci. Existující podrobné nálezy a ID zůstávají zachovány.
- Rozhodnutí: první implementační balík opravuje potvrzené chyby; rozšíření SEC-01 bude vykazováno po částech, dokud nejsou splněna všechna kritéria.

- 2026-10-25: uživatel doplnil závazné zachování stromové OOP architektury a namespaces pro nové funkce jádra. Pravidlo doplněno do globálních omezení plánu.
- 2026-10-25: 14 nových regresních scénářů před změnou selhalo z očekávaných příčin; po změně cílená sada 171 testů / 252 assertions prošla. Finální ověření a review ještě zbývají.

### 2026-10-25: První ověřený implementační balík 4.0.0

- Implementováno: JWT iat setter; odmítnutí nových hesel při úzkém sloupci bez MD5 fallbacku; oddělení fragmentů podle locale; přísnější page cache, ignorování starých položek, allowPaths/denyPaths a request-wide bypass.
- Cache zachovává standardní bezpečnostní hlavičky včetně cross-origin politik na HIT a 304. Aktuální deployment politika má přednost před uloženou. CSP nonce, soukromé odpovědi, libovolné aplikační cookies a anonymní session s osobními daty sdílenou cache obcházejí.
- Rozhodnutí: vlastní cookies konzervativně znamenají bypass, což může snížit podíl HIT u webů s analytickými cookies. Bez explicitního schématu variant má přednost oddělení osobních dat; případné bezpečné výjimky řešit samostatně.
- Rozhodnutí: `PageCache::bypass()` volaný před konstrukcí Application chrání čtení i zápis; volání v boxu chrání pouze zápis aktuální odpovědi. Tato hranice je výslovně v README.
- Review: nezávislý čtecí reviewer našel chybějící cross-origin hlavičky; nový HTTP test selhal, hlavičky byly doplněny a test prošel. Doplněn integrační test skutečného varchar(32) včetně legacy autentizace; původní schéma se obnovuje ve finally.
- Jeden standardní Composer běh timeoutoval; následující kompletní diagnostický běh a dva standardní běhy prošly. Finální výsledek: `docker compose -p wbengine-modernization run --rm php composer test`, **835 testů / 1603 assertions**, 9.913 s, PHP 8.4.27, MySQL 8.4.
- `docker compose -p wbengine-modernization run --rm php composer lint`: úspěch. `git diff --check`: úspěch.
- Změny zůstávají ve stávajících produkčních třídách a namespaces. Nové samostatné PHP soubory v tomto balíku jsou pouze HTTP test a jeho fixture; nevznikl paralelní produkční framework.
- Neimplementováno: vlastní Markdown, odstranění Smarty/Texy, API v2 ani centrální administrace. Jejich úkoly zůstávají otevřené.
- Další krok: dokončit explicitní scope fragmentů a návrh obsahové invalidace, poté SEC-03 až SEC-06. Pro GitHub připravit draft PR 4.0.0, bez tagu/release a bez merge do master.

- Git commit prvního balíku: `6e799ed`. GitHub: [draft PR #2](https://github.com/rrsoftcz/wbengine/pull/2), větev `codex/wbengine-4.0`, základ `master`. Tag ani release nebyly vytvořeny. Lokální testovací databáze byla po ověření zastavena.

- GitHub CI pro commit `6e799ed`: všech 5 jobs úspěšných, PHP 8.3/8.4/8.5 s MySQL 8.4 a PHP 8.4 s MariaDB 10.11/11.4. Výsledky jsou dostupné v draft PR #2.

- 2026-10-25: na žádost uživatele doplněno závazné pravidlo commitovat a pushovat po každé větší změně nebo fázi také aktuální stav plánu, včetně pravidel rozpracovaných kontrolních bodů a bezpečného navázání. Před změnou ověřena shoda lokálního HEAD a upstreamu na `1d98025`.

### CACHE-01: Navazující implementační postup

Stav: rozpracováno, Codex. Výchozí kontrolní bod `b3a7116`, lokální a vzdálená větev shodná.

- Rozšířit blok `{cache}` o literální `scope='public'` (výchozí) a `scope='user'`, volitelně s `ttl`. Neznámý nebo duplicitní parametr odmítnout.
- Přidat objekt `Wbengine\Template\FragmentCache` jako lazy potomka `Template\Engine`. Kompilátor volá engine, renderer adapter předává resolver uživatele a `Renderer` jej napojuje přes Application na aktuální User. Veřejný scope resolver nevolá.
- Rozhraní: `Engine::setCacheUserResolver(?callable)`, `Engine::cache($key, $ttl, callable $render, $scope = 'public')`; NativeRenderer a Renderer\Adapter zprostředkují nastavení resolveru.
- Osobní klíč obsahuje platné kladné user ID, locale a název fragmentu; veřejný a osobní klíč mají různé jmenné prostory. Chybějící nebo neplatná identita nesmí spadnout do veřejné cache. Resolver se vyhodnotí při použití, ne při konstrukci.
- Osobní scope vyvolá page-cache bypass i při vypnuté cache nebo anonymním návštěvníkovi. Veřejný obal, který při renderování použil osobní fragment, se nesmí sdíleně uložit. Public blok musí mít obsah nezávislý na uživateli; jiné varianty patří do explicitních klíčů.
- Zachovat původní syntaxi cache bloků a Runtime::cache pro přímé veřejné volání; změnit verzi kompilátoru a namespace fragmentových klíčů, aby se nepoužily staré obaly.
- Testy před implementací: oddělení dvou uživatelů a locale, veřejný versus osobní scope, anonymní/neplatná identita, odložený resolver, nested/private obal, neplatná syntaxe, předání přes NativeRenderer a Renderer\Adapter, page-cache bypass přes skutečné HTTP.
- Po cílených testech spustit celou sadu, lint, nezávislé review a zapsat výsledky. Commit a push kódu i aktualizovaného plánu před dalším větším úkolem.

### 2026-10-25: CACHE-01 dokončen

- Nová služba `Template\FragmentCache` je lazy potomkem Engine. Kompilátor volá engine; NativeRenderer a Renderer\Adapter předávají resolver napojený na Application. Veřejný scope nevyvolává autentizaci ani session lookup.
- Scope user odděluje klíče podle aktuální autentizované session a locale. Neplatná nebo chybějící identita ani chyba resolveru nepoužijí veřejnou cache. Při změně identity se nepoužije zastaralé ID z User resource.
- Osobní fragmenty vypínají page cache; veřejný obal s vykonaným osobním fragmentem se neuloží. Stejná ochrana platí přes kompatibilní Runtime helper. Chyba renderování uklidí vnořené buffery.
- Rozhodnutí: po osobním scope se konzervativně obchází i ostatní veřejné fragmenty do konce požadavku. Může to snížit počet HIT u přihlášených, ale zabrání sdílenému uložení osobního obalu. Public blok musí být nezávislý na návštěvníkovi i na větvích, které při cache hitu neběží.
- Rozhodnutí: zachován public-only Runtime helper, vnitřně deleguje stejnému OOP cache mechanismu. Compiler VERSION=2 a prefix tpl:v3 brání použití starých sdílených obalů.
- Před implementací 17 scénářů chybělo (neexistující resolver/syntaxe); další regresní test zachytil stale User ID. Po opravě celá sada: **862 testů, 1676 assertions**, 10.126 s, PHP 8.4.27 / MySQL 8.4. Lint a git diff --check prošly. Nezávislé review bez konkrétních nálezů.
- Další krok po push: SEC-03, sjednotit automatické escapování obou rendererů a dokumentovat bezpečné výstupní kontexty; změny vstupních helperů ponechat na následující samostatný balík SEC-04.

### SEC-03: Navazující implementační postup

- Nastavit automatické HTML escapování také ve Smarty adaptéru a přidat setAutoEscape do stejného adapterového rozhraní jako NativeRenderer. Explicitní false zachovat jako dočasnou migrační volbu.
- Zachovat explicitní raw a escape v bundlovaných šablonách. Historický ekvivalenční corpus porovnává explicitně legacy režim obou rendererů, nikoli nové bezpečné defaulty.
- Opravit native pipeline: bezpečnost výstupu určuje poslední modifier, nikoli libovolný dřívější safe modifier. Například escape|default:$fallback musí escapovat nový fallback. Změnit compiler version a navázat fragmentové klíče na tuto verzi.
- Dokumentovat důvěryhodnost šablon a rozdíly HTML textu, atributů, URL a script/JSON kontextu. Zatím neměnit vstupní helpery (SEC-04).
- Testy: XSS payload výchozím Smarty výpisem, true/false přes adaptéry, žádné dvojí escapování u explicitního escape, raw jen explicitně, unsafe fallback po safe modifieru v native.

- SEC-03, upřesnění z regresních testů: parser musí sledovat pouze modifikátory výsledného výrazu. Escapovaný operand, argument metody ani index pole nesmí označit jiný obsah za bezpečný. Příslušné XSS scénáře před opravou selhaly.
- SEC-03, rozhodnutí: Smarty compile ID obsahuje režim autoEscape, protože jinak jeho paměťová cache po přepnutí true/false může znovu použít starý template objekt. Původní uživatelské compile ID zůstává součástí výsledného ID.
- SEC-03, rozhodnutí: testy zachytily starý problém compileCheck, kdy stejná velikost a timestamp neodhalily změnu zdroje. Doplněn SHA-256 podpis při zapnutém compileCheck; produkční zásah s compileCheck=false checksum nepočítá. Cena je další čtení zdroje ve vývojovém režimu, přínosem spolehlivá obnova šablon a bezpečnostních úprav.

- SEC-03, review: žádné nové nálezy v dosavadním diffu. Reviewer navíc reprodukoval starší kolizi fragmentů mezi autoEscape=false a true. Zahrnuta do tohoto bezpečnostního balíku: oba scope ji potvrdily RED testem, klíč nově obsahuje také režim escapování. Ruční vyčištění cache tak není jedinou ochranou proti převzetí raw HTML z migračního režimu.

### 2026-10-25: SEC-03 dokončen

- Hotovo: automatické escapování obou rendererů, explicitní migrační autoEscape=false, oprava modifikátorů finálního výrazu v native parseru a zachování raw/escape pro explicitně určené kontexty.
- Výchozí 8 scénářů XSS/defaultů před opravou selhalo; další 3 scénáře potvrdily přenos nesprávné důvěry z operandu, argumentu metody a indexu pole. Opraveno bez rozšiřování gramatiky template tagů.
- Samostatný RED test potvrdil zastaralou kompilaci při stejném timestampu a délce. SHA-256 freshness se nyní počítá jen při compileCheck=true; podpis při kompilaci vychází ze stejného zdrojového snapshotu.
- Review dosavadního diffu bez nových nálezů. Starší kolize mezi raw a escaped fragmenty navíc opravena podle 2 RED testů; režim escapování a compiler version jsou součástí klíče veřejných i osobních fragmentů.
- Finální ověření: **878 testů / 1703 assertions**, 9.859 s, PHP 8.4.27 / MySQL 8.4; composer lint a git diff --check prošly.
- Dokumentace popisuje bezpečný HTML text, quoted atributy, validaci URL schémat, script-safe JSON a důvěryhodnost šablon. Smarty je stále dočasný adaptér; odstranění z runtime závislostí patří do TPL-03.
- CACHE-01 je uložen a pushnut jako `f998303`. Tento následující kontrolní bod zahrnuje kód SEC-03 i stav plánu; přesný hash je v git log pracovní větve.
- Další konkrétní krok: SEC-04, inventarizace volání Http::Post/Get/getCookie/secureClean, zavedení nezměněných vstupů a explicitní validace, regresní testy apostrofů, lomítek, polí a chybějících hodnot. Neměnit současně autentizační model nebo routování. SEC-01 obsahová invalidace navazuje na nové write API v pozdější etapě.

### SEC-04: Postup a smlouva vstupních helperů

Výchozí kontrolní bod `4ae723f`, lokální i vzdálená větev shodná. Pracovní větev `codex/wbengine-4.0`, odpovědnost Codex.

- Inventura: interní secureClean používají pouze Post/Get. Box\Auth používá Post pro CSRF a CAPTCHA; přihlášení již používá PostRaw. Další runtime volající v knihovně ani ve zdrojích vzorové aplikace nebyli nalezeni. API JSON příjem se v tomto balíku nemění.
- Post($name=null) a Get($name=null) vracejí beze změny hodnotu z PHP superglobálu, při null celý odpovídající vstupní map; chybějící klíč vrací null bez warningu. Platné názvy jsou string a int, včetně 0 a prázdného řetězce. Jiný typ názvu vrací null.
- PostString($name), GetString($name) a getCookie(string $name) vracejí pouze nezměněný string nebo null, nikdy nepřevádějí pole/objekt/scalar na text. PostRaw zůstává kompatibilní alias PostString.
- Rozhodnutí: raw znamená hodnoty po standardním zpracování HTTP v PHP, ne původní wire bytes. Bez trimování, HTML entit a přidávání/odstraňování lomítek. I neplatné UTF-8 bajty zůstávají nezměněné; textová pole musí validovat typ, UTF-8, délku a význam ve své aplikační vrstvě. Řetězcový helper řeší pouze tvar hodnoty, ne její důvěryhodnost.
- Rozhodnutí pro major 4.0: secureClean odstranit, nevracet pod jeho bezpečnostně zavádějícím názvem tiše raw hodnoty. Migraci přímých externích volání výslovně popsat v README a CHANGELOG. Staré již znečištěné DB hodnoty automaticky neodkódovávat.
- Box\Auth převede token a CAPTCHA na PostString. Neřetězcový CSRF token odmítne před hash_equals. SQL se nadále parametruje v Db, výstup escapuje renderer (SEC-03 hotovo).
- Postup: nejprve RED testy apostrofů, lomítek, HTML, prázdných/zero hodnot, nested polí, chybějících klíčů, neplatného UTF-8 a malformed tokenů. Pak minimální změna helperů a volajících; integrační průchod raw -> parametrizované SQL -> escaped HTML v obou adaptérech.
- Akceptace: žádná změna hodnot v DB, žádné dvojí escapování ve výsledku, neplatné tvary protokolových vstupů bez TypeError. Cílené testy, plná sada, lint a nezávislé review; poté commit+push kódu i plánu před další větší změnou.

- SEC-04 review: bez runtime nálezů. Reviewer upozornil, že původní CAPTCHA test s prázdným secretem neprokazuje odmítnutí před sítí. Rozhodnutí: zpřesnit požadovaný důkaz, neodkládat jej jako kosmetickou změnu. Síťový kód zůstává stejný, pouze vyčleněn do protected verifyCaptcha v témže Box\Auth. Mockovaný ověřovač s nakonfigurovaným testovacím secretem musí mít nulové volání pro malformed vstup v běžném i AJAX POST; platné řetězce se předávají beze změny. Žádné skutečné CAPTCHA požadavky se při testech neposílají.

### 2026-10-25: SEC-04 dokončen

- Post/Get zachovávají původní PHP-parsed hodnoty a při vynechání názvu vracejí celou mapu. PostString/GetString, kompatibilní PostRaw a getCookie přijímají pouze řetězce. Chybějící hodnoty vracejí null bez warningů. secureClean je v major 4.0 odstraněn.
- Box\Auth odmítá neplatné tvary CSRF a CAPTCHA polí. Stávající síťové ověření CAPTCHA je vyčleněno do protected metody v téže třídě; nevznikla další produkční služba ani závislost. Architektura a namespaces zůstávají zachovány.
- TDD: původních 42 cílených testů ukázalo 14 errors a 7 failures. Dva integrační průchody přes parametrizovanou DB a oba renderery před změnou selhaly na změněných hodnotách. Nové testy hranice CAPTCHA před implementací selhaly kvůli chybějícímu ověřovači; po doplnění prokazují odmítnutí před sítí i přesné předání platných řetězců v běžném a AJAX požadavku.
- Finální ověření: `docker compose -p wbengine-modernization run --rm php composer test`, **933 testů / 1876 assertions**, 10.049 s, 32 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` a `git diff --check` prošly. Nezávislé review bez runtime nálezů; upozornění na slabší test CAPTCHA bylo zapracováno.
- README a CHANGELOG popisují major změnu, explicitní validaci, parametrizaci SQL a escapování výstupu. Historická data se automaticky nepřepisují. API JSON a autentizační model se v tomto balíku nemění.
- Kontrolní bod zahrnuje kód, testy i tento plán; přesný hash je v historii pracovní větve. Push a shoda upstreamu se ověřují před navazující fází.
- Další konkrétní krok: SEC-05, inventura detekce IP/HTTPS a všech cookie flags, návrh lazy služby v existující stromové architektuře a testy explicitně důvěryhodných proxy IPv4/IPv6. Výchozí konfigurace bude důvěřovat pouze přímému spojení.

### SEC-05: Implementační postup a rozhodnutí

- Výchozí checkpoint d1e319a, izolovaný worktree /private/tmp/wbengine-modernization. Stávající Utils používají REMOTE_ADDR a nebezpečný fallback hlaviček při chybějícím peeru. SessionAbstract a Api detekují HTTPS samostatně. API refresh issuance i vymazání používají společné cookie options.
- Zavést Application\Env\RequestContext jako lazy potomka Application s getParent. Pro stávající samostatné API a statické Http/Utils zůstane dostupná stejná služba bez Application rodiče. Resolver nemá cache uživatelských dat, aktuální server/config čte při použití, takže nezmění bootstrap ani session inicializaci.
- Rozhodnutí: explicitní http.trustedProxies (IP/CIDR IPv4/IPv6) a http.forwardedHeaders (pouze x-forwarded-for, x-forwarded-proto); výchozí oba seznamy prázdné. Chybná konfigurace vyvolá výjimku, nikoli rozšíření důvěry. Nepoužívat starý IPv4-only ipInRange pro bezpečnostní hranici.
- XFF: ověřit přímého peera, projít řetězec zprava přes povolené proxy a zastavit na prvním nedůvěryhodném klientovi. Chybějící/neplatný peer vrací prázdnou IP, nikdy fallback na Client-IP nebo jinou hlavičku. Neplatný procházený hop nebo nadlimitní hlavička použije přímého peera. IPv6 výstup kanonizovat, žádné porty, zone ID ani obfuscated adresy.
- XFP: podporovat pouze jedinou hodnotu http/https od povoleného přímého peera a jen při explicitním povolení této hlavičky. Proxy ji musí přepsat podle skutečného původního schématu, ne připojit klientský text. Seznamy a alternativní hlavičky se ignorují. Platné http může přepsat backend TLS. Bez důvěryhodné hodnoty použít pouze lokální HTTPS on/1. Explicitní API override Secure zachovat.
- Postup: RED testy spoofingu, chybějícího peera, řetězců, IPv6/CIDR hranic a konfigurace; minimální implementace; integrační test skutečných session/API cookie flags; cílené a plné testy, lint a nezávislé review. Pak aktualizace plánu, commit a push.
- Zdroj pravidel pro řetězce a schéma: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Forwarded-For a https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Forwarded-Proto. Žádné automatické načítání Cloudflare/AWS rozsahů ani univerzální trust všech proxy.

- SEC-05 review: nezávislé čtecí review bez bezpečnostních/regresních nálezů. Doplněny dvě drobné mezery v důkazech: neplatný peer pro XFP a změna metadat i konfigurace u dlouho žijící statické fasády.
- Diagnostika plné sady: 988 testů, jedno starší kolísavé selhání testARequestWithoutACookieCostsNoSessionQuery. Session::_load před lookupem s pravděpodobností 1/100 spouští úklid. Izolovaný diagnostický proces s vynuceným GC prokázal přesně jeden DELETE FROM cms_sessions WHERE session_expire < ?, bez SELECT. Tentýž proces se setAutoClean(false) prokázal nula dotazů. Rozhodnutí: v testu lookupu vypnout GC existujícím setterem; produkční úklid se nemění. Dočasný diagnostický soubor byl v ignorované .phpunit.cache a po ověření odstraněn.

- Závěrečná globální inventura nalezla také I18n::_remember a I18n::origin se samostatnou HTTPS detekcí. Zahrnuty do SEC-05: isSecure přijímá volitelný server snapshot, aby zůstalo zachováno existující explicitní rozhraní I18n::boot/origin. Nejprve dvě RED selhání cookie flags, po propojení cookie další dvě RED selhání schématu origin. Test záměrně nastavuje opačné ambientní HTTPS, aby prokázal použití předaného požadavku. Hreflang page-cache klíče již vycházejí z I18n::origin, tedy budou rozlišovat původní schéma; host z forwarded hlaviček se nepřijímá.

### 2026-10-25: SEC-05 dokončen

- Hotovo: lazy Application::getRequestContext, parent vazba a společný resolver pro Http/Utils, session, API i I18n. Žádné další balíčky, DB dotazy nebo session start při samotném vyhodnocení spojení. Cache obsahuje pouze validovanou deployment politiku, nikoli údaje požadavků.
- Ve výchozím stavu žádná důvěra ve forwarded hlavičky. Explicitní proxy IP/CIDR i hlavičky, IPv4/IPv6, validace peera, XFF zprava, kanonizace IPv6, limity délky/hopů, odmítnutí neplatné konfigurace. XFP pouze jedna hodnota od důvěryhodného přímého peera; nedůvěryhodné a alternativní hlavičky se ignorují. API Secure override zůstává.
- TDD: počáteční 51testová sada ukázala 26 failures a chybějící getRequestContext. Dva integrační RED scénáře doložily chybné Secure za TLS terminací. Další RED scénáře potvrdily vlastní chyby I18n cookie a origin; všechny byly následně opraveny a ověřeny. Session integrace ověřuje také skutečně uloženou IP v DB; API integrace vydání i smazání refresh cookie.
- Finální ověření: `docker compose -p wbengine-modernization run --rm php composer test`, **993 testů / 1993 assertions**, 10.061 s, 32 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` a `git diff --check` prošly. Nezávislé review i navazující review I18n změny bez nálezů.
- Dokumentace obsahuje nasazovací konfiguraci, požadavek na přepsání XFP a správné sestavení XFF na proxy, oddělení adresních rodin a dopad změny IP na sessions/rate limit. Při nasazení vyčistit page cache; absolutní locale odkazy nově respektují důvěryhodné původní schéma. Žádné změny skutečných produkčních proxy ani deploymentu nebyly provedeny.
- Kontrolní bod zahrnuje kód, testy, dokumentaci i stav plánu. Přesný hash je v git historii; commit a push před dalším větším krokem.
- Další konkrétní krok: SEC-06, inventarizace formulářů a operací autentizovaných cookies; lazy CSRF služba navázaná na session ve stromu Application, ochrana formulářů/AJAX a odstranění odhlašování přes GET. Zachovat veřejné GET bez session zápisu a zabránit sdílení tokenů přes cache.

### SEC-06: Postup a smlouva ochrany

- Výchozí checkpoint 4025119, větev codex/wbengine-4.0, izolovaný worktree /private/tmp/wbengine-modernization. Jediný dodávaný POST formulář je Box\Auth. Dosavadní token se přepisuje při každém renderu, AJAX větev nebývá omezena metodou a GET a=logout mění stav. Vlastní projektové formuláře musí ochranu použít explicitně.
- Session\Csrf bude lazy potomkem Session (getParent); Application::getCsrf pouze deleguje přes existující session. Bez explicitního použití CSRF služby se veřejný GET nemění. Token je HMAC-SHA256 účelu z náhodného 32bajtového session secretu; účel má 1 až 128 bajtů, token je 64 hex znaků. Jediný secret místo neomezené mapy formulářů. Tokeny stejného účelu jsou stabilní pro více panelů, cizí session/účel se odmítají. Ověření nikdy nevytváří secret. Při regeneraci session ID po přihlášení secret invalidovat.
- Služba token/verify/validateRequest podporuje pole token nebo hlavičku X-CSRF-Token. validateRequest přijímá jen POST/PUT/PATCH/DELETE, nikdy query token. Přítomná neplatná hlavička nesmí spadnout na platný POST. Neplatné tvary tokenů se necastují.
- Tokenové odpovědi explicitně private,no-store a PageCache bypass. Nový request-wide bypass fragmentů musí zastavit HIT i ukládání veřejných i user fragmentů, včetně obalů, které token vydají během renderu. Token získat před vstupem do cachovaného renderu; vlastní Smarty output cache nepatří do podporovaného cache mechanismu.
- Box\Auth přejde na účel auth.login; stávající name=token zůstává pro template/AJAX kompatibilitu. GET logout odmítnout 405 bez změny session. POST a=logout vyžaduje auth.logout token, chyba 403; přidat veřejný getLogoutBox s dodávanou POST šablonou. CSRF ověřit před CAPTCHA a autentizací, AJAX jen POST.
- Rozhodnutí pro cookie API login/refresh/logout: povinné POST, application/json a X-WBengine-CSRF: 1. Nejde o synchronizer token, ale o vynucení nesimple požadavku a preflight/CORS hranice. Povolit tuto hlavičku v existující CORS konfiguraci; operátor smí povolit jen důvěryhodné originy pro credentialed požadavky. Refresh zůstává bez PHP session. Ostatní API mutace s explicitním ověřeným bearer tokenem nepřebírají session CSRF. Zdroj: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html.
- Akceptace: RED/GREEN účely, cizí session, typy, metody, více panelů, opakované vydání bez zápisu, odmítnutý GET logout bez změny identity, platné POST logout, login/AJAX před autentizací, tokeny mimo oba cache scope a skutečné HTTP no-store; API odmítne simple požadavek před změnou DB/cookie. Kompletní testy, lint, nezávislé review; pak commit a push kódu i plánu.

- SEC-06 TDD průběh: 25 nových unit scénářů nejprve selhalo na chybějící službě; po implementaci 25/70 assertions prošlo. HTTP RED prokázal změnu tokenu mezi panely a GET logout s 302 místo 405. Po propojení služby 3 HTTP scénáře prošly. API RED prokázal chybějící preflight hranici; nová kontrola ověřuje odmítnutí všech tří cookie operací bez DB/cookie/session vedlejších efektů.
- Lifecycle RED prokázal zachování tokenu po regeneraci ID; reset doplněn do Session::regenerateId. Další RED prokázal, že po destroy zůstával secret v interních datech Session v témže požadavku; discardPendingWrites jej nyní odstraňuje přímo, aniž by obnovil dirty stav nebo zápis zničené session.
- Nezávislé review bez konkrétních bezpečnostních/regresních nálezů, cílené unit ověření reviewerem 57/122 assertions. Doplňující HTTP scénáře pokrývají AJAX GET 405, platný AJAX login s X-CSRF-Token a cizí session. CORS politika je provozní důvěryhodnost originů; tato fáze nezavádí browserovou end-to-end automatizaci.

### 2026-10-25: SEC-06 dokončen

- Session\Csrf je lazy potomek Session s getParent; Application deleguje na stejnou instanci. Stabilní purpose tokeny, ověření bez vytvoření secretu, pole token i X-CSRF-Token, pouze unsafe metody pro validateRequest. Root secret se invaliduje při regeneraci ID i zničení session včetně aktuálního požadavku.
- Dodávaný login a AJAX používají auth.login, logout auth.logout. GET logout a AJAX GET vracejí 405, neplatné tokeny 403 před CAPTCHA/login. Přidán getLogoutBox a escapovaná POST šablona s českým/anglickým popiskem. Legacy template name=token zůstává, historický session token se již nepřijímá.
- Private,no-store a request-wide bypass page cache i obou native fragment scope; obaly, které vydaly token během renderu, se neukládají. Helper volat před cachovaným renderem. Vlastní aplikace musí chránit své cookie formuláře explicitně a v bootstrapu vyloučit personalizované form routes z dřívější page cache.
- Cookie API login/refresh/logout vyžadují POST, application/json (charset povolen) a X-WBengine-CSRF: 1. HTTP 405/415/403 před DB, rotací tokenu nebo změnou cookies. Header povolen v dosavadní CORS konfiguraci, credentialed originy jsou explicitní provozní hranice důvěry. Refresh dál bez PHP session. Bearer-authorized mutace nadále používají role/token, bez session CSRF.
- Finální ověření: `docker compose -p wbengine-modernization run --rm php composer test`, **1025 testů / 2164 assertions**, 11.005 s, 32 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` a `git diff --check` prošly. Nezávislé review celé fáze i doplňující lifecycle změny bez nálezů.
- HTTP testy: více panelů, cizí session, invalidní/platný AJAX, GET a POST logout s různými účely, no-store i při opakovaném tokenu, veřejný GET jako cache HIT bez session cookie a DB zápisu. Integrační test ověřuje invalidaci/přetrvání po rotaci ID i nemožnost obnovit zničenou session přes flush. Unit test dokládá odmítnutí tvarů a vyloučení již cachovaných user/public fragmentů i nového tokenového obalu.
- README a CHANGELOG obsahují rozhraní, použití v šabloně/AJAX, cache a upgrade API klientů. Tato major změna není nasazena ani releasována. Kontrolní bod obsahuje kód i plán; hash je v historii a push se ověřuje před další fází.
- Další konkrétní krok: PERF-01, diagnostické dbinfo ve Site::initialize/getDBVersion pouze v debug nebo na explicitní žádost. Nejprve změřit SQL dotazy a otevření DB spojení pro zahřátou stránku s datovou cache, poté odstranit pouze zbytečný diagnostický přístup. Následují PERF-02 menu N+1 a PERF-06 jediné dekódování JSON podle závislostí plánu.

### PERF-01: Postup a smlouva diagnostiky

- Výchozí checkpoint dd352b8, větev codex/wbengine-4.0, worktree /private/tmp/wbengine-modernization. Lokální HEAD a upstream shodné. Site::initialize vždy přiřazuje server, dbinfo a phpversion; SiteModel::getDBVersion přes Mysqli::getDbVersion otevírá skutečné spojení bez SQL dotazu. Db::isConnected znamená pouze vytvořený adapter, nikoli potvrzený socket.
- Rozhodnutí: stávající diagnostické proměnné automaticky přiřadit pouze podle Application::isDebugOn. Pro explicitní použití přidat Site::assignDiagnostics vracející Site, s původními názvy proměnných. Zůstává ve stávajícím stromu Site -> model -> Db, bez nové služby, konfigurace nebo balíčku. Chybějící SERVER_SOFTWARE má bezpečný prázdný výsledek.
- Produkční inicializace diagnostické klíče nově nepřipravuje; šablony, které je potřebují, musí požádat explicitně. Diagnostika může otevřít DB spojení a neslouží pro veřejné stránky. Neměnit routing/lifecycle ani cache identitu v tomto balíku.
- Testy: zahřát lookup skutečného SiteModel do file cache, provést initialize v novém PHP procesu s odděleným adapterem a bez menu/sekčního obsahu, měřit rozdíl SQL statistik a mysqlnd connect_success. Porovnat produkci bez diagnostiky, debug, explicitní žádost a chybějící server metadata; ověřit zachování site/metadat. Toto měření izoluje diagnostickou cenu, neslibuje nula spojení pro libovolný obsah nebo custom boxy.
- RED musí prokázat jedno spojení při nule SQL dotazů v původní produkční inicializaci. Po opravě produkce 0/0, debug/explicitní žádost jedno spojení a stejné diagnostické údaje. Pak kompletní sada, lint, nezávislé review, záznam výsledků, commit a push kódu i plánu.

### 2026-10-25: PERF-01 dokončen

- Site::initialize automaticky přiřazuje server/dbinfo/phpversion jen v debug. Explicitní Site::assignDiagnostics zachovává původní názvy, modelovou cestu i přiřazení do Vars a vrací Site. Bez nové runtime třídy nebo závislosti. SERVER_SOFTWARE při chybějící hodnotě vrací prázdný text.
- RED: ze čtyř integračních scénářů tři selhaly, produkční warm lookup otevřel jedno spojení při nule SQL; explicitní metoda chyběla a chybějící server metadata vyvolala warning. Po opravě čtyři scénáře / 36 assertions prošly. Nové PHP procesy nezdědí adapter ani socket z PHPUnit, mysqlnd connect_success ověřuje skutečné spojení odděleně od SQL statistik.
- Výsledek izolované inicializace s warm site-data cache: produkce 0 spojení / 0 SQL, debug a explicitní diagnostika 1 spojení / 0 SQL; zachované site resource a meta title. Menu, sekční obsah a custom boxy jsou z měření vyjmuty, proto se tento výsledek nevydává za univerzální počet spojení kompletního webu ani za měření latence.
- Finální ověření: `docker compose -p wbengine-modernization run --rm php composer test`, **1029 testů / 2200 assertions**, 11.207 s, 32 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` a `git diff --check` prošly. Nezávislé read-only review bez nálezů.
- README a CHANGELOG popisují změnu výchozích template proměnných v major 4.0 a explicitní použití diagnostiky. Kód i tento plán tvoří jeden checkpoint; přesný hash je v git historii. Push a shoda upstreamu se ověřují před další fází.
- Další konkrétní krok: PERF-02, odstranění N+1 lookupů menu. Před změnou ověřit selekci rodičů, skryté stránky, duplicitní URL, strict stránky a DB collation; sdílet jednou vyhodnocenou sadu aktivních ID bez změny zamýšlené navigace. Při vypnuté cache použít dávkový lookup. PERF-06 a další etapy zůstávají otevřené.

### PERF-02: Postup a rozhodnutí o sémantice

- Výchozí checkpoint 2047280, větev codex/wbengine-4.0, izolovaný worktree /private/tmp/wbengine-modernization; HEAD i upstream shodné. Site::isMenuSelected pro každou neaktuální položku znovu vyhodnocuje všechny URL prefixy přes SQL getSiteIdByUrl. Menu::selected má vlastní přímé porovnání aktuální stránky, které se v tomto balíku nemění.
- Rozhodnutí: requestová memoizace výsledných dvojic prefix -> ID a množina existujících aktivních ID v Site. Jedna metoda SiteModel::getSiteIdsByUrls provede všechny indexové lookupy v jednom parametrizovaném SQL statementu, výstup je zarovnaný s původním pořadím včetně opakovaných/chybějících prefixů. Prázdná/neplatná kolekce prefixů bez SQL a bez foreach warningu. Aktuální kladné site ID zůstává aktivní bez lookupu.
- Ruling: dávkový SQL lookup použít také při zapnuté cache. Pouhá sites:visible mapa vynechává skryté rodiče a její PHP lowercase porovnávání nereprodukuje databázovou collation (accent/case/binary pravidla). Úplnou DB sémantiku bez spojení by vyžadoval nový canonical URL kontrakt nebo další kolacionální index. Cena tohoto konzervativního rozhodnutí: při potřebě rodičů nejvýše jeden SQL statement i s warm cache, nikoli slib nuly SQL. Přínos: počet dotazů nezávislý na počtu menu položek a hloubce URL, bez neomezené persistentní cache pro náhodné URL.
- Ruling: duplicitní DB linky deterministicky vybírají nejnižší site_id, místo dosavadního LIMIT 1 bez ORDER BY; stejné pravidlo použije jednopoložkový helper. Viditelnost a strict režim se pro výběr prefixového rodiče nadále nefiltrují. Site ID 0 a záporné hodnoty nejsou aktivní položky, chybějící lookup vrací 0 pouze ve veřejných dvojicích prefixů.
- Zachovat SQL porovnávání link = ? přímo nad sloupcem, bez PHP napodobování collation a bez JOIN mezi dvěma implicitními odlišnými collations. SQL UNION ALL scalar lookupů vrací pozice a minimum ID; parametry zůstávají daty. Strom Site -> SiteModel -> Db, bez dalšího produkčního subsystému.
- Testy nejprve RED: mnoho menu položek a několik prefixů s cache i bez ní, skrytí a strict rodiče, duplicity, neexistující prefix, case/accent citlivost a binary collation, apostrofy/injection vstup, opakování a změna prefixů, prázdné URL a ID0. Akceptace: 0 dotazů pro samotné aktuální ID, nejvýše1 pro celou další selekci nad stejnými prefixy; skutečná shoda s DB lookupem. Poté celá sada, lint, nezávislé review, plán+commit+push.

- Inventura skutečného Url::_setUrlPairs navíc odhalila prefixy bez koncového lomítka (/blog), zatímco standardní DB link je /blog/. Původní helper proto takového rodiče vůbec neoznačí. Ruling: Site zachová původní URL v returned dvojicích, nejprve hledá přesný prefix a teprve při miss variantu s jedním koncovým lomítkem. SiteModel batch zůstává exact-URL kontrakt; obě varianty předá Site v jednom statementu. Přesné odkazy bez lomítka mají přednost, takže existující takto uložené weby fungují dál. Test musí zahrnovat skutečný Url parser, nikoli pouze ručně zkonstruované prefixy s lomítkem.

### 2026-10-25: PERF-02 dokončen

- Site ukládá výsledné dvojice URL/ID a množinu aktivních kladných ID pro aktuální seznam prefixů. Změna prefixů a initialize snapshot invalidují. Aktuální ID bez SQL; ostatní selekce sdílejí jeden batch. SiteModel::getSiteIdsByUrls zachovává pořadí, opakování, miss=0, parametrizaci a skutečnou collation; jedno-URL helper používá stejnou cestu.
- Záměrné major změny: duplicity deterministicky nejnižší site_id; prefix nejprve přesný a až při miss s koncovým lomítkem; nula/záporné ID neoznačují aktivní položku. Skrytí a strict rodiče dál povolení. Menu::selected zůstává přímou selekcí aktuální stránky.
- Ruling platí: i s warm cache nejvýše jeden statement, protože sites:visible není ekvivalent starého SQL. Nulový warm lookup z této fáze neslibujeme; žádný neomezený persistentní cache klíč podle URL. DB indexových vyhledání je stále úměrně prefixům, ale síťových round trips není úměrně položkám menu. Není to benchmark latence celého webu.
- RED: 7 scénářů, 2 errors kvůli chybějícímu batch API, 4 failures a foreach warning. Opakované menu kontroly + veřejný getter původně provedly 244 SQL statementů při cache off i warm cache. Nově v obou případech **1 statement**, stejné očekávané výběry, opravený skutečný parser/slash případ. Cílené testy 7 / 156 assertions prošly.
- Collation test na skutečné DB střídá utf8mb4_unicode_ci a utf8mb4_bin, ověřuje case i accent shody a původní schéma obnovuje ve finally. Další scénáře: hidden/strict, přesný bare link versus slash, nejnižší ID duplicity, apostrof/injection, prázdné prefixy/ID0, změna seznamu. Nezávislé review bez nálezů.
- Finální ověření: `docker compose -p wbengine-modernization run --rm php composer test`, **1036 testů / 2356 assertions**, 11.198 s, 32 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` a `git diff --check` prošly. OOP strom Site -> model -> Db zachován; bez nového runtime subsystému a závislosti.
- README, CHANGELOG a ref-inventory popisují chování i cenu jednoho dotazu s cache. Tento checkpoint zahrnuje kód, testy a plán; přesný hash je v git historii. Push a shoda upstreamu se ověřují před další etapou.
- Další konkrétní krok podle pořadí etapy 3: PERF-06, jedno dekódování JSON. Nejprve popsat dosavadní Json(true/false) pro validní null, skaláry, objekty/pole, prázdné a neplatné tělo, UTF-8 a hloubku; pak odstranit duplicitní json_decode bez nechtěné změny controller kontraktu. Následuje PERF-07 a ARCH-01, ostatní položky zůstávají v registru otevřené.

### PERF-06: Postup a smlouva JSON vstupu

- Výchozí checkpoint dbda94e, větev codex/wbengine-4.0, worktree /private/tmp/wbengine-modernization; HEAD a upstream shodné. Http::Json čte php://input, validuje přes isJson (json_decode) a při decode === true znovu dekóduje asociativně. API auth/user/section/translation routes očekávají tuto smlouvu.
- Rozhodnutí: jeden json_decode na volání Json, chybu vyhodnotit ihned z téhož parsování; běžný decoded vstup přímo asociativně, kompatibilní NUL/object cesta je popsána níže. Žádná změna signatury ani controllerů, žádná výjimka, payload cache nebo nový runtime objekt. isJson zůstává samostatně volatelný validátor s původním chováním.
- Zachovat strict decode === true: pouze boolean true vrací dekódované PHP pole/skalár/null; false a další dosud podporované ne-true hodnoty vracejí původní platný text včetně whitespace. Neplatný JSON vrací null v obou režimech. Platné null má JSON_ERROR_NONE, chyba jiný json_last_error; kód musí přečíst stav ihned před dalším JSON voláním. Zachovat výchozí hloubku512, UTF-8 validaci i číselnou politiku PHP.
- Testy přes skutečný HTTP POST a php://input. Fixture počítá namespaced json_decode, který vždy deleguje native PHP parseru; count se vynuluje pro každý požadavek. Není to produkční injekční rozhraní ani časový benchmark. Nejdříve RED pro validní decoded payload, který původně parsuje2x; ostatní scénáře charakterizují kompatibilitu. raw a isJson nadále validují jedním parsováním.
- Scénáře: null/bool/čísla/řetězce/pole/objekty, whitespace a prázdné tělo, vadná syntaxe/UTF-8/hloubka, opakovaná volání nad stejným tělem a strict režim. Po minimální změně plná sada, lint, nezávislé review, aktualizace plánu, commit a push.

- Review P2 reprodukovalo rozdíl mezi object a associative decode pro klíč začínající escaped NUL: původní validace vracela JSON_ERROR_INVALID_PROPERTY_NAME, prostý assoc decode jej přijal. Regresní testy nejprve potvrdily dvě chyby; výstupní kontrakt proto zachovat, ne rozšířit v lokální optimalizaci.
- Ruling: raw režim parsuje jako původní object validace. Decoded režim pro běžný text používá přímo asociativní decode; pouze při možné sekvenci \u0000 použije jeden object decode a rekurzivní převod výsledku na asociativní pole. Chyba NUL-leading klíče tak zůstává nativní error9. Cena: rychlý substring scan běžného raw textu, u vzácných NUL hodnot navíc PHP převod objektů/polí. Počet průchodů JSON parserem stále přesně1; žádné nové výjimky a žádná dodatečná runtime závislost.

### 2026-10-25: PERF-06 dokončen

- Http::Json provede právě jeden json_decode a vyhodnotí chybu téhož parsování. Jen boolean true vrací decoded pole/skalár/null; raw a legacy ne-true režim vrací původní validní text. isJson zůstává samostatný validátor. Nezměněna rozhraní API controllerů, výchozí hloubka512, UTF-8/číselná pravidla ani json_last_error kontrakt.
- Review P2 opraveno: JSON s NUL-leading klíčem se nadále odmítá s nativním JSON_ERROR_INVALID_PROPERTY_NAME. Pro běžné decoded tělo přímé assoc parsování; konzervativně detekovaný NUL escape vede k jednomu object parsování a následnému převodu. NUL hodnoty, numerické klíče, escaped backslash text a platná hloubka510 mají regresní pokrytí. Cena kompatibility je substring scan a vzácný PHP převod, nikoli druhý průchod parserem.
- TDD: původních23 HTTP scénářů ukázalo15 failures, vždy2parsy místo1 u validního decoded těla a4místo2 při dvou voláních. Po minimální změně23/188 assertions prošlo. Review reprodukce přidala dvě RED selhání NUL klíčů; po opravě rozšířených28/228 assertions prošlo, následně přidán i hluboký platný NUL případ. Finální HTTP corpus má29 scénářů a měří skutečný php://input přes namespaced wrapper nativního parseru pouze v oddělené fixture.
- Finální ověření: `docker compose -p wbengine-modernization run --rm php composer test`, **1065 testů / 2592 assertions**, 12.173 s, 34 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` a `git diff --check` prošly. Nezávislé review nalezlo výše uvedenou kompatibilitní chybu, reprodukovanou a opravenou RED/GREEN testy; žádné další nálezy. Reviewer sám HTTP testy nespustil kvůli svému socket sandboxu, finální ověření provedeno zde v Dockeru.
- README a CHANGELOG popisují návratové hodnoty, okamžité čtení error state, validní null a legacy NUL klíče. Jde o lokální snížení počtu parserových průchodů, nikoli benchmark latence/memory celého webu. Stávající Http facade a namespaces zachovány, žádný nový runtime subsystém či balíček.
- Kód, testy a plán patří do stejného checkpointu; hash je v git historii. Push a shoda upstreamu se ověřují před pokračováním.
- Další konkrétní krok: PERF-07, Renderer::dispatch a globální slučování whitespace. Nejprve regresní corpus pre/textarea/inline JS/CSS a významových textových mezer, potom bezpečný výchozí výstup a migrační pravidla pro dosavadní minimizeHtml. Přenosovou kompresi ponechat webserveru. Po této etapě ARCH-01 odložený lifecycle podle schválených závislostí.

### PERF-07: Postup a smlouva HTML výstupu

- Výchozí checkpoint ef28474, větev codex/wbengine-4.0, worktree /private/tmp/wbengine-modernization; HEAD a upstream shodné. Renderer::dispatch při minimizeHtml=true provádí preg_replace na celém fetch výsledku, jinak display. Regex mění pre/textarea, JS line comments i whitespace ve string literals/atributech.
- Rozhodnutí: dispatch vždy přiřadí aplikační proměnné a vypíše site template přes stávající display adaptér bez globální úpravy obsahu. Zachovat strom Application -> Renderer -> adapter a stávající escapování. Bez HTML parseru, závislosti nebo alternativního nebezpečného legacy režimu.
- minimizeHtml zůstává kompatibilní čitelná konfigurační položka/getter pro externí kód, ale v core dispatch je od major4.0 ignorována; getter označit deprecated v dokumentaci bez runtime warningů. minimizeCss/minimizeJs se v této fázi nemění. Přenosovou kompresi konfiguruje webserver/reverse proxy, ne runtime PHP frameworku.
- Testy nejprve RED přes skutečný Renderer::dispatch s NativeRenderer i SmartyRenderer a s oběma hodnotami flagu; byte-exact výstup předaného důvěryhodného obsahu a escapování běžných proměnných. Pre, textarea, JS line comment a multiline string, CSS string, významové inline mezery/atributy a script JSON. Chybějící minimizeHtml nesmí vyvolat warning.
- Akceptace: oba adaptéry zobrazí stejný beze změny emitovaný obsah při true/false/missing config. Nezavádět produkční kompresi nebo deployment změny. Dokumentace výslovně vyžaduje při upgradu vyčistit dřívější HTML page cache, která mohla obsahovat poškozený výstup. Poté plná sada, lint, nezávislé review, plán+commit+push.

### 2026-10-25: PERF-07 dokončen

- Renderer::dispatch vždy přiřadí aplikační proměnné a vypíše site template stávajícím adapter display. Celoplošné preg_replace odstraněno. OOP strom a výstupní escapování zachovány, žádná nová runtime třída/dependency ani kompresní handler.
- minimizeHtml v core dispatch ignorováno i při true; Config::minimizeHtml getter zachován a označen deprecated bez runtime warningů. Změna zapadá do major4.0. Cena bezpečného výstupu: nekomprimované HTML může být větší; gzip/Brotli patří do deployment konfigurace webserveru/proxy. Žádné skutečné serverové nastavení v této fázi změněno nebylo.
- TDD: 30 scénářů pro NativeRenderer a SmartyRenderer, true/false/missing konfiguraci. RED ukázal14 poškozených výstupů a jeden PHP warning vyvolaný ve dvou missing případech. GREEN **30 testů / 30 assertions**: byte-exact pre/textarea, JS line comment/template literal, CSS string, inline mezery/atributy a JSON script; běžný suffix nadále escapovaný.
- Finální ověření: `docker compose -p wbengine-modernization run --rm php composer test`, **1095 testů / 2622 assertions**, 12.719 s, 34 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` a `git diff --check` prošly. Nezávislé review bez nálezů; reviewer také samostatně spustil cílené30/30 unit ověření.
- README/CHANGELOG/ref-inventory aktualizovány. Při nasazení vyčistit page/external HTML cache, protože dříve uložené poškozené tělo nelze opravit pouhou změnou dispatch. Kompilace šablon a minimizeCss/minimizeJs zůstávají mimo rozsah.
- Tento checkpoint zahrnuje implementaci, testy i plán; hash je v git historii. Push a shoda upstreamu se ověřují před další fází.
- Další konkrétní krok: ARCH-01, inventura constructor/get/run/Router a i18n/page-cache pořadí. Vypracovat konkrétní lifecycle ve stávajícím Application stromu: konfigurace služeb a registrace vlastních rout před CMS/boxy, vlastní ukončená route bez CMS dotazů, page cache až po routách a správných politikách. Nejprve regresní testy pořadí a kompatibility, nepřesouvat pouze Site::initialize bez definovaného řízení požadavku.

### ARCH-01: Konkrétní lifecycle a migrační smlouva

- Výchozí checkpoint c80907d, větev codex/wbengine-4.0, worktree /private/tmp/wbengine-modernization; HEAD a upstream shodné. Constructor dnes připraví konfiguraci a i18n, ale také může exitnout redirectem/cache HIT, inicializuje Site a spustí boxy. Application::get vykonává callback hned a až potom zapisuje matched route. display volá neexistující init; run může vykreslit opakovaně. Statický Router vykonává match ihned a používají jej i API boxy.
- Constructor bude pouze příprava konfigurace, namespaces/path, error handler/timezone a časná i18n normalizace + bootstrap locale proměnné. Redirect se uloží, neodešle. Žádné CMS dotazy, boxy, page-cache HIT ani běžné vykreslení. Gettery Site/Vars mohou vytvořit prázdné lazy objekty bez načtení dat.
- Application::get bude registrovat deferred GET přes novou Application::route(method,path,callable), obě vracejí Application pro chaining. Route podporuje běžné GET/POST/PUT/PATCH/DELETE/HEAD/OPTIONS a deleguje matching stávajícímu Router/Route. Chybný pattern typ nebo callback odmítnout při registraci. Statický Router zůstane immediate kvůli existujícím API modulům uvnitř boxů.
- Ruling: callback se v run volá s (route,app). Boolean true znamená hotovou odpověď (callback již nastavil hlavičky/vypsal tělo), bez CMS a layoutu. Boolean false pokračuje další routou a případně CMS. Ostatní návraty zachovají HTML callback model: nastavit proměnné/central a vykreslit layout. První přijatá route vyhrává. Cena nového explicitního terminal kontraktu: vlastní API callbacky musí vrátit true nebo jako dosud exitnout; samotné echo s null by vedlo k layoutu.
- run: ochrana před duplicitní/reentrantní obsluhou -> deferred vlastní routy -> neobsloužený i18n canonical redirect -> cache gate -> případná CMS inicializace -> 404/410 -> debug -> jediný dispatch. Matched vlastní route a explicitní central obcházejí sdílenou page cache. Odpověď 410 nesmí být přepsána starým 200 HIT ani 404.
- Cache gate porovná Vars s bootstrap snapshotem, takže pre-run/dřívější declined route změny přímo přes Vars také zabrání HIT. Předčasný přístup k rendereru (před cache gate) konzervativně obchází page cache pro jeho přímé assign/config zásahy. Cena: takto přizpůsobený požadavek ztrácí page HIT, dokud aplikace nerozhodne o bezpečné samostatné cache politice.
- display(content) sjednotit s lifecycle, uvnitř route umožnit explicitní jediné vykreslení bez rekurze. Prázdný string i "0" jsou explicitní obsah. central nastavený před run se nemá přepsat CMS obsahem. Debug objekt až po obsluze CMS/route, ne v constructoru.
- Časný i18n boot normalizuje prefix i při pending default-language redirectu; vlastní route tak uvidí kanonickou cestu a může požadavek obsloužit před redirectem. Bez vlastní obsluhy zůstává301 s původním query stringem. Při 404 musí Site inicializace bez resource skončit před sekcemi; prázdné sections musí být platná prázdná kolekce, ne sizeof(null).
- Testy: skutečný constructor + žádné SQL/socket/boxy; deferred callback, priorita/decline/true terminal a generic POST; pre-run Vars/renderer nastavení a CMS box pozorující hodnotu; opakované run/display bez druhého renderu; explicitní0/empty obsah; původní API box; 404/410; i18n route před redirectem; existující page-cache HIT předběhnutý routou či privátní politikou a zachovaný anonymní HIT. Ověřit HTTP i interní stavy, plnou sadu/lint/review; potom plán+commit+push.

- TDD první HTTP corpus po korekci testovacího rewrite vstupu: 15 scénářů,14 RED selhání. Constructor původně4SQL/1spojení/1box, v novém lifecycle0/0/0. Původní display volal neexistující init a run zobrazoval layout vícekrát. První GREEN téměř kompletní: "0" zakrýval default modifier pouze v testovací šabloně, odstraněn z fixture, produkční template gramatika se neměnila.
- Doplněna bezpečná cache gate pro dřívější výstup a status. RED prokázal převzetí starého HIT při declined handleru se403. Ověřeno také standardní MISS/HIT, privátní politika, pre-run Vars/direct renderer assign a legacy API router uvnitř skutečného CMS boxu.
- Nezávislé review: P2 pro spodní output buffer s prázdným horním bufferem a druhé vykreslení při display volaném z CMS boxu. Oba nálezy zařazeny do oprav, protože garance jediné odpovědi a respektování outputu platí pro celý lifecycle. Dva HTTP RED testy chyby reprodukovaly. Oprava: kontrolovat buffer_used všech ob_get_status(true) vrstev a po CMS inicializaci znovu ověřit requestFinished. Bez ukládání/čtení obsahu bufferů a bez dalšího request subsystému.

### 2026-10-25: ARCH-01 dokončen

- Constructor připravuje konfiguraci, cestu/namespace, error handler/timezone a časné i18n. Nespouští CMS, boxy ani page-cache HIT a neukončuje redirectem. I18n normalizuje i kanonický default prefix před uloženým redirectem. Vytvoření lazy Site/Vars je bez načtení resource.
- Deferred Application::get/route (běžné HTTP metody, validační registrace a chaining). Při run první accepted route: true ukončí obsluhu bez layoutu, false pokračuje, ostatní návraty připravují layout proměnnými. Statický Router zůstává immediate a skutečný legacy API box v testu funguje. Nový terminal kontrakt je major migrační pravidlo, ne změna existujícího API controlleru.
- run/display idempotent, včetně přímého display z callbacku a obranného reentrantního display z boxu. Explicitní "0" a prázdný obsah nevyvolají CMS. 404 nenatáhne sdílené boxy; prázdné sections vrací[]; explicitní410 zachová status a vynechá CMS/cache. Debug vzniká při obsluze, ne v constructoru.
- Cache gate následuje vlastní routy a jejich politiku. Změněné Vars oproti bootstrapu, renderer použitý před gate, přijaté routy, předchozí výstup v libovolném bufferu/odeslané hlavičky a nestandardní HTTP status konzervativně vypínají sdílenou cache. Společný PageCache bypass ovlivní i public native fragmenty. Cena: takto přizpůsobené požadavky mohou mít méně HIT; běžná anonymní stránka MISS/HIT stále funguje. Globální cacheable nastavení dávat do Settings, dynamické personalizace explicitně obsluhovat.
- HTTP měření: constructor původně4 SQL / 1 spojení / 1 box, nyní0/0/0. Deferred terminální GET i POST taktéž0/0/0, callback se nespustí při registraci. 18 cílených scénářů / 55 assertions prošlo: locale/pending redirect, route priorita a decline, pre-run Vars/renderer/central, starýHIT versus route/private/status/output, jedno renderování, 404/410, legacy API box a emptysections.
- Review: potvrzený P2 nested-buffer cache konflikt a reentrantní dvojí dispatch z boxu, oba reprodukovány dvěma HTTP RED testy a opraveny v jednom průchodu. Žádné další nálezy. Reviewer nespouštěl sdílené DB testy; všechny runtime kontroly provedeny zde v Dockeru.
- Finální ověření: `docker compose -p wbengine-modernization run --rm php composer test`, **1113 testů / 2677 assertions**, 12.953 s, 34 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` a `git diff --check` prošly. Stávající Application/Site/Section/Box/Renderer/Router namespaces a lazy služby zachovány, žádný další runtime balíček nebo middleware stack.
- README obsahuje nový lifecycle, návratové hodnoty callbacků, povinné run/display, konzervativní cache chování a migraci API bootstrapu před CMS se zachováním boxové varianty. CHANGELOG a inventory aktualizovány. Při nasazení vyčistit staré page cache. Tento checkpoint obsahuje kód/testy/plán; přesný hash v git historii, push ověřit před další fází.
- Další konkrétní krok podle etapy3: PERF-03, délka PHP session locku. Změřit paralelní požadavky jedné session, inventarizovat regenerateId/destroy/CSRF/shutdown flush a přístup k nativnímu session stavu. Navrhnout explicitní bezpečnou release/read-only fázi bez předčasného zavření, ztracených zápisů nebo rozbití přihlášení. Nespouštět refaktor bez regresí a měření. Ostatní položky registru včetně obsahové invalidace SEC-01 zůstávají otevřené.


### 2026-10-25: PERF-03 dokončen

- Inventura: data WBengine nejsou uložena v `$_SESSION`; vlastní model zapisuje odloženě přes shutdown `flush`. Nativní PHP session drží synchronizační zámek. Samotné `session_write_close()` by uvolnilo zámek před WBengine zápisem. Login regeneruje ID a resetuje CSRF, logout ruší DB řádek a další persistentní zápisy.
- Rozhodnutí: explicitní `Session::close(): void`, `isClosed(): bool` a lazy delegace `Application::closeSession(): void`. Žádné automatické zavírání po prvním čtení ani implicitní znovuotevírání. Nový stav zůstává v existujícím Session stromu a namespace, DB operace používají současný Model/Db. Zavření aplikace bez session ji nevytvoří.
- Postup close: ověřit shodu aktivního nativního ID, načíst DB snapshot ještě pod zámkem, uložit odložené změny, uzavřít objekt pro persistentní změny a zavolat nativní close. Opakované close je bez účinku. Chyba INSERT/UPDATE ponechá dirty stav a zámek, vyvolá SessionException s příčinou; opakování uloží změny. Selhání lazy načtení neoznačí snapshot za načtený. Zavření již zničené session nevytvoří řádek.
- Po close: getValue a CSRF verify pracují se snapshotem; existující secret může bez zápisu odvodit token i pro další účel. Vytvoření chybějícího secretu, setter včetně user_id, magic setter, save, unsetKey, regenerateId, destroy a sessionStart vyvolají SessionException. Reference objektů mohou měnit lokální snapshot, ale flush uzavřeného objektu nic neuloží. Snapshot není nová autorizační kontrola po souběžném logoutu. Přihlášení, autorizaci a změny stavu dokončit před close.
- Měření: PHP 8.4.27 / MySQL 8.4, nativní files handler, dvě samostatná CLI PHP se stejnou cookie; první požadavek má po bodu zavření 250 ms simulované práce. Pět čekání druhého procesu při drženém zámku: 246.028, 246.789, 246.779, 247.315, 248.017 ms, medián 246.789 ms. Po časném close: 1.074, 1.210, 1.007, 1.061, 1.168 ms, medián 1.074 ms. Nejde o propustnost webu ani slib pro jiné handlery. Opakovatelný postup: `docker compose -p wbengine-modernization run --rm php php tests/benchmarks/session-lock.php`; používá pouze testovací konfiguraci, vlastní session ID a dočasný adresář, vlastní řádky odhlašuje.
- Souběhový test přes proc_open a IPC ověřuje skutečné blokování druhého procesu před close, průchod po close ještě za běhu prvního procesu, zachování změn obou writerů, ignorování lokálních změn uzavřeného snapshotu a žádné obnovení řádku po logoutu druhého požadavku a pozdějším shutdown prvního. Nejde o sekvenční simulaci requestů ani jednovláknový HTTP server.
- TDD: 12 počátečních RED případů pro chybějící close/closeSession; následně zelené integrační scénáře persistence, čtení nedotčené session po close, změny práv/rotace ID a CSRF, zákaz pozdějších mutací, no-op bez session, žádný INSERT pro nepoužitou/zničenou session a odmítnutí zavření po ručním nativním unlock. Failure coverage zahrnuje INSERT i UPDATE, výjimku i false a následné opakování.
- Nezávislé read-only review bez blokujících nálezů. Upřesněna smlouva CSRF odvozování a lokálně měnitelných referencí; doplněny failure scénáře UPDATE/false. Žádný nový runtime balíček, rozhraní ani automatická změna lifecycle běžných webů.
- Omezení: záruka serializace vyžaduje locking handler. Nativní chyby handleru mají PHP sémantiku; jakmile začne nativní close, objekt zůstává uzavřený i při výjimce. Žádné globální zavírání před renderem, zpožděný login/CSRF zápis by tím přestal fungovat.
- Finální ověření: `docker compose -p wbengine-modernization run --rm php composer test`, **1132 testů / 2765 assertions**, 14.275 s, 34 MB, PHP 8.4.27 / MySQL 8.4. `composer lint`, `git diff --check` a uložený benchmark prošly. Opakované měření uloženým skriptem: mediány 249.531 ms / 1.265 ms. Následuje commit a push této etapy společně s plánem a ověření CI v draft PR #2.
- Další konkrétní krok: posoudit PERF-04/PERF-05 měřením rostoucího počtu stránek a EXPLAIN nad realistickými boxy v MySQL/MariaDB. Zachovat collation, pořadí a shared semantiku, bez prokázaného přínosu nepřidávat indexy/mapy. Poté TPL-01: inventura Smarty konstrukcí, kompatibilitní corpus a migrační smlouva pro vlastní renderer. SEC-01 obsahová invalidace čeká na write API.


### 2026-10-25: PERF-04 změřen, PERF-05 dokončen

- Odpovědnost: Codex. Testovací nástroj `tests/benchmarks/content-lookup.php` používá `SHOW CREATE TABLE` a connection-local TEMPORARY tabulky stínící pouze v daném spojení cms_sites/cms_boxes. Žádné trvalé DDL nebo mazání dat. Dočasná data a vlastní file cache se odstraňují ve finally. První pokus CREATE TEMPORARY ... LIKE se stejným jménem MySQL odmítl jako duplicitní alias; přepis načteného CREATE TABLE zachovává skutečné schéma a indexy bez tohoto problému.
- Postup: PHP 8.4.27, MySQL 8.4.13 a MariaDB 11.4.13; 100/1000/10000 viditelných stránek, jeden lookup na instanci Site, 15 opakování a medián. SQL s existujícím indexem link; memory kontrola bez serializace a skutečný FileCache, cold i warm. Výsledky lookupů pro základní corpus musí být shodné. Map build se měří odděleně, není vydáván za implementovanou URL mapu ani za APCu výsledek.
- PERF-04, MySQL pro 100/1000/10000 stránek: SQL 0.2120/0.0612/0.1468 ms, warm memory scan 0.0113/0.0962/0.9076 ms, warm FileCache 0.2320/1.8920/20.0181 ms, samotný map build 0.0073/0.0848/0.7137 ms. MariaDB pro 10000: SQL 0.1380 ms, memory 0.8522 ms, FileCache 19.7163 ms, map build 0.7115 ms. Cold FileCache pro 10000: MySQL 22.1060 ms a MariaDB 20.2981 ms. Nejde o cold disk, end-to-end ani produkční benchmark.
- Ruling: nepřidávat nyní URL mapu. Jediný lookup na request stále platí O(n) sestavení nebo přenos/dekódování celé cache; indexovaný SQL byl v rozsáhlejším vzorku levnější. Přetrvávající sdílenou mapu a její invalidaci má smysl znovu vyhodnotit až po FIX-02 a měření paměti/serializace. Cena případného chybného odkladu: zůstane lineární scan stávající cache na velkých webech; změna runtime API se neprovádí.
- FIX-02: benchmark prokázal existující neshody SiteModel::_findVisibleSite a SQL. Při uloženém /café/ a unicode_ci najde SQL /cafe/, cache nikoli; u bin najde cache /CAFÉ/, SQL nikoli. Tato fáze porovnávání URL nemění. Před URL mapou a release 4.0 definovat a implementovat shodnou smlouvu DB/cache pro case, diakritiku, Unicode, padding, strict/prefix, hidden, duplicity a prioritu nejvyššího site_id. Ověřit MySQL/MariaDB a alespoň unicode_ci/bin, nulový růst cache pro náhodné URL, invalidaci a skutečné SQL/connection náklady. Nepředpokládat, že mb_strtolower reprodukuje libovolnou collation.
- PERF-05 dataset: 30000 boxů, 100 sekcí po 300, 20 procent shared v každé sekci, třetina site membership 42, ostatní 99; 140 výsledků pro section1/site42. Porovnány žádný sekundární index, (section_id, order) a (section_id, shared, order), původní i společná section podmínka. Každá varianta musí vrátit stejné řádky/pořadí. EXPLAIN je uložen ve výstupu benchmarku. Bez indexu odhad asi 30000 řádků/ALL; s oběma kandidáty 300/ref či range.
- MySQL medián původní/no-index 5.3782 ms, společná/no-index 5.1404 ms, původní/section_order 0.4216 ms, společná/section_order 0.3839 ms, společná/section_shared_order 0.3949 ms. Původní dotaz na MySQL řadí i s section_order; společná podmínka s section_order eliminuje filesort. Kandidát se shared filesort zachovává.
- MariaDB medián původní/no-index 6.6089 ms, společná/no-index 4.4199 ms, původní/section_order 0.3543 ms, společná/section_order 0.3637 ms, společná/section_shared_order 0.4070 ms. S section_order zde filesort nemá ani původní podmínka. Neprezentovat drobné rozdíly jako univerzální zrychlení, hlavní efekt je omezení řádků sekcí.
- Zápisy měřeny samostatně: 5 batchů po 1000 INSERT, cleanup mimo měřený interval. MySQL mediány bez indexu/section_order/section_shared_order 2.4174/2.9838/3.0217 ms, MariaDB 2.1693/2.7339/2.8832 ms. Index není zdarma. Zahřátý box FileCache i memory driver mají 0 SQL v 15 lookupech, cold cache čte DB. Vzorek není důkazem přínosu pro malé tabulky nebo libovolný poměr shared.
- Implementace: Section\Model::getBoxes má společné b.section_id a parametrizovaný JSON_SEARCH/shared OR; SELECT, cache klíče a strom beze změn. Volitelná migrace `migrations/4.0.0/001-boxes-section-order.sql` přidává (section_id, order). Bez runtime DDL, nových balíčků nebo změny JSON schématu. Instalační postup: SHOW INDEX, nepřidávat ekvivalentní index, upravit prefix, použít běžný migrační postup a EXPLAIN; rollback DROP INDEX wb_boxes_section_order pouze pro index přidaný touto migrací. Relační vazbu box/site zatím nezavádět bez prokázané potřeby.
- TDD: integrační RED nejprve chybějící migrace, následně filesort na skutečném dotazu MySQL i s indexem. Po úpravě SQL GREEN, zachované výsledky před/po migraci i po rollbacku. Nezávislé review upozornilo na čištění trvalých testovacích tabulek zděděným DbTestCase a na měření INSERT včetně DELETE; test nyní dědí přímo TestCase a ověřuje jen spojení, INSERT timing cleanup nezahrnuje. Benchmark i nový test manipulují pouze dočasnými tabulkami.
- Finální ověření: kompletní MySQL sada `composer test`, **1133 testů / 2771 assertions**, 14.188 s, 34 MB. MariaDB 11.4 BoxIndexTest/BoxLookupTest **7 testů / 16 assertions**. Oba benchmarky prošly. `composer lint` a `git diff --check` prošly. Následuje společný commit/push a CI draft PR #2.
- Opakování MySQL: `docker compose -p wbengine-modernization run --rm php php tests/benchmarks/content-lookup.php`. MariaDB spustit v samostatném Compose projektu s DB_IMAGE=mariadb:11.4 a stejným PHP image, po měření zastavit pouze jeho DB. Nevyměňovat image běžícího projektu s cizími daty.
- Další konkrétní krok: TPL-01, inventura Smarty konstrukcí v jádře i vzorové aplikaci, identifikace kompatibilních nativních konstrukcí, testovací corpus a migrační smlouva. FIX-02 musí být vyřešen před návratem k URL mapě a před release. SEC-01 obsahová invalidace navazuje na write API; žádný merge/tag/deployment se neprovádí.


### 2026-10-26: TPL-01 dokončen

- Odpovědnost: Codex. Referenční inventura a migrační smlouva uložena v `ref-template-migration.md` podle prefixového systému; zdrojové identity v `tests/fixtures/template-migration/inventory.json`. Framework vychází z b7cf3d4. Vzorová aplikace src/View pouze čtena, čistý commit d22281fc86e9efd63a333ac6668b9261f22777e9; žádné změny tohoto druhého repozitáře.
- Inventura knihovny: 7 `.tpl`, z toho 4 rendererové zdroje (Application/error, Article, Login, Logout) a 3 sprintf šablony výjimek. Vzorová aplikace: 13 zdrojů, již nastaven NativeRenderer. Všechny rendererové zdroje se nativně kompilují; nejde o důkaz vykreslení celého webu. Příklady používají navíc nativní set/t, foreach, objektové metody a i18n/formátovací modifikátory.
- Read-only nástroj `tests/tools/template-inventory.php [application-template-dir]` vypisuje JSON se SHA-256, consumer, compile stavem a heuristickými reviewHints. Pouze volá stávající Compiler::compile, nikdy nevykonává zdroj ani nevytváří kompiláty, nespouští DB/session. Ignoruje symlinky a vendor/node_modules/.git/Compiled/tests. Úspěšná kompilace je jen compiled-only; neznámé Smarty tagy mohou být textem. Hints nejsou lexer, runtime validace ani záruka kompatibility.
- Nový verzovaný corpus: 17 pojmenovaných scénářů / 31 konkrétních výstupů native/Smarty, explicitní očekávané výstupy místo pouhé rovnosti adaptérů. Dalších 5 scénářů vykresluje přímo skutečné frameworkové šablony s automatickým escapováním, obě větve CAPTCHA a zachování důvěryhodného HTML článku. Integrační souběh s API/session/cache dosavadní sady zachován.
- Potvrzené rozdíly: native default nahrazuje i nulu, Smarty ji zachová; truncate native počítá délku před suffixem, Smarty včetně suffixu; native nl2br vydává escapovaný vstup a HTML break, Smarty se zapnutým autoescape escapuje i vložený break. Include a přiřazení vyžadují převod syntaxe. Tag t/set je native-only, přímé Smarty API a pluginy vyžadují aplikační migraci. Změny runtime modifikátorů nejsou součástí TPL-01.
- Rozhodnutí: neopakovat slib úplné Smarty kompatibility, zachovat malý kompilovaný jazyk a stávající Application/Renderer/Adapter/Template strom. README již netvrdí, že libovolné legacy stránky nebo aktuální skeleton jsou automaticky shodné po pouhém přepnutí adaptéru. Šablony jsou důvěryhodný kód vývojáře, protože mohou volat metody přiřazených objektů; Markdown bude samostatný obsah bez této logiky.
- Migrační smlouva zachycuje také no-op config/cache API a ignorované cache_id/compile_id/nocache v NativeRenderer, odmítnutí delimiterů, vyčištění všech cache při nasazení a rollback kompatibilních zdrojů/config. Návrat na Smarty nestačí změnou adaptéru u native-only set/t. Composer a produkční renderery beze změny.
- TDD: RED chybějící inventurní nástroj a RED chybějící corpus; GREEN po dodání implementace/fixtures. Kalibrace očekávaných výstupů odhalila Smarty autoescape u nl2br, zaznamenáno jako vědomý rozdíl, nikoli skrytá úprava produkčního renderu. Izolovaná sada **37 testů / 87 assertions**. Celá MySQL sada **1170 testů / 2858 assertions**, 14.367 s, 34 MB, PHP 8.4.27 / MySQL 8.4. Smarty lokálně 5.8.5. `composer lint` a `git diff --check` prošly.
- Nezávislé read-only review bez důležitých nálezů nebo blokací; ověřena read-only povaha nástroje, nezávislost CI na externím checkoutu a shoda dokumentace s adaptéry. Následuje společný commit/push této etapy včetně plánu a CI draft PR #2. Referenční dokument obsahuje přesné příkazy opakování inventury a corpus testů; CI nepotřebuje sousední checkout.
- Další konkrétní krok: TPL-02, nejprve diagnostika známých nepodporovaných Smarty řídicích tagů v existujícím Compileru (včetně uzavíracích tagů, cesta/řádek), zachovat CSS/JS/literal. Současný assign-as-text charakterizační test změnit na očekávanou výjimku. Potom migrační corpus include/set/modifikátorů a HTTP smoke vzorového webu (login/POST logout, locale, 404, články, fragmenty/page cache); zohlednit jeho starý GET logout odkaz a debug-only diagnostické proměnné. Balíček Smarty odstranit až v TPL-03 po těchto branách. FIX-02 a SEC-01 zůstávají otevřené release podmínky.


### 2026-10-26: TPL-02A, diagnostika kompilátoru dokončena

- Rozsah kontrolního bodu: první část TPL-02, nikoli dokončení celé migrace nebo odstranění Smarty. Výchozí commit 4719bb0. Odpovědnost Codex. Registry ponechává TPL-02 rozpracovaný, další krok je uveden níže.
- Implementace ve stávajícím Wbengine\Template\Compiler: známá nepodporovaná Smarty syntaxe se vrací jako token a zpracovává běžným průchodem. Před výjimkou se započítá předcházející text, takže TemplateException uvádí skutečnou cestu a řádek včetně CRLF, odsazení a native control flow. Bez nového preprocesoru, závislosti, frameworku nebo změny Application/Renderer stromu.
- Konkrétní tvary: assign/append, extends/block, capture, section/sectionelse, config_load, eval/fetch/insert, php/include_php, strip/nocache, ldelim/rdelim, call/forelse, Smarty hlavičky function/for/while a uzavírací tvary. Finite pravidla a omezení jsou v ref-template-migration.md. Function rozpozná pojmenované atributy i při odlišném pořadí a dlouhých mezerách; for vyžaduje Smarty $ proměnnou; while nerozpoznává parentetické hlavičky, aby nekolidoval s JavaScriptem. Není to plná detekce Smarty nebo pluginů.
- Zachována obyčejná CSS pravidla, objektové JS vlastnosti s dvojtečkou, běžné deklarace a cykly včetně $ready, podobně pojmenované identifikátory a neznámé pluginy. Literal a komentáře chrání doslovné příklady. Přesně vyhrazené zápisy jako JS shorthand {capture} musí být uvnitř literal; kompilátor neparsuje kontext HTML/JS.
- Compiler::VERSION zvýšen 3 -> 4. Test vkládá skutečný starý kompilát pod verze-3 identitou a prokazuje, že s compileCheck=false nemůže obejít novou diagnostiku. Fragmentové identity závislé na verzi se oddělí; při deployi nadále vyčistit kompilované/page/fragment/externí HTML cache dle migrační smlouvy.
- Corpus TPL-01 záměrně aktualizován: místo assign-as-text se očekává výjimka. Má stále 17 scénářů / 31 očekávaných výsledků, nyní 30 výstupů a jednu diagnostiku. Inventurní test ověřuje, že assign má failed compile a konkrétní chybovou zprávu. Opakovaná read-only inventura knihovny i 13 externích šablon se shoduje s uloženým snapshotem; vzorový checkout nebyl změněn.
- TDD: počáteční 33 RED (32 unsupported hlaviček/uzavření a dlouhé mezery), další RED starý kompilát, následně RED pořadí atributů function a while true. Vše GREEN po úpravách. Testuje se cesta/řádek, hranice názvu a zachování CSS/JS/literal, nikoli pouze hledání textu ve zdrojovém kódu.
- Review našlo kolizi validního JS function run() {while ($ready) ...}. Vlastní RED regresní test ji potvrdil, detekce while byla zúžena a běžný JS zachován. Ruling: parentetické while hlavičky nezakazovat, protože stejné bajty mohou být platný JavaScript; cena rozhodnutí je, že odpovídající Smarty while(...) může zůstat textem a vyžaduje inventuru/ruční převod. Přesná kolize {capture} zůstává rezervovanou syntaxí s explicitním literal řešením. Druhé review se neopakovalo, fix kryje regrese a finální sada.
- Finální ověření: `docker compose -p wbengine-modernization run --rm php composer test`, **1215 testů / 2975 assertions**, 14.580 s, 34 MB, PHP 8.4.27 / MySQL 8.4. `composer lint` a `git diff --check` prošly; opakovaná inventura se shoduje s původním snapshotem. TPL-02A je připraven jako samostatný verzovaný checkpoint; následuje commit/push s tímto stavem a ověření CI draft PR #2.
- Další konkrétní krok: TPL-02B, uzavřít testovanou smlouvu include/set a modifikátorů pro aplikační migraci, včetně neplatných/chybějících partials, předávání a izolace dat, cest a obnovy bufferů. Potom TPL-02C: samostatný izolovaný checkout vzorové aplikace, převod starého GET logout odkazu a diagnostických proměnných, HTTP smoke menu/login/CSRF/POST logout/404/článků/locale/fragmentů/page HIT/BYPASS. Smarty odstranit až po těchto branách v TPL-03. FIX-02/SEC-01 a ostatní release úkoly zůstávají otevřené.


### 2026-10-26: TPL-02B dokončen, průběžná samostatná etapa

- Ověřena smlouva kořenových/dynamických include cest, dědění lokálních set hodnot, izolace partials i příštího fetch. Stávající sada pokrývá syntaktické chyby, neexistující soubory, traversal/symlink, rekurzi a modifikátory. Významy modifikátorů z migračního corpusu se nemění, nepřidává se úplná Smarty emulace.
- RED testy prokázaly dva skutečné problémy běžných metod otevírajících vlastní buffer: úspěšný render ztratil prefix a nested partial výjimka nechala otevřený buffer. Opraveno společným Engine::capture v původním Template stromu; postup se používá i uvnitř cache callbacků. Hloubka renderu a výstupní buffery se obnovují bez úniku částečného HTML.
- Nezávislé review zachytilo stejný problém v generovaném cache callbacku, kde zůstalo původní ob_get_clean. Vlastní RED cold/warm test prokázal chybějící prefix a následně GREEN ověřuje celý fragment, jedinou invokaci služby a původní úroveň bufferů. Compiler::VERSION 5 invaliduje staré callbacky. FragmentCache cleanup má ochranu proti nekonečné smyčce při neodstranitelném bufferu.
- Omezení: služba je důvěryhodný kód; nemá rušit buffery rendereru ani vytvářet neodstranitelné buffery. Takové vedlejší účinky PHP nedokáže plně obnovit. Změny se drží stávajících Engine/Compiler/FragmentCache namespaces a bez balíčků.
- Ověření: explicitní TemplateEngineTest **170 testů / 265 assertions**. Integrační pracovní sada se souběžně dokončeným FIX-02 **1222 testů / 3142 assertions**, 14.385 s, 36 MB. Použita úplná množina dosud verzovaných testů plus nový SiteLookupCollationTest, aby se nenačítaly rozpracované testy nezávislých Markdown/API workerů. Následuje oddělený commit/push TPL-02B a FIX-02, poté CI přesného vzdáleného stavu.
- Kontinuální práce podle nového pokynu uživatele: mezi fázemi se již nečeká na další souhlas. Pro nezávislé MD-01 a API-01/04 se používají odděleně vlastněné moduly a subagent-driven-development. Root zajišťuje review, společný plán a verzování; pracovní soubory dalších etap se nepřimíchají do tohoto commitu.
- Další krok: TPL-02C, izolovaný checkout vzorové aplikace, úprava lifecycle a POST logout/layout diagnostik, HTTP smoke v samostatném Docker projektu. FIX-02 se samostatně zapisuje hned následujícím kontrolním bodem. Smarty se odstraňuje až po ověření TPL-02C.


### 2026-10-26: FIX-02 dokončen

- Odpovědnost Codex + samostatný worker, nezávislé read-only review bez blokujících nálezů. Odstraněno PHP mb_strtolower porovnávání celé tabulky sites:visible. SiteModel používá původní parametrizovaný indexovaný SQL resolver bez závislosti na cache driveru. Nevzniká cache pro náhodná URL ani paměťový přenos celé tabulky.
- Ruling: správnost přesného DB porovnávání má přednost před dřívějším slibem 0 SQL na zahřáté datové cache. Výběr stránky nyní stojí jeden dotaz/spojení, vrací čerstvé řádky a respektuje konkrétní collation včetně padding a hodnot strict=0/1; úspěšný whole-page HIT a terminal route dál mohou CMS přeskočit. Cena změny je jeden skutečný SQL lookup i při zahřáté datové cache. Překladové/menu/box cache zůstávají a potřebují svou invalidaci.
- TDD RED na MySQL prokázal chybějící unicode_ci accent shodu a chybnou bin case shodu; GREEN pokrývá unicode_ci/bin, složený/dekomponovaný Unicode, trailing spaces, strict/prefix/null/-1/2, hidden, nejvyšší ID duplicity, quote/injection a missing. MySQL 8.4 i MariaDB 11.4 **14 testů / 179 assertions**, vždy právě jeden SQL lookup pro každou variantu driveru. Cache test již nevyžaduje falešnou nulu, ověřuje okamžitou změnu title/visibility a nulové URL cache soubory.
- Diagnostický test upraven věcně: nutný výběr stránky má 1 SQL/1 spojení, debug či explicitní server-version přístup nepřidá další SQL. Diagnostické proměnné nadále chybějí v běžném produkčním renderu. Aktualizován benchmark a README; historické časy staré full-table cache zůstávají výslovně historické.
- Aktuální benchmark MySQL 8.4.13: SQL mediány pro 100/1000/10000 stránek 0.2309/0.1297/0.0873 ms. Zapnutý memory či file driver nyní také měří SQL, nikoli starou cache cestu. Syntetické sestavení mapy mimo fetch tabulky 0.0089/0.0696/0.7497 ms. Žádný slib produkční latence a žádná nová URL mapa.
- Společná ověřená sada dosud verzovaných testů a nového SiteLookupCollationTest: **1222 testů / 3142 assertions**, 14.385 s, 36 MB, PHP 8.4.27/MySQL 8.4; TPL-02B již samostatně commitnut a pushnut jako 06818d7. Lint a git diff --check prošly. Práce dalších workerů Markdown/API není součástí tohoto checkpointu. Následuje samostatný commit/push FIX-02 s tímto plánem a CI přesného commitu.
- Další krok: pokračuje TPL-02C a paralelně MD-01/API-01/04 podle přidělených souborů. SEC-01 obsahová invalidace zůstává otevřená, budoucí URL mapa PERF-04 odložena bez potřeby. Mezi kontrolními body se již nečeká na zprávu uživatele.


### 2026-10-26: MD-01 dokončen

- Vlastní Wbengine\Markdown facade skládá oddělené Parser/InlineParser/readonly Node/HtmlRenderer a sdílený Budget, bez dalšího runtime balíčku. Jde o strom obsahu ve stejných namespaces; žádná spuštěná template/PHP logika z Markdownu. Přímé zdroje a App/DB vazba pokračují v MD-02.
- CommonMark 0.31.2: všech 652 oficiálních příkladů má přesný syntaktický test přes pouze testovou projekci AST i přesný produkční bezpečný výstup. 76 explicitních výsledků v safe-deviations.json: 72 escapované HTML a 4 zakázaná URL schémata. 12 oficiálních příkladů GFM tabulek/task/strikethrough také přesně prochází. zdroje/autoři/licence/SHA jsou v sources.json, veřejná smlouva v ref-markdown.md. Nejde o tvrzení shody pro všechny možné řetězce.
- Limity: 1MiB vstup, parser64/renderer128 depth, 8M work, 100k alokací uzlů, 8MiB rozbalený HTML výstup. Překročení LengthException. HTML vždy escapováno, URL http/https/mailto/relative, bez nebezpečného přepínače. Čítače se resetují mezi dokumenty.
- Nezávislé review přineslo konkrétní případy mimo oficiální corpus. RED/GREEN: escaped less-than v angle destination, nepovolená závorka v title, escaped closing label, 999 Unicode znaků versus bajty, invalid UTF8 bez PHP deprecation a amplifikace krátkého referenčního zdroje do obrovského výstupu. Výstupní limit ji zastaví; běžné 1000 odkazy/code spans nezamítá příliš konzervativní suffix-accounting. Další regrese pro hloubku image alt/inline/bloků, počet uzlů a adversariální delimiter reduction.
- Izolované Docker ověření PHP8.4.27 **695 testů / 1826 assertions**. Doplňkový deterministický malformed-input smoke 1000 vstupů bez PHP warnings. Integrační sada všech dosud verzovaných testů + Markdown souborů **1917 testů / 4968 assertions**, 14.607 s, 46 MB; rozpracované API/MD-02 testy byly záměrně oddělené. composer lint a git diff --check prošly. TPL-02B/FIX-02 předchozí checkpoint má všech 5 CI zelených.
- Review fixy ověřeny regresními testy a společnou sadou; další review kolo není podmínkou čekání. Následuje samostatný commit/push MD-01 s tímto plánem a CI přesného vzdáleného commitu. Texy/Smarty závislosti se v tomto checkpointu nemění.
- Další krok: MD-02 worker provádí lazy App službu, .md source dispatch v Engine, explicitní formát článků/překladů. Pozor: skutečný starý Article getter dnes nevolá Texy formatter, může již obsahovat HTML. Zachovat existující bajty jako legacy_html, zdroj pro MD-03 požadovat explicitně (HTML/Texy), neodhadovat syntax. Root pokračuje v samostatném checkoutu vzorové aplikace a API foundation po review opravuje vlastnictví transakcí.
