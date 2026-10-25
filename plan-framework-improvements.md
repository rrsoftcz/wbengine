# Plán zlepšení WBengine

Datum: 2026-10-25

## Účel a stav

Cílové vydání: **4.0.0**, nová hlavní verze po 3.5.0. Příprava na GitHubu bude probíhat v samostatné větvi a draft PR; tag `v4.0.0` a release až po dokončení release gate.

Trvalý společný seznam návrhů z rozboru frameworku a jejich následného doplnění uživatelem. Uživatel chce návrhy realizovat. Tento dokument zachycuje směr, priority a podmínky ověření; implementace byla zahájena v odděleném worktree `/private/tmp/wbengine-modernization`. Konkrétní návrh rozhraní a migrační postup se dopracují před jednotlivými zásahy.

Hlavní cíl: zachovat snadnou tvorbu webů a rychlé zpracování požadavků. Zachovat model Application, Site, Section a Box, malé komponenty, odloženou inicializaci služeb, parametrizované SQL a kompilované šablony. Každá nová abstrakce musí mít konkrétní přínos.

Závazné architektonické pravidlo uživatele: nové funkce PHP jádra musí zachovat stromovou OOP architekturu WBengine, odpovídající namespaces, parent vazby a současné modelové/adapterové vzory. Služby zapojovat přes stávající lazy přístup aplikace, obsah přes Site/Section/Box, databázové operace přes modely a Db, API moduly přes současné namespace a route/controller vzory. Nevytvářet paralelní framework ani volné produkční skripty obcházející strom. Existující statické fasády lze zachovat jako vstupní rozhraní. Samostatná SvelteKit administrace zůstává odděleným API klientem podle schváleného návrhu; PHP konektor respektuje architekturu WBengine.

Priority níže jsou doporučením z rozboru. P1 znamená bezpečnost a správnost, P2 běžný výkon a předvídatelnost, P3 pohodlí vývoje nebo optimalizaci podmíněnou měřením. Stav jednotlivých položek je uveden v registru níže. Při navázání práce vždy nejprve přečíst tento registr a záznam postupu.

## Pracovní registr

Aktuální etapa: CACHE-01 a SEC-02 až SEC-06 dokončeny, následuje PERF-01. SEC-01 zůstává otevřený pro invalidaci budoucích obsahových zápisů a API publish. Odpovědnost rozpracovaných úkolů: Codex. Další etapy navazují podle závislostí. Žádný z nových subsystémů není označen jako hotový bez implementace a testů.

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
- [x] CACHE-01: hotovo (Codex, 2026-10-25). Veřejný/osobní scope, lazy identita, locale, anonymní bypass a ochrana public obalů ověřeny včetně HTTP. Jiné obsahové varianty vyžadují explicitní klíče.
- [ ] PERF-01: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] PERF-02: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] PERF-03: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] PERF-04: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] PERF-05: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] PERF-06: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] PERF-07: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] ARCH-01: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] DX-01: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] DX-02: čeká. Postup a akceptace jsou v odpovídající části níže.
- [ ] DX-03: čeká. Postup a akceptace jsou v odpovídající části níže.

- [x] PLAN-01: hotovo, schválená rozhodnutí a etapový plán zapsány, 2026-10-25.
- [x] BASE-01: hotovo, výchozí sada 802 testů / 1534 assertions v odděleném Docker prostředí, 2026-10-25.
- [ ] TPL-01: čeká, inventura Smarty konstrukcí a kontrola kompatibility.
- [ ] TPL-02: čeká, dokončení NativeRendereru a migrace šablon; závisí na TPL-01 a SEC-03.
- [ ] TPL-03: čeká, volitelná kompatibilní vrstva, odstranění Smarty z jádra; závisí na TPL-02.
- [ ] MD-01: čeká, vlastní CommonMark parser a bezpečný HTML renderer.
- [ ] MD-02: čeká, přímé .md zdroje v layoutu a databázové Markdown texty; závisí na MD-01.
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
