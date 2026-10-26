# Volitelný offline převod Texy

Balíček `wbengine/texy-migration` slouží jen k řízenému převodu existujících Texy zdrojů přes bezpečnou HTML migrační bránu. Není runtime formatterem webu a jádro Texy neinstaluje. Staré DB řádky legacy_html zůstávají původním HTML, automaticky se nepřekládají.

## Instalace v izolovaném migračním projektu

Dokud balíček není publikovaný v Composer registru, použijte důvěryhodný lokální checkout:

```json
{
  "repositories": [
    {"type":"path","url":"/trusted/wbengine/compat/texy-migration","options":{"symlink":false,"versions":{"wbengine/texy-migration":"4.0.x-dev"}}},
    {"type":"path","url":"/trusted/wbengine","options":{"symlink":false,"versions":{"wbengine/wbengine":"4.0.x-dev"}}}
  ],
  "require":{"wbengine/texy-migration":"4.0.x-dev"},
  "minimum-stability":"dev",
  "prefer-stable":true
}
```

Po `composer install` použijte důvěryhodný autoload jako `--texy-autoload=/migration-project/vendor/autoload.php`. Pro spuštění CLI z nainstalované knihovny použijte její Composer bin proxy. Explicitní suchý běh:

```sh
php /trusted/wbengine/bin/migrate-content.php --config=/site/Config/Settings.json --article=42 --source-format=texy --texy-autoload=/migration-project/vendor/autoload.php
```

Dále postupujte podle ref-markdown.md v jádru: chráněný report, kontrola původních textů i výsledného renderu, jeden schválený hash, záloha a konfliktní rollback. Manual issues se nesmí obcházet. Nelze předpokládat automatický bezeztrátový převod všech Texy rozšíření.

Samostatné testování v checkoutu: `sh compat/texy-migration/bin/test`. Runner má dočasný vendor mimo checkout, vlastní mirror jádra a po sobě odstraní pouze své závislosti.
