# BCW Migration: Hoasted vs Hetzner - Diff Audit

Datum: 2026-06-05
Doel: identificeer alle verschillen tussen oude Hoasted shared hosting (`v2284.hostingsecure.com`, user `betoncire`) en nieuwe Hetzner dedicated (`5.9.62.15`, user `bcw`) die post-migratie issues kunnen veroorzaken (Cookiebot, GA, settings issues).

## Belangrijkste conclusie up-front

**De DATA is correct gemigreerd.** Cookiebot CBID, GTM container ID, alle plugin-settings in de DB zijn IDENTIEK op beide servers. De plugin-versies zijn IDENTIEK. De gerenderde HTML bevat dezelfde Cookiebot- en GTM-script tags op beide.

**De issues zitten in: caching layer (LSCache weg, nginx FastCGI ervoor in de plaats), Cloudflare Worker die cookies toevoegt, ontbrekende custom scripts/automation, en PHP-versie sprong 8.0 → 8.3.**

---

## Status checks

- [x] PHP version + extensions
- [x] WordPress version + active plugins
- [x] wp-config.php constants
- [x] wp_options (Cookiebot/GA/GTM/IHF/siteurl)
- [x] Mu-plugins
- [x] Cron jobs (system + WP)
- [x] Custom scripts directory
- [x] Plugin versies (Wordfence, Cookiebot, GTM4WP)
- [x] Rendered HTML (live frontpage)
- [x] Response headers (nginx vs LSCache)
- [x] .user.ini PHP overrides
- [x] Redis object cache state
- [ ] CSP / Wordfence WAF policy (gedeeltelijk)
- [ ] CF Worker cookie-impact op Cookiebot consent state

---

## Finding 1: PHP versie sprong 8.0.30 → 8.3.31 (CRITIEK)

| | Hoasted | Hetzner |
|---|---|---|
| PHP | 8.0.30 NTS | 8.3.31 NTS |
| Build | May 2026 | May 2026 |
| OPcache | v8.0.30 | v8.3.31 |
| Modules | 51 | ~55 |

**Risico**: HOOG. Veel deprecations in 8.0 zijn FATAL ERRORS in 8.1+. Plugins die niet zijn bijgewerkt sinds 2024 kunnen breken op:
- Dynamic property deprecation (8.2+)
- Implicit nullable types (8.4+)
- `mt_rand()` removed signatures
- `intl` API changes
- `imagick` interface changes

**Te checken**:
- PHP error log voor `Deprecated` / `Warning` / `Fatal error` van specifieke plugins na elke pageload
- Vergelijk specifieke plugins die WP Mail SMTP, Cookiebot, GTM4WP, Wordfence aansturen

**Impact op Cookiebot/GA**: zeer waarschijnlijk niet direct (beide draaien al jaren op 8.3 bij andere klanten), maar als één plugin in de boot-chain fataal faalt, kan dat een knock-on effect hebben dat consent-API stilzet.

---

## Finding 2: PHP modules verschillen

**Hoasted heeft maar Hetzner NIET**:
- `imap` - gebruikt door WP Mail SMTP voor IMAP inbox-check (mail-reply receive)
- `sqlite3` + `pdo_sqlite` - sommige plugins (WSAL) hadden vroeger sqlite-fallback voor logs
- `timezonedb` - fallback tijdzone-data (PHP heeft zelf ook timezone data, redundant)
- `i360` - Imunify360 host-extension (Hoasted-specifiek, geen WP-impact)

**Hetzner heeft maar Hoasted NIET**:
- `redis` ← gebruikt door redis-cache plugin
- `imagick` ← image processing (sterker dan GD)
- `igbinary` ← Redis serializer
- `gettext`, `random`, `shmop`, `sysvmsg/sem/shm`, `FFI` ← system-niveau

**Impact**: MIDDEL.
- `imap` ontbrekend kan WP Mail SMTP IMAP-feature breken (mail-ontvangst). Niet kritiek voor BCW als die alleen send doet.
- `sqlite3` ontbrekend - geen modern plugin gebruikt dit voor primary storage, MariaDB is altijd er.
- Geen direct verband met Cookiebot of GA.

**Te verifiëren**: WP Mail SMTP settings → gebruikt die IMAP voor inbox-check? Zo ja: installeer `php8.3-imap` op Hetzner.

---

## Finding 3: LSCache verwijderd, nginx FastCGI ervoor in de plaats (CRITIEK voor GA/Cookiebot)

**Hoasted**: LiteSpeed Cache plugin actief. Dat plugin doet:
- Page cache (HTML caching)
- Object cache (transients in shared memory)
- Browser cache headers (Cache-Control)
- CSS/JS minify + combine
- Image lazy-load injection
- Image WebP-conversie on-the-fly
- ESI fragments (admin-bar, mini-cart apart cacheen)
- **Per-cookie cache variants** (LSCache kan logged-in vs logged-out variant van zelfde URL apart cacheen)
- LSCache-helper voor Cookiebot (eigen integration plugin van Cookiebot werkt mét LSCache)

**Hetzner**: nginx FastCGI cache + Redis object cache. Dat doet:
- Alleen page cache (HTML serving van tmpfs)
- Redis object cache via `redis-cache` plugin
- Browser cache headers via nginx config
- GEEN CSS/JS minify
- GEEN image lazy-load
- GEEN image WebP-conversie on-the-fly
- GEEN ESI
- **Geen cookie-aware cache variants** - een URL = één cache-entry voor iedereen

**Cookiebot-impact**:
- LSCache had specifieke "Cookiebot addon" support (option `litespeed_cache` in `cookiebot_available_addons`). Op Hetzner is dat addon nutteloos (LSCache plugin niet actief).
- Cookiebot's auto-blocking werkt CLIENT-SIDE in JavaScript, dus de page cache laag zou geen verschil moeten maken voor de blocking zelf.
- MAAR: als de page met een Set-Cookie van vorige bezoeker wordt gecached door nginx, krijgt elke volgende bezoeker die cookie geserveerd. Zie Finding #4 - `_fbp` cookie wordt geserveerd uit cache.

**GA-impact**:
- GTM container (GTM-P27Z67VR) staat in HTML, fired bij elke page load
- GTM is GEEN consent-mode geïntegreerd (`integrate-cookiebot: false` in GTM4WP-options) - was al zo op Hoasted
- Op Hoasted: dezelfde setting werkte. Op Hetzner: zou ook moeten werken.

**JS/CSS asset issue**:
- Op Hoasted: LSCache combineerde 20+ JS files → 1-2 grote bundles. Minder HTTP requests.
- Op Hetzner: 20+ JS files apart gelaad. **TRAGER initial render**, kan Cookiebot's "first interactive" detection beïnvloeden.

**Risico**: HOOG voor performance, MIDDEL-HOOG voor Cookiebot timing.

---

## Finding 4: Set-Cookie wordt gecached door nginx (CRITIEK bug)

**Beide servers** retourneren in de homepage response:
```
set-cookie: _fbp=fb.1.1780665581896.1257691630.AQECAQIB; ...
```

De `_fbp` waarde is **identiek** in beide tests vanaf verschillende sessies. Dat betekent: **de eerste bezoeker creëerde `_fbp`, en die cookie wordt nu uit cache geserveerd aan ELKE volgende bezoeker**.

**Impact**:
- Facebook Pixel ziet alle bezoekers als één persoon (zelfde `_fbp` ID)
- Conversie-tracking via Facebook is kapot
- Audiencebuilding via Facebook is kapot
- Mogelijk ook andere cookies (`woocommerce_*` etc.) die door cache lekken

**Oorzaak op Hetzner**: nginx config heeft `fastcgi_ignore_headers ... Set-Cookie ...`. Dat maakt nginx blind voor de Set-Cookie header bij cache-beslissing → page wordt gecached MET Set-Cookie erin.

**Bewijs dat dit ook op Hoasted speelde**: same `_fbp` waarde teruggekomen. Maar de issue is op Hetzner waarschijnlijk ERGER omdat nginx FastCGI cache TTL is 4h, en LSCache deed dit niet (LSCache strip standaard Set-Cookie uit cache).

**Aanbevolen fix op Hetzner**:
- nginx config: skip cache voor responses die `Set-Cookie` met user-specifieke value bevatten
- Of: voeg pagina's met FBP/GA-cookies aan skip_cache rules toe
- Of: strip `_fbp`, `woocommerce_*`, `_ga*` uit cached responses

**Risico**: ZEER HOOG voor marketing/tracking. Patrick zal hier minder conversies in zien.

---

## Finding 5: WP-cron werking - system cron OK, achterstand normaal

| | Hoasted | Hetzner |
|---|---|---|
| `DISABLE_WP_CRON` | `false` (default) | **`true`** |
| Trigger mechanism | WP page loads triggeren wp-cron | systemd-cron triggert wp-cron elke minuut |
| `/etc/cron.d/wp-cron` | n.v.t. (geen system cron) | bestaat: `* * * * * root sudo -u bcw wp cron event run --due-now` |
| Pending events | 48 totaal | 53 totaal |
| Recent execution | onbekend (geen wp cli logs) | bevestigd om de 1 min (`journalctl`) |

**Status**: WP-cron werkt op Hetzner. Het hoge aantal events (53) is normaal - veel zijn weekly/monthly events met `next_run` ver in de toekomst.

**Risico**: LAAG.

---

## Finding 6: Hoasted-only custom scripts NIET gemigreerd (HOOG risico voor automation)

Op Hoasted in `/home/betoncire/scripts/` (NIET op Hetzner):

| Script | Doel | Cron-frequentie | Status na migratie |
|---|---|---|---|
| `db_dump_staging.sh` | Dagelijkse DB-dump staging | 03:00 | NIET migrated |
| `git_autocommit_wpcontent.sh` | Auto-commit wp-content naar git (safety net) | elk uur :05 | NIET migrated |
| `prewarm_all.php` + `prewarm_*.php` | Cache warming | n.v.t. (script) | NIET migrated |
| `run_prewarm.sh` | Cache prewarm 04:00 | dagelijks | NIET migrated (warm-cache.sh op Hetzner is anders) |
| `run_email_concepts.sh` | BSQ email-concepts generatie | elke 3 uur | NIET migrated (BSQ runt niet op prod, alleen staging) |
| `webp_bulk_convert.sh` | Bulk WebP-conversie | handmatig | NIET migrated |
| `what_changed.sh` | Diff-tool voor wp-content | handmatig | NIET migrated |
| `wp_stdio_fix.php` | WP-CLI stdio compat fix | n.v.t. | NIET migrated |
| `update-tr-homepage.py` | Vertaalde homepage updates | handmatig | NIET migrated |
| `write-tr-slugs.py` | Vertaalde slug-import | handmatig | NIET migrated |
| `restore/` dir | Restore-scripts | n.v.t. | NIET migrated |
| `migration/` dir | Migration helpers | n.v.t. | NIET migrated |

**bcw-social-queue (BSQ) cron-jobs** op Hoasted (vanaf `/home/betoncire/staging/`):
- `0 8 * * 1 wp bsq import-customer-photos --max=10` (maandag 08:00)
- `0 9 * * * wp bsq send-daily-reminder --to=info@beton-cire-webshop.nl` (dagelijks 09:00)
- `0 */3 * * * run_email_concepts.sh` (elke 3u)
- `0 4 * * * wp bsq fetch-performance` (dagelijks 04:00)
- `0 */4 * * * wp bsq health-check --alert` (elke 4u)

**Op Hetzner**: BSQ plugin is wel geïnstalleerd op staging (`/home/bcwstaging/public_html/wp-content/plugins/bcw-social-queue`) maar er zijn GEEN cron-jobs gedefinieerd om die te triggeren.

**Impact**:
- Geen daily reminders worden verstuurd → Patrick mist dagelijkse social media triggers
- Geen email-concepts gegenereerd → workflow stopt
- Geen weekly customer photos import → user-generated content komt niet binnen
- Geen performance fetch / health check → BSQ runt blind
- Geen DB dumps van staging → staging-only data niet backuped

**Aanbevolen actie**: 
- Migreer `/home/betoncire/scripts/` naar `/home/bcwstaging/scripts/` (of `/usr/local/sbin/` als root-level)
- Migreer crontab entries van betoncire-user naar bcwstaging-user
- Fix alle hardcoded paths in scripts (`/home/betoncire` → `/home/bcwstaging`)
- Update WP-CLI command paths

**Risico**: HOOG voor business automation, LAAG voor BCW prod direct.

---

## Finding 7: Mu-plugins - IDENTIEK

Beide servers hebben dezelfde 7 mu-plugins:
- automation-by-installatron.php
- burst_rest_api_optimizer.php
- channable-category-filter.php
- fix-facebook-pixel-loading.php
- oz-fix-protocol-relative.php
- oz-fix-stucsoorten-pagination.php
- oz-login-defer-fix.php

**Status**: gemigreerd OK. Geen diff.

---

## Finding 8: wp-config.php constants

**Hoasted-only**:
- `define( 'WP_CACHE', true );` - vereist door sommige cache-plugins (niet meer relevant zonder LSCache)
- `define( 'DISABLE_WP_CRON', false );` - default behaviour

**Hetzner-only**:
- `define( 'DISABLE_WP_CRON', true );` - system cron neemt over
- `define( 'WP_REDIS_DATABASE', 0 );` - Redis-cache plugin config
- `define( 'WP_REDIS_PREFIX', 'bcw:' );` - Redis-cache plugin config

**DB credentials** (verwacht ander):
- Hoasted: `DB_NAME=betoncire_wp_9zro0`, `DB_USER=betoncire_wp_boavs`
- Hetzner: `DB_NAME=bcw_wp`, `DB_USER=bcw_wp`

Beide hosts: `DB_HOST=localhost:3306`, `table_prefix='OTBgD_'`, same Turnstile keys.

**Risico**: LAAG. Verschillen verklaarbaar door migratie.

---

## Finding 9: Plugin-versies - IDENTIEK voor de relevante

| Plugin | Hoasted | Hetzner |
|---|---|---|
| Cookiebot | 4.7.1 | 4.7.1 |
| GTM4WP (duracelltomi-...) | 1.22.3 | 1.22.3 |
| Wordfence | 8.2.2 | 8.2.2 |

Geen verschil. De DB-state is hetzelfde. De HTML output is hetzelfde.

---

## Finding 10: Cloudflare Worker `language-cookie-injector` (mogelijk impact)

Op Hetzner draait sinds de migratie de CF Worker `language-cookie-injector.ozinternetservices-company.workers.dev` op routes voor BCW, epoxystone, en smartdeco.

**Wat de Worker doet bij elk request**:
1. Leest `CF-IPCountry` header
2. Mapping country → language code (NL/BE/SR → nl, DE → de, etc.)
3. Voegt `epoxy_lang=<lang>` toe aan **request Cookie header**
4. Voegt `Set-Cookie: epoxy_lang=<lang>; SameSite=Lax; Secure` toe aan response

**Potentiële issues met Cookiebot**:
- Cookiebot vereist een specifieke cookie voor consent (`CookieConsent`). Worker raakt die niet aan.
- MAAR: Worker append `epoxy_lang` aan request cookie. Als bezoeker al een complexe cookie-string heeft, kan dit edge-cases triggeren in Cookiebot's parsing.
- De Worker zet ook `Set-Cookie` op response. Browsers ondersteunen multiple `Set-Cookie` headers maar oude/strikt-CORS-mode requests kunnen de tweede negeren.

**Op Hoasted**: bestaat deze Worker niet. epoxy_lang werd alleen via WP plugin code gezet (PHP cookie).

**Impact**: MOGELIJK MIDDEL. Niet bevestigd dat Worker daadwerkelijk Cookiebot breekt, maar het is een NIEUWE laag tussen browser en origin op Hetzner.

**Te testen**: zet Worker tijdelijk uit voor beton-cire-webshop.nl routes en test of Cookiebot dan correct werkt.

---

## Finding 11: nginx server-name conflict (klein)

In nginx error.log:
```
[warn] conflicting server name "staging.beton-cire-webshop.nl" on 0.0.0.0:80, ignored
[warn] conflicting server name "staging.beton-cire-webshop.nl" on 0.0.0.0:443, ignored
```

Twee nginx vhost-configs claimen `staging.beton-cire-webshop.nl`. Eén wordt gebruikt, andere genegeerd. Niet kritiek voor PROD, kan staging vreemd laten gedragen.

**Risico**: LAAG voor BCW prod.

---

## Finding 12: Redis Object Cache - vers / leeg na migratie

Hoasted gebruikte object cache via LSCache (in-memory). Hetzner gebruikt Redis (apart proces).

Bij migratie:
- Alle transients waren weg
- Plugin configuration caches (Cookiebot consent log, WC product cache, WPSEO settings) moesten opnieuw worden opgebouwd
- Sessions waren weg → ingelogde gebruikers uitgelogd

**Status**: self-healing binnen 24-48h door organic traffic. Werkt waarschijnlijk al weer normaal.

**Verify**:
```bash
sudo -u bcw redis-cli -n 0 -a $(sudo cat /etc/redis/redis-password) INFO stats
```

---

## Mogelijke root-causes per user-issue

### "Cookiebot werkt niet meer goed"
Meest waarschijnlijk:
1. **Cache van consent banner** in nginx FastCGI - banner verschijnt soms wel/niet door cache state
2. **CF Worker setting cookie** dat consent-state in war stuurt
3. **Plugin-load order verandered** door wegvallen LSCache injection-volgorde

### "GA fired niet meer"
Meest waarschijnlijk:
1. **GTM consent mode integration was nooit aan** (`integrate-cookiebot: false`). Op Hoasted werkte het toch - waarschijnlijk omdat LSCache aparte cache-variants per consent state had. Op Hetzner: één cache voor allemaal.
2. **`_fbp` en wellicht `_ga*` cookies lekken via cache** → GA ziet alle bezoekers als dezelfde returning visitor
3. **GTM tag fires** (HTML bevat `googletagmanager.com/gtm.js?id=GTM-P27Z67VR`) - verifieer met GA Tag Assistant of GA daadwerkelijk events ontvangt

### "Settings issues"
Niet specifiek genoeg om te diagnosticeren. Welke settings precies?
- WC settings? Vergelijk `wp option get woocommerce_*`
- Plugin admin pages die anders renderen? Mogelijk PHP 8.0 → 8.3 deprecations
- Theme customizer? `wp_options` waarden zijn 1-op-1 gemigreerd

---

## Aanbevolen volgende stappen (gerangschikt naar impact)

1. **Fix Set-Cookie caching** (Finding #4) - nginx config aanpassen om responses met `_fbp`, `_ga*`, WC session cookies NIET te cachen
2. **Migreer Patrick's custom scripts** (Finding #6) - `/home/betoncire/scripts/` → `/home/bcwstaging/scripts/` met path-rewrite
3. **Migreer BSQ cron jobs** (Finding #6) - crontab voor bcwstaging user toevoegen
4. **Test Cookiebot zonder CF Worker** (Finding #10) - pause Worker, retest
5. **Activeer GTM consent mode** (`integrate-cookiebot: true` in GTM4WP options) - was al fout op Hoasted, maar nu een goed moment om te fixen
6. **Browser console check** - open https://beton-cire-webshop.nl/ in incognito, kijk naar console errors
7. **GA Tag Assistant** - installeer Chrome extension, test of GTM/GA tags daadwerkelijk fire

---

## Bestanden + diffs nog te onderzoeken

- `[ ]` Volledige Hetzner .user.ini vs Hoasted .user.ini regel-voor-regel
- `[ ]` `/etc/nginx/sites-enabled/bcw.conf` skip-cache rules vs LSCache cache-rules op Hoasted
- `[ ]` Wordfence config (`wordfence_*` options)
- `[ ]` WP Mail SMTP settings (gebruikt het IMAP?)
- `[ ]` Vergelijk woocommerce-options tussen beide
- `[ ]` Cookiebot Manager dashboard (cookiebot.com) - domain whitelist
- `[ ]` Cloudflare Page Rules / Workers routes (wordt staging anders behandeld?)
- `[ ]` Recente order data - werkt de bestelflow überhaupt nog goed?
