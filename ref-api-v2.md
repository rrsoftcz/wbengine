# API v2 WBengine 4.0

Stav 2026-10-26: API-01 dokončen, základ API-04 klíčů/scopes/auditu připraven. CRUD, revize, publish, obrázky a invalidace obsahu ještě navazují; capability discovery je do té doby výslovně nevydává za dostupné.

## Controller a kontrakt

`Api -> Api\V2 -> V2\Routes/Model/Contract/Problem` používá současný řadič a route/model vzory. Model dědí ModelAbstract, služby vznikají lazy, nejsou přijímány libovolné názvy PHP tříd nebo metod. V1 zůstává registrované samostatně.

- `GET /api/v2` a `/api/v2/capabilities`: veřejná minimální verze API 2.0, capability flags a stránkovací smlouva. Bez připojení k DB, serverových cest či diagnostických verzí.
- `GET /api/v2/identity`: HTTPS, Bearer integration key a scope `discovery:read`. Vrací ID/název/scopes integrace, nikoli její klíč.
- Úspěch: `success: true`, `data`, případně `pagination`. Chyba: `success: false`, `error.code`, `error.message`. Neznámé cesty 404, nepovolené metody 405 s Allow. Interní DB chyby 503 s obecným textem.

V2 je server-to-server rozhraní pro server centrální administrace. Neposkytuje CORS prohlížeči, OPTIONS vrací 405. Webový prohlížeč komunikuje s centrálním serverem, nikoli přímo s integračním klíčem webu. HTTPS za proxy používá již nastavenou politiku důvěryhodných proxy; náhodný forwarded header není důkaz TLS.

Contract umí omezené stránkování (default25, maximum100), JSON object body versus list, omezení velikosti/hloubky a whitelist polí. Samotná existence těchto helperů nepovoluje obsahové endpointy; flagy vyjadřují skutečně implementované operace.

## Integrační identita a instalace

Volitelná SQL migrace `002-api-v2-integrations.sql` vytváří tabulky klíčů a auditu. Provádí ji operátor při explicitním upgradu schématu, nikdy webový request. Bez tabulek může veřejné discovery dál fungovat, autentizované identity selžou uzavřeně s obecnou 503.

V tomto kontrolním bodu se klíče vydávají pouze důvěryhodným lokálním PHP rozhraním `Api\V2\Model::provision($adminUserId, $name, $scopes)` a odvolávají přes `revoke`. Aktuální role správce se načte z DB. Volající je lokální správní kód, ne ID přijímané ze vzdáleného požadavku. Správní CLI navazuje v DX-02; veřejný provision endpoint neexistuje.

Nový klíč má kryptograficky náhodnou část, vrátí se jednou a DB ukládá pouze SHA-256 hash. Každý request ověřuje aktivní řádek, takže revoke účinkuje okamžitě. Scope whitelist je konečný a příliš široká identita sama nevytvoří nepodporovaný endpoint. Rotace znamená nový klíč, aktualizaci centrálního spojení a následné odvolání starého.

Provision/revoke a jejich audit commitnou společně. Metody vyžadují neaktivní připojení: již otevřená transakce je odmítnuta s `nested_transaction`409, nesmí commitnout nebo rollbacknout práci volajícího. Guard používá podporovaný SQL transakční kontrakt, nikoli neexistující veřejný mysqli server_status flag. Testy ověřují BEGIN i autocommit(false) a následný rollback původních dat. Při chybě revocation auditu zůstává klíč aktivní.

Audit rozlišuje integration_id, lokálního administrátora a volitelný `X-WBengine-Actor`. Ten je pouze omezený, nedůvěryhodný atribuční popisek uživatele centrály, nikdy důvod oprávnění. Klíče ani těla požadavků se do auditu nevkládají. Chybějící credentials či scope se auditují; chyba auditu povolenou identitu nevydá. Rate limiting a provozní retence auditů budou dokončeny s obsahovým API-04 před vydáním.

## Ověření a další kroky

Samostatná Docker sada `ApiV2Test` + `ApiV2IntegrationTest`: 22 testů /126assertions na PHP8.4.27/MySQL. Integrační tests používají connection-local TEMP tabulky, nikoli mazání trvalých uživatelů. Testy zahrnují hlavičky, HTTPS/trusted proxy hranici, revoke/scope, chyby auditu, JSON/stránkování, injekční hodnoty a vlastnictví transakcí. Vzorová aplikace navíc ověřila skutečnou HTTP discovery odpověď a původní 401 API v1.

API-02 přidá obsahové zdroje s validovanými typy boxů, ETag/If-Match a bezpečnými soubory. API-03 naváže revizemi, koncepty/publish/preview. Po commitnutém zápisu se musí invalidovat odpovídající cache (SEC-01). Nikdy se nepovolí vzdálená editace PHP či šablon. Tyto brány zůstávají v hlavním plánu otevřené.
