# Izolovaný společný pilot WBengine 4.0

Testovací poskytovatel dvou nezávislých WBengine instalací a MySQL databáze centrály. Pouze vlastní Docker projekt/síť wbengine-admin-api-pilot, bez hostitelských portů. Žádné produkční hosty nebo databáze. Browserové formuláře ověřuje Node HTTP/SSR klient; nejde o grafickou Chromium automatizaci.

## Příprava

Potřebujete Docker Compose, openssl, python3, důvěryhodný checkout administrace a připravený vendor jádra. Příkazy z kořene frameworku:

```sh
docker build -t wbengine-modernization-php:latest docker
docker run --rm -v "$PWD:/app" -w /app wbengine-modernization-php:latest composer install --no-dev --no-interaction --prefer-dist
docker build -t wbengine-admin-foundation-check:local /path/to/wbengine-admin
docker pull mysql:8.4
docker pull nginx:stable-alpine
docker pull node:22-alpine
sh tests/pilot/pilot.sh start
```

Pokud pracujete na vývoji jádra, zachovejte svůj dev vendor a nepouštějte zde no-dev instalaci nad používanou testovací sadou. CI má vlastní checkout. Start nespouští obecné migrace nebo libovolný SQL soubor: seed používá pevné testovací databáze a zkontrolovaný seznam vlastních schémat. API a API2 mají vlastní uživatele, klíče, konfiguraci, cache i úložiště. Nginx rozlišuje dva pevné TLS hostname, nevytváří cestu z nedůvěryhodného Host headeru.

## Ověření

```sh
sh tests/pilot/pilot.sh probe
sh tests/pilot/pilot.sh consumer /path/to/wbengine-admin
sh tests/pilot/pilot.sh backup-check
```

Probe ověřuje skutečné HTTPS a veřejný render, oddělené změny a odmítnutí klíče jiného webu, i odmítnutí soukromé CA bez explicitní důvěry. Consumer spouští testy administrace v jejím produkčním Node24 obrazu: login/MFA, skutečné formuláře, koncept/překlad/preview/publish, revize, souběžný412/ruční merge, obnova struktury, obrázky, dva weby a omezený editor, výpadek/ruční opakování, odvolaný klíč a veřejné weby po zastavení centrály.

Výpadek a revokace se ovládají konečným lokálním testovacím protokolem revoke-second/stop-second/start-second. Consumer nemá Docker socket, veřejný ovládací endpoint ani možnost předat shell příkaz. Wrapper po testu druhou službu obnoví a obnoví její klíč pro další běh. Odeslané změny při výpadku nesmějí samy proběhnout po návratu webu.

Backup-check používá skutečný single-transaction dump pouze vlastního API schématu, obnoví pevnou pomocnou wbengine_api_pilot_restore a porovná databázové defaulty, definice tabulek a deterministické SHA256 hashe všech řádků. V finally odstraní pomocnou DB i dump. Zdrojovou DB nemění. Záloha souborových médií a klíčů je samostatný provozní postup v book-wbengine-4-upgrade.md; tento DB test ji nesimuluje.

## Tajemství a úklid

Runtime je ignorovaný Gitem. Bootstrap, integrační klíče a privátní CA mají0600; Linux seed používá UID/GID hostitele, aby hostitelský Docker klient mohl číst soukromé env soubory. Consumer dostává env přes Docker a mount pouze veřejné CA, nikdy bootstrapu nebo privátního CA klíče. Neodesílejte runtime do Gitu, screenshotů či logů.

```sh
sh tests/pilot/pilot.sh status
sh tests/pilot/pilot.sh stop
sh tests/pilot/pilot.sh reset
```

Stop zastaví pouze vlastní projekt, reset navíc odstraní jeho DB volume a generovaný runtime. Nepoužívejte obecné Docker prune nebo zastavování cizích kontejnerů.
