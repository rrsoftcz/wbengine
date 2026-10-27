# Integrace analytiky do WBengine Admin

> **Pro implementaci:** postupovat po úkolech níže, u každého nejprve přidat regresní test, ověřit jeho selhání, implementovat změnu a test spustit znovu. Pracovat v samostatných větvích příslušných repozitářů a zachovat oddělené checkpoint commity.

**Cíl:** Zobrazit agregované metriky vybraného WBengine webu v soukromé administraci a prokázat testy, že oprávnění, časová období, vzdálené chyby a oddělení webů fungují bezpečně.

**Architektura:** Administrace zůstane server-rendered aplikací SvelteKit. Nová serverová analytics gateway použije existující HTTPS klient, připojení ke konkrétnímu webu, MFA session, grant uživatele a integrační scope `analytics:read`; přihlašovací klíč zůstane výhradně na serveru. Dashboard bude na `/analytics/[site]` a bude číst jediný verzovaný souhrnný endpoint.

**Technologie:** Node.js `>=22.18`, SvelteKit `3.1.0`, Svelte `5.57.2`, TypeScript `6.0.3`. Nepřidávat frontendové ani grafové balíčky.

**Specifikace:** [plan-analytics.md](plan-analytics.md). Tento plán pokrývá integraci administrace a její testovací bránu. API a ukládání metrik musí dodat framework podle stejné specifikace.

## Globální omezení

- Vzdálená komunikace používá stávající HTTPS klient `src/lib/server/remote.ts`, kontrolu cílové adresy a limit odpovědi 1 MiB.
- Soukromé route vyžadují existující autentizaci s MFA a oprávnění uživatele k danému webu.
- Vzdálený klíč nesmí být součástí dat předaných komponentě, HTML, klientského JavaScriptu ani chybového hlášení.
- Analytický scope je pouze `analytics:read`; dashboard nic nemění na vzdáleném webu.
- Nepersistovat v administraci návštěvnické IP adresy, cookies, User-Agent, referrer ani individuální záznamy návštěv.
- Žádná analytics route nesmí vracet data z cache sdílené mezi weby; globální hook již nastavuje `Cache-Control: no-store` a toto chování musí zůstat ověřené.
- Chybějící scope, nepodporovaný endpoint, prázdná data a výpadek webu jsou odlišné stavy. Nesmí se všechny zobrazit jako nula návštěv.

## Rozhraní mezi API a administrací

Framework poskytne:

```text
GET /api/v2/analytics/overview?from=YYYY-MM-DD&to=YYYY-MM-DD&granularity=day|week|month
```

Úspěšná odpověď `data` obsahuje:

```text
timezone: IANA time-zone string
period: { from, to, granularity, complete }
totals: { page_views, article_views, box_appearances }
series: [{ period_start, page_views, article_views, box_appearances }]
top_pages: [{ id, title, views }]
top_articles: [{ id, title, views }]
top_boxes: [{ id, name, appearances }]
```

Čítače jsou nezáporná bezpečná celá čísla. Pole `complete=false` znamená, že požadované období začíná před prvním zaznamenaným veřejným zobrazením. Rozsahy mimo retenci API odmítá. API vrací nejvýše 10 položek v každém top seznamu. Endpoint vyžaduje `analytics:read` a parametry nesmějí překročit retenci 62 dní.

## Přehled souborů

### WBengine Admin, checkout `/Users/roza/Sites/svelte/wbengine-admin`

- `src/lib/server/api-contract.ts`: bezpečný typ a serializace analytics read požadavku.
- `src/lib/server/analytics.ts`: parsování rozsahu, ověření odpovědi a scope gateway.
- `src/routes/content/[site]/+page.server.ts` a `+page.svelte`: dostupnost a odkaz na analytics podle scope/capability.
- `src/routes/analytics/[site]/+page.server.ts` a `+page.svelte`: chráněné načtení a zobrazení dashboardu.
- `src/lib/components/AnalyticsChart.svelte`: lehký přístupný graf bez další závislosti, s tabulkovou alternativou.
- `tests/analytics.test.ts`: validace rozsahu, oprávnění, tvaru dat a nulových/částečných výsledků.
- `tests/analytics-transport.test.ts`: HTTP metoda, query, bearer scope a ochrana tajemství.
- `tests/api-pilot.ts`: skutečný browser-like scénář proti odděleným PHP API webům.
- `README.md`: instalace scope a používání dashboardu.

### WBengine framework, checkout `/Users/roza/Sites/php/wbengine`

- `tests/pilot/seed-installation.php`: apply nové analytics migrace a deterministické testovací agregáty pro izolované pilotní weby.
- `tests/pilot/ref-pilot.md`: pilotní předpoklady a cleanup, pokud se mění postup fixtures.
- Stávající `.github/workflows/ci.yml` administrace již spouští dvouwebový PHP API pilot. Udržet test v tomto jobu, nevytvářet paralelní neizolovaný production test.

## Review Focus

- Uživatel má přístup k jednomu webu a zkouší jiný `site_id`: před vzdáleným voláním musí dojít k odmítnutí a nesmí se zobrazit cizí data. Pokrývá Task 2 a Task 4.
- Integrační klíč postrádá `analytics:read`: dashboard musí vysvětlit chybějící oprávnění a nesmí zobrazit prázdné grafy jako skutečné nuly. Pokrývá Task 1 a Task 4.
- Vzdálený web vrátí chybnou, příliš velkou, negativní či časově neslučitelnou odpověď: dashboard odmítne data bez částečného vykreslení a bez úniku klíče. Pokrývá Task 1 a Task 3.
- Rozsah překročí retenci, má prohozené hranice nebo přechází přes časovou zónu: formulář rozsah odmítne nebo výslovně označí neúplnost, nikdy tiše nerozšíří data. Pokrývá Task 1 a Task 3.
- API web je nedostupné nebo vrátí 403/404/503: stránka zobrazí srozumitelný stav oddělený od nulové návštěvnosti, zachová oddělení ostatních webů a nastaví `no-store`. Pokrývá Task 3 a Task 4.

---

### Framework prerequisite: Analytics API

- [x] Scope `analytics:read`, `GET /api/v2/analytics/overview`, `lifetime_totals`, `capabilities.analytics.read` a validace hranic/retence jsou implementované ve framework worktree.
- [x] Testy `ApiV2AnalyticsTest`: 4 testy, 14 assertions.
- [x] Framework sbírá anonymní ID obsahu ze stromu a PageCache, transakčně ukládá součty a nabízí CLI úklid.
- [x] Docker pilot aplikoval migraci a ověřil oddělené denní součty dvou nezávislých instalací.
- [x] Veřejné renderování zaznamenává anonymní počty včetně cache metadata; analytický dashboard načetl data přes HTTPS.
- [x] Editor s přístupem k prvnímu webu nedostal oprávnění k druhému webu.

### Task 1: Analytics API kontrakt a bezpečný gateway

Stav: implementováno, unit testy, transport testy, typová kontrola a produkční build prošly.

**Soubory:**

- Admin Modify: `src/lib/server/api-contract.ts`
- Admin Create: `src/lib/server/analytics.ts`
- Admin Create: `tests/analytics.test.ts`
- Admin Create: `tests/analytics-transport.test.ts`

**Rozhraní:**

- `AnalyticsGranularity = 'day' | 'week' | 'month'`.
- `AnalyticsRange = { from: string; to: string; granularity: AnalyticsGranularity }`.
- `parseAnalyticsRange(params: URLSearchParams, now: Date): AnalyticsRange` použije při chybějícím rozsahu 30 kalendářních dat končících předchozím dnem UTC, aby datum nebylo budoucí pro žádnou platnou časovou zónu cílového webu. Odmítne nevalidní datum, `from > to`, neplatnou granularitu a rozsah delší než 62 dní.
- `validateAnalyticsOverview(value: unknown, expected: AnalyticsRange): AnalyticsOverview` ověří kontrakt, počty, hranice období, IANA timezone, nejvýše 10 top položek a shodu požadované granularity.
- `canReadAnalytics(role: Role, scopes: string[], capabilities: Capabilities): boolean` vrátí true pouze s povoleným site read rolí, `analytics:read` scope a `capabilities.analytics.read === true`.
- `createAnalyticsGateway({ role, scopes, capabilities, call }).overview(range): Promise<AnalyticsOverview>` odmítne chybějící oprávnění před sítí a volá pouze GET overview.
- `ApiCall` dostane operation `analyticsOverview` a povinné `from`, `to`, `granularity`. `planApiCall()` vrátí přesnou cestu `/analytics/overview` a deterministicky zakódovaný query string.

- [x] **Krok 1: Přidat regresní testy rozsahu a scope**

  V `tests/analytics.test.ts` pokrýt výchozí posledních 30 dní, délku 62 dní, ISO datum, prohozené hranice, nepovolenou granularitu, neplatné timezone, záporný/necelý čítač, chybějící sérii, top seznam delší než 10, neúplný rozsah a zákaz volání vzdáleného callbacku bez `analytics:read` nebo capability.

- [x] **Krok 2: Spustit nový unit test a potvrdit selhání**

  Run from admin repo: `node --experimental-strip-types --test tests/analytics.test.ts`

  Expected: FAIL, protože analytics helper a operation zatím neexistují.

- [x] **Krok 3: Přidat typovaný read-only API operation a gateway**

  Implementovat výše uvedené typy a funkce. Parametry validovat před serializací a neumožnit klientské předání cesty, HTTP metody, scope ani volných query parametrů.

- [x] **Krok 4: Ověřit unit test**

  Run: `node --experimental-strip-types --test tests/analytics.test.ts`

  Expected: PASS pro všechny validní a chybné vstupy.

- [x] **Krok 5: Testovat skutečný HTTPS transport s fixture serverem**

  V `tests/analytics-transport.test.ts` ověřit `GET`, přesnou query cestu, `Authorization: Bearer`, limit odpovědi, neakceptování klíče v JSON odpovědi a že 403/404/503 skončí jako sanitizované `RemoteFailure`.

- [x] **Krok 6: Spustit transport test a commitnout**

  Run: `node --experimental-strip-types --test tests/analytics-transport.test.ts`

  Expected: PASS; commit v admin repozitáři `feat: add analytics API gateway`.

### Task 2: Chráněná stránka dashboardu a navigace

Stav: implementováno, oprávnění zůstávají v server loaderu, HTML nevystavuje integrační klíč. Produkční build prošel.

**Soubory:**

- Admin Modify: `src/routes/content/[site]/+page.server.ts`
- Admin Modify: `src/routes/content/[site]/+page.svelte`
- Admin Create: `src/routes/analytics/[site]/+page.server.ts`
- Admin Create: `src/routes/analytics/[site]/+page.svelte`
- Admin Create: `src/lib/components/AnalyticsChart.svelte`
- Admin Modify: `src/lib/server/analytics.ts`

**Rozhraní:**

- Route URL: `/analytics/{siteId}?from=YYYY-MM-DD&to=YYYY-MM-DD&granularity=day|week|month`.
- Server loader nejprve použije `signedIn(e)`, následně `connectContent(session, siteId)` pro ověření MFA a site grantu. Pouze poté ověří scope/capability a požádá vzdálené API.
- Server loader vrací pouze webové jméno, normalizovaný rozsah, timezone, agregované čítače a top seznamy. Nikdy nevrací `gateway`, callback ani integrační klíč.
- Při absenci analytics podpory zobrazit stav "Analytika není pro tento web povolena". Nulové výsledky zobrazit jako prázdný stav, ne jako chybu.
- Denní/týdenní/měsíční volba a data od/do budou odeslána jako GET query. Graf musí mít přístupný textový souhrn nebo tabulku.

- [x] **Krok 1: Přidat testy oprávnění a dashboard datového modelu**

  Rozšířit `tests/analytics.test.ts`: neprůchozí grant/scope nesmí zavolat remote callback; nula návštěv je validní; `complete=false` zachová upozornění na neúplnou historii; nebezpečné názvy z API zůstávají obyčejným textem.

- [x] **Krok 2: Spustit unit test a potvrdit selhání**

  Run from admin repo: `node --experimental-strip-types --test tests/analytics.test.ts`

  Expected: FAIL pro nové dashboard oprávnění a partial/empty stavy.

- [x] **Krok 3: Přidat server loader a navigační odkaz**

  Použít existující `contentLoad()` pro sanitizované HTTP chyby. Site hub zobrazí odkaz jen při `canReadAnalytics(...)`; přímé otevření route bez oprávnění skončí 403 nebo existující bezpečnou chybovou stránkou. Přidat `Cache-Control: no-store`, i když jej nastavuje globální hook.

- [x] **Krok 4: Přidat přístupné zobrazení**

  Vykreslit souhrn page views, article views a box appearances, časovou osu a top seznamy. Svelte escapování musí zůstat aktivní pro názvy z API. Graf kreslit nativním SVG/CSS a zpřístupnit stejné hodnoty v tabulkové podobě.

- [x] **Krok 5: Ověřit typy a build**

  Run: `npm run check && npm run build`

  Expected: Svelte/TypeScript kontrola i produkční build projdou bez nových závislostí; commit `feat: add per-site analytics dashboard`.

### Task 3: Skutečný dvouwebový PHP API pilot

**Soubory:**

- Admin Modify: `tests/api-pilot.ts`
- Framework Modify: `tests/pilot/seed-installation.php`
- Framework Modify: `tests/pilot/seed.php` pouze pokud je potřeba deterministický denní fixture.
- Framework Modify: `tests/pilot/ref-pilot.md` pouze při změně spouštěcího nebo cleanup postupu.

**Rozhraní:**

- Obě izolované instalace dostanou vlastní `analytics:read` klíč a odlišný název seedované stránky, aby pilot prokázal oddělení dat. Chybějící scope ověřují framework API testy i Admin gateway test bez vzdáleného volání.
- Administrace testuje skutečný MFA session, site grant, šifrovaný klíč na serveru, skutečné HTTPS API a serverem renderované HTML.

- [x] **Krok 1: Připravit izolované analytické fixture v pilotní DB**

  Použít pevné datum v rámci retence a vytvořit známé denní součty pro stránky, články a boxy. Dodat migration před seedem. Test musí po cleanupu ponechat obě běžné DB beze změny.

- [x] **Krok 2: Přidat end-to-end regresní scénáře**

  V `tests/api-pilot.ts` po přihlášení MFA ověřit MFA session, dostupnost odkazu, platné i neplatné období, seedované hodnoty, oddělení dat druhého webu, `no-store`, absenci klíčů v HTML a odmítnutí druhého webu editorovi s grantem pouze na první web. Chybějící scope a výpadek se ověřují na gateway/transport testech a existujících outage scénářích.

- [x] **Krok 3: Spustit všechny administrativní testy a dvouwebový pilot**

  Run from admin repo: `npm test && npm run check && npm run build`.

  Run from framework repo: `sh tests/pilot/pilot.sh start`, `sh tests/pilot/pilot.sh probe`, `sh tests/pilot/pilot.sh consumer /Users/roza/Sites/svelte/wbengine-admin`, `sh tests/pilot/pilot.sh backup-check`, potom `sh tests/pilot/pilot.sh reset`.

  Výsledek 2026-10-27: `npm test` prošel (43 testů), `npm run check` prošel bez chyb a varování, `npm run build`, `npm run test:mysql` a `npm audit --audit-level=high` prošly; npm audit nenašel zranitelnosti. Lokální Docker pilot obou samostatných HTTPS API prošel včetně centrálních formulářů, analytics dashboardu a oddělení dat. Po nové tabulce `cms_analytics_meta` ověřil `backup-check` 20 tabulek a 158 řádků se shodnými hashi schématu a dat; `reset` odstranil pouze pilotní kontejnery, síť a volume.

  Framework GitHub CI run [40570318253](https://github.com/rrsoftcz/wbengine/actions/runs/40570318253) prošel všemi 7 úlohami: PHP 8.3–8.5, MySQL 8.4, MariaDB 10.11/11.4, Texy migration a Smarty compatibility. Admin GitHub CI run [40570331198](https://github.com/rrsoftcz/wbengine-admin/actions/runs/40570331198) prošel `test` i dvouwebovým API pilotem.

  Po následné revizi se CI pilot připíná na framework commit `351a5e79b0061d69a1e714552ccdd4c07b675db0`, ne na starý tag `v4.0.0`. Tento commit obsahuje nové API, migration metadata, start měření i veřejný event guard. Po vydání frameworku se pin aktualizuje na stabilní release tag.

  Lokální checkpoint administrační změny je `34c9f6871c00358c96f04c8eda30055c4cf4bf4e` (`fix: align analytics CI with framework API`).

  Read-only review 2026-10-27 našel a následně byly opraveny: article/box události před veřejným HTML renderem a nepřesné `period.complete` před začátkem měření. Výchozí admin období také končí předchozím UTC dnem, aby nebylo budoucím datem na webu v západním časovém pásmu.

- [x] **Krok 4: Zaznamenat důkazy a commitnout oba repozitáře**

  Do plánu zapsat lokální i vzdálené CI důkazy. Samostatné commity jsou na cílových větvích framework `master` a admin `main`; zbývá vydání a nasazení.

### Task 4: Návod a připravenost k zapnutí

**Soubory:**

- Admin Modify: `README.md`
- Framework Modify: `README.md`
- Framework Modify: `plan-analytics.md`
- Framework Modify: `plan-framework-improvements.md`

- [x] **Krok 1: Dokumentovat konfiguraci a podporu webu**

  Vysvětlit nový `analytics:read` scope, 62denní dostupnost denních řádků, trvalé celoživotní součty, definici zobrazení, zahrnutí robotických požadavků, prázdný/částečný stav a Docker CLI údržbu.

- [x] **Krok 2: Aktualizovat rozhodovací tracker**

  Zapsat dokončenou implementaci, lokální testovací důkazy, migraci, retenční údržbu a otevřené nasazení. Obě CI sady prošly. GA4 zůstává aktivní do samostatného provozního porovnání.

- [x] **Krok 3: Ověřit změny dokumentace**

  Run: `git diff --check` v obou repozitářích.

  Výsledek 2026-10-27: `git diff --check` prošel v obou worktree; README uvádějí ruční migraci a opt-in konfiguraci před zapnutím.

## Hranice dokončení

Administrace je připravená k vydání, když unit testy, HTTP testy, type-check/build a dvouwebový actual API pilot projdou; uživatel s omezeným grantem ani klíč bez analytics scope nezíská data; výpadky jsou srozumitelné; `no-store` a absence tajemství jsou ověřeny. GA4 se vypíná až v samostatném schváleném provozním kroku po nasazení frameworku i administrace.

Stav 2026-10-27: lokální ověření i GitHub CI release gate prošly. Změny jsou na `wbengine/master` a `wbengine-admin/main`. Analytika zůstává opt-in, migration `006-analytics.sql` se musí na cílovém webu aplikovat před zapnutím. Produkční aktivace ani vypnutí GA4 zatím neproběhly.

Příprava vydání 2026-10-27: pracovní návrhy v `CHANGELOG.md` cílí na framework `v4.1.0` a soukromý admin `v0.2.0`. GitHub release objekty ani tagy zatím nejsou vytvořené; zveřejnění a produkční nasazení zůstávají oddělenými kroky.

## Doporučené pořadí

Nejprve dokončit frameworkovou analytics API a izolovaný pilot fixture. Poté navázat Task 1 a Task 2 v administraci. Task 3 a Task 4 tvoří release gate a mají proběhnout před vydáním. Task 5 uzavírá dokumentaci a kontrolní bod.
