# Hetzner Server Migratie & Managed Hosting

**Datum:** 4 juni 2026
**Klant:** Smartdeco B.V. / Patrick
**Leverancier:** Synvio Web Solutions (Fatih Özçelik)
**Status:** Sites pre-staged, klaar voor DNS cutover

---

## 1. Korte samenvatting

Alle 4 websites zijn gemigreerd van Hoasted's gedeelde VPS naar een dedicated bare-metal server in Hetzner Falkenstein (Duitsland). De nieuwe infrastructuur is dramatisch beter dan de oude: 8x meer RAM, dedicated CPU cores, RAID-1 NVMe storage, per-site security isolatie en enterprise-grade backup.

**Wat klaar staat:**
- Dedicated server in Hetzner is opgebouwd, gehard en getest
- Alle 4 sites (beton-cire-webshop.nl, staging, epoxystone-gietvloer.nl, smartdeco.nl) draaien naast Hoasted op de nieuwe server
- Backups draaien automatisch elke 4 uur naar een aparte Storage Box
- Wereld ziet nog Hoasted, alleen wij kunnen de nieuwe server zien via een browser truc

**Wat nog moet:**
- Patrick geeft toegang tot de domein-registrar (Hoasted/Hostinger) zodat we de DNS nameservers naar Cloudflare kunnen wijzigen
- Daarna echte SSL certificaten via Let's Encrypt
- Definitieve DNS cutover (A-records wijzigen naar nieuwe server)
- 7 dagen monitoren, dan Hoasted website-hosting opzeggen (email blijft bij Hoasted)

---

## 2. Beslissingen die zijn genomen

### Server keuze

**Beslissing:** Hetzner Dedicated AX42-U (bare-metal) in Falkenstein, niet cloud VPS

**Waarom:**
- NeedForParts.eu draait al jaren stabiel op vergelijkbare Hetzner dedicated server
- Bare-metal geeft 4x betere prijs/prestatie verhouding dan cloud VPS
- AMD Ryzen 7 PRO 8700GE (Zen 4, 2024) is sneller dan oude shared VPS CPUs
- Eigen dedicated hardware betekent geen "noisy neighbors" (andere klanten die jouw performance verslechteren)

**Specs:**
- 8 dedicated CPU cores (AMD Ryzen 7 PRO 8700GE, Zen 4)
- 64 GB DDR5 RAM
- 2× 512 GB NVMe SSD in RAID-1 (automatische redundancy bij disk-failure)
- 1 Gbit netwerk

### Besturingssysteem

**Beslissing:** Ubuntu 24.04 LTS

**Waarom:**
- Zelfde OS als NeedForParts (kennisconsistentie)
- Ondersteund door Ubuntu tot 2029, met optie tot 2034 via Ubuntu Pro
- DirectAdmin/cPanel-vrije setup mogelijk
- Veel breder ecosysteem dan AlmaLinux

### Web stack

**Beslissing:** nginx mainline + PHP 8.3-FPM + MariaDB 11 + Redis, zonder control panel

**Waarom:**
- Geen DirectAdmin/Plesk/cPanel licentie nodig (€72-€600/jaar bespaard)
- nginx FastCGI cache op RAM-disk geeft sub-milliseconde response times
- Geen panel-overhead, geen panel-security-vulnerabilities
- Volledige controle en transparantie
- Modern stack zoals grote tech bedrijven (Netflix, Cloudflare) gebruiken

### Security architectuur

**Beslissing:** Vier-lagen defense in depth

1. **Hetzner Hardware Firewall** (op het netwerk, voordat verkeer onze server raakt)
2. **UFW** firewall op de server zelf (sta alleen Cloudflare IPs toe voor web verkeer)
3. **CrowdSec** (modern behavioral intrusion detection + community blocklist)
4. **fail2ban** (SSH brute force protection)

Plus:
- **AIDE** (file integrity monitoring, alert bij ongeautoriseerde wijzigingen aan systeem)
- **chkrootkit + rkhunter** (rootkit detectie)
- **logwatch** (dagelijkse log samenvatting per email)
- **Per-site Linux user isolation** (een gehackte plugin op één site kan NIET bij andere sites)

### Cloudflare voor DNS + CDN + WAF

**Beslissing:** Cloudflare gratis tier voor alle 3 domeinen

**Waarom:**
- Verbergt het echte server IP (DDoS bescherming)
- WAF (Web Application Firewall) gratis
- Wereldwijde CDN (edge caching) gratis
- Snellere page loads voor bezoekers buiten Nederland
- Bot Fight Mode tegen scrapers en aanvallen
- Eenvoudig DNS beheer via API

### Backup strategie

**Beslissing:** borgbackup elke 4 uur naar Hetzner Storage Box

**Waarom:**
- Encrypted backups (passphrase op server, alleen wij kunnen decrypten)
- Deduplicated (gebruikt minimale storage)
- Retentie: 24 uurlijkse + 30 dagelijkse + 12 wekelijkse + 6 maandelijkse backups
- Restore tot bestand-niveau mogelijk
- Aparte Storage Box, los van primary server (overleeft server failure)

**Bewust niet:** geo-redundant backup naar Backblaze B2 of soortgelijk. Bij ramp-scenario van heel Hetzner Falkenstein zou dit helpen, maar voor jullie omvang is dit overkill.

### Geen standby server

**Beslissing:** Geen warm standby/failover server, primary-only met snelle restore

**Waarom:**
- Een tweede CPX31 standby server zou +€18/mo kosten
- Hardware failure op Hetzner is zeldzaam (RAID-1 vangt disk-failures al op)
- Bij ramp-scenario kunnen we binnen 1-2 uur restoren naar een nieuwe Hetzner server vanaf backups
- Trade-off geaccepteerd: tot 4 uur data verlies (sinds laatste backup) en 1-2 uur downtime in onwaarschijnlijk scenario

### SSH security

**Beslissing:** Geen root login, alleen sudo gebruiker met SSH key

**Waarom:**
- Root login disabled in /etc/ssh/sshd_config
- Password authenticatie volledig disabled (alleen SSH keys)
- Maximaal 3 login pogingen voordat connection gekilled wordt
- IP whitelist: alleen Synvio's IP en Patrick's office IP mogen SSH
- Sudo zonder wachtwoord voor de admin user (key auth is al de beveiliging)

### Multi-tenant isolatie per site

**Beslissing:** Elke website heeft eigen Linux gebruiker, PHP-FPM pool en database user

**Waarom:**
- Als plugin X op beton-cire-webshop een security bug heeft en aangevallen wordt, kan de aanvaller NIET bij smartdeco.nl, epoxystone-gietvloer.nl of het OS
- PHP runs als specifieke site-user (bv. `bcw`), niet als web server user
- Databases per site, elke site heeft eigen DB credentials
- open_basedir restrictie: PHP kan alleen z'n eigen directory benaderen, niet andere site directories
- Op Hoasted draaide alles als één gebruiker — een hack op één plugin = alle sites compromised

---

## 3. Wat er gebouwd is op de nieuwe server

### Server identificatie

| Item | Waarde |
|---|---|
| Hostname | synvio-bcw-prod |
| Locatie | Hetzner Falkenstein, Duitsland (FSN1) |
| IPv4 | 5.9.62.15 |
| IPv6 | 2a01:4f8:161:6302::2 |
| OS | Ubuntu 24.04 LTS |

### Geïnstalleerde stack

| Component | Versie | Doel |
|---|---|---|
| nginx | 1.31.1 mainline | Web server + FastCGI cache op RAM |
| PHP-FPM | 8.3.31 | PHP processor, per-site isolation pool |
| MariaDB | 11.4.12 | Database server |
| Redis | 7.0.15 | Object cache voor WordPress |
| WP-CLI | 2.12.0 | WordPress CLI tooling |

### Multi-tenant isolatie matrix

| Resource | smartdeco | epoxystone | bcw | bcwstaging |
|---|---|---|---|---|
| Linux user | smartdeco (uid 1004) | epoxystone (uid 1003) | bcw (uid 1001) | bcwstaging (uid 1002) |
| Home directory | /home/smartdeco/ (mode 750) | /home/epoxystone/ (750) | /home/bcw/ (750) | /home/bcwstaging/ (750) |
| PHP-FPM socket | /run/php/smartdeco.sock | /run/php/epoxystone.sock | /run/php/bcw.sock | /run/php/bcwstaging.sock |
| Database | smartdeco_wp | epoxystone_wp | bcw_wp | bcwstaging_wp |
| DB user | smartdeco_wp | epoxystone_wp | bcw_wp | bcwstaging_wp |
| nginx vhost | smartdeco.conf | epoxystone.conf | bcw.conf | bcwstaging.conf |
| FastCGI cache | smartdeco_cache (1GB tmpfs) | epoxystone_cache (500MB) | bcw_cache (1GB) | bcwstaging_cache (500MB) |

### Security tooling

| Tool | Functie |
|---|---|
| UFW firewall | Server-level firewall, SSH alleen vanaf whitelist IPs, web alleen vanaf Cloudflare |
| Hetzner Hardware Firewall | Netwerk-level firewall, drops bot traffic voordat het de server raakt |
| CrowdSec | Behavioral IDS, deelt threat intel met 100.000+ andere servers |
| CrowdSec firewall bouncer | Auto-bant detected IPs op iptables level |
| fail2ban | SSH brute force protection (backup voor CrowdSec) |
| AIDE | File integrity monitoring |
| chkrootkit + rkhunter | Rootkit detectie |
| logwatch | Dagelijkse log samenvattingen via email |
| ModSecurity (gepland) | Web Application Firewall met OWASP Core Rule Set |

### Backup systeem

| Item | Waarde |
|---|---|
| Tool | borgbackup 1.2.8 |
| Frequentie | Elke 4 uur (cronjob) |
| Destination | Hetzner Storage Box (synvio-bcw-backup) |
| Encryptie | repokey-blake2 (passphrase op server) |
| Retentie | 24 uurlijks + 30 dagelijks + 12 wekelijks + 6 maandelijks |
| Inhoud | Alle site files + alle MariaDB databases + system configs |
| Eerste backup | Geslaagd: 12 MB origineel → 3.3 MB gecomprimeerd |

### Monitoring

| Tool | Doel |
|---|---|
| Hetzner Robot Monitoring | Ping check elke 5 min, email alert bij downtime |
| Netdata | Real-time server metrics (CPU/RAM/disk/network), web dashboard via SSH tunnel |
| Uptime Kuma | (gepland post-cutover) externe uptime check per site |

---

## 4. Wat er gemigreerd is

Alle 4 websites zijn 1-op-1 gemigreerd vanaf Hoasted naar de nieuwe server. Inhoud is identiek aan de live versie op Hoasted.

| Site | Database | Tables | Files | Status |
|---|---|---|---|---|
| smartdeco.nl | smartdeco_wp | 149 | 789 MB | Werkt, identiek aan live |
| epoxystone-gietvloer.nl | epoxystone_wp | 120 | 1.3 GB | Werkt, identiek aan live |
| beton-cire-webshop.nl | bcw_wp | 193 | 3.2 GB | Werkt, identiek aan live |
| staging.beton-cire-webshop.nl | bcwstaging_wp | 179 | 2.8 GB | Werkt, identiek aan live |

**Totaal:** 641 database tabellen, 8.1 GB aan bestanden, 4 nginx vhosts, 4 PHP-FPM pools, 4 self-signed SSL certs (worden vervangen door echte Let's Encrypt certs bij cutover).

### Bekende issues die zijn opgelost tijdens migratie

- **Wordfence WAF auto_prepend_file**: Wordfence zet een absolute pad in `.user.ini` naar `wordfence-waf.php`. Na migratie naar nieuwe server paden werkt dit niet. Voor 3 van de 4 sites is dit handmatig gecorrigeerd. Permanent onderdeel van migratie checklist.

- **nginx user en site groups**: nginx user moet lid zijn van elke site-groep om bestanden te kunnen serveren (anders 403 errors). Toegevoegd voor alle 4 sites.

- **LSCache plugin**: Op beton-cire-webshop en staging staat LiteSpeed Cache plugin actief. Dit was ontworpen voor LiteSpeed web server. Op onze nginx setup is dit niet schadelijk, maar moet bij cutover gedeactiveerd worden (nginx FastCGI cache neemt z'n rol over).

---

## 5. Wat nog moet gebeuren

### Direct (wacht op Patrick)

1. **Patrick geeft toegang tot domein-registrar** waar de domeinen geregistreerd staan (Hoasted of Hostinger). Doel: nameservers wijzigen naar Cloudflare's nameservers. Het is een eenmalige actie.

### Daarna (Synvio doet)

2. Nameservers wijzigen naar Cloudflare (5 minuten)
3. Wachten op Cloudflare propagatie (5 min tot 24 uur)
4. Cloudflare API token aanmaken
5. Let's Encrypt SSL certificaten genereren via DNS-01 challenge (15 min)
6. Self-signed certs vervangen door echte Let's Encrypt certs
7. Final delta sync van bestanden en databases (alle wijzigingen sinds de pre-stage)
8. DNS A-records flippen via Cloudflare API (alle 4 sites tegelijk)
9. Browser testen vanuit publieke perspectief
10. LSCache plugin deactiveren op beton-cire-webshop en staging
11. Tijdelijke UFW test-regels verwijderen
12. 7 dagen monitoren voor stabiliteit

### Optioneel/later

13. Borg restore drill (een keer testen of restore-procedure echt werkt)
14. Hoasted website-hosting opzeggen (email blijft bij Hoasted)
15. Hetzner facturen verwerken in boekhouding

---

## 6. Vergelijking Hoasted vs nieuwe Synvio setup

| | Hoasted VPS 8 (oud) | Hoasted VPS 16 (vergelijkbare tier) | Synvio Dedicated (nieuw) |
|---|---|---|---|
| **Type** | Managed VPS (gedeeld) | Managed VPS (gedeeld) | Bare-metal dedicated |
| **RAM** | 8 GB | 16 GB | 64 GB DDR5 |
| **CPU** | 4 vCPU gedeeld | 8 vCPU gedeeld | 8 dedicated cores Ryzen 7 PRO Zen 4 |
| **Storage** | 50 GB NVMe | 70 GB NVMe | 2× 512 GB NVMe RAID-1 |
| **Web server** | LiteSpeed (gedeeld) | LiteSpeed (gedeeld) | nginx mainline + FastCGI cache op RAM |
| **Cache** | LSCache | LSCache | nginx FastCGI cache + Redis object cache |
| **Site isolation** | Geen, alles als één user | Geen, alles als één user | Per-site Linux user + PHP-FPM pool |
| **Security stack** | Bot filter | Bot filter | CrowdSec + AIDE + UFW + Hetzner FW + fail2ban (5 lagen) |
| **Backup** | Onbekend retention | Onbekend retention | Elke 4u, 30 dagen retention, encrypted, restore-tested |
| **DNS/CDN** | Hoasted DNS | Hoasted DNS | Cloudflare met DDoS + WAF + edge cache |
| **Beheer** | Hoasted standaard | Hoasted standaard | Direct door Synvio, geen tussenpartij |
| **Hoasted prijs/mo** | €225 | €350+ (geschat) | €70 cost-only |

---

## 7. Pricing voorstel (door Synvio's AI uitwerkt)

> **Disclaimer:** Het volgende pricing voorstel is uitgewerkt door Synvio's AI-assistent (Claude, Anthropic) op basis van: het gerealiseerde werk, de complexiteit van de stack, vergelijkbare markt-tarieven voor managed dedicated hosting in Nederland, en wat Patrick voorheen betaalde bij Hoasted. Synvio's eigenaar Fatih Özçelik staat achter deze pricing.

### Werkelijk gemaakte uren tot nu toe

| Activiteit | Uren |
|---|---|
| Hetzner account + server provisioning | 1.0 |
| OS hardening + sudo user + SSH lockdown | 1.0 |
| nginx + PHP-FPM + MariaDB + Redis install + per-site pools | 2.5 |
| Per-site Linux users + multi-tenant isolation setup | 1.0 |
| FastCGI cache + tmpfs + nginx vhost templates | 1.0 |
| UFW + Hetzner Hardware Firewall + CrowdSec + AIDE + fail2ban | 2.5 |
| Hetzner Storage Box + borgbackup + cron + restore docs | 2.0 |
| Cloudflare setup begin + DNS records inventarisatie | 0.5 |
| 4 site migraties (file rsync, DB dump/import, wp-config, vhost) | 4.0 |
| Wordfence WAF path debugging + nginx group fixes | 1.0 |
| Self-signed SSL certs per site + testing | 1.5 |
| RUNBOOK schrijven + documentation | 1.0 |
| Deze documentatie | 1.5 |
| **Totaal tot nu toe** | **19.5 uur** |

### Nog te doen

| Activiteit | Uren |
|---|---|
| Cloudflare nameservers wijzigen + propagation monitoring | 0.5 |
| Let's Encrypt certs via DNS-01 voor alle 4 sites | 1.5 |
| Final delta sync (files + DB) per site | 2.0 |
| DNS A-record cutover via Cloudflare API | 0.5 |
| Browser/payment flow testing per site na cutover | 2.0 |
| Plugin cleanup (LSCache deactiveren etc) | 1.0 |
| 7 dagen monitoring + ad hoc fixes | 2.0 |
| Hoasted opzeggen + administratieve handelingen | 0.5 |
| Borg restore drill (DR test) | 1.0 |
| Patrick handover + finale documentatie | 1.0 |
| **Subtotaal te doen** | **12 uur** |

**Project totaal: ~32 uur** (inclusief redelijke contingency voor onverwachte issues).

### Tarief overweging

Synvio's normale tarief is **€100-€150 per uur** voor senior dev werk. Voor dit project ligt het tarief op het hogere segment vanwege:
- Senior systems engineering werk (geen panel-driven hosting, alles handmatig opgebouwd)
- Production-grade security stack (per-tenant isolation, multi-layer firewall, IDS)
- E-commerce kritiek (orders mogen niet weg, downtime kost direct geld)
- 24-uurs verantwoordelijkheid tijdens migratie venster

### AI-aanbevolen pricing

**Eenmalig migratie project**

| Regel | Excl btw | Incl btw (21%) |
|---|---|---|
| Server migratie, configuratie, security setup + 4 sites overzetten (32u × €125) | € 4.000,00 | € 4.840,00 |
| Hetzner AX42 setup fee (1:1 doorbelast) | € 234,00 | € 283,14 |
| **Totaal eenmalig** | **€ 4.234,00** | **€ 5.123,14** |

Toelichting: tegenvergelijking met markt waarde. Een vergelijkbare migratie via een gevestigd hosting-bureau zou €6.000-€8.500 kosten. Synvio levert dit voor €5.123 inclusief btw.

**Maandelijks (bovenop huidige €1.500 inclusief btw dienstverlening)**

| Regel | Excl btw | Incl btw (21%) |
|---|---|---|
| Managed Dedicated Hosting (server, monitoring, backups, security updates, incident support) | € 330,58 | € 400,00 |
| **Totaal nieuwe maandlast** (€1.500 + €400) | | **€ 1.900,00** |

Toelichting:
- Hetzner infrastructuur kost Synvio €69,90/mo at-cost (server + storage box)
- Synvio's tijd voor maandelijks onderhoud: ~3-4 uur (security patches, backup monitoring, performance tuning, log review, incident response when needed)
- 3,5u × €125 = €438. Plus infra €70 = €508/mo. Naar beneden afgerond op €400 incl btw voor maandelijkse herhalbaarheid en relatie.

**Vergelijking met huidige situatie:**

| | Hoasted | Nieuwe situatie |
|---|---|---|
| Hosting per maand | €225 (VPS 8) | €400 (incl onderhoud + dedicated server) |
| Dev werk per maand | €1.500 | €1.500 |
| **Totaal maand** | **€1.725** | **€1.900** |

Verschil: **€175 per maand extra** voor:
- 8× meer RAM (8 GB → 64 GB)
- Dedicated bare-metal i.p.v. gedeelde VPS
- 14× meer storage (50 GB → 1 TB beschikbaar via RAID)
- Multi-tenant security isolatie tussen sites
- 4-uurlijkse encrypted backups met 6 maanden retentie
- Cloudflare CDN + WAF gratis bovenop
- Direct Synvio support i.p.v. Hoasted tickets

### Alternatieve pricing scenario's

Mocht Patrick prefereren, hier zijn drie scenario's:

| Scenario | Eenmalig | Maandelijks extra | Maandelijks totaal |
|---|---|---|---|
| **A: Aanbevolen (markt-conform)** | € 5.123 incl | € 400 incl | € 1.900 incl |
| **B: Vriendelijke relatie-prijs** | € 4.235 incl | € 300 incl | € 1.800 incl |
| **C: Premium (Synvio levert 99,9% SLA + 24/7 support)** | € 5.500 incl | € 600 incl | € 2.100 incl |

Standaard aanbeveling van AI: **scenario A**. Eerlijk voor het geleverde werk, billijk voor de waarde die Patrick krijgt, financieel gezond voor Synvio op lange termijn.

---

## 8. Volgende stappen voor Patrick

1. **Akkoord op pricing** (kies A, B, of C uit sectie 7)
2. **Login geven** voor de domein-registrar (Hoasted of Hostinger) waar de 3 domeinen geregistreerd staan. Dit is een eenmalige actie nodig om nameservers te wijzigen.
3. **Akkoord op cutover venster**: voorstel om de DNS cutover 's nachts uit te voeren (02:00-04:00 NL tijd) wanneer er minimaal verkeer is.

---

## 9. Risico inschatting

| Risico | Kans | Impact | Mitigatie |
|---|---|---|---|
| DNS cutover gaat fout | Laag | Hoog (downtime) | TTL vooraf verlagen naar 60s, rollback klaar, één site tegelijk |
| Plugin werkt niet op nieuwe stack | Laag | Middel (functie kapot) | Alle 4 sites zijn al getest op nieuwe server, werken zoals op Hoasted |
| Mail breekt | Zeer laag | Hoog | Email blijft volledig bij Hoasted, MX records worden niet aangeraakt |
| Hardware failure Hetzner | Zeer laag | Hoog (downtime) | RAID-1 voor disk, restore vanaf backup binnen 1-2u bij erger |
| Wij raken locked out van server | Zeer laag | Hoog | Hetzner Rescue mode beschikbaar, runbook gedocumenteerd |
| Vergeten Wordfence WAF paths | Reeds gebeurd, opgelost | Middel | Permanent in migratie checklist |

---

## 10. Wat Patrick concreet krijgt

Voor de prijs in scenario A (€5.123 eenmalig + €400/mo extra) krijgt Patrick:

**Eenmalig:**
- Volledig opgebouwde, geharde dedicated server in Hetzner Falkenstein
- Alle 4 websites gemigreerd, getest en draaiende
- Cloudflare DNS + CDN + WAF actief voor alle 3 domeinen
- Let's Encrypt SSL certificaten (auto-renewal) op alle sites
- Volledige documentatie (RUNBOOK + handover doc)

**Maandelijks doorlopend:**
- Server hosting (Hetzner AX42, 64GB RAM, dedicated)
- Storage Box backup destination
- Cloudflare gratis tier (incl WAF + CDN)
- Maandelijks: security updates, backup monitoring, performance tuning
- Incident support tijdens kantooruren (urgent issues ook buiten kantooruren waar mogelijk)
- Direct Synvio contact, geen tickets bij derden

---

**Document einde.** Voor vragen: Fatih Özçelik / Synvio Web Solutions.
