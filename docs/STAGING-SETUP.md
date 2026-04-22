# Staging Omgeving — BCW (beton-cire-webshop.nl)

## Overzicht

Een staging omgeving is een exacte kopie van de live website waar je veilig kunt testen.
Nieuwe features, plugin updates en design wijzigingen test je eerst op staging.
Pas als alles werkt, push je het naar live.

```
┌─────────────────────────────────────────────────────────────┐
│                        GitHub                                │
│                                                              │
│   staging branch ──deploy──→ staging.beton-cire-webshop.nl  │
│   main branch    ──deploy──→ beton-cire-webshop.nl (live)   │
│                                                              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    Server (Hoasted)                           │
│                                                              │
│   Live DB ──kopie──→ Staging DB  (eenrichtingsverkeer)      │
│                                                              │
│   Live bestanden:    /home/betoncire/public_html/            │
│   Staging bestanden: /home/betoncire/staging/                │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## Wat zit waar?

| Component | Waar het leeft | Hoe het deployed |
|-----------|---------------|------------------|
| Onze plugin (oz-variations-bcw) | GitHub | Automatisch via GitHub Actions |
| Ons child theme (OzTheme) | GitHub | Automatisch via GitHub Actions |
| WordPress zelf | Server | Handmatig geinstalleerd, 2x (live + staging) |
| Database (orders, producten, klanten) | Server | 2 databases, live → staging sync script |
| Andere plugins (Mollie, WooCommerce, etc.) | Server | Handmatig installeren op beide sites |

## Belangrijke regel

**Database stroomt alleen van LIVE → STAGING. Nooit andersom.**

Zo raken orders, klanten en bestellingen nooit kwijt.

```
LIVE database ──kopie──→ STAGING database     ✅ OK
STAGING database ──→ LIVE database            ❌ NOOIT DOEN
```

---

## Stap 1: Voorvereisten bij Hoasted

Voordat we beginnen, heb je nodig bij Hoasted:

- [ ] **Subdomain** aanmaken: `staging.beton-cire-webshop.nl`
- [ ] **Tweede database** aanmaken (bijv. `betoncire_staging`)
- [ ] **Database gebruiker** met rechten op de staging database
- [ ] **SSL certificaat** voor het subdomain (vaak automatisch via Let's Encrypt)

> Neem contact op met Hoasted support als je dit niet zelf kunt aanmaken.
> Vraag: "Ik wil een subdomain staging.beton-cire-webshop.nl met een eigen database."

---

## Stap 2: WordPress installeren op staging

### 2.1 Bestanden kopieren

```bash
# SSH naar de server
ssh bcw

# Maak staging map aan
mkdir -p /home/betoncire/staging

# Kopieer de volledige WordPress installatie
cp -r /home/betoncire/public_html/* /home/betoncire/staging/
```

### 2.2 wp-config.php aanpassen voor staging

Bewerk `/home/betoncire/staging/wp-config.php`:

```php
// Database — verander naar staging database
define('DB_NAME', 'betoncire_staging');     // ← staging database naam
define('DB_USER', 'betoncire_staging');     // ← staging database gebruiker
define('DB_PASSWORD', 'STAGING_WACHTWOORD');

// URL — verander naar staging subdomain
define('WP_HOME', 'https://staging.beton-cire-webshop.nl');
define('WP_SITEURL', 'https://staging.beton-cire-webshop.nl');

// Voorkom dat staging emails stuurt naar echte klanten!
// Installeer de plugin "Disable Emails" op staging, of:
define('DISABLE_WP_CRON', true);  // Geen achtergrond taken
```

### 2.3 Database kopieren

```bash
# Dump live database
mysqldump -u LIVE_USER -p LIVE_DB > /tmp/live-dump.sql

# Laad in staging database
mysql -u STAGING_USER -p STAGING_DB < /tmp/live-dump.sql

# Vervang URLs in de database (live → staging)
# Gebruik WP-CLI als dat geinstalleerd is:
cd /home/betoncire/staging
wp search-replace 'beton-cire-webshop.nl' 'staging.beton-cire-webshop.nl' --skip-columns=guid

# Of handmatig in MySQL:
mysql -u STAGING_USER -p STAGING_DB -e "
  UPDATE wp_options SET option_value = 'https://staging.beton-cire-webshop.nl' WHERE option_name IN ('siteurl', 'home');
"

# Opruimen
rm /tmp/live-dump.sql
```

---

## Stap 3: GitHub Actions voor staging

Voeg een tweede workflow toe: `.github/workflows/deploy-bcw-staging.yml`

```yaml
name: Deploy BCW Plugin + Theme (STAGING)

on:
  push:
    branches:
      - staging              # ← alleen de staging branch triggert dit
    paths:
      - "oz-variations-bcw/**"
      - "oz-theme/**"
      - ".github/workflows/deploy-bcw-staging.yml"
  workflow_dispatch:

permissions:
  contents: read

concurrency:
  group: deploy-bcw-staging
  cancel-in-progress: true

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - name: Check out repository
        uses: actions/checkout@v4

      - name: Configure SSH access
        shell: bash
        env:
          BCW_HOST: ${{ secrets.BCW_HOST }}
          BCW_PORT: ${{ secrets.BCW_PORT }}
          BCW_USER: ${{ secrets.BCW_USER }}
          BCW_SSH_KEY: ${{ secrets.BCW_SSH_KEY }}
        run: |
          set -eu
          port="${BCW_PORT:-22}"
          install -m 700 -d ~/.ssh
          printf '%s\n' "$BCW_SSH_KEY" > ~/.ssh/id_ed25519
          chmod 600 ~/.ssh/id_ed25519
          touch ~/.ssh/known_hosts
          ssh-keyscan -p "$port" -H "$BCW_HOST" >> ~/.ssh/known_hosts

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Build JS bundles
        working-directory: oz-variations-bcw
        run: npm ci && npm run build

      - name: Ensure staging directories exist
        shell: bash
        env:
          BCW_HOST: ${{ secrets.BCW_HOST }}
          BCW_PORT: ${{ secrets.BCW_PORT }}
          BCW_USER: ${{ secrets.BCW_USER }}
        run: |
          set -eu
          port="${BCW_PORT:-22}"
          ssh -p "$port" "${BCW_USER}@${BCW_HOST}" \
            "mkdir -p /home/betoncire/staging/wp-content/plugins/oz-variations-bcw \
                      /home/betoncire/staging/wp-content/themes/OzTheme"

      - name: Deploy plugin to STAGING
        shell: bash
        env:
          BCW_HOST: ${{ secrets.BCW_HOST }}
          BCW_PORT: ${{ secrets.BCW_PORT }}
          BCW_USER: ${{ secrets.BCW_USER }}
        run: |
          set -eu
          port="${BCW_PORT:-22}"
          rsync -az --delete \
            --exclude ".git" \
            --exclude ".github" \
            --exclude ".DS_Store" \
            --exclude "node_modules" \
            --exclude "src" \
            --exclude "package.json" \
            --exclude "package-lock.json" \
            --exclude "esbuild.config.mjs" \
            --exclude "*.map" \
            -e "ssh -p $port" \
            ./oz-variations-bcw/ \
            "${BCW_USER}@${BCW_HOST}:/home/betoncire/staging/wp-content/plugins/oz-variations-bcw/"

      - name: Deploy child theme to STAGING
        shell: bash
        env:
          BCW_HOST: ${{ secrets.BCW_HOST }}
          BCW_PORT: ${{ secrets.BCW_PORT }}
          BCW_USER: ${{ secrets.BCW_USER }}
        run: |
          set -eu
          port="${BCW_PORT:-22}"
          rsync -az --delete \
            --exclude ".git" \
            --exclude ".DS_Store" \
            -e "ssh -p $port" \
            ./oz-theme/ \
            "${BCW_USER}@${BCW_HOST}:/home/betoncire/staging/wp-content/themes/OzTheme/"
```

---

## Stap 4: Database Refresh Script

Maak dit script op de server: `/home/betoncire/refresh-staging.sh`

```bash
#!/bin/bash
# refresh-staging.sh — Kopieer live database naar staging
#
# Gebruik: ssh bcw "bash /home/betoncire/refresh-staging.sh"
#
# Wat het doet:
# 1. Dumpt de live database
# 2. Laadt het in de staging database (overschrijft alles)
# 3. Vervangt live URLs met staging URLs
# 4. Schakelt email notificaties uit op staging
#
# VEILIG: raakt de live database NIET aan.

set -eu

# === CONFIGURATIE — pas aan naar jullie gegevens ===
LIVE_DB="betoncire_live"
LIVE_USER="betoncire"
LIVE_PASS="LIVE_WACHTWOORD"

STAGING_DB="betoncire_staging"
STAGING_USER="betoncire_staging"
STAGING_PASS="STAGING_WACHTWOORD"

LIVE_URL="beton-cire-webshop.nl"
STAGING_URL="staging.beton-cire-webshop.nl"

DUMP_FILE="/tmp/bcw-live-dump-$(date +%Y%m%d-%H%M%S).sql"
# =====================================================

echo "=== BCW Staging Database Refresh ==="
echo ""

# Stap 1: Dump live database
echo "[1/4] Live database dumpen..."
mysqldump -u "$LIVE_USER" -p"$LIVE_PASS" "$LIVE_DB" > "$DUMP_FILE"
echo "      Dump klaar: $(du -h "$DUMP_FILE" | cut -f1)"

# Stap 2: Laad in staging database
echo "[2/4] Staging database vervangen..."
mysql -u "$STAGING_USER" -p"$STAGING_PASS" "$STAGING_DB" < "$DUMP_FILE"
echo "      Database geladen."

# Stap 3: Vervang URLs (live → staging)
echo "[3/4] URLs vervangen ($LIVE_URL → $STAGING_URL)..."
mysql -u "$STAGING_USER" -p"$STAGING_PASS" "$STAGING_DB" -e "
  UPDATE wp_options SET option_value = 'https://$STAGING_URL' WHERE option_name = 'siteurl';
  UPDATE wp_options SET option_value = 'https://$STAGING_URL' WHERE option_name = 'home';
"
echo "      URLs bijgewerkt."

# Stap 4: Schakel email uit op staging (voorkom spam naar echte klanten)
echo "[4/4] Email notificaties uitschakelen op staging..."
mysql -u "$STAGING_USER" -p"$STAGING_PASS" "$STAGING_DB" -e "
  UPDATE wp_options SET option_value = '0' WHERE option_name = 'woocommerce_email_enabled';
  UPDATE wp_options SET option_value = 'staging@beton-cire-webshop.nl' WHERE option_name = 'admin_email';
  UPDATE wp_options SET option_value = 'staging@beton-cire-webshop.nl' WHERE option_name = 'woocommerce_email_from_address';
"
echo "      Emails uitgeschakeld."

# Opruimen
rm -f "$DUMP_FILE"

echo ""
echo "=== KLAAR ==="
echo "Staging is nu up-to-date met live."
echo "Open: https://$STAGING_URL"
echo ""
```

---

## Dagelijks Gebruik

### Nieuwe feature testen

```
1. Maak een staging branch van main:
   git checkout -b staging

2. Schrijf je code, commit, push:
   git push origin staging
   → GitHub Actions deployed automatisch naar staging site

3. Test op staging.beton-cire-webshop.nl
   → Werkt alles? Door naar stap 4.
   → Bug gevonden? Fix, commit, push naar staging. Herhaal.

4. Merge naar main (= gaat live):
   git checkout main
   git merge staging
   git push origin main
   → GitHub Actions deployed automatisch naar live site
```

### Database refreshen

```bash
# Wanneer je verse data wilt op staging:
ssh bcw "bash /home/betoncire/refresh-staging.sh"

# Duurt ~30 seconden. Doe dit:
# - Voor je begint met een nieuwe feature testen
# - Als Patrick veel producten/prijzen heeft aangepast
# - NIET automatisch, NIET elke dag — alleen wanneer nodig
```

### Plugin update testen

```
1. Refresh staging database (optioneel, als je verse data wilt)
2. Log in op staging WP admin: staging.beton-cire-webshop.nl/wp-admin
3. Update de plugin op staging
4. Test of alles werkt (checkout, cart, productpagina's)
5. Werkt het? → Update dezelfde plugin op live
```

---

## Beveiligingsregels

### Moet je doen:
- [ ] Staging achter wachtwoord zetten (HTTP Basic Auth of plugin)
- [ ] Robots blokkeren op staging (`Disallow: /` in robots.txt)
- [ ] Emails uitschakelen op staging (refresh script doet dit automatisch)
- [ ] Betalingen op test-modus zetten (Mollie test API key)
- [ ] Google Analytics / Clarity NIET laden op staging (voorkom vervuilde data)

### staging robots.txt
```
# /home/betoncire/staging/robots.txt
User-agent: *
Disallow: /
```

### staging wp-config.php toevoegingen
```php
// Blokkeer indexering
define('DISALLOW_FILE_EDIT', true);

// Markeer als staging (voor eigen checks in code)
define('WP_ENVIRONMENT_TYPE', 'staging');

// Gebruik in je code om staging te detecteren:
// if (wp_get_environment_type() === 'staging') { ... }
```

---

## Overzicht: Wat raakt wat?

```
                    STAGING                          LIVE
                    ───────                          ────
Database:           staging DB (kopie)               live DB (bron van waarheid)
Plugin code:        ← staging branch (GitHub)        ← main branch (GitHub)
Theme code:         ← staging branch (GitHub)        ← main branch (GitHub)
WordPress:          eigen installatie                 eigen installatie
Andere plugins:     apart geinstalleerd              apart geinstalleerd
Orders:             test orders (weggooien)           echte orders (heilig!)
Emails:             uitgeschakeld                     aan
Betalingen:         test modus                        live modus
Google/Analytics:   uit                               aan
```

---

## Veelgestelde vragen

**V: Wat als ik per ongeluk de staging DB naar live push?**
A: Dat kan niet per ongeluk. Het refresh script werkt maar in één richting (live → staging). Er is geen script voor de andere kant.

**V: Moet ik producten aanmaken op zowel staging als live?**
A: Nee. Producten maak je aan op live. Als je ze op staging nodig hebt, draai je het refresh script.

**V: Wat als staging en live uit sync raken?**
A: Draai het refresh script. Dat overschrijft de staging DB met een verse kopie van live.

**V: Kan Patrick ook op staging testen?**
A: Ja. Geef hem de URL en login. Hij kan daar vrij rondklikken zonder iets kapot te maken op live.

**V: Kost dit extra geld?**
A: Alleen als Hoasted extra betaalt voor een subdomain/database. De GitHub Actions en het script zijn gratis.

**V: Wat als ik een WooCommerce update wil testen?**
A: Refresh staging, update WooCommerce op staging, test checkout + cart + admin. Werkt het? Update live.
