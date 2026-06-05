# Server context for Patrick / beton-cire-webshop.nl

Last updated: 2026-06-05

Drop-in context document for Patrick's LLM. Paste into system prompt / context / "instructions" field so the LLM understands the post-migration environment before suggesting any commands.

## TL;DR

The hosting setup migrated from Hoasted shared VPS to a Hetzner dedicated server on 2026-06-04. SSH access patterns, file paths, deployment workflow, and caching layer have all changed. Read this fully before suggesting any commands.

## Server

- Host: `5.9.62.15` (Hetzner AX42-U, Ubuntu 24.04 LTS)
- Hostname: `synvio-bcw-prod`
- Hardware: AMD Ryzen 7 PRO 8700GE, 64 GB DDR5, 2x 512 GB NVMe RAID-1
- Datacenter: Falkenstein, Germany

## SSH access for Patrick

```powershell
ssh synvio@5.9.62.15
```

- Private key: `~/.ssh/id_ed25519` (auto-detected, no `-i` flag needed)
- Username: `synvio` (NOT root, NOT a site user)
- Has passwordless `sudo` -> can do anything with `sudo <cmd>` or `sudo -i`
- Whitelisted IPs in Hetzner firewall: `157.97.197.30` (work), `77.171.196.15` (home)
- If Patrick connects from another IP, SSH will time out. He must update the Hetzner Robot firewall (https://robot.hetzner.com) to add his current IP before connecting.

## Sites hosted on this server

Four sites with strict per-user isolation via Linux users + PHP-FPM pools:

| Site | Linux user | Web root | DB name |
|---|---|---|---|
| beton-cire-webshop.nl (prod) | `bcw` | `/home/bcw/public_html` | `bcw_wp` |
| staging.beton-cire-webshop.nl | `bcwstaging` | `/home/bcwstaging/public_html` | `bcwstaging_wp` |
| epoxystone-gietvloer.nl | `epoxystone` | `/home/epoxystone/public_html` | `epoxystone_wp` |
| smartdeco.nl | `smartdeco` | `/home/smartdeco/public_html` | `smartdeco_wp` |

Site users have `nologin` shell. To work as a site user, use `sudo -u <user> <cmd>`. Example:

```bash
sudo -u bcw wp option get siteurl --path=/home/bcw/public_html
```

## DB

- MariaDB 11.4 on localhost via socket
- Connect as root: `sudo mariadb`
- WordPress table prefix on `bcw_wp`: `OTBgD_` (not `wp_`)
- Per-site DB users exist (`bcw_wp`, `epoxystone_wp`, etc.) but Patrick should use `sudo mariadb` for ad-hoc queries.

## Architecture

- nginx 1.31 mainline (no Apache, no LiteSpeed)
- PHP 8.3.31 FPM, separate pool per site (`/etc/php/8.3/fpm/pool.d/<user>.conf`)
- MariaDB 11.4 with Redis object cache (`/etc/redis/redis.conf`)
- Cloudflare proxy (orange cloud) for all 4 sites
- Cloudflare Worker `language-cookie-injector` for epoxystone + bcw + smartdeco (handles geo->lang detection)
- nginx FastCGI cache on tmpfs at `/var/cache/nginx/<site>/` (TTL: 4h fresh, 7d inactive)

## Deployment (CRITICAL)

**Do NOT edit files directly on the server.** All code changes deploy via GitHub Actions on push to `main`:

- BCW prod plugin/theme: https://github.com/zerefukun/synvio-product-overhaul
- BCW staging: same repo, `staging` branch
- Epoxystone: separate repo with similar workflow

If Patrick edits a file via SSH, the next deploy will overwrite it (rsync --delete). Always go through git.

Exception: emergency hotfixes on the server are OK but MUST be backported to git within the day.

## Cache management

FastCGI cache purge (per site):

```bash
sudo find /var/cache/nginx/bcw -type f -delete           # BCW prod
sudo find /var/cache/nginx/bcwstaging -type f -delete    # BCW staging
sudo find /var/cache/nginx/epoxystone -type f -delete    # Epoxystone
sudo find /var/cache/nginx/smartdeco -type f -delete     # Smartdeco
```

GitHub Actions auto-purges the relevant site's cache after every deploy.

Cloudflare purge: use the CF dashboard or API. Token stored at `/root/.cloudflare-token` (root-readable).

## Logs (where to look when something breaks)

| What | Path |
|---|---|
| PHP errors per site | `/home/<user>/logs/php-error.log` |
| PHP slow requests (bcw only) | `/home/bcw/logs/php-fpm-slow.log` |
| nginx access (all sites) | `/var/log/nginx/access.log` |
| nginx error (all sites) | `/var/log/nginx/error.log` |
| MariaDB error | `/var/log/mysql/error.log` |
| Cron jobs | `/var/log/syslog` |
| SSH access | `sudo journalctl -u ssh` |
| Firewall drops | `/var/log/ufw.log` |

## Backups

- Borg backup to Hetzner Storage Box every 4 hours
- Repo: `ssh://u607981@u607981.your-storagebox.de:23/./synvio-bcw-backup`
- Script: `/usr/local/sbin/backup-borg.sh`
- Cron: `/etc/cron.d/backup-borg`
- List archives: `sudo BORG_PASSPHRASE=$(sudo cat /root/.borg-passphrase) borg list <repo>`

## Common operations Patrick may want to do

Check what's slow:

```bash
sudo tail -50 /home/bcw/logs/php-error.log
sudo tail -50 /home/bcw/logs/php-fpm-slow.log
sudo tail -50 /var/log/nginx/error.log
```

See current WordPress version on BCW:

```bash
sudo -u bcw wp core version --path=/home/bcw/public_html
```

List active plugins:

```bash
sudo -u bcw wp plugin list --status=active --path=/home/bcw/public_html
```

Clear all caches after a config change:

```bash
sudo find /var/cache/nginx/bcw -type f -delete
sudo -u bcw wp cache flush --path=/home/bcw/public_html
sudo systemctl reload php8.3-fpm nginx
```

Restart services (careful, brief downtime):

```bash
sudo systemctl reload nginx          # zero-downtime
sudo systemctl reload php8.3-fpm     # zero-downtime
sudo systemctl restart mariadb       # ~5s downtime
```

Check recent orders:

```bash
sudo mariadb bcw_wp -e "SELECT ID, post_status, post_date FROM OTBgD_posts WHERE post_type='shop_order' ORDER BY post_date DESC LIMIT 10"
```

## Firewall (important for outbound!)

Hetzner Robot hardware firewall is stateful for inbound. Outbound rule #3 allows all. For TCP return-traffic to work, inbound rule "TCP established returns" must be ABOVE the "Block rest" rule. If a deploy or plugin update suddenly fails with TCP timeouts to external APIs (Mollie, GitHub, Sendcloud), this is the first thing to check.

UFW on the server enforces inbound restrictions. Allowed inbound: SSH from Fatih + Patrick IPs, 80/443 from Cloudflare ranges only.

## What NOT to do

- Do NOT run `git push --force` to any branch
- Do NOT disable UFW or Hetzner firewall without coordination
- Do NOT install LiteSpeed Cache plugin (incompatible with nginx FastCGI cache)
- Do NOT change DNS (managed via Cloudflare API by Fatih)
- Do NOT touch `/etc/nginx/`, `/etc/php/`, or `/etc/mysql/` configs without backing up first
- Do NOT delete files from `/var/cache/nginx/` other than via the purge commands above
- Do NOT skip git hooks with `--no-verify`
- Do NOT modify production without testing on staging first

## When stuck or unsure

Patrick should ping Fatih (Synvio Web Solutions, zhouikun@gmail.com). Server bringup, firewall, GitHub Actions, and Cloudflare setup were all done by Fatih.

## Pre-migration archive (read-only reference)

The old Hoasted shared hosting (`v2284.hostingsecure.com`) is still live for ~7 days as a fallback. After that it gets decommissioned. DO NOT push any new work to it.

## Quick reference card

```
Server IP:        5.9.62.15
SSH user:         synvio
SSH key:          ~/.ssh/id_ed25519
Sudo:             passwordless
Web root prod:    /home/bcw/public_html
WP table prefix:  OTBgD_
DB connect:       sudo mariadb
PHP version:      8.3.31
WP version:       6.9.4
Cache layer:      nginx FastCGI on tmpfs
Cache TTL:        4h fresh / 7d inactive
Deploy method:    git push to main (auto via GH Actions)
Backups:          Borg every 4h to Hetzner Storage Box
```
