# API v2 WBengine 4.0

Stav 2026-10-26: API-01 až API-04 implementovány, obsahové operace mají transakční audit, ETag a invalidaci cache. Dostupnost obrázků závisí na explicitním bezpečném úložišti, katalog boxů na místní registraci.

## Controller a kontrakt

`Api -> Api\V2 -> V2\Routes/Model/Contract/Problem` používá současný řadič a route/model vzory. Model dědí ModelAbstract, služby vznikají lazy, nejsou přijímány libovolné názvy PHP tříd nebo metod. V1 zůstává registrované samostatně.

- `GET /api/v2` a `/api/v2/capabilities`: veřejná minimální verze API 2.0, capability flags, podporované jazyky webu (`i18n.default`, `i18n.locales`) a stránkovací smlouva. Bez připojení k DB, serverových cest či diagnostických verzí.
- `GET /api/v2/identity`: HTTPS, Bearer integration key a scope `discovery:read`. Vrací ID/název/scopes integrace, nikoli její klíč.
- `GET /api/v2/analytics/overview`: HTTPS, Bearer key se scope `analytics:read`, capability `analytics.read` a migration `006-analytics.sql`. Vrací souhrn podle zadaného období a časové zóny, top stránky/články/boxy a lifetime totals. `period.complete=false` označuje začátek období před prvním zaznamenaným veřejným zobrazením. Denní historii omezuje konfigurace `analytics.retentionDays`; endpoint nepřijímá návštěvnické identifikátory.
- Úspěch: `success: true`, `data`, případně `pagination`. Chyba: `success: false`, `error.code`, `error.message`. Neznámé cesty 404, nepovolené metody 405 s Allow. Interní DB chyby 503 s obecným textem.

V2 je server-to-server rozhraní pro server centrální administrace. Neposkytuje CORS prohlížeči, OPTIONS vrací 405. Webový prohlížeč komunikuje s centrálním serverem, nikoli přímo s integračním klíčem webu. HTTPS za proxy používá již nastavenou politiku důvěryhodných proxy; náhodný forwarded header není důkaz TLS.

Contract umí omezené stránkování (default25, maximum100), JSON object body versus list, omezení velikosti/hloubky a whitelist polí. Samotná existence těchto helperů nepovoluje obsahové endpointy; flagy vyjadřují skutečně implementované operace.

## Integrační identita a instalace

Volitelná SQL migrace `002-api-v2-integrations.sql` vytváří tabulky klíčů a auditu. Provádí ji operátor při explicitním upgradu schématu, nikdy webový request. Bez tabulek může veřejné discovery dál fungovat, autentizované identity selžou uzavřeně s obecnou 503.

Klíče se vydávají a odvolávají na webu přihlášeným místním správcem přes obrazovku „Integrace“ (`Wbengine\Box\Integrations`), případně důvěryhodným lokálním PHP kódem `Api\V2\Model::provision($adminUserId, $name, $scopes)` a `revoke`. Aktuální role `ADMIN` se vždy načte z DB (`ApiUserModel::getAuthorization`), nikdy ze session, JWT nebo formuláře. Veřejný provision endpoint ani izolovaný skript neexistují.

Migrace `migrations/4.0.0/007-api-integration-last-used.sql` (po `002`) přidá do `cms_api_integrations` sloupec `last_used_at BIGINT UNSIGNED NULL`. Je idempotentní (sloupec přidá jen tehdy, když chybí), provádí ji operátor po záloze, nikdy webový request. `Model::authenticate()` aktualizuje `last_used_at` nejvýše jednou za 60 s na klíč jedním podmíněným UPDATE; chyba zápisu (například neaplikovaná migrace) požadavek nikdy neshodí. `Model::list()` vrací jen nesekretní metadata (`integration_id`, `name`, `created_by`, `created_at`, `revoked_at`, `scopes`, `last_used_at`), nikdy klíč ani jeho hash. `Console\Doctor` kontroluje obě migrace (`migration.002`, `migration.007`) bez DDL.

### Obrazovka Integrace

Box dědí z `WbengineBoxAbstract` a aplikace jej zapojí tenkou podtřídou ve svém namespace (například `App\Box\Central\Integrations`) a řádkem v `cms_boxes` u stránky s `acl = 1`. ACL stromu rozlišuje jen přihlášeného uživatele, proto roli `ADMIN` ověřuje sám Box. Odhlášený dostane 401, přihlášený bez role `ADMIN` 403.

- GET jen zobrazuje stav a seznam. Každá změna je POST s CSRF tokenem vázaným na akci (`integrations.issue`, `integrations.revoke`) a s čerstvým potvrzením hesla (`User\PasswordConfirmation`). Heslo se nikam neukládá ani neloguje, ověřuje se konstantním časem proti hashi uživatele ze session a pokusy jsou omezeny (5 na uživatele a 20 na IP za okno přihlašovacího limiteru, poté 429 s `Retry-After`). Selhané potvrzení se zapíše do auditu (`integration.reauth`, `denied`) bez hodnot formuláře.
- Předběžná kontrola (`Api\V2\Preflight`, jen čtení): `apiV2.enabled`, tabulky a sloupec `last_used_at` (stejné požadavky jako Doctor), požadavek přes HTTPS (včetně `i18n.baseUrl`), aktuální role `ADMIN`. Při nesplnění stránka ukáže konkrétní návod a klíč se nevydá. DDL se v HTTP requestu nikdy nespouští.
- Vydání: `Model::provision()` s pevným profilem 17 scope `Box\Integrations::PROFILE_SCOPES` (discovery, čtení a zápis článků, stránek, menu, sekcí, boxů, překladů a obrázků, náhled, publikace). Pevný profil `PROFILE_SCOPES` nikdy neobsahuje `analytics:read`, ani když jej `Model::SCOPES` zná; ten se přidává jen vědomou volbou „Analytika“ níže. Název se validuje (1 až 128 bajtů bez řídicích znaků), doporučený je `WBengine Admin`. Scope se zobrazují lidsky čitelně (cs, en, klíče `wb.integrations.*`).

- Analytika (volitelná): formulář vydání má volbu „Analytika“ (`analytics=1`). Zaškrtnutá přidá k pevnému profilu scope `analytics:read` (`Integrations::scopesFor(true)`, celkem 18; bez volby se vydává přesně 17 scope jako dosud). Server čte parametr jen jako boolean (`1` zaškrtnuto, prázdné nebo `0` ne, jiná hodnota je 400), seznam scope z klienta nikdy nečte. Volba je nabídnutá jen když je analytika použitelná: `Config::getAnalyticsSettings()['enabled']` je `true` (v `Settings.json` `analytics.enabled = true`) a existuje schéma migrace `migrations/4.0.0/006-analytics.sql` (`Doctor::SCHEMA_ANALYTICS`, kontrola `Preflight::analyticsProblems()` přes `MigrationStatus` bez DDL). Jinak je pole zakázané, vedle něj jsou kroky co zapnout a odkaz na dokumentaci, vydání bez analytiky funguje dál, a pokud klient volbu přesto pošle, server odpoví 400 (`error.analytics_unavailable`), nic nevydá a nezapíše. Problémy analytiky (`analytics_disabled`, `analytics_schema`, `analytics_database`) nikdy neblokují vydání klíče. Po vydání je seznam oprávnění sbalený s počtem 17 nebo 18, `analytics:read` se zobrazí jako „Číst přehled návštěvnosti webu“ a v seznamu klíčů má takový klíč štítek „Analytika“. Při rotaci se volba předvyplní podle toho, zda nahrazovaný klíč analytiku měl. Scope klíče se později nemění, přidání či odebrání analytiky znamená nový klíč.
- Surový klíč je jen v těle odpovědi POST s `Cache-Control: private, no-store` a `Referrer-Policy: same-origin` (ne `no-referrer`, ten způsobí `Origin: null` u POST formulářů). PageCache i FragmentCache jsou obejity. Klíč není v URL, logu, auditu, session ani cache. Stránka ukáže adresu webu, doporučený název a klíč s tlačítkem Kopírovat (schránka, bez trvalého úložiště prohlížeče) pro registraci v centrálním Adminu.
- Rotace: vydat nový klíč (volitelně s označením nahrazované integrace), ověřit jej v centrálním Adminu a teprve potom odvolat starý. Box nic neodvolává automaticky. Scope existujícího klíče nelze měnit, jiné scope znamenají nový klíč.
- Odvolání: POST s heslem přes `Model::revoke()`, účinkuje okamžitě. Při chybě auditu se vydání i odvolání vrátí zpět.

Nový klíč má kryptograficky náhodnou část, vrátí se jednou a DB ukládá pouze SHA-256 hash. Každý request ověřuje aktivní řádek, takže revoke účinkuje okamžitě. Scope whitelist je konečný a příliš široká identita sama nevytvoří nepodporovaný endpoint. Rotace znamená nový klíč, aktualizaci centrálního spojení a následné odvolání starého.

Provision/revoke a jejich audit commitnou společně. Metody vyžadují neaktivní připojení: již otevřená transakce je odmítnuta s `nested_transaction`409, nesmí commitnout nebo rollbacknout práci volajícího. Guard používá podporovaný SQL transakční kontrakt, nikoli neexistující veřejný mysqli server_status flag. Testy ověřují BEGIN i autocommit(false) a následný rollback původních dat. Při chybě revocation auditu zůstává klíč aktivní.

Audit rozlišuje integration_id, lokálního administrátora a volitelný `X-WBengine-Actor`. Ten je pouze omezený, nedůvěryhodný atribuční popisek uživatele centrály, nikdy důvod oprávnění. Klíče ani těla požadavků se do auditu nevkládají. Chybějící credentials či scope se auditují; chyba auditu povolenou identitu nevydá. Neúspěšná autentizace je omezena přes existující limiter podle důvěryhodně určené IP před přidáváním auditu. Místní Model::pruneAudit vyžaduje skutečnou ADMIN roli, má omezenou retenci a velikost dávky; její pravidelné spouštění je provozní úloha.

## Ověření a další kroky

Obrazovku Integrace pokrývají `IntegrationsHttpTest` (skutečná session, CSRF, hlavičky, jednorázové zobrazení, hash v DB), `IntegrationsBoxTest` (rollback při selhání auditu, role, preflight, rotace), `PasswordConfirmationTest`, `ApiV2LastUsedTest` (list, throttling `last_used_at`, migrace, Doctor) a `IntegrationsPreflightTest`.

Samostatná Docker sada `ApiV2Test` + `ApiV2IntegrationTest`: 22 testů /126assertions na PHP8.4.27/MySQL. Integrační tests používají connection-local TEMP tabulky, nikoli mazání trvalých uživatelů. Testy zahrnují hlavičky, HTTPS/trusted proxy hranici, revoke/scope, chyby auditu, JSON/stránkování, injekční hodnoty a vlastnictví transakcí. Vzorová aplikace navíc ověřila skutečnou HTTP discovery odpověď a původní 401 API v1.

## Zapojení před CMS

Nastavte `apiV2.enabled` na JSON boolean true. Application po přijatých vlastních routách, před locale přesměrováním, cache a CMS, lazy použije svůj Api controller a jeho V2/Routes. Prefix je přesně /api/v2 nebo /api/v2/, query jej nevytvoří. Není potřeba CMS API stránka; její smazání či přesun konektor neodpojí. Bez opt-in funguje původní CMS API zapojení. Vlastní terminální routy zachovávají prioritu. Discovery bez CMS řádků neotevírá DB/session, nevytváří boxy ani CORS.

Pro obsah aplikujte po záloze SQL002,003,005. Schémata musejí používat InnoDB, runtime neprovádí DDL. Všechny chráněné odpovědi jsou private,no-store. Posílejte přesný silný quoted ETag v If-Match: chybějící428, zastaralý412. Po konfliktu znovu načtěte aktuální stav a slučte změny, neopakujte slepě zápis.

## Obsahové zdroje

Pro articles/pages/menu/sections/boxes: GET kolekce, POST vytvoření, GET detail /{id}, PATCH úprava a DELETE s If-Match. Scope {resource}:read/write. Kolekce page/per_page, výchozí25,max100. Neznámá pole a nesprávné typy se odmítají. Úplný konečný seznam polí je ve Resources.php. Boolean hodnoty jsou čísla0/1.

Box type pochází pouze z discovery katalogu. Důvěryhodný startup registruje např. `BoxRegistry::current()->register('story','Blog','Story',['variant'=>['full','compact']],['central'])`. Výchozí katalog je prázdný. Klient nevybírá PHP třídu, module či metodu, settings jsou registrované enum hodnoty. Změna section.key i obnova znovu validuje všechny připojené boxy vůči namespace. Registraci je třeba udělat před run(). Vykonávaný box čte dekódované hodnoty přes `$this->getSettings()` nebo `$this->getSetting('variant', 'full')`. Staré schéma bez api_settings i neplatný JSON vracejí prázdné nastavení/default. Dekodér nepřijímá objekty s vnořenou logikou a má limity velikosti/hloubky; registr enumů nadále hlídá API. Section načítá interní b.* řádek, aby nový volitelný sloupec nemusel vyžadovat probe SQL ani rozbil staré schéma. Cena je, že budoucí sloupce boxes také vstoupí do interní řádkové cache.

Articles POST vyžaduje title,introtext,bodytext a standardně Markdown. Vytváří prázdnou rezervaci site_id0, skutečný obsah a cílová stránka zůstanou v privátním draftu. PATCH mění draft, živý článek se nemění. Detail má translations a draft:null nebo content/translations/revision_id; kolekce has_draft/draft_title. Změna textu legacy_html vyžaduje explicitní content_format=markdown a náhradu obou textových polí. Nezměněné důvěryhodné staré HTML a metadata lze zachovat. Pole source je nepovinný volný text (max. 255 znaků, bez řídicích znaků \x00 až \x1f a \x7f, jinak invalid_source); prázdná hodnota nebo null je v pořádku. Při zobrazení se stane odkazem jen http(s) URL, místní cesta začínající jedním / nebo kotva #, vše ostatní (včetně javascript: a data:) se vypíše jako escapovaný text.

- GET /articles/{id}/revisions: stránkované neměnné revize.
- POST /articles/{id}/restore s {revision_id}: nová kopie do draftu, articles:write a ETag článku.
- POST /articles/{id}/publish s prázdným JSON objektem: articles:write+publish:write a ETag. Atomicky publikuje obsah/překlady, ověří existující stránku a odmítne druhý článek na stejné stránce.
- GET /articles/{id}/preview?locale=cs: articles:read+preview:read, privátní title/introtext/bodytext, bezpečný Markdown; staré HTML je escapované. Nespouští se PHP ani template kód z obsahu.
- GET /articles/{id}/metrics: views bez ETag. Návštěvy webu proto neblokují editaci či publish.

DELETE článku uchová audit/revize, ale API nemá undelete článku. Před mazáním zálohujte nebo uchovejte revizi v provozním postupu.

GET/PUT/DELETE /translations/{site|menu|article}/{id}/{locale} vyžadují translations:read/write a scope rodiče. PUT/DELETE používá vlastní překladový ETag z GET. Článkové překlady jsou draft, site/menu přímý transakční zápis s historií. Locale např.cs,en,en_US.

## Obnova struktury

GET /{pages|menu|sections|boxes}/{id}/changes vrací stránkované úplné snapshoty a recovery ETag i po smazání. POST stejné cesty s /restore a {change_id} vyžaduje write scope a právě ETag z changes, nikoli běžný detailový ETag. Token zahrnuje aktuální řádek, všechny překlady i poslední událost. Obnova předchozího/počátečního snapshotu umí vrátit smazané ID a překlady, znovu ověří reference a současný katalog boxů, atomicky připojí audit i novou recovery událost. Stale snapshot nesmí přepsat souběžné změny.

## Obrázky

Trvalý absolutní `apiV2.imageDirectory` nebo WBENGINE_IMAGE_DIR musí být mimo DOCUMENT_ROOT. Bez něj discovery hlásí images nedostupné. POST /images má {base64,mime}, nejvýše512KiB PNG/JPEG/WebP, scope images:write. GET kolekce/detail /images/{opaque-id}, GET /content vrací metadata+base64, DELETE vyžaduje ETag a write scope. Metadata mají image_id,mime,size,sha256,created_at,url,public:true.

Soubor se vytváří pod náhodným opaque ID mimo veřejný filesystem, stage/rename je svázáno s transakcí. GET /media/{id} veřejně vydává jen existující metadata+soubor se shodným hashem/MIME, nosniff a pevným jménem. Jde o veřejná média, nikoli tajné přílohy draftu. Kontrola struktury a MIME není plným dekodérem pixelů ani antivirem; nepoužívat jako obecný upload souborů. Externí proxy musí mít odpovídající limit JSON request body.

## Cache a transakční hranice

Zápisy obsahu, publish, obnova, překlady a média vyžadují vlastní transakci a InnoDB, drží potřebné zámky a auditují atomicky. Kontrola po zápisu brání tichému MySQL zkrácení. Až po commitu se invaliduje společná WBengine datová, fragmentová i page cache pomocí Cache::flush. Rollback nevydává falešný úspěch. Externí CDN není automaticky purgovaná, nastavte provozní purge. Přímé externí SQL musí stejnou invalidaci zajistit samo.

Review regresí pokrývá nepovolené legacy HTML zápisy, volatile views ETag, namespace bypass při přejmenování sekce a souběžné překlady při obnově. Obsahová API sada58/331 a lifecycle36/256 prošly. Společná aktuální sada celého jádra bez Smarty/Texy:1981testů/5596assertions,15.250s48MB PHP8.4.27/MySQL8.4.
