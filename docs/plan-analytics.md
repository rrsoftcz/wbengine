# Návrh vlastní analytiky WBengine

Stav k 2026-10-27: schválený plán; analytics API a úložiště jsou součástí frameworku `v4.1.0`. Čerstvá produkční databáze má migraci 006, ale sběr je vypnutý. Připojení lokální administrace a zapnutí měření zůstávají otevřené.
Datum: 2026-10-27.

## Záměr

Nahradit Google Analytics pro základní přehled o provozu webů vlastní analytikou WBengine, kterou bude zobrazovat samostatná soukromá administrace. Měřit návštěvnost obsahu a trendy, nikoli identitu nebo chování jednotlivých návštěvníků.

## Rozhodnutí a hranice

- Celoživotní počty zobrazení článků se zachovají trvale. Stávající hodnoty `cms_articles.views` se při zavedení nezahodí.
- Denní zobrazení článků budou dostupná nejméně jeden měsíc. Týdenní a měsíční údaje se budou počítat z denních agregátů. Starší denní agregáty lze odstranit, celoživotní součty zůstanou.
- Analytické úložiště nebude zaznamenávat IP adresy, cookies, uživatelská ID, User-Agent, URL query parametry, referrer ani jednotlivé návštěvy.
- Bez identifikátoru nelze přesně určit počet unikátních lidí. Rozhraní proto bude používat pojem "zobrazení", nikoli "unikátní návštěvníci".
- Počítadlo boxu znamená, že box vygeneroval obsah zahrnutý do veřejné odpovědi. Neprokazuje, že návštěvník obsah skutečně viděl na obrazovce.
- Souhrny nebudou při výmazu uživatelského účtu mazány, pokud nejsou spojitelné s konkrétní osobou.
- Do dokončení a nasazení této funkce zůstává případné měření přes GA4 samostatnou provozní záležitostí. Jeho vypnutí ani odstranění historických reportů není součástí této fáze.

## Zvažované přístupy

1. **Agregace ve WBengine s dávkovým zápisem:** framework zaznamená pouze čítače a denní součty. Existující `Wbengine\\Counter\\BatchCounter` omezuje počet databázových zápisů. Požaduje doplnit obsluhu cache HIT a spolehlivé vyprázdnění nízkoobjemových čítačů.
2. **Zápis každého zobrazení přímo do databáze:** jednoduchý průběh a aktuální data, ale přidává databázovou operaci ke každému zobrazení a boxu.
3. **Analýza webserverových logů:** zachytí cache HIT bez zásahu do renderování, ale zavádí závislost na formátu logů a jejich rotaci. Logy mohou obsahovat osobní či identifikující údaje a nepřinášejí spolehlivou vazbu mezi požadavkem a boxy.

Doporučený směr je první přístup, protože odpovídá stromové architektuře WBengine a omezuje náklady na požadavek. Jeho implementace musí zahrnout pravidelné vyprázdnění dávek. Pouhé čekání na další návštěvu stejného obsahu by u málo navštěvovaných stránek mohlo odložit zápis příliš dlouho.

## Navržené metriky MVP

- Zobrazení veřejné stránky podle `site_id`, po dnech a jako celoživotní součet.
- Zobrazení článku podle `article_id`, po dnech, za týden, za kalendářní měsíc a jako celoživotní součet.
- Výskyt boxu podle `box_id`, po dnech a jako celoživotní součet.
- Celková návštěvnost webu podle dne, týdne a měsíce.
- Řazení nejnavštěvovanějších stránek, článků a boxů za zvolené období.

MVP nebude vykazovat unikátní návštěvníky, čas na stránce, cesty mezi stránkami, demografii, kampaně ani zařízení. Metriky se nebudou rozlišovat podle jazyků, dokud nebude určeno, jak jazyk bezpečně a jednotně mapovat na obsahové ID.

## Pravidlo započítání

- Započítat úspěšné veřejné požadavky `GET`, které vrátí HTML konkrétního webu. Cache HIT se započítá stejně jako dynamicky vykreslená odpověď. Odpověď 304 se počítá jako zobrazení požadovaného obsahu.
- Nezapočítávat API, administraci, přihlášené či neveřejné stránky, náhledy, statické soubory, přesměrování a chybové odpovědi.
- Jeden požadavek může zvýšit počet zobrazení stránky a článku a současně počet výskytů vykreslených boxů. Každá dimenze se bude ukládat samostatně.
- Obsahové ID se získá v existujících vrstvách stromu: site ve `Wbengine\\Site`, boxy v `Wbengine\\Section` a článek v jeho box/modelu. Nevytvářet paralelní resolver rout ani samostatnou aplikační architekturu.
- Výchozí započítávání bude zahrnovat i neznámé robotické požadavky, protože bez identifikace nelze bezpečně určit skutečného člověka. Případné vyloučení známých robotů lze přidat jako oddělenou volitelnou funkci a nebude ukládat User-Agent.

## Denní období a retence

- Datové součty budou ukládány po kalendářních dnech v časové zóně nastavené pro web. Navržený konfigurační klíč je `analytics.timezone`, s výchozí hodnotou `UTC` a validací IANA názvu.
- Doporučená retence denních řádků je 62 dní. To umožní zobrazit aktuální a předchozí kalendářní měsíc včetně denního průběhu. Jde o souhrnné čítače bez návštěvnických identifikátorů.
- Týdenní součet se vypočítá za ISO týden od pondělí do neděle. Měsíční součet znamená kalendářní měsíc; API musí oznámit, pokud požadované období přesahuje uchovávanou denní historii.
- Po uplynutí retence se denní řádky odstraní. Celoživotní součty zůstanou zachované.
- Pravidelný CLI příkaz provede bezpečné dávkové vyprázdnění čekajících čítačů a promazání denních řádků po retenci. Provozní návod stanoví spouštění přes plánovač kontejneru. Přesný název příkazu se určí při implementaci podle registrace příkazů `bin/wbe`.

## Ukládání a výkon

- Nové modely budou v namespace `Wbengine\\Analytics`; služba bude dostupná přes současný lazy přístup Application a navázaná na Site/Section/Box tam, kde vzniká obsahové ID.
- Navržené databázové uložení oddělí krátkodobé denní součty od trvalých součtů. Denní klíč bude obsahovat datum, web, typ metriky a obsahové ID. Trvalá tabulka bude obsahovat jen tyto rozměry a celoživotní počet.
- Pro článek bude zachována kompatibilita se stávajícím `cms_articles.views`. Historická hodnota bude výchozí celoživotní základnou; denní analytická série začne datem zapnutí nové funkce a nebude předstírat zpětně získaná historická data.
- Dávkový čítač bude rozlišovat denní klíč, aby se pozdější flush nepřipsal do nesprávného dne. Současný `BatchCounter` ukládá pouze hash klíče a jeho callback žije jen v procesu požadavku, takže sám neumí čekající nízkoobjemové součty vyjmenovat pro pozdější CLI flush. Analytika proto potřebuje obnovitelný seznam čekajících agregačních klíčů nebo vlastní frontu s bezpečnými omezenými rozměry. Výmaz denních součtů nesmí odečíst celoživotní počet.
- `PageCache` nyní obslouží HIT před spuštěním Site/Section/Box. Cacheovaný objekt proto bude potřebovat omezená metadata obsahových ID a po HIT předat pouze tato ID agregátoru. Metadata nebudou obsahovat URL, cookies ani data návštěvníka.
- Požadavek nesmí provádět jeden synchronní SQL zápis za každý box. Akceptační měření stanoví horní mez práce při MISS i HIT a ověří, že obsluha HIT nevyvolá načítání databázové cache CMS.
- Selhání analytiky nesmí způsobit chybu veřejné stránky. Chyby se zaznamenají provozně bez IP, URL query, User-Agent a obsahu požadavku; míra ztracených čekajících čítačů při pádu procesu bude zdokumentována.

## API a administrace

- Rozšířit `Wbengine\\Api\\V2` o read-only scope `analytics:read`. Integrace bez tohoto scope nesmí získat žádná analytická data.
- Doporučený první endpoint je `GET /api/v2/analytics/overview?from=YYYY-MM-DD&to=YYYY-MM-DD&granularity=day|week|month`. Vrátí webový souhrn a omezené seznamy nejnavštěvovanějších stránek, článků a boxů. Detailní endpointy podle entity mohou následovat, pokud se ukážou potřebné.
- Odpověď přehledu bude mít `timezone`, `period` (`from`, `to`, `granularity`, `complete`), `totals` (`page_views`, `article_views`, `box_appearances`), `lifetime_totals` se stejnými klíči a `series` (`period_start` a stejné čítače) a `top_pages`, `top_articles`, `top_boxes`. Záznam top seznamu obsahuje stabilní ID, bezpečný zobrazovaný název a počet. `complete=false` označuje období začínající před prvním zaznamenaným veřejným zobrazením.
- API přijme jen validní datum od/do v podporovaném rozsahu a granularitu `day`, `week` nebo `month`; maximální období omezí podle retence (výchozí nejvýše 62 kalendářních dnů včetně dnešního dne). Odpověď uvede časovou zónu, přesné hranice a informaci o chybějícím či částečném rozsahu. Nepřijímat libovolný SQL, název tabulky ani libovolnou metriku od klienta.
- Soukromá SvelteKit administrace zobrazí přehled trendu, nejnavštěvovanější obsah a boxy a možnost přepnout denní, týdenní a měsíční souhrn. Nezavádět do administrace klientské měření webových návštěvníků.
- Změna vyžaduje koordinované vydání frameworku, migrace vzorové aplikace a změny klienta administrace. Veřejné analytické endpointy ani veřejný dashboard nejsou cílem.

## Přechod z GA4

1. Implementovat agregaci a API za vypnutou konfigurací.
2. Ověřit počítání veřejného webu, cache MISS/HIT, denní hranice, dávkování, retenci a oprávnění v testovací aplikaci.
3. Zobrazit metriky ve správní administraci a po omezenou dobu porovnat celkové trendy s GA4. Čísla nemusí být shodná, protože se liší definice, souhlas, roboti a zachycení cache.
4. Po potvrzení funkčnosti vypnout GA tag a odstranit souhlasové úložiště používané výhradně pro GA. Aktualizovat zásady soukromí a cookies. Ověřit, zda web používá ještě jiná než nezbytná cookies.
5. Historické GA reporty lze ponechat pro čtení nebo samostatně požádat o jejich vymazání. Vypnutí tagu je samo o sobě nemaže.

## Akceptační podmínky

- Počty pro článek jsou správné za jednotlivý den, sedmidenní interval, kalendářní měsíc a celoživotní období; týdenní a měsíční součty odpovídají denním datům.
- Celoživotní `cms_articles.views` před migrací se zachovají a denní historie před zapnutím se nevymýšlí.
- Jedna cache MISS a následné HIT požadavky zvýší všechny odpovídající agregáty přesně jednou za požadavek. 304, chybové odpovědi, API, statické soubory a neveřejné stránky dodrží uvedená pravidla.
- Po 62 dnech zmizí pouze staré denní řádky; celoživotní počty zůstanou beze změny.
- Databáze a analytické dávkové soubory neobsahují IP adresy, cookies, User-Agent, query parametry, referrer ani per-visit záznamy.
- Chybějící nebo nedostatečný `analytics:read` scope vrací standardní API odmítnutí; existující API integrace bez nového scope zůstanou funkční.
- Veřejná stránka zůstane dostupná i při chybě analytického úložiště. Měření výkonu zahrne MISS, HIT, stránku s mnoha boxy a dávkové vyprázdnění.
- CLI údržba je opakovatelná, dávkovaná, bezpečná při souběhu s webovými požadavky a zdokumentovaná pro Docker nasazení.

## Implementační rozhodnutí

- Uživatel schválil jako základ implementace vlastní dávkovanou agregaci, 62denní denní historii, trvalé celoživotní součty a započítávání neznámých robotických požadavků. Počet zobrazení se nebude označovat jako počet unikátních lidí.
- Před implementací určit konkrétní bezpečný formát seznamu box ID v metadatech cache, jeho limit a chování při překročení limitu.
- Dodržet navržený endpoint a JSON tvar v `Api/V2`; případnou změnu kontraktu koordinovat současně ve frameworku a administraci.
- Právní a provozní kontrola musí před zapnutím potvrdit oznámení o agregované analytice, GA přechod, retenční nastavení a zacházení se serverovými logy. Tento návrh není právním stanoviskem.

## Stav implementace

- [x] Uživatel schválil vlastní agregovanou analytiku, 62denní retenci, trvalé počty a pokračování testováním administrace.
- [x] Přidán scope `analytics:read` a endpoint `GET /api/v2/analytics/overview`.
- [x] Endpoint vrací denní/týdenní/měsíční řadu, top obsah, součty období i celoživotní součty; stránkování se zatím nevyžaduje.
- [x] Přidána migrace `006-analytics.sql` pro denní součty a trvalé počty stránek/boxů. Migraci je nutné ručně aplikovat před zapnutím.
- [x] Přidána validace nastavení a rozsahu; analytika je ve výchozím stavu vypnutá.
- [x] Integrační testy scope, odpovědi, lifetime součtů a chybných období procházejí.
- [x] Zachytávání veřejných GET renderů, boxů s neprázdným výstupem a článků; metadata cache verze 3 počítají HIT i 304 bez návštěvnických údajů.
- [x] Jedna request-local dávka se ukládá transakčně v omezeném počtu SQL operací; CLI `analytics:prune` maže nejvýše 1000 denních řádků na spuštění. Trvalá souborová fronta není použita, pád během právě zpracovávané odpovědi může ztratit její dávku.
- [x] Zaznamenání článků a boxů vyžaduje potvrzený anonymní veřejný HTML požadavek. Cache metadata bez validního veřejného page eventu se ignorují.
- [x] `cms_analytics_meta` ukládá datum prvního měřeného veřejného zobrazení; API tím rozliší nezměřenou historii od nulové návštěvnosti.
- [x] Lokální dvouwebový Docker API pilot ověřil oddělení instalací, analytics dashboard, MFA/grant omezení a nezávislé HTTPS API. Po přidání měřicího metadata ověřila zálohovací kontrola 20 tabulek a 158 řádků se shodnými hashi.
- [x] README a administrační návod popisují opt-in konfiguraci, migraci `006`, `analytics:read`, 62denní denní historii, celoživotní součty a CLI údržbu.
- [x] Framework PHPUnit: 2007 testů, 5732 assertions; `composer lint` prošel a `composer audit` nenašel žádná bezpečnostní upozornění.
- [x] Framework GitHub CI run [40570318253](https://github.com/rrsoftcz/wbengine/actions/runs/40570318253) prošel všemi sedmi úlohami. Admin GitHub CI run [40570331198](https://github.com/rrsoftcz/wbengine-admin/actions/runs/40570331198) prošel testy i dvouwebovým API pilotem.
- [x] Lokální checkpointy jsou pushnuté na `wbengine/master` a `wbengine-admin/main`: framework `4e46bc3`, `351a5e7`, `bc4b1f9`, `2ad8b78`; administrace `50c7487`, `34c9f68`.
- [x] Framework `v4.1.0` a soukromá administrace `v0.2.0` jsou publikovány jako GitHub Releases. Tagy ukazují na otestované commity `cdf7b902` a `6e4a1253`.
- [x] Skeleton App `main` zamyká `wbengine/wbengine` na `v4.1.0` (`5bcc949`). Smoke test nyní používá framework 4.1, ověřuje aktuálních 10 článků a jazykový přepínač; lokální Docker prošel a GitHub CI [40577284046](https://github.com/rrsoftcz/wbengine-skeleton-app/actions/runs/40577284046) prošlo.
- [ ] Nasadit pouze veřejný Skeleton App na VPS; centrální administrace zůstává lokální na vývojovém Macu. Na VPS je SSH jako root a Docker Engine 29.9.2 s Compose 5.6.1, ověřený přes `hello-world`. DNS `www.wbengine.org` nyní míří na tento VPS, ale porty 80/443 zatím neposlouchají a web není dostupný. Apex stále přesměrovává na www ze starého hostu. Starý web je čitelný na původní IP a vrací API 2.0 bez `analytics.read`. Před vytvořením databáze na novém hostu je třeba rozhodnout, zda se přenese dosavadní DB, nebo se inicializuje aktuálním seedem.
- [ ] GA4 vypnutí zůstává samostatným krokem po porovnání trendů a aktualizaci provozních/ochranářských informací.
