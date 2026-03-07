# Product Page UI Audit & Recommendations

**Beton Ciré Webshop — OZ Variations BCW Plugin**
**Datum:** 7 maart 2026
**Status:** Ter beoordeling

---

## 1. Samenvatting

De productpagina's van de webshop zijn gemoderniseerd met een eigen template-systeem (OZ Variations). Dit document beschrijft de huidige staat van alle 261 productpagina's, wat er goed werkt, en waar verbetermogelijkheden liggen.

**Kernbevinding:** 212 van de 261 producten (81%) hebben een volledig ingerichte pagina. De overige 49 producten (gereedschap, losse items) missen verkooppunten (USPs) en specificaties, maar zijn verder functioneel.

---

## 2. Huidige Situatie

### 2.1 Drie typen productpagina's

| Type | Aantal | Voorbeeld | Wat je ziet |
|------|--------|-----------|-------------|
| **Configured Line** | 210 | EasyLine Hippo, Original, Microcement | Volledig: kleurselectie, PU/primer/colorfresh opties, gereedschap, USPs, specificaties |
| **Generic + Addons** | 2 | Gereedschapset K&K, Zelf Mengen | Goed: addon-keuzes (rollerformaat, troffels), USPs, specificaties |
| **Generic Simple** | 49 | Verfbak, PU Roller, Blokkwast | Basis: product afbeelding, prijs, winkelwagen |

### 2.2 Content dekking per type

#### Configured Line (210 producten) — Volledig

| Content | Aanwezig | Percentage |
|---------|----------|------------|
| Productbeschrijving | 210/210 | 100% |
| Korte beschrijving | 174/210 | 83% |
| Galerij (meerdere foto's) | 160/210 | 76% |
| USPs (verkooppunten) | 210/210 | 100% |
| Specificaties | 210/210 | 100% |

**Status: Volledig. Geen actie nodig.**

#### Generic + Addons (2 producten) — Volledig

| Content | Aanwezig | Percentage |
|---------|----------|------------|
| Productbeschrijving | 2/2 | 100% |
| Korte beschrijving | 2/2 | 100% |
| Galerij | 2/2 | 100% |
| USPs | 2/2 | 100% |
| Specificaties | 2/2 | 100% |

**Status: Volledig. Geen actie nodig.**

#### Generic Simple (49 producten) — Onvolledig

| Content | Aanwezig | Percentage |
|---------|----------|------------|
| Productbeschrijving | 35/49 | 71% |
| Korte beschrijving | 40/49 | 82% |
| Galerij (meerdere foto's) | 13/49 | 27% |
| USPs (verkooppunten) | 3/49 | **6%** |
| Specificaties | 3/49 | **6%** |

**Status: Functioneel maar mager. Verbetermogelijkheden beschreven in sectie 3.**

---

## 3. Wat ontbreekt bij Generic Simple producten

### 3.1 De 5 "kale" producten (helemaal geen content)

Deze producten hebben geen beschrijving, geen korte beschrijving, geen USPs en geen specificaties. De pagina toont alleen een afbeelding, prijs en winkelwagenknop:

| Product | Prijs | Categorie |
|---------|-------|-----------|
| Blokkwast | 6,99 | Gereedschap |
| Effect Kwast hout | 16,99 | Gereedschap |
| Structuur roller 8 cm | 16,99 | Gereedschap |
| Structuur spaan | 29,95 | Gereedschap |
| Cursus | 75,00 | Geen categorie |

### 3.2 De 44 overige producten

Deze hebben WEL een beschrijving en/of korte beschrijving, maar GEEN USPs of specificaties. De pagina is functioneel maar mist de vertrouwenwekkende verkooppunten die de Configured Line pagina's wel hebben (vinkjes met voordelen, specificatietabel).

---

## 4. Verbetermogelijkheden

### Optie A: Categorie-brede USPs toevoegen (Aanbevolen)

**Wat:** Voor elke productcategorie (bijv. "Gereedschap") standaard USPs en specificaties instellen die automatisch op alle producten in die categorie verschijnen.

**Voorbeeld voor categorie "Gereedschap":**
- Professionele kwaliteit
- Geschikt voor alle Beton Cire toepassingen
- Snel geleverd — voor 14:00 besteld, dezelfde dag verzonden

**Impact:** 46 van de 49 generic simple producten vallen in bekende categorieen. Met 3-4 categorie-USPs zijn vrijwel alle pagina's afgedekt.

**Inspanning:** Klein — eenmalige configuratie per categorie (geschat 4-6 categorieen).

### Optie B: Per product USPs/specificaties invullen

**Wat:** Voor elk van de 49 producten handmatig verkooppunten en specificaties schrijven.

**Impact:** Maximaal — elke productpagina krijgt unieke, relevante content.

**Inspanning:** Gemiddeld — 49 producten x 3-5 minuten = circa 3 uur contentwerk.

### Optie C: Layout aanpassing voor "kale" pagina's

**Wat:** De pagina-indeling aanpassen zodat producten zonder uitgebreide content er niet leeg uitzien. Denk aan: compacter formaat, afbeelding prominenter, beschrijving naast de afbeelding in plaats van eronder.

**Impact:** Visuele verbetering, geen extra content nodig.

**Inspanning:** Klein — eenmalige CSS aanpassing.

### Optie D: Niets doen

**Wat:** De 49 generic simple producten laten zoals ze zijn.

**Motivatie:** De pagina's zijn functioneel. Klanten die deze losse producten zoeken, weten vaak al wat ze willen. De 210 hoofdproducten (Beton Cire pakketten) die het meeste omzet genereren zijn volledig ingericht.

---

## 5. Vergelijking: Voor en Na

### Configured Line pagina (volledig ingericht)

```
+---------------------------+-------------------------+
|                           | Kleur: Stone White      |
|   [Hoofdafbeelding]       | Beton Cire EasyLine     |
|                           | EUR 170,00 per 4m2      |
|   [thumb] [thumb] [thumb] |                         |
|                           | V Kant en klaar         |
|                           | V Compleet pakket       |
|                           | V Incl primer en PU     |
|                           |                         |
|                           | KLEUR: [swatches...]    |
|                           | PAKKET: [4m2] [8m2]    |
|                           | PU TOPLAAG: [1x] [2x]  |
|                           | GEREEDSCHAP: [...]      |
|                           |                         |
|   Productinformatie       | -- Prijsoverzicht --    |
|   [beschrijving...]       | Base          EUR 170   |
|                           | PU            EUR 39    |
|   Specificaties           | Totaal        EUR 209   |
|   [tabel...]              |                         |
|                           | [1] [-][+] [WINKELMAND] |
|                           |                         |
|                           | [betaalpictogrammen]    |
|                           | [trust badges]          |
+---------------------------+-------------------------+
```

### Generic Simple pagina (kaal)

```
+---------------------------+-------------------------+
|                           |                         |
|   [Hoofdafbeelding]       | Blokkwast               |
|                           | EUR 6,99 per stuk       |
|   [thumb]                 |                         |
|                           | -- Totaal --            |
|                           | Blokkwast     EUR 6,99  |
|                           | Totaal        EUR 6,99  |
|                           |                         |
|                           | [1] [-][+] [WINKELMAND] |
|                           |                         |
|                           | [betaalpictogrammen]    |
|                           | [trust badges]          |
|                           |                         |
|   (leeg)                  |                         |
|                           |                         |
+---------------------------+-------------------------+
```

### Generic Simple pagina (NA optie A: categorie USPs)

```
+---------------------------+-------------------------+
|                           |                         |
|   [Hoofdafbeelding]       | Blokkwast               |
|                           | EUR 6,99 per stuk       |
|   [thumb]                 |                         |
|                           | V Professionele kwalit. |
|                           | V Geschikt voor Beton C.|
|                           | V Snel geleverd         |
|                           |                         |
|                           | -- Totaal --            |
|                           | Blokkwast     EUR 6,99  |
|                           | Totaal        EUR 6,99  |
|                           |                         |
|                           | [1] [-][+] [WINKELMAND] |
|                           |                         |
|                           | [betaalpictogrammen]    |
|                           | [trust badges]          |
+---------------------------+-------------------------+
```

---

## 6. Aanbeveling

**Optie A + C combineren** voor het beste resultaat met de minste inspanning:

1. Categorie-brede USPs instellen (klein werk, grote impact op 46 producten)
2. Layout aanpassen voor kale pagina's (eenmalige CSS fix)

De 5 compleet kale producten (Blokkwast, Effect Kwast, etc.) hebben dan alsnog USPs via hun categorie, en de pagina ziet er verzorgd uit.

Optie B (per product handmatig) kan later alsnog, en overschrijft dan automatisch de categorie-USPs.

---

## 7. Huidige technische staat

### Wat is al gebouwd en werkend

- Universeel template-systeem voor alle 261 producten
- 3 pagina-modi: configured_line, generic_addons, generic_simple
- Automatische kleurdetectie en swatchweergave
- Addon-systeem (vervangt YITH WAPO plugin)
- Prijsopbouw met live berekening
- Mobiele sticky bar + bottom sheet
- Bezorgtijdlijn met dynamische datums
- Betaalpictogrammen vanuit WooCommerce
- Trust badges
- Per-product content fallback systeem (USPs + specs)

### YITH WAPO plugin status

Alle functionele YITH addons zijn gemigreerd naar het eigen OZ systeem. De YITH WAPO plugin kan worden uitgeschakeld zodra bevestigd is dat alle productpagina's correct werken. De plugin injecteert nog globale CSS maar heeft geen functionele rol meer.

---

*Document gegenereerd op basis van live data-analyse van beton-cire-webshop.nl*
