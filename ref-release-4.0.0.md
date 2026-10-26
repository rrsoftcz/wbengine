# WBengine 4.0.0: připravené vydání

Stav 2026-10-26: implementace a technická brána dokončeny v pracovních větvích. Tento dokument je podklad k vydání, nikoli tvrzení, že tag nebo release už byly zveřejněné. Produkční data nebyla migrována.

## Změny

- Vlastní nativní kompilované šablony jako základ, Smarty pouze volitelný kompatibilní balíček. Defaultní escapování, diagnostika nepodporované syntaxe a korektní vlastnictví výstupních bufferů.
- Vlastní omezený Markdown parser, AST a bezpečný HTML renderer, soubory .md i explicitní DB formáty. Texy pouze volitelný offline převodník. Dry-run, schválený hash, přesné zálohy a konfliktní rollback článků i překladů.
- Odložené CMS, terminální routy před cache, personalizované cache scope, bezpečnější sessions, vstupy, CSRF, proxy a autentizace. Parametrizované menu lookupy a změřený index boxů.
- API v2 s omezenými klíči/scopes, auditem a limity, CRUD obsahu, registrovanými boxy, skutečně dostupným nastavením vykonávaného boxu, koncepty/revizemi/publikací, překlady, obnovou struktury, ETag a veřejnými rastrovými médii.
- Samostatná centrální SvelteKit administrace: pozvánky, MFA, role po webech, šifrované klíče, ověřené pinned HTTPS, editor, preview/publish/recovery a zachování rozepsaných polí při konfliktu i výpadku.
- Instalované Composer CLI pro diagnostiku, read-only schéma a bezpečný scaffold; opakovatelná skutečná CMS benchmark matice.

PHP jádro zachovává Application/Site/Section/Box, namespaces, lazy služby a model/adapter vzory. Nevznikl paralelní PHP framework. URL mapa se po měření záměrně nezavádí: skutečný indexovaný SQL lookup zachovává DB collation a nemá zbytečné full-table/paměťové náklady.

## Ověření

- Jádro: 1989 testů,5666 assertions na PHP8.4.27/MySQL8.4; audit a lint bez chyb. CI PHP8.3/8.4/8.5 a MariaDB10.11/11.4.
- Optional Smarty86/123 a offline Texy1/5, samostatné CI profily. Jádro je neinstaluje, jediná runtime Composer závislost zůstává firebase/php-jwt.
- Vzorový web:20 skutečných HTTP kontrol, fresh no-dev optimized strictPSR autoload bezSmarty/Texy;15bodová CMS matice300requestů/0chyb. Public pageHIT0SQL/0connections, osobní stránky nejsou sharedHIT. Jde o malou localhost fixture, nikoli produkční kapacitní garanci.
- Administrace:34 unit/transport/action/komponentových testů,5 MySQL scénářů a HTTP auth smoke,check0chyb/0warnings a produkčníDockerbuild. Komponentový test kompiluje skutečný Svelte formulář.
- Dva nezávislé HTTPS weby: crosskey401, oddělené změny/databáze, omezenýeditor, actualHTML draft/preview/publish/revize/konflikt/recovery/překlady/média, odvoláníklíče a výpadek/ručníretry. Veřejnéweby zůstaly dostupné po vypnutí centrály.
- Skutečný DBdump/restore17tabulek se shodnými definicemi a SHA256 dat, pomocné artefakty uklizené. Společný automatický admin CI pilot prošel, včetně této obnovy. Node HTTP/SSR test nenahrazuje grafickou Chromium automatizaci.

## Migrační dopady

Jde o novou major verzi. Zkontrolujte přímé Smarty/Texy API a pluginy, raw versus escapovaný výstup, nezměněné HTTP vstupy, POST logout a účelové CSRF, deferred lifecycle a produkční cache. Staré HTML se automaticky neoznačuje za Texy ani Markdown. Nový API konektor vyžaduje explicitní SQL002/003/005 a InnoDB, migrace obsahu také004. Schema se samo nemění za requestu.

Kompatibilní Composer balíčky v compat/ ještě nejsou samostatně publikované. Pro lokální path repository použijte zdokumentované mirror nastavení symlink=false. Preview je soukromé; nahraná média jsou veřejná a úložiště musí být trvalé mimo document root. Externí CDN se nepurguje automaticky.

Přesný postup a návrat: book-wbengine-4-upgrade.md. Aktuální úkoly a historie: plan-framework-improvements.md. API, Markdown a templates mají samostatné ref dokumenty. Provozní správce musí ověřit vlastní zálohu, konkrétní obsah a ruční migrační issues před nasazením.

## Připravené GitHub změny

- Jádro: https://github.com/rrsoftcz/wbengine/pull/2
- Vzorová aplikace: https://github.com/rrsoftcz/wbengine-skeleton-app/pull/1
- Privátní centrála: https://github.com/rrsoftcz/wbengine-admin/pull/1

Závěrečný publikační krok: schválení a merge odpovídajících PR, ověření merge CI, tag a GitHub release v4.0.0. Produkční deploy a konverze provozních dat jsou samostatné řízené operace podle upgrade návodu.
