# BCW deploy-gids: code van staging naar prod pushen (gedeeltelijk)

**Aan:** Patrick
**Van:** Fatih
**Versie:** 13 juni 2026

Een gids voor wanneer je een **stuk** van staging naar prod wil brengen (niet alles in één klap). Dit gebeurt vaak omdat staging meestal weken voorloopt op prod met work-in-progress, en je alleen het afgeronde, geteste stuk live wil zetten.

---

## Coördinatie: pushes worden netjes in de wachtrij gezet

Sinds we `cancel-in-progress: false` op de workflow hebben gezet (juni 2026) is gelijktijdig pushen geen drama meer. Push jij om 14:00:05 en Iboyla om 14:00:30 terwijl jouw deploy nog draait? Dan wacht haar run in de queue tot die van jou klaar is en draait dan netjes daarna. Geen meer half-gedeployde state, geen meer cancelled deploys.

**Eén ding om bewust van te zijn**: GitHub geeft maar één wachtrij-slot per workflow. Als er DRIE mensen binnen 30 seconden pushen terwijl er een deploy loopt, krijgt alleen de LAATSTE van die drie z'n run; de tussenliggende push wordt stilletjes gedropt. Voor jullie 2-3 mensen-team zelden een issue. Als je merkt dat je push "verdwenen" is (geen workflow gestart) na een snelle reeks pushes: gewoon opnieuw pushen.

**Voor `functions.php` blijft elkaar appen wel handig.** Twee mensen die binnen een uur dezelfde file reverse-syncen of beide editen leidt tot merge-conflicts, ongeacht de queue. Bij twijfel een snelle "ik pak even functions.php" via WhatsApp.

---

## Server-side flow (Patrick & Iboyla, vanaf de Hetzner box)

Jullie hoeven geen Windows-checkout, geen IDE, geen lokale tooling. Alles vanaf de SSH-sessie waar jullie Claude al draaien.

### Eenmalige check
Er staat op de server een werkende checkout van deze repo: `/home/bcwstaging/synvio-product-overhaul/`. Group-writable. De SSH-key voor push naar GitHub is al gekoppeld (deploy key met write-access op `zerefukun/synvio-product-overhaul`). Push gaat via SSH-host-alias `github-spo`.

### De basis-flow voor elke wijziging

```bash
ssh bcwstaging@5.9.62.15
cd /home/bcwstaging/synvio-product-overhaul

# 1. Altijd eerst pullen — anders krijg je non-fast-forward bij push.
git pull origin main      # of: git pull origin staging   (afhankelijk van je doel)

# 2. Edit je file(s) in oz-theme/ of oz-variations-bcw/.
#    LET OP: edit in DEZE checkout, niet in /home/bcw/.../wp-content/themes/OzTheme/
#    Die laatste is de gedeployde uitkomst — daar editen wordt overschreven door de
#    volgende deploy (en je werk komt nooit in git).

# 3. Stage bij naam (nooit git add .).
git add oz-theme/path/to/file.php

# 4. Commit met eigen identity (eenmalig opslaan met --global kan ook).
git -c user.name="Patrick" -c user.email="patrick@beton-cire-webshop.nl" \
    commit -m "fix(theme): korte omschrijving"

# 5. Push.
git push origin main      # → triggert deploy-bcw.yml         → rsync naar prod
# of:
git push origin staging   # → triggert deploy-bcw-staging.yml → rsync naar staging
```

Tussen push en live-resultaat zit ~3 minuten (build + rsync + smoke-tests). Volg de run op:
https://github.com/zerefukun/synvio-product-overhaul/actions

### Welke branch?

| Branch | Wat het doet | Wanneer kiezen |
|---|---|---|
| `main` | Deploy naar **productie** (`beton-cire-webshop.nl`) | Hotfix, productie-tweak, of staging-werk dat klaar is om live te gaan |
| `staging` | Deploy naar **staging** (`staging.beton-cire-webshop.nl`) | Werk-in-uitvoering dat eerst getest moet worden |

Push **nooit dezelfde commit naar allebei** in één actie. Push naar één branch, controleer, push daarna naar de andere als 't ook nodig is.

### Hotfix (= klein, urgent, direct naar prod)

Dezelfde flow als hierboven, maar je skipt staging. Werk maken, pushen naar `main`, 3 minuten wachten op smoke-tests. Klaar.

Belangrijk bij hotfixes:
- **Pull eerst altijd** (`git pull origin main`). Als ik of Iboyla net iets gepusht heeft krijg je anders non-fast-forward.
- **Stage bij naam** — geen `git add .`. Je wil zeker weten dat alleen jouw fix in de commit zit, niet random ongerelateerde wijzigingen die toevallig in de checkout staan.
- **Stuur een korte WhatsApp** ("ik push een hotfix voor X") als ik of Iboyla mogelijk ook iets aan het pushen is. De queue handelt het wel af maar voorkomt onverwachte resultaten.

### Bij rode workflow (smoke-tests gefaald → site is mogelijk stuk)

Vóór de rsync wordt een complete backup van het theme + plugin op de server gezet. Rollback duurt 10 seconden:

```bash
ssh bcwstaging@5.9.62.15
sudo rsync -a --delete /home/bcw/backups/pre-deploy/OzTheme/ /home/bcw/public_html/wp-content/themes/OzTheme/
sudo rsync -a --delete /home/bcw/backups/pre-deploy/oz-variations-bcw/ /home/bcw/public_html/wp-content/plugins/oz-variations-bcw/
sudo find /var/cache/nginx/bcw -type f -delete
```

Site is meteen terug naar de staat van voor je push. Daarna kijken wat er fout ging:
```bash
sudo tail -50 /home/bcw/logs/php-error.log
```

**Let op**: de pre-deploy backup houdt maar **één versie** vast. Elke deploy overschrijft de vorige. Doe je twee slechte deploys snel achter elkaar, dan kun je niet meer met deze rollback terug naar de eerste goede staat. Dan val je terug op de hourly snapshot-branch hieronder.

### Bij drift-check fail (push naar main wordt geweigerd)

Drift-check detecteert files die direct op prod gewijzigd zijn maar niet in de repo staan. Bijvoorbeeld: ik of een ander heeft live een file getweakt en die zit nog niet in `synvio-product-overhaul`.

```bash
# Pull de gedrifteerde file vanaf prod naar je checkout:
sudo cp /home/bcw/public_html/wp-content/themes/OzTheme/<file> \
        /home/bcwstaging/synvio-product-overhaul/oz-theme/<file>
sudo chown bcwstaging:bcwstaging /home/bcwstaging/synvio-product-overhaul/oz-theme/<file>

cd /home/bcwstaging/synvio-product-overhaul
git add oz-theme/<file>
git commit -m "sync: <file> (drift opgevangen)"
git push origin main
```

De workflow draait automatisch opnieuw.

### Bij non-fast-forward (push wordt geweigerd)

Iemand anders heeft net naar dezelfde branch gepusht.
```bash
git pull --rebase origin main
git push origin main
```

**Nooit** `git push --force` op main of staging.

### Welke safety nets draaien automatisch onder je push

- **Pre-deploy backup** (10 seconden rollback, zie hierboven)
- **6 smoke-tests** (workflow gaat rood bij fail)
- **Hourly auto-snapshot** in een lokale `staging-snapshots` branch op de server (point-in-time recovery, ook na meerdere slechte deploys)
- **MariaDB binlog** (7 dagen DB-recovery tot op de seconde)
- **5 SQL-triggers** (bulk-deletes worden geblokkeerd op 50-500 per minuut)
- **5 honeypot canaries** (hourly check; alert naar `info@` bij vermissing)
- **Borg-backup** elk uur naar Hetzner Storage Box

Voor jou betekent dit: een slechte push is 10 seconden terug te draaien. Een ergere fout een uur terug. DB-issues 7 dagen terug. Niets staat op één punt of falen.

### Wanneer NIET hotfixen vanaf de server

- Wijzigingen die meerdere files raken in code waar je niet zeker over bent → eerst naar `staging`-branch, daar testen op `staging.beton-cire-webshop.nl`, daarna pas naar `main`.
- Wijzigingen die afhankelijkheden in plugins/DB nodig hebben → mij erbij halen.
- Bij twijfel: WhatsApp.

---

## In 30 seconden: hoe een prod-push werkt

1. Je pusht naar `main`. Dat triggert GitHub Actions (`deploy-bcw.yml`) op de Hetzner self-hosted runner.
2. De runner doet (in volgorde): **drift-check** → **backup** → **build** plugin → **rsync** plugin + theme + public → **nginx cache purge** → **6 smoke-tests** op live URLs.
3. Faalt één van die stappen → workflow rood, geen prod-impact bij drift-check, en bij smoke-fail is de nieuwe code wel live (rollback handmatig).
4. Drift-check is je vriend: hij detecteert wijzigingen die jij of Iboyla direct op prod hebt gedaan en die nog niet in de repo staan. Pas op die files reverse-syncen → committen → opnieuw pushen.

**Belangrijkste regel**: nooit `git add .` of `git add -A` op `main`. Altijd files bij naam stagen, want functions.php en archive-product.php worden door meerdere mensen op meerdere plekken bewerkt.

---

## De vier soorten pushes die wij doen

### Type A — Reverse-sync vanaf prod naar repo
Jij of Iboyla heeft op prod een file aangepast (SEO-tweak, copy-fix). Voordat ik een nieuwe feature push moet ik die wijziging eerst terug in de repo zetten, anders gooit `rsync --delete` jouw werk weg.

**Voorbeeld**: commit `f3d8823` "sync: footer trim trilogy links + CO2 line (Patrick prod edit 11 jun)" — 1 file, 8 deletions, 0 insertions.

### Type B — Eén klein feature uit staging porteren naar main
Een specifieke fix die op staging klaar staat. Niet de hele staging-branch mergen, alleen die ene feature.

**Voorbeeld**: commit `cf25b27` "Cart drawer Eerder bekeken: fix first-slide glued to left edge" — 2 files (css + js), 8 insertions / 3 deletions.

### Type C — Groter feature uit staging porteren
Meerdere files samen, maar nog steeds binnen één scope.

**Voorbeelden**:
- `9274936` "feat(shop): faceted filter sidebar" — 6 files, 1.423 insertions
- `7910cd1` "PDP: Aanbreng & afwerking PU explainer per product line (port van staging)" — 4 files, 1.378 insertions, 0 deletions

### Type D — Hotfix direct op main
Hotfix die nooit door staging hoeft (te klein). Twee routes mogelijk:
- **Server-side** (Patrick & Iboyla): direct vanaf de SSH-sessie. Zie de sectie "Server-side flow" hierboven — dat is jullie default.
- **Windows-worktree** (mijn route): schrijf in een worktree vanaf `origin/main`, één file aanraken, pushen.

---

## De pre-push checklist (vier commando's, voor elke main-push)

Doe dit ALTIJD voor je gaat pushen, anders verlies je werk of gaat de deploy rood:

### 1. Drift-check op wp-content (zien wat er recent op prod is gedaan)
```bash
ssh synvio@5.9.62.15 'sudo -u bcw git -C /home/bcw/public_html/wp-content log --oneline | head -10'
```

De server commit elk uur op minuut :10 alle wijzigingen in `wp-content` automatisch naar een lokale git-repo (`/etc/cron.d/staging-safety-net`). Zie je commits met timestamps **ná** je laatste main-merge? Dan heeft iemand (Iboyla, ik, of een plugin) iets op prod aangepast. Reverse-sync dat eerst.

### 2. Recente deletes (zien wat er via SQL verwijderd is)
```bash
ssh synvio@5.9.62.15 'sudo mysql -e "SELECT * FROM bcw_wp.bcw_audit_deletes ORDER BY id DESC LIMIT 20"'
```

Sinds 13 juni hebben we 5 SQL-triggers die elke DELETE op posts / postmeta / options / terms / term_relationships loggen. Onverklaarbare rijen = onderzoek voor je deployt.

### 3. Verifieer dat de safety nets draaien
```bash
ssh synvio@5.9.62.15 'sudo cat /etc/cron.d/staging-safety-net /etc/cron.d/bcw-canary'
```

Beide cron-files moeten bestaan en niet leeg zijn.

### 4. Bij elke twijfel: stop, vraag eerst
Geen `rsync --delete` op prod, geen `wp post delete` op bulk, geen raw SQL die DELETE bevat. De triggers blokkeren bulk-deletes hoe dan ook (50 deletes/min op posts, 500 op postmeta), maar voorkomen is beter.

---

## Hoe je een selectieve push doet (de echte stappen)

Voor Type B en C — een specifieke feature uit staging naar prod brengen zonder de rest van staging mee te nemen.

### Stappen

**1. Maak een worktree op basis van prod (`origin/main`), niet staging.**

Dit voorkomt dat je per ongeluk andere staging-commits meeneemt:

```bash
cd C:\Users\zeref\OneDrive\OzIS\betoncire\synvio-product-overhaul
git fetch origin
git worktree add .claude/worktrees/deploy-mijnfeature -b deploy/mijnfeature origin/main
```

**2. Doe je drift-check (stap 1 hierboven).** Zie je commits op prod die nog niet in de repo zitten? Pull die files via `scp` of `rsync` van de server, commit ze met een `sync:` prefix:

```bash
scp synvio@5.9.62.15:/home/bcw/public_html/wp-content/themes/OzTheme/functions.php .claude/worktrees/deploy-mijnfeature/oz-theme/functions.php
cd .claude/worktrees/deploy-mijnfeature
git add oz-theme/functions.php
git commit -m "sync: functions.php (Patrick prod-edit X juni)"
```

**3. Cherry-pick of kopieer alleen je feature-files** uit staging. Twee manieren:

- **Cherry-pick** als de feature al één nette commit op staging is:
  ```bash
  git cherry-pick <staging-commit-sha>
  ```
- **Handmatig kopiëren** als de feature verspreid is over meerdere commits of niet schoon afgesloten:
  ```bash
  # Voor elke file die je wil mee porteren:
  scp .claude/worktrees/fix-mijnfeature/oz-theme/css/mijnfeature.css .claude/worktrees/deploy-mijnfeature/oz-theme/css/mijnfeature.css
  cd .claude/worktrees/deploy-mijnfeature
  git add oz-theme/css/mijnfeature.css <andere file paths>
  git commit -m "feat(scope): korte omschrijving"
  ```

**Belangrijk**: altijd files bij naam stagen. **Nooit** `git add .`

**4. Push naar main:**

```bash
git push origin deploy/mijnfeature:main
```

GitHub Actions start. Volg de run in real-time op:
https://github.com/zerefukun/synvio-product-overhaul/actions/workflows/deploy-bcw.yml

**5. Wacht op de smoke-tests groen.** Duurt ~3 minuten. Krijg je rode workflow → zie "Bij failures" hieronder.

---

## Wat de deploy-workflow precies doet (zodat je weet wat er live komt)

### Wat wordt gedeployed
- `oz-theme/` → `/home/bcw/public_html/wp-content/themes/OzTheme/` met `rsync -az --delete`
- `oz-variations-bcw/` → `/home/bcw/public_html/wp-content/plugins/oz-variations-bcw/` met `rsync -az --delete` (alleen built JS + PHP; `src/`, `node_modules`, `*.map`, `package.json` worden uitgesloten)
- `public/` → `/home/bcw/public_html/` zonder `--delete` (additief; alleen llms.txt / robots.txt etc.)

### Wat NIET wordt gedeployed
- mu-plugins (`wp-content/mu-plugins/`) — die leven alleen op de server, deploy raakt ze niet
- Custom plugins die we niet onderhouden (Yoast, WooCommerce, etc.) — niet in repo
- Theme `.bak*` en `.disabled*` files op prod blijven staan (excluded uit zowel drift-check als rsync)

### Drift-check details
- Vergelijkt de file-list die rsync zou aanraken tegen de file-list in jouw commit-range
- Faalt als rsync files zou overschrijven die NIET in jouw push zitten = iemand heeft direct op prod gewerkt
- Dekt alleen de **theme** (`oz-theme/`), niet de plugin of public
- Wordt **overgeslagen** bij:
  - Eerste push naar `main` (geen vorige SHA)
  - `workflow_dispatch` (handmatige trigger via Actions tab)
  - Force-push (vorige SHA gewist)

Op `workflow_dispatch` verlies je dus de drift-bescherming. Gebruik alleen als je 100% zeker weet dat prod = repo.

### Pre-deploy backup
- Voor de rsync: complete theme + plugin worden 1:1 gekopieerd naar `/home/bcw/backups/pre-deploy/`
- **Eén snapshot retentie** — elke nieuwe deploy overschrijft de vorige backup
- Voor langere historie: hourly git-snapshots van wp-content + borg-dumps elk uur

### Build
- Alleen plugin `oz-variations-bcw` wordt gebouwd (Node 20, `npm ci && npm run build`)
- Theme files worden as-is geshipped (geen build-pipeline)

### Smoke-tests (allemaal moeten groen om succes te zijn)
- `/` → 200 + bevat `googletagmanager` + bevat geen "kritieke fout" / "critical error"
- `/ruimtes/beton-cire-badkamer/` → 200
- `/beton-cire-easyline-all-in-one/` → 301
- `/beton-cire-all-in-one/` → 200
- `/winkelwagen/` → 200
- `/mijn-account/` → 200

---

## Bij failures

### Drift-check faalt
GitHub Actions log laat zien welke files in conflict zijn. Pak die files één voor één:

```bash
# pull elke gedrifteerde file van prod
scp synvio@5.9.62.15:/home/bcw/public_html/wp-content/themes/OzTheme/<file> <repo>/oz-theme/<file>
```

Commit met `sync:` prefix die de auteur en datum noemt:
```bash
git add oz-theme/<file>
git commit -m "sync: <file> (Patrick X juni perf-tweak)"
git push origin deploy/mijnfeature:main
```

Workflow draait automatisch opnieuw.

### Smoke-tests falen
Site is op dat moment al gedeployed met de nieuwe code. Snel rollbacken:

```bash
ssh synvio@5.9.62.15
sudo rsync -a --delete /home/bcw/backups/pre-deploy/OzTheme/ /home/bcw/public_html/wp-content/themes/OzTheme/
sudo rsync -a --delete /home/bcw/backups/pre-deploy/oz-variations-bcw/ /home/bcw/public_html/wp-content/plugins/oz-variations-bcw/
sudo find /var/cache/nginx/bcw -type f -delete
```

Daarna kijken wat er fout ging in de PHP error log:
```bash
sudo tail -50 /home/bcw/logs/php-error.log
```

### Push lukt niet (non-fast-forward)
Iemand heeft naar main gepusht na jouw laatste fetch:
```bash
git fetch origin
git rebase origin/main
git push origin deploy/mijnfeature:main
```

**Nooit** `git push --force` op main.

---

## Valkuilen die wij eerder hebben gehad

### "Selectief pushen vanaf verouderde basis"
Commit `9e94d44` "fix(functions): herstel Patrick's werk - vorige push kwam van verouderde basis" — een eerdere push was gemaakt op basis van een main-state van vóór de reverse-sync, en revertte 4 van jouw features stilletjes. Reparatie was de gemiste features handmatig terugzetten.

**Voorkom dit door**: altijd je worktree maken vanaf de actuele `origin/main` (niet `main`, niet een oudere branch), en altijd eerst drift-check te draaien.

### "Build voor je pusht is niet nodig"
De plugin wordt op de runner gebuild. Lokaal hoef je niets te bouwen. Push gewoon de PHP en `src/`. De `assets/` mag je laten zoals 'ie was.

### "functions.php is een danger-zone file"
Hij wordt bewerkt door:
- Jou (perf-tweaks, defer-handles, preconnects)
- Iboyla (occasioneel SEO-meta-aanpassingen via code)
- Mij (theme features)

**Altijd reverse-syncen vóór een nieuwe feature die functions.php raakt.** Anders revert je iemand zijn werk.

### "rsync --delete is permanent"
Op prod betekent een `rsync -a --delete` van een file die niet in de repo zit = die file wordt verwijderd. De drift-check waarschuwt hiervoor, maar je moet de waarschuwing wel lezen. Negeer rode workflows niet.

---

## De vijf DB-bescherming-lagen die nu actief zijn

Goed om te weten, niet om actief te bedienen:

1. **5 SQL-triggers** op posts/postmeta/options/terms/term_relationships. Loggen elke delete naar `bcw_audit_deletes`. Boven rate-limit (50-500/min) wordt de DELETE op SQL-niveau geweigerd. Geldt voor jouw, mijn, plugin-code, alles.
2. **5 honeypot canaries** in de DB (ID 88800001..88800005). Hourly cron checkt of ze er nog zijn; bij vermissen alert naar `info@`.
3. **Hourly auto-snapshot** van wp-content via git op de server. Hier komt de drift-check info vandaan.
4. **MariaDB binlog ON**. 7 dagen point-in-time recovery — als er iets misgaat kunnen we precies terug naar het moment vóór de fout.
5. **Borg-backups elk uur** naar Hetzner Storage Box. Volledige DB + filesystem.

Voor jou betekent dit: als jouw agent ooit per ongeluk iets bulkmatig verwijdert, wordt het na ~50 rijen geblokkeerd, gelogd, gedetecteerd binnen het uur, en kunnen we tot op de seconde herstellen.

---

## Snelle referentie

| Wat | Commando |
|---|---|
| Drift-check (prod-edits) | `ssh synvio@5.9.62.15 'sudo -u bcw git -C /home/bcw/public_html/wp-content log --oneline \| head -10'` |
| Recente SQL-deletes | `ssh synvio@5.9.62.15 'sudo mysql -e "SELECT * FROM bcw_wp.bcw_audit_deletes ORDER BY id DESC LIMIT 20"'` |
| Worktree vanaf prod-staat | `git worktree add .claude/worktrees/deploy-<naam> -b deploy/<naam> origin/main` |
| Push naar prod | `git push origin deploy/<naam>:main` |
| Workflow runs bekijken | https://github.com/zerefukun/synvio-product-overhaul/actions |
| Rollback theme | `sudo rsync -a --delete /home/bcw/backups/pre-deploy/OzTheme/ /home/bcw/public_html/wp-content/themes/OzTheme/` |
| Rollback plugin | `sudo rsync -a --delete /home/bcw/backups/pre-deploy/oz-variations-bcw/ /home/bcw/public_html/wp-content/plugins/oz-variations-bcw/` |
| Nginx cache purge | `sudo find /var/cache/nginx/bcw -type f -delete` |
| PHP error log | `sudo tail -50 /home/bcw/logs/php-error.log` |

---

## Vragen? Direct via WhatsApp.

Voor de eerste paar deploys: laat me meekijken via een gedeelde sessie. Daarna doe je het zelf.
