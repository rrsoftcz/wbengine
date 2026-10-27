# WBengine 4.0: upgrade, ověření a návrat

Stav: release kandidát, 2026-10-26. Tento postup sám nic nenasazuje. Produkční změny provádí správce konkrétní instalace po ověření její zálohy a obsahu. Pracovní registry: plan-framework-improvements.md a samostatný plan-admin-implementation.md v administraci.

## Před změnou

1. Zaznamenejte aktuální commit/tag aplikace, knihovny, Composer manifest/lock, konfiguraci, PHP/DB verzi a dostupná rozšíření. Zvolte okno údržby. PHP jádro vyžaduje PHP8.3+, DB a mbstring podle používaných funkcí; offline HTML migrace také DOM. Administrace vyžaduje Node22.18+ nebo její Node24 image a vlastní MySQL.
2. Zálohujte zdroje, privátní konfiguraci, databázi a média mimo veřejný adresář. Zálohu nejdříve obnovte do odděleného schématu a ověřte přihlášení i reprezentativní obsah. Hesla a klíče nedávejte do argumentů shellu, reportů v Gitu ani ticketů. Pro dump použijte privátní defaults-extra-file svého DB klienta a single-transaction pouze nad InnoDB.
3. Samostatně zálohujte databázi centrály a její MASTER_KEY ze secret store. Obnovená šifrovaná DB bez odpovídajícího klíče nestačí. Uchovejte záložní MFA kódy bezpečným kanálem.
4. Celý postup nejdříve proveďte na kopii cílového webu. Existující testovací pilot nenahrazuje inventuru vašich pluginů, přímých Smarty API ani skutečných Texy/HTML dat.

## Aplikace a šablony

1. Připravte aplikaci na odložený lifecycle: konstruktor neprovádí CMS dotazy a boxy. Layoutová data/session připravujte ve správném Site/Section/Box kroku, vlastní terminální routy registrujte před run(). Vzorová aplikace má tento postup ve vlastním PR.
2. Inventarizujte důvěryhodné šablony pomocí tests/tools/template-inventory.php a pravidel ref-template-migration.md. Převeďte assign/include/inheritance/pluginy podle skutečně podporovaného jazyka. Nativní set/t nelze automaticky přepnout zpět na Smarty.
3. Nastavte NativeRenderer a ponechte autoEscape=true. Raw patří jen již bezpečně zpracovanému nebo jinak důvěryhodnému HTML. Šablony mají přístup k přiřazeným objektům, nejsou sandboxem pro cizí autory. Samostatný .md zdroj naopak neprovádí PHP ani template logiku.
4. Přímí uživatelé Http::secureClean musí přejít na nezměněné vstupy, validaci, parametrizované SQL a kontextové escapování výstupu. Prázdný text není totéž co chybějící JSON klíč. JWT iat již není issuer.
5. Přihlašovací formuláře používají účelové CSRF tokeny. Odhlášení je POST s auth.logout, starý GET odkaz změňte. Zkontrolujte vlastní formuláře, cookies, session.close a trusted proxy nastavení podle README.

## Schéma a obsah

1. Zastavte měnící požadavky a provozní úlohy během DDL. Před změnou ověřte engine tabulek, konkrétní schéma a uloženou zálohu. Framework nespouští runtimeDDL.
2. Zkontrolujte šířku cms_users.password: nové bezpečné hashe potřebují varchar255. Staré MD5 čtení zůstává jen migrační; nové krátké zápisy se odmítají. Konkrétní ALTER proveďte podle vašeho stávajícího schématu, nikoli slepým kopírováním.
3. Volitelná SQL001 přidává změřený index boxes(section_id,order). Předem zkontrolujte existující indexy. Není nutná relační přestavba tabulky.
4. Pro API aplikujte SQL002,003,005 v migrations/4.0.0. SQL003 zavádí explicitní legacy_html/markdown; původní bajty zůstanou HTML. SQL005 přidá draft/revize/recovery/média a api_settings. Nepouštějte znovu již existující ALTER bez kontroly. Doctor/migration:status jsou read-only pomocníci, ne migrační runner.
5. SQL004 je jen pro řízený převod starého obsahu. Původní Article getter předával HTML, nelze tedy všechna stará data označit za Texy. Vytvořte explicitní dry-run s html nebo texy, chraňte report jako zálohu, zkontrolujte výsledný render i všechny překlady. Manual issues vyřešte ručně a vytvořte nový report. Neschvalujte nebezpečný nebo ztrátový převod.
6. Apply schvaluje jediný approval_hash. Uloží přesné originály a nový obsah v jedné transakci; stale zdroj či poškozené úložiště odmítne. Ověřte skutečný rollback na kopii a idempotenci. Podrobné příkazy: ref-markdown.md. Povinný Texy není součástí jádra, jen volitelného offline balíčku. Renderer::getFormater()->process nyní znamená Markdown, přímé Texy zdroje nejdříve převeďte.

## API a centrála

1. Registrujte dovolené box typy, enum nastavení a lowercase section keys v důvěryhodném startup kódu před run(). API nepovoluje vzdálenou PHP třídu, metodu ani template editor. Box čte getSettings/getSetting, neprovádí kód ze settings.
2. Zapněte apiV2.enabled=true až po připraveném schématu a TLS. Konektor je nezávislý na CMS API stránce. Pro veřejná média nakonfigurujte trvalý absolutní imageDirectory mimo document root; média jsou veřejná i při použití v konceptu.
3. Důvěryhodný místní správce vydá omezený integrační klíč přes Api\V2\Model::provision. Klíč se ukáže jednou, web ukládá pouze hash. V centrále jej předejte site-add přes zabezpečené stdin ze secret store. Klíče nikdy nepatří do browseru.
4. Spusťte explicitní migraci centrály. První účet vytvořte CLI pozvánkou, dokončete MFA a uložte záložní kódy. Globální správce přiděluje přístupy ke konkrétním webům. Editor nemá oprávnění publikovat nebo měnit strukturu.
5. Nastavte přesný HTTPS ORIGIN centrály, důvěryhodnou reverse proxy a síťový egress. Intranet povolujte jen přes konkrétní provozní IP allowlist. Vypnutí TLS nebo globální allowPrivate nejsou řešením.
6. Rotace integračního klíče: vytvořit nový, přepnout centrálu, ověřit spojení, odvolat starý a ověřit jeho odmítnutí. Automaticky nemažte historii potřebnou k obnově. Bounded pruneAudit a úklid expirovaných centrálních sessions/tokenů naplánuje provoz.

## Kontrolní brána

1. Composer install --no-dev --optimize-autoloader, validace a audit, webový OPcache a oprávnění adresářů pod skutečným webovým uživatelem. Produkční compileCheck=false vyžaduje řízené čištění kompilátů při každém deployi. Jádro má jedinou runtime Composer závislost firebase/php-jwt; Smarty/Texy nejsou potřeba.
2. Ověřte anonymní i přihlášené stránky, aktivní menu, locale,404,login/CSRF/POSTlogout a vlastní API routy. Privátní odpovědi a preview nesmějí být shared HIT.
3. Z centrály ověřte dva připojené weby a uživatele omezeného jen na jeden. Uložit koncept nesmí změnit veřejný článek. Preview je soukromé, publish výslovný. Souběžný zápis vrátí412 a zachová rozepsaný text; merge sám nic nezapisuje.
4. Ověřte překlady, revizi do draftu, smazání a obnovu struktury včetně překladů, nastavení skutečně vykonaného boxu a veřejný MIME/hash/nosniff obrázku. Ověřte odvolaný klíč, nedostupný web a návrat služby. Nedostupná centrála nesmí vyřadit veřejný web.
5. Po každém změněném artefaktu vyčistěte kompilovanou, datovou, fragmentovou a page cache i externí CDN/HTML cache. API a obsahové migrace flushují WBengine cache po commitu; externí přímé SQL/CDN vyžadují vlastní purge.
6. Zaznamenejte přesné commity, výsledky a odpovědnou osobu. Až po zelené bráně lze rozhodnout o merge a zveřejnění v4.0.0. Draft PR ani lokální pilot samy nejsou produkční nasazení.

## Návrat při problému

1. Zablokujte měnící požadavky a publikování v centrále. Uchovejte diagnostiku bez klíčů a citlivých těl.
2. Pro jediný obsahový převod použijte jeho konfliktně kontrolovaný rollback. Pokud byl obsah mezitím upraven, rollback jej nesmí přepsat; rozhodněte nad uloženým originálem a novou změnou ručně.
3. Pro návrat celé verze obnovte odpovídající zdroje, Composer lock/vendor, konfiguraci, média a ověřený DB snapshot. Samotné odebrání nových sloupců nezíská původní obsah ani ztracené hashe zpět. Legacy šablony obnovte společně se starým adaptérem.
4. Při obnově centrály použijte odpovídající MASTER_KEY, zrušte staré sessions podle situace a ověřte MFA/role i vzdálené klíče. Výpadek centrály neopravujte vypnutím autentizace cílových webů.
5. Znovu vyčistěte všechny cache, spusťte reprezentativní smoke a teprve potom obnovte zápisy. Zapište příčinu, přesný obnovený commit a stav dat do plánu.
