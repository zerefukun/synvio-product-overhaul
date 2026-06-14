# Briefing Patrick — waarom vandaag (13 juni) geen sales op BCW

**Aan:** Patrick
**Van:** Fatih
**Datum:** 13 juni 2026
**Onderwerp:** Naweleffecten van het database-incident van 12 juni en wat we vragen om herhaling te voorkomen

---

## TL;DR

Op 12 juni tussen 19:08 en 19:18 heeft een AI-agent op jouw thuismachine via directe SQL ~9.800 rijen uit de productie-database verwijderd, inclusief de homepage, ~70 pagina's, alle menu-items, ~40 blog­posts en ~6.000 oude orders. De site gaf bijna een uur 404, ik heb tot diep in de nacht hersteld uit borg-backups. Dat we vandaag geen verkoop hebben is een direct gevolg van:

1. De volledige site lag ~50 minuten plat tijdens piekuren (19:00-20:00 op een vrijdag = hoog verkoop-moment).
2. Google heeft tijdens het incident 404's en lege pagina's gecrawld. Een deel van de rankings is daardoor verschoven; sommige ten goede (`beton cire badkamer` zit nu op #3 met de homepage), maar de hoofdcategorie `betonstuc` (1.600 zoekvolume) is van #20 naar buiten beeld gevallen.
3. Bezoekers die gisterenavond probeerden te kopen kregen een 404 of een stukgeknipte navigatie te zien. Die mensen komen vandaag niet automatisch terug.
4. De cart-drawer en /producten/ filter heb ik vannacht en vanochtend nog gefixt; betalingsverwerking zelf was altijd in orde.

Het probleem is niet wat de agent probeerde te doen ("ruimt redirects op" of "fix de daling in keywords"). Het probleem is dat hij raw `DELETE FROM` statements liet uitvoeren op de live database zonder backup vooraf en zonder bevestiging. Hieronder vraag ik wat we van jou nodig hebben om dit voortaan technisch onmogelijk te maken.

---

## Tijdlijn 12 juni

| Tijd (CEST) | Wat gebeurde |
|---|---|
| Hele dag | 314 SSH-commando's vanaf 77.171.196.15 (jouw thuis-IP) met de key `pbuff@Patitohome`. Patroon: typische LLM-agent activiteit. |
| 16:07 | Laatste schone borg-backup van de database (dit is wat ons gered heeft). |
| 19:08 | Eerste delete-statements beginnen te raken |
| 19:18 | Massa-delete voltooid: ~9.800 posts-rijen + ~1.100 options weg. Onder andere: homepage (ID 2358), `siteurl`, alle menu-items "Drawer Footer" en "Shop Sidebar", contact, blog-index, ~40 blogposts, 6.000 oude orders. |
| 19:18 – 19:30 | Site geeft 404 op homepage, /ruimtes/*, /contact/, /blog/, /veelgestelde-vragen/. WordPress kan niet meer bootstrappen omdat `siteurl` weg is. |
| 19:30 – 20:00 | Mijn eigen deploy (PU-explainer) faalde op de smoke-tests. Daardoor kreeg ik de melding. Diagnose: het was niet mijn deploy, het was een DB-incident van enkele minuten daarvoor. |
| 20:00 – 03:00 | Herstel: borg-dump van 16:07 + nachtdump 03:30 + 573 verse rijen uit de Redis-cache gecombineerd. Alle pagina's, menu's, oude orders teruggezet. Geen klant- of nieuwe-order data aangeraakt. |
| 03:30 (vandaag) | Site volledig functioneel. SEO/Google nog aan het bijwerken. |

**Niets in de audit-log.** Geen WordPress-event, geen `post_modified` update, geen admin-actie. De deletes liepen via raw SQL buiten WordPress om. Het standaard Audit-log plugin had hier nooit op kunnen reageren.

**Geen backup vooraf.** De agent heeft geen `mysqldump`, geen `wp db export`, niets gemaakt voor hij begon te verwijderen. Als de borg-backups van vier uur eerder er niet waren geweest, was de schade onomkeerbaar.

---

## Wat dit vandaag concreet voor de verkoop betekent

### Directe omzetderving (12 juni, 19:00-20:00)
Vrijdagavond is in deze branche een hoge omzet-tijd (mensen plannen weekend-projecten). De site was effectief plat tussen 19:18 en 20:00. Voor BCW geldt een gemiddeld basket-bedrag van ~€88. Met een typische conversie van enkele procenten op enkele honderden bezoekers in dat uur ben je ergens tussen de 1 en 5 orders verloren — een schatting van €100 tot €500 in directe orderverlies.

### Indirecte impact (vandaag, 13 juni)
- **`betonstuc` (1.600 zoekvolume) ranking is van #20 weggevallen**. De hoofdcategorie-pagina `/stucsoorten/betonstuc/` was de hoofdranker en is uit Google's resultaten verdwenen. Dit is het zwaarste verlies in de Semrush-rapportage van vannacht en zal niet vanzelf herstellen. Vermoeden: de pagina is door de agent SEO-meta-rewrite of door de delete + late herstel uit een oudere staat gerold.
- **Een aantal kennisbank-pagina's over kosten/gietvloer is gezakt** over meerdere queries tegelijk, wat wijst op één onderliggende verandering aan die pagina(s).
- **Kleur-cannibalisatie** is door de shuffle zichtbaarder geworden: dezelfde kleur (bv. "pale stone") staat onder meerdere productlijnen en Google verliest het overzicht. Dit is structureel en moet sowieso opgelost worden, maar de timing maakt het pijnlijker.

### Goede shifts (vermelden voor de balans)
- `beton cire badkamer` (3.600 zoekvolume): de homepage staat nu op #3. Niet de ruimte-pagina, maar het netto-effect is +234 traffic. Google heeft besloten de homepage relevanter te vinden voor deze head term.
- `kosten beton ciré badkamer`: `/kennisbank/wat-kost-beton-cire/` heeft een AI Overview-citatie gewonnen.

Dat de cijfers er dus niet alleen maar slechter op zijn, klopt — maar de pijn zit verspreid: `betonstuc` weg is een grote head term en dat overschaduwt de wins.

---

## Wat ik je vraag: twee afspraken

### 1. Eigen SSH-key, eigen Linux-gebruiker voor je agent

Vanaf nu krijgt je thuismachine geen toegang meer als `synvio` (die heeft passwordless sudo en kan alles), maar als een aparte gebruiker `bcwagent`. Die kan alleen specifieke `wp-cli`-commando's uitvoeren via een wrapper-script. Geen `wp db cli`, geen `wp eval`, geen raw SQL. Lezen mag, schrijven mag (post update, option update), maar bulk-deletes worden via dezelfde wrapper geweigerd.

Concreet betekent dit: jouw agent kan blijven doen wat hij vorige week deed (SEO-rondes, redirect-cleanups, content-tweaks). Hij kan niet meer in één commando 9.800 rijen weghalen.

**Wat ik van jou nodig heb:** akkoord op deze inrichting. Ik regel de Linux/SSH-kant zelf, je merkt er praktisch niets van behalve een ander commando om te connecten.

### 2. Audit-trigger op de posts-tabel

Ik zet een `BEFORE DELETE` trigger op de posts-tabel die elke verwijderde rij plus een tijdstempel kopieert naar een audit-tabel `bcw_audit_deleted_posts`, 90 dagen terug bewaard. Volgende keer dat iemand iets gerommeld heeft, kunnen we exact zien wát en wanneer en eenvoudig herstellen, zonder borg-dumps te hoeven dragen.

**Wat ik van jou nodig heb:** niets, alleen ter info.

---

## En aan mijn kant

Naast de afspraken hierboven zet ik vandaag ook nog:

- MariaDB binary logging aan (point-in-time recovery; stond uit, was een gemiste verdedigingslaag).
- Een mu-plugin "tripwire" die meer dan 50 deletes per minuut hard blokkeert met een fatal + alert-mail naar `info@`.
- Vijf honeypot-posts met sentinel-ID's die elk uur worden gecontroleerd; als ze verdwijnen krijg ik direct een alert en wordt automatisch hersteld uit de laatste borg-dump.
- Backup-cadens omhoog: nu elke 4 uur borg, ik zet dit naar elk uur.

Geen van deze had ik vooraf hoeven inrichten als de standaard `synvio`-key niet ook door agent-tooling werd gebruikt. Het is een combinatie van mijn lakse standaard-configuratie en de manier waarop jouw agent toegang krijgt.

---

## Wat ik graag van je hoor

1. **Welk prompt of welke taak heb je je agent precies gegeven op 12 juni?** "Onderzoek waarom keywords dalen" had ik begrepen. Was er nog iets concreters, bijvoorbeeld "ruim oude data op" of "verwijder verlopen content"? Dat helpt om te begrijpen waar het zo grandioos verkeerd ging.
2. **Welke tool/agent draaide er precies?** Was het Claude Code, een lokale Aider-setup, iets eigen-gebouwds via OpenAI API? Dat is voor mij geen oordeel, maar het bepaalt wel hoe het agent-gedrag te begrenzen is aan jouw kant.
3. **Akkoord op punt 1 hierboven**? Zo ja, zet ik dat vandaag op. Zo nee, dan denken we samen aan een andere oplossing — maar zonder enige rem hierop kan ik de site niet rustig laten draaien.

---

## Korte recap voor als je dit doorscrolt

- Geen verkoop vandaag = naweleffect van 9.800 verwijderde rijen op 12 juni + tijdelijk-platte site tijdens vrijdagavond + verschoven Google-rankings.
- Site draait, geen klant- of betaaldata verloren.
- `betonstuc` ranking (1.600 zoekvolume) is uit beeld; dat herstel ik vandaag via content-revert.
- Voor herhalingsbestrijding heb ik twee afspraken nodig waar ik akkoord op vraag.

Dank voor je tijd. Vragen kan altijd via Whatsapp.
