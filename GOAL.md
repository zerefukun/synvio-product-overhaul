# BCW Deploy & Omgevingen — Goal

Toon en handhaaf dit goal bij alles wat met de Beton Ciré Webshop deploy/omgevingen te maken heeft.

## Principes
1. PROD is heilig: 100% uptime, orders/klanten/forms/e-mails zijn onaantastbaar.
2. CODE stroomt alleen staging → prod, via git. Niemand edit prod-files direct.
   Hotfix nodig? Fix op staging, verifieer, push. De deploy is binnen 2 min live.
3. CONTENT (teksten, Yoast, redirects, menu's) wordt op PROD bewerkt.
   Grote content-overhauls: bouwen op staging, gemigreerd via script
   (per-post backup, idempotent, geverifieerd).
4. DATA stroomt alleen prod → staging (periodieke refresh: DB-kopie met
   URL-rewrite, mail-blackhole, test-betaalmodus, geanonimiseerd waar nodig).
5. CONFIG van externe koppelingen (GTM, pixels, Channable, GSC, Mollie,
   Sendcloud) leeft in de prod-database en wordt door geen enkele deploy
   aangeraakt. Staging heeft eigen/uitgeschakelde keys.

## Technische waarborgen (elke push, automatisch)
- PRE: drift-check — deploy FAALT hard als prod files bevat die de push zou
  overschrijven maar die NIET uit deze commit-range komen (= server-side edits;
  eerst reverse-syncen naar repo).
- PRE: automatische snapshot (git-commit wp-content) vóór elke rsync.
- POST: smoke-tests — homepage, ruimte-pagina, PDP, cart, checkout-render,
  GTM-tag aanwezig, geen PHP-errors. Faalt er één → workflow rood.
- SEO is additief: redirects via één plugin (Redirection), deploys raken nooit
  de DB, content-migraties behouden alle metas. Na grote releases: crawl-diff.

## Definitie van "vlekkeloos"
Een push die code wijzigt zonder één request te laten falen, zonder één
DB-rij aan te raken, en die binnen 60 sec aantoonbaar gezond is (smoke-tests
groen). Alles daarbuiten is geen push maar een migratie — en die heeft een
draaiboek met backup en GO-moment.
