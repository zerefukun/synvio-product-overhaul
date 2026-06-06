# CLAUDE.md - synvio-product-overhaul

Last updated: 2026-06-05

Repo voor de BCW WordPress overhaul: custom plugin `oz-variations-bcw` + child theme `oz-theme` (deployed as `OzTheme`). Production target: https://beton-cire-webshop.nl.

## TL;DR voor een nieuwe LLM-sessie

- Code wordt **niet handmatig op de server bewerkt**. Alles via deze repo en GitHub Actions.
- Push naar `main` triggert auto-deploy naar productie via self-hosted GitHub Actions runner op de Hetzner server.
- Push naar `staging` triggert auto-deploy naar staging.beton-cire-webshop.nl.
- **Server is op dit moment leidend qua content**: Patrick werkt direct op de server in. Voordat je naar `main` pusht, sync server-state naar local. Zie `docs/migration-diff-2026-06-05.md`.
- Lees ook `docs/patrick-llm-server-context.md` voor server-operations context.

## Repo structuur

```
.
├── oz-variations-bcw/        # WP plugin (deployed naar plugins/oz-variations-bcw/)
│   ├── src/                  # esbuild source (LOCAL-ONLY, niet gedeployed)
│   ├── assets/               # built CSS/JS (wel gedeployed)
│   ├── includes/             # PHP classes (cart-manager, analytics, etc.)
│   ├── classes/              # PHP classes (product-processor)
│   ├── templates/            # PHP templates
│   ├── package.json          # npm config (LOCAL-ONLY, niet gedeployed)
│   ├── esbuild.config.mjs    # build config (LOCAL-ONLY)
│   └── oz-variations-bcw.php # plugin entry
│
├── oz-theme/                 # Child theme (deployed naar themes/OzTheme/)
│   ├── functions.php
│   ├── style.css
│   ├── css/, js/, img/, templates/
│   └── (sitemap-template, oz-scripts, screenshot)
│
├── .github/workflows/        # CI/CD
│   ├── deploy-bcw.yml         # main -> productie
│   └── deploy-bcw-staging.yml # staging -> staging
│
└── docs/                     # Documentatie + migration logs
    ├── migration-diff-2026-06-05.md     # Hoasted vs Hetzner verschillen
    ├── patrick-llm-server-context.md    # Server context voor LLMs
    ├── hetzner-migration-2026-06-04.md  # Migration record + pricing
    └── BUG-translate-path-encoding-loop-2026-06-05.md
```

## Tech stack

- **WordPress 6.9.4** + WooCommerce
- **PHP 8.3.31 FPM**
- **MariaDB 11.4** met **Redis 7** object cache
- **nginx 1.31 mainline** met FastCGI cache op tmpfs (LiteSpeed is verwijderd!)
- **Cloudflare proxy** + Worker `language-cookie-injector` voor geo->lang detectie
- **esbuild** voor JS/CSS bundling (oz-variations-bcw plugin)
- **GitHub Actions self-hosted runner** voor deploys

## Plugin build

```bash
cd oz-variations-bcw
npm install
npm run build   # esbuild, ~50ms
```

Output gaat naar `assets/css/` en `assets/js/`. Built files moeten in git want het deploy proces gebruikt die direct (GH Actions runner doet `npm ci && npm run build` voor productie).

## Deploy

Push naar `main`:
1. GitHub triggert workflow `Deploy BCW Plugin + Theme`
2. Self-hosted runner (op Hetzner) checkt code uit
3. `npm ci && npm run build` in oz-variations-bcw
4. Rsync `oz-variations-bcw/` -> `/home/bcw/public_html/wp-content/plugins/oz-variations-bcw/`
5. Rsync `oz-theme/` -> `/home/bcw/public_html/wp-content/themes/OzTheme/`
6. Purge nginx FastCGI cache: `/var/cache/nginx/bcw/`

Geen SSH-secrets meer nodig - runner draait OP de server.

## Belangrijke constraints

- **Geen LSCache plugin**. Hij is uit. Niet weer activeren - incompatible met nginx FastCGI cache.
- **Geen directe edits op de server in deze paden**. Volgende deploy overschrijft ze (rsync --delete).
- **Uitzondering: emergency hotfixes** mogen direct, maar moeten dezelfde dag in een commit landen.
- **`src/`, `package.json`, `node_modules/`, `esbuild.config.mjs`** worden niet gedeployed (zie excludes in workflow YAML).
- **Geen image-binary commits** (zie `.gitignore`: jpg/png/webp/svg/etc allemaal genegeerd).
- **Server vs local sync**: vóór elke serieuze ronde commits, sync server -> local. Server is bron van waarheid in deze migratie-fase.

## Server (verkort, zie patrick-llm-server-context.md voor details)

- Host: `5.9.62.15` (Hetzner AX42-U Ubuntu 24.04)
- SSH user: `synvio` (passwordless sudo)
- WP user: `bcw` (nologin shell)
- DB: `bcw_wp` (table prefix `OTBgD_`)
- Cache zone: `/var/cache/nginx/bcw/` op tmpfs
- Logs: `/home/bcw/logs/` (php-error, php-fpm-slow, access, error)
- Backups: borg naar Hetzner Storage Box elke 4u

## Recente lessons

- **2026-06-05 cart race**: `WC_Cart->set_quantity()` op stale cart key faalt met `get_tax_class() on null`. Guard toegevoegd in `oz_cart_drawer_update` en `OZ_Cart_Manager::calculate_addon_prices`. Zie `/home/bcw/_notes/2026-06-05-cart-race-fatih.md`.
- **2026-06-05 Mollie 20s timeout**: Hetzner firewall blokte TCP return-traffic outbound. Fix: "TCP established returns ack" rule boven "Block rest" in Hetzner Robot firewall.
- **2026-06-05 ATC visibility**: `catalog_visibility !== 'hidden'` check te streng. Verwijderd in `class-ajax-handlers.php`.

## Veelvoorkomende commands

```bash
# Lokaal builden
cd oz-variations-bcw && npm run build

# Status server
ssh synvio@5.9.62.15 'sudo tail -30 /home/bcw/logs/php-error.log'

# Cache purge handmatig
ssh synvio@5.9.62.15 'sudo find /var/cache/nginx/bcw -type f -delete'

# WP CLI als bcw user
ssh synvio@5.9.62.15 'cd /home/bcw/public_html && sudo -u bcw wp <cmd>'

# DB query
ssh synvio@5.9.62.15 'sudo mariadb bcw_wp -e "SELECT ..."'
```

## Wat heeft Patrick gewijzigd? (auto-snapshot log)

Sinds 2026-06-05 draait op alle 4 sites een hourly git auto-snapshot van `wp-content`.
Fatih gebruikt mij (Claude) om hieruit te kijken; geen email-rapport opgezet.

| Site | Cron tijd | Pad | User |
|---|---|---|---|
| bcwstaging | xx:05 | `/home/bcwstaging/public_html/wp-content` | bcwstaging |
| bcw prod | xx:10 | `/home/bcw/public_html/wp-content` | bcw |
| epoxystone | xx:15 | `/home/epoxystone/public_html/wp-content` | epoxystone |
| smartdeco | xx:20 | `/home/smartdeco/public_html/wp-content` | smartdeco |

Cron-config: `/etc/cron.d/staging-safety-net`. Script: `/usr/local/sbin/git-autocommit-site.sh <user>`
(bcwstaging gebruikt aparte oudere `git-autocommit-bcwstaging.sh`).

**Hoe Claude Patrick's werk leest** (use cases die Fatih kan vragen):

```bash
# "Wat heeft Patrick vandaag gedaan op BCW?"
ssh synvio@5.9.62.15 \
  "sudo -u bcw git -C /home/bcw/public_html/wp-content log --since=today --stat"

# "Wat is deze week veranderd op epoxystone?"
ssh synvio@5.9.62.15 \
  "sudo -u epoxystone git -C /home/epoxystone/public_html/wp-content log --since='1 week ago' --stat"

# "Toon de complete diff van de laatste auto-snapshot op smartdeco"
ssh synvio@5.9.62.15 \
  "sudo -u smartdeco git -C /home/smartdeco/public_html/wp-content show HEAD"

# "Welke regel in dit bestand is door Patrick gewijzigd?"
ssh synvio@5.9.62.15 \
  "sudo -u bcw git -C /home/bcw/public_html/wp-content blame <path>"

# "Wanneer is dit bestand voor het eerst toegevoegd?"
ssh synvio@5.9.62.15 \
  "sudo -u bcw git -C /home/bcw/public_html/wp-content log --diff-filter=A -- <path>"
```

**Wat wordt getrackt**: `themes/`, `mu-plugins/`, custom `plugins/oz-*` + `plugins/bcw-*` + `plugins/sd-*` + `plugins/keuzehulp-*`

**Niet getrackt**: `uploads/`, `cache/`, `wflogs/`, `litespeed/`, `*.log`, `*.bak`, `node_modules/`, WP core plugins (WooCommerce/Yoast/etc.)

Errors in script naar `/home/<site>/logs/git-autocommit.log`.

## Wie kan helpen

- **Fatih** (Synvio Web Solutions, zhouikun@gmail.com) - server, deploy, infra
- **Patrick** (eigenaar BCW) - business logic, plugin features, design
