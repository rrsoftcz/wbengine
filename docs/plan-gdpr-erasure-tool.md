# Návrh nástroje pro vyřízení žádosti o výmaz

Stav: implementace schválena uživatelem jako další fáze. CLI je první rozhraní. Zásady analytiky schváleny 2026-10-27.
Datum: 2026-10-27.

## Záměr

Přidat do WBengine bezpečný CLI postup, který správci ukáže osobní údaje nalezené v jádře a registrovaných modulech, připraví návrh jejich odstranění nebo anonymizace a provede schválené lokální kroky. Později může stejné aplikační rozhraní používat administrační API. Nástroj nemá sám rozhodovat, zda konkrétní žádost splňuje právní podmínky.

## Zvažované přístupy

1. **CLI nejdřív:** správce provede kontrolu a výslovné potvrzení přímo na serveru. Je to zvolený přístup, protože nepřidává veřejný endpoint a používá existující `bin/wbe` a `Wbengine\\Console`.
2. **Admin API a rozhraní jako první:** pohodlnější vzdálené ovládání, ale vyžaduje další oprávnění, autentizaci, audit a ochranu před chybným či zneužitým požadavkem. Odkládá se do další fáze.

## Architektura

- Koordinátor bude součástí `Wbengine\\Privacy` a bude získávat závislosti přes současný strom aplikace a modelů. CLI vstup zůstane v `Wbengine\\Console`; nebude vznikat paralelní skript mimo framework.
- Jádro poskytne registr obsluh osobních údajů. Obsluhy musí nejprve umět vyhledat záznamy a vrátit přehled zdroje, počtu a navržené akce. Aplikační boxy a moduly budou moci přidat vlastní obsluhu v namespace aplikace.
- První obsluhy mají pokrýt uživatelský účet `cms_users`, relace `cms_sessions`, obnovovací tokeny `cms_refresh_tokens`, soubory limitů přihlášení a osobní identifikátory v API auditu. Historie obsahu, volný text autora a data ve vlastních modelech vyžadují konkrétní pravidla dané aplikace.
- Každá obsluha musí rozlišit odstranění, anonymizaci a ponechání s vysvětleným důvodem. Auditní události se nesmějí bez rozmyslu mazat celé, pokud lze odstranit vazbu na osobu a zachovat nezbytný provozní záznam.

## Uživatelský postup CLI

1. `privacy:inspect` provede pouze čtení a vypíše redigovaný přehled nalezených zdrojů, navržených akcí, neregistrovaných úložišť a externích kroků.
2. Správce ověří totožnost žadatele, oprávněnost žádosti, případné zákonné důvody pro ponechání údajů a správnost rozsahu mimo CLI.
3. `privacy:erase` bude ve výchozím stavu pouze simulovat kroky. Pro zápis bude vyžadovat výslovné potvrzení navázané na konkrétní náhled. Před provedením znovu ověří, zda se cílové záznamy od náhledu nezměnily.
4. Výsledek uvede úspěšné, přeskočené a chybné obsluhy bez vypisování e-mailu, tokenů nebo obsahu záznamů. Opakování po částečném selhání musí být bezpečné.

První verze používá `--user-id` a hash `--confirm` získaný z redigovaného náhledu. Hash není osobní údaj, ale slouží jako výslovné potvrzení; CLI jej přijímá pouze přes argument procesu. Nepoužívej jej ve sdílených hostech ani automatizovaných shell logách.

## Rozsah a omezení

- Místní databázi a soubory, které spravuje aplikace, lze obsloužit registrovanými handlery. Výmaz nesmí smazat údaje jiných osob ani obsah, který nemá být odstraněn.
- Souhrnné analytické metriky bez vazby na konkrétní osobu, například celoživotní počet zobrazení článku nebo stránky, nejsou cílem výmazu uživatelského účtu. Návrh vlastní analytiky nesmí vytvářet identifikátory, podle kterých by šlo tyto agregáty zpětně přiřadit návštěvníkovi. Zachování agregátů není právním posouzením konkrétní žádosti.
- Cílem vlastní analytiky je měřit souhrnná zobrazení a trendy, nikoli identifikovat jednotlivé lidi. IP adresy, analytické cookies, uživatelské identifikátory ani individuální návštěvní profily se nemají ukládat. Celoživotní součty zůstanou zachované; případné dočasné technické údaje budou mít samostatně stanovenou nezbytnou dobu uchování.
- Počet zobrazení není počet unikátních návštěvníků. Unikátní návštěvníky bez identifikátoru nelze přesně deduplikovat; případný odhad musí být výslovně označen jako nepřesný.
- GA4 je samostatná služba. WBengine nyní neposílá identifikátor přihlášeného uživatele do Google Analytics ani neuchovává vazbu na GA `clientId`. Žádost o výmaz v GA4 proto nelze automaticky sestavit jen z e-mailu v `cms_users`. První verze tento krok vykáže jako externí úkon. Případná integrace Admin API bude samostatná volitelná obsluha a bude vyžadovat identifikátor rozpoznatelný Googlem i samostatné přihlašovací údaje.
- VPS přístupové a chybové logy, logy databáze a zálohy jsou mimo běh aplikace. První verze je nesmaže; vypíše jejich ověření podle provozní konfigurace. Postup obnovy zálohy musí zabránit opětovnému zavedení již odstraněných údajů nebo zajistit opakování výmazu.
- Výchozí nastavení retence GA4 není známo. Dlouhá historie souhrnných reportů sama o sobě nedokládá, jak dlouho Google uchovává uživatelská a událostní data. Retence GA4 a žádost o výmaz zůstávají samostatnou provozní odpovědností, dokud nebude schválen a ověřen konektor.
- Nástroj nebude v první fázi dostupný přes HTTP ani nebude mazat při běžném odhlášení, deaktivaci účtu nebo API požadavku na smazání uživatele.

## Podmínky přijetí

- Náhled neprovádí zápisy a ukazuje všechny registrované obsluhy i mezery v pokrytí.
- Provedení vyžaduje kontrolu správce, potvrzení konkrétního náhledu a kontrolu změn od jeho vytvoření.
- Integrační ověření dokládá odstranění nebo anonymizaci uživatele, všech jeho relací a obnovovacích tokenů bez zásahu do jiného účtu.
- Chyba jedné obsluhy nezpůsobí tiché tvrzení, že byl dokončen úplný výmaz. Výsledek umožní bezpečné pokračování bez opakovaného poškození.
- Reporty a logy nástroje neobsahují nepotřebné osobní údaje. Pokrytí vlastních modelů a externích systémů je výslovně uvedeno.
- CLI návod a aplikační dokumentace vysvětlí ověření žadatele, zálohy, externí úložiště a limity výmazu.

## Další krok

Implementační plán je v této dokumentaci rozepsán do čtyř úloh níže. Práce běží v samostatné větvi `codex/wbengine-privacy-erasure`; produkční data ani VPS se v této fázi nemění.

## Provedení implementace

Stav: implementace schválena uživatelem jako další fáze. CLI je první rozhraní. Implementace musí navazovat na současnou `Wbengine\Console` a strom namespace frameworku.

### Rozhodnutí pro první verzi

- Cílový záznam se zadává povinným kladným `--user-id`, nikoli e-mailem v argumentech procesu nebo historii shellu. `privacy:inspect` nezobrazuje jméno, e-mail, IP ani obsah záznamu.
- Náhled vrací neprůhledný SHA-256 potvrzovací otisk výčtu standardních cílových záznamů. `privacy:erase` vyžaduje tentýž otisk a před zápisem znovu sestaví plán. Jakákoli změna cílové množiny výmaz odmítne.
- Poslední aktivní účet s rolí `admin` nelze odstranit, dokud správce nevytvoří náhradní účet. Příkaz nesmí způsobit ztrátu posledního administrátorského přístupu.
- Standardní databázový výmaz v jedné transakci smaže `cms_users`, `cms_sessions` a `cms_refresh_tokens`; v `cms_api_audit` odstraní vazbu přes `local_user_id` a odstraní přesnou shodu uživatelského e-mailu nebo jména z `central_actor`. V `cms_api_integrations.created_by` nahradí ID hodnotou `0`, protože schéma vyžaduje hodnotu a číselný identifikátor bez účtu není přímý identifikátor. Souhrnné metriky se nemění.
- Login limiter smaže pouze soubory odvozené z e-mailu/jména a známých IP uživatele. Chyba mazání souboru zablokuje databázové odstranění a dovolí bezpečné opakování. Ostatní přihlašovací a IP limity zůstávají nedotčené.
- Vlastní tabulky, CMS články, VPS/proxy/DB logy, zálohy a Google Analytics jsou mimo automatický rozsah. Inspekce je uvede jako neprohledané nebo externí zdroje. Aplikační modul může později registrovat vlastní handler podle veřejného rozhraní.
- Opakované spuštění po dokončeném odstranění je bezpečné a oznámí, že účet ani cílové záznamy již nejsou nalezeny.

### Task 1: Náhled a registrované handlery (hotovo)

Rozhraní: `Wbengine\Privacy\HandlerInterface::name(): string` a
`inspect(int $userId): array`; každé zjištění nese redigované `actions`,
neveřejný `fingerprint` a `blocked`. `HandlerRegistry::inspect()` skládá
zjištění unikátních handlerů. `Erasure::inspect(int $userId): array` vrací
redigovaný souhrn a potvrzovací otisk; vnitřní fingerprinty nikdy nevrací.

1. Přidat integrační schéma s cílovým uživatelem, relacemi, refresh tokeny, API audit řádky, integračními vazbami a druhým nesouvisejícím uživatelem.
2. Spustit nový test `PrivacyErasureTest` a ověřit očekávané selhání při chybějícím koordinátoru.
3. V `Wbengine\Privacy` přidat typovaný kontrakt handlerů, registr, redigovaný náhled a výpočet potvrzovacího otisku. Závislosti předávat konstruktorům, nevytvářet paralelní framework.
4. Implementovat schématicky bezpečné standardní SQL handlery pro účet a API audit. Volitelné tabulky/kolonky detekovat bez chybového výpisu, SQL vždy parametrizovat.
5. Integrační test ověří počty a typy akcí, neodhalení PII, ochranu druhého uživatele a změnu otisku po změně cílových řádků.

### Task 2: Bezpečné provedení (hotovo)

1. Přidat regresní testy pro chybějící či chybný otisk, změněné cílové záznamy, posledního administrátora, rollback při SQL chybě a opakované spuštění.
2. Spustit testy před implementací a zaznamenat očekávané selhání.
3. Implementovat `Wbengine\Privacy\Erasure` s kontrolou otisku bezprostředně před zápisem a vlastnictvím databázové transakce pro každý standardní databázový handler. Změny ve filesystem limiteru nelze zařadit do MySQL transakce: pokud po jejich vymazání selže SQL, účet zůstane nedotčen a CLI dovolí bezpečné opakování.
4. Integrační test prokáže odstranění/anonymizaci pouze dat cílového účtu, zachování druhého uživatele a bezezměnné analytické součty.
5. Ověřit rollback, pokud handler selže, a bezpečné pokračování po opakovaném běhu.

### Task 3: CLI a krátkodobé limiter soubory (hotovo)

1. Přidat testy pro validaci `privacy:inspect --config=... --user-id=...` a `privacy:erase --config=... --user-id=... --confirm=...`, sanitizované JSON/text výstupy a návratové kódy.
2. Spustit CLI testy před přidáním příkazů.
3. Přidat příkazy do `Wbengine\Console\Application` a resolver zdrojových hodnot držet pouze v paměti.
4. Smazat pouze `.rl` cesty deterministicky odvozené z uživatelských jmen/e-mailů a známých IP. `Limiter::clear()` maže přesný hashovaný název, symlink neprochází, chybějící soubor považuje za hotovo a hlásí selhání unlinku.
5. Ověřit, že logy, výjimky a JSON neobsahují PII; při chybě se nevypíše uživatelské pole ani SQL.

### Task 4: Dokumentace a testovací brána (hotovo)

1. Doplnit README/CLI referenci o přípravu žádosti, kontrolu náhledu, potvrzení otisku, omezení, zálohy a externí systémy.
2. Celý PHPUnit suite po finálním concurrency fixu prošel v PHP 8.4.27/MySQL 8.4: 2014 testů, 5782 assertions. `composer lint`, `git diff --check`, cílený Docker privacy test (5 testů/36 assertions), administrační npm testy (43/43), Svelte check (0 chyb/varování) a build prošly.
3. README a CLI návod doplněny. Všechna dokumentační Markdown je v `docs/`, root obsahuje pouze `README.md`. Ověřeny lokální relativní odkazy.

### Akceptační brána

Funkce je připravená k uživatelskému testu, když `privacy:inspect` nic nezapisuje, `privacy:erase` odmítá neaktuální nebo chybné potvrzení, cílené odstranění zachová druhý účet i všechny analytické součty, poslední administrátor je chráněn, výstupy neobsahují PII a všechny příkazy i testy projdou. Živý výmaz reálného účtu ani vypnutí GA4 nejsou součástí testovací přípravy.

## Stav implementace

- `Wbengine\Privacy\HandlerInterface`, registr, databázový handler a koordinátor poskytují redigovaný náhled a výmaz. Náhled vnitřně otiskuje identitu a všechny cílové databázové řádky, ale nikde nevrací jejich hodnoty.
- Databázový handler ověřuje InnoDB, vlastní transakci, zamyká cílové řádky, znovu porovná otisk a anonymizuje API `central_actor`. Ochrana posledního administrátora je znovu kontrolována v transakci. Případná SQL chyba vrací celý databázový výmaz zpět.
- Integrace a refresh/sessions jsou mazány nebo odpojeny podle schématu; API audit zachová událost a odstraní lokální vazbu a přesné osobní štítky. Celoživotní a denní analytické součty se nemění.
- CLI příkazy jsou `privacy:inspect` a `privacy:erase`; oba potřebují `--config` a `--user-id`. Výmaz navíc potřebuje přesný 64znakový hash z aktuální inspekce.
- Přihlášení limiteru odstraňuje jen účetní a account/IP pair čítače, které lze záznamy uživatele doložit. Agregované IP-only čítače zůstávají, aby výmaz jednoho účtu nevymazal ochranu pro jiné osoby.
- Ověření v izolovaném Dockeru: celý suite `2014 tests, 5782 assertions` a `composer lint` prošly po implementaci. Dokumentační přesuny následně prošly kontrolou odkazů a `git diff --check`.
- Vlastní aplikační tabulky a soubory, volný text autora, web/server/DB logy, zálohy, Google Analytics a právní posouzení zůstávají ručními kroky.

### Testovací stav k 2026-10-28

- Implementace privacy CLI a testy jsou publikované v GitHub větvi `codex/wbengine-privacy-erasure`, commit `d504e20`; nezávislý reviewer nenašel regresi. Push byl potvrzen odpovědí GitHubu. Pozdější opakované DNS ověření už selhalo, proto stav větve vychází z úspěšné push odpovědi.
- Aktualizace analytického a privacy stavu je v commitu `172e4a5`; následný protokol pilotu je v `a236ab6`. Draft [PR #4](https://github.com/rrsoftcz/wbengine/pull/4) zůstává otevřený a nesloučený. GitHub CI run `40600632549` prošel všech 7 jobů na PHP 8.3–8.5/MySQL 8.4, PHP 8.4/MariaDB 10.11 a 11.4 a volitelných Smarty/Texy jobech.
- Lokální dvouwebový HTTPS API pilot byl znovu spuštěn z této větve: TLS/identity probe 3/3, skutečný Svelte Admin scénář 1/1 za 14,4 s, analytika, granty, výpadek, obnovení, revokace i oddělení instalací prošly. Záloha/obnova shodná pro 20 tabulek a 158 řádků. Pilotní kontejnery, síť, volume a vygenerovaná tajemství byly po testu odstraněny.
- WBengine Admin dokumentace byla přesunuta do `docs/` a commit `92db050` pushnut do `main`; kořen obsahuje pouze README. Ověřeno `npm test` 43/43, `npm run check` bez chyb/varování, produkční build, MySQL 5/5 a HTTP/MFA 1/1. GitHub CI run `40600470237` pro tento commit prošel.
- Nový VPS: veřejný `capabilities` endpoint potvrzuje analytics disabled. Po potvrzení přesné Docker brány `172.18.0.1` a Nginx hlaviček byla do `Settings.json` přidána výhradně tato trusted proxy `/32` s hlavičkami `x-forwarded-for` a `x-forwarded-proto`. Web kontejner byl restartován.
- Lokální WBengine Admin je připravený na `https://localhost:13443`, používá lokální Docker MySQL a samostatný jednorázový testovací účet. Do lokálního admin vaultu byl přes bezpečný pipe uložen HTTPS klíč s pouze čtecími scopes (`discovery:read`, obsah/preview read a `analytics:read`); žádný write scope nebyl vydán. Pinned HTTPS klient ověřil identity a přečetl kolekci článků. API dále hlásí analytics disabled, sběr nebyl zapnut a GA4 zůstává aktivní.
- Původní VPS `docker.rrsoft.cz` byl dříve prověřen read-only přes SSH jako `roza`: projekty jsou v `/home/roza/docker`. Při závěrečném opakování zde DNS překlad hostitele selhal, proto je nutné opakovat dostupnostní kontrolu. Nebyly měněny jeho repozitáře ani kontejnery.
- Před uživatelským testováním zůstává první přijetí lokální jednorázové pozvánky a přihlášení s MFA. Pozvánka je soubor mimo Git s právy `0600`; odkaz a přesný postup budou uvedeny v dokončovací zprávě.
