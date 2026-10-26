# API v2 WBengine 4.0

Stav 2026-10-26: API-01 až API-04 implementovány, obsahové operace mají transakční audit, ETag a invalidaci cache. Dostupnost obrázků závisí na explicitním bezpečném úložišti, katalog boxů na místní registraci.

## Controller a kontrakt

`Api -> Api\V2 -> V2\Routes/Model/Contract/Problem` používá současný řadič a route/model vzory. Model dědí ModelAbstract, služby vznikají lazy, nejsou přijímány libovolné názvy PHP tříd nebo metod. V1 zůstává registrované samostatně.

- `GET /api/v2` a `/api/v2/capabilities`: veřejná minimální verze API 2.0, capability flags a stránkovací smlouva. Bez připojení k DB, serverových cest či diagnostických verzí.
- `GET /api/v2/identity`: HTTPS, Bearer integration key a scope `discovery:read`. Vrací ID/název/scopes integrace, nikoli její klíč.
- Úspěch: `success: true`, `data`, případně `pagination`. Chyba: `success: false`, `error.code`, `error.message`. Neznámé cesty 404, nepovolené metody 405 s Allow. Interní DB chyby 503 s obecným textem.

V2 je server-to-server rozhraní pro server centrální administrace. Neposkytuje CORS prohlížeči, OPTIONS vrací 405. Webový prohlížeč komunikuje s centrálním serverem, nikoli přímo s integračním klíčem webu. HTTPS za proxy používá již nastavenou politiku důvěryhodných proxy; náhodný forwarded header není důkaz TLS.

Contract umí omezené stránkování (default25, maximum100), JSON object body versus list, omezení velikosti/hloubky a whitelist polí. Samotná existence těchto helperů nepovoluje obsahové endpointy; flagy vyjadřují skutečně implementované operace.

## Integrační identita a instalace

Volitelná SQL migrace `002-api-v2-integrations.sql` vytváří tabulky klíčů a auditu. Provádí ji operátor při explicitním upgradu schématu, nikdy webový request. Bez tabulek může veřejné discovery dál fungovat, autentizované identity selžou uzavřeně s obecnou 503.

V tomto kontrolním bodu se klíče vydávají pouze důvěryhodným lokálním PHP rozhraním `Api\V2\Model::provision($adminUserId, $name, $scopes)` a odvolávají přes `revoke`. Aktuální role správce se načte z DB. Volající je lokální správní kód, ne ID přijímané ze vzdáleného požadavku. Veřejný provision endpoint neexistuje; provisioning je důvěryhodný lokální kód.

Nový klíč má kryptograficky náhodnou část, vrátí se jednou a DB ukládá pouze SHA-256 hash. Každý request ověřuje aktivní řádek, takže revoke účinkuje okamžitě. Scope whitelist je konečný a příliš široká identita sama nevytvoří nepodporovaný endpoint. Rotace znamená nový klíč, aktualizaci centrálního spojení a následné odvolání starého.

Provision/revoke a jejich audit commitnou společně. Metody vyžadují neaktivní připojení: již otevřená transakce je odmítnuta s `nested_transaction`409, nesmí commitnout nebo rollbacknout práci volajícího. Guard používá podporovaný SQL transakční kontrakt, nikoli neexistující veřejný mysqli server_status flag. Testy ověřují BEGIN i autocommit(false) a následný rollback původních dat. Při chybě revocation auditu zůstává klíč aktivní.

Audit rozlišuje integration_id, lokálního administrátora a volitelný `X-WBengine-Actor`. Ten je pouze omezený, nedůvěryhodný atribuční popisek uživatele centrály, nikdy důvod oprávnění. Klíče ani těla požadavků se do auditu nevkládají. Chybějící credentials či scope se auditují; chyba auditu povolenou identitu nevydá. Neúspěšná autentizace je omezena přes existující limiter podle důvěryhodně určené IP před přidáváním auditu. Místní Model::pruneAudit vyžaduje skutečnou ADMIN roli, má omezenou retenci a velikost dávky; její pravidelné spouštění je provozní úloha.

## Ověření a další kroky

Samostatná Docker sada `ApiV2Test` + `ApiV2IntegrationTest`: 22 testů /126assertions na PHP8.4.27/MySQL. Integrační tests používají connection-local TEMP tabulky, nikoli mazání trvalých uživatelů. Testy zahrnují hlavičky, HTTPS/trusted proxy hranici, revoke/scope, chyby auditu, JSON/stránkování, injekční hodnoty a vlastnictví transakcí. Vzorová aplikace navíc ověřila skutečnou HTTP discovery odpověď a původní 401 API v1.

## Zapojení před CMS

Nastavte `apiV2.enabled` na JSON boolean true. Application po přijatých vlastních routách, před locale přesměrováním, cache a CMS, lazy použije svůj Api controller a jeho V2/Routes. Prefix je přesně /api/v2 nebo /api/v2/, query jej nevytvoří. Není potřeba CMS API stránka; její smazání či přesun konektor neodpojí. Bez opt-in funguje původní CMS API zapojení. Vlastní terminální routy zachovávají prioritu. Discovery bez CMS řádků neotevírá DB/session, nevytváří boxy ani CORS.

Pro obsah aplikujte po záloze SQL002,003,005. Schémata musejí používat InnoDB, runtime neprovádí DDL. Všechny chráněné odpovědi jsou private,no-store. Posílejte přesný silný quoted ETag v If-Match: chybějící428, zastaralý412. Po konfliktu znovu načtěte aktuální stav a slučte změny, neopakujte slepě zápis.

## Obsahové zdroje

Pro articles/pages/menu/sections/boxes: GET kolekce, POST vytvoření, GET detail /{id}, PATCH úprava a DELETE s If-Match. Scope {resource}:read/write. Kolekce page/per_page, výchozí25,max100. Neznámá pole a nesprávné typy se odmítají. Úplný konečný seznam polí je ve Resources.php. Boolean hodnoty jsou čísla0/1.

Box type pochází pouze z discovery katalogu. Důvěryhodný startup registruje např. `BoxRegistry::current()->register('story','Blog','Story',['variant'=>['full','compact']],['central'])`. Výchozí katalog je prázdný. Klient nevybírá PHP třídu, module či metodu, settings jsou registrované enum hodnoty. Změna section.key i obnova znovu validuje všechny připojené boxy vůči namespace. Registraci je třeba udělat před run(). Vykonávaný box čte dekódované hodnoty přes `$this->getSettings()` nebo `$this->getSetting('variant', 'full')`. Staré schéma bez api_settings i neplatný JSON vracejí prázdné nastavení/default. Dekodér nepřijímá objekty s vnořenou logikou a má limity velikosti/hloubky; registr enumů nadále hlídá API. Section načítá interní b.* řádek, aby nový volitelný sloupec nemusel vyžadovat probe SQL ani rozbil staré schéma. Cena je, že budoucí sloupce boxes také vstoupí do interní řádkové cache.

Articles POST vyžaduje title,introtext,bodytext a standardně Markdown. Vytváří prázdnou rezervaci site_id0, skutečný obsah a cílová stránka zůstanou v privátním draftu. PATCH mění draft, živý článek se nemění. Detail má translations a draft:null nebo content/translations/revision_id; kolekce has_draft/draft_title. Změna textu legacy_html vyžaduje explicitní content_format=markdown a náhradu obou textových polí. Nezměněné důvěryhodné staré HTML a metadata lze zachovat. Source URL mají omezená bezpečná schémata.

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
