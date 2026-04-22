# Beton Cire Webshop - Homepage SEO Verbetervoorstel

Status: Voorstel ter review - nog niet doorgevoerd op productie.
Wijzigingen staan op staging ter beoordeling.

---

## 1. Ontbrekende alt-teksten op afbeeldingen

**Probleem:** 5 afbeeldingen op de homepage hebben geen alt-tekst (leeg alt-attribuut). Dit betreft de hero banner, kleurstalen CTA, All-In-One productafbeelding, Original productafbeelding, en de inspiratie-sectie afbeelding.

**Voorgestelde fix:** Beschrijvende, beknopte alt-teksten toevoegen die de afbeelding in context plaatsen.

**Bronnen:**

Google Search Central - Image SEO Best Practices:
> "When writing alt text, focus on creating useful, information-rich content that uses keywords appropriately and is in context of the content of the page."
> "Avoid filling alt attributes with keywords (also known as keyword stuffing) as it results in a negative user experience and may cause your site to be seen as spam."
- https://developers.google.com/search/docs/appearance/google-images

Google SEO Starter Guide:
> "Writing good alt text is quite important. A short, but descriptive piece of text that explains the relationship between the image and your content."
> "Google uses alt text along with computer vision algorithms and the contents of the page to understand the subject matter of the image."
- https://developers.google.com/search/docs/fundamentals/seo-starter-guide

---

## 2. Open Graph tags ontbreken voor de homepage

**Probleem:** De homepage heeft geen og:title, og:description, of og:image ingesteld (niet in Yoast Social instellingen, niet per pagina). Wanneer iemand de homepage deelt op Facebook, LinkedIn of Twitter/X verschijnt een generieke of willekeurige preview.

**Voorgestelde fix:** In Yoast SEO > Social de frontpage OG title, description en image instellen.

**Bronnen:**

Meta/Facebook - Sharing Webmasters Documentation:
> "Without these Open Graph tags, the Facebook Crawler uses internal heuristics to make a best guess about the title, description, and preview image for your content. Designate this info explicitly with Open Graph tags to ensure the highest quality posts on Facebook."
- https://developers.facebook.com/docs/sharing/webmasters/

Meta/Facebook - Best Practices:
> "Maximize the click through rates of your stories by specifying Open Graph tags for all your articles and including compelling images, titles and descriptions."
- https://developers.facebook.com/docs/sharing/best-practices

Meta/Facebook - Image Requirements:
> "Use images that are at least 1200 x 630 pixels for the best display on high resolution devices."
> "The minimum allowed image dimension is 200 x 200 pixels."
> "Try to keep your images as close to 1.91:1 aspect ratio as possible to display the full image in Feed without any cropping."
- https://developers.facebook.com/docs/sharing/webmasters/images/

Open Graph Protocol Specification:
> Vereiste tags: og:title, og:type, og:image, og:url.
- https://ogp.me/

web.dev - Social Discovery:
> Geeft uitleg over hoe OG tags de weergave op sociale netwerken bepalen.
- https://web.dev/social-discovery

Yoast - Open Graph Documentation:
> "With Yoast SEO, you can customize the appearance of your site on Facebook with Open Graph meta tags, and you can set a custom appearance for your pages, posts, products, categories, tags and homepage."
- https://yoast.com/help/custom-open-graph-tags/

---

## 3. Paginatitel is "HOME" (niet beschrijvend)

**Probleem:** De WordPress post_title van de homepage is "HOME". Hoewel Yoast de <title> tag overschrijft, kan deze generieke titel doorlekken naar breadcrumbs, RSS feeds, interne zoekresultaten, en Google kan deze als fallback gebruiken voor title links.

**Voorgestelde fix:** Wijzigen naar "Beton Cire Webshop" of een andere beschrijvende titel.

**Bronnen:**

Google Search Central - Title Links:
> "Avoid vague descriptors like 'Home' for your home page, or 'Profile' for a specific person's profile."
> "Write descriptive and concise text for your <title> elements."
> "Title links are critical to giving users a quick insight into the content of a result and why it's relevant to their query. It's often the primary piece of information people use to decide which result to click."
- https://developers.google.com/search/docs/appearance/title-link

Google Search Central - Hoe Google titels genereert:
> "Content in <title> elements, main visual title shown on the page, heading elements such as <h1> elements, content in og:title meta tags, other content that's large and prominent through the use of style treatments."
- https://developers.google.com/search/docs/appearance/title-link

---

## 4. Prijsinformatie in heading tags (H3)

**Probleem:** "Vanaf", "EUR28", "per 1m2" (en vergelijkbare prijzen) staan elk in een apart `<h3>` tag. Heading tags zijn bedoeld voor content-structuur, niet voor prijsweergave of visuele opmaak.

**Voorgestelde fix:** Wijzigen van `<h3>` naar `<p>` tags met CSS-klassen voor visuele opmaak.

**Bronnen:**

web.dev - Headings and Sections:
> "Don't use headings to make text bold or large; use CSS instead."
> "It is recommended to use heading levels similarly to heading levels in a text editor: starting with a <h1> as the main heading, with <h2> as headings for sub-sections, and <h3> if those sub-sections have sections; avoid skipping heading levels."
- https://web.dev/learn/html/headings-and-sections

Google Search Central - SEO Starter Guide:
> Headings moeten content hierarchisch organiseren: "Use heading tags to organize content hierarchically -- for example, <h1>, <h2>, and <h3> in HTML."
- https://developers.google.com/search/docs/fundamentals/seo-starter-guide

W3C WAI - Page Structure Headings:
> "Nest headings by their rank (or level). The most important heading has the rank 1 (<h1>), the least important heading rank 6 (<h6>)."
- https://www.w3.org/WAI/tutorials/page-structure/headings/

---

## 5. Dubbele H1/H2 tekst

**Probleem:** De H1 tekst "Een unieke stijl met Beton Cire: De trend voor vloeren en wanden" wordt verderop op de pagina herhaald als H2. Identieke heading-tekst op dezelfde pagina maakt de structuur onduidelijk.

**Voorgestelde fix:** De dubbele H2 een andere, unieke tekst geven (voorstel: "Waarom kiezen voor Beton Cire?" -- maar dit is ter beoordeling door de SEO specialist).

**Bronnen:**

Google Developer Documentation Style Guide:
> "If your document is long or has many headings, create an outline to keep track of how you organize the document. Ensure each page has a unique h1 heading."
> "It's easier to jump between pages and sections of a page if the headings and titles are unique."
- https://developers.google.com/style/headings

---

## 6. Heading levels overgeslagen (H1 -> H4)

**Probleem op staging:** "Zeker zijn van je kleur?" was gewijzigd van H2 (productie) naar H4, wat heading levels H2 en H3 overslaat. Evenzo was "Beton cire vloer All in one" van H3 naar H4 gewijzigd.

**Voorgestelde fix:** Terugzetten naar H2 respectievelijk H3 (de originele productie-waarden).

**Bronnen:**

W3C WAI - Page Structure Headings:
> "Skipping heading ranks can be confusing and should be avoided where possible: Make sure that a <h2> is not followed directly by an <h4>."
- https://www.w3.org/WAI/tutorials/page-structure/headings/

MDN Web Docs - Heading Elements:
> "Do not skip heading levels: always start from <h1>, followed by <h2> and so on."
- https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/Heading_Elements

Deque University - Axe Accessibility Rules:
> "Headings must be in a valid logical order, meaning h1 through h6 element tags must appear in a sequentially-descending order."
> "For example, the heading level following an h1 element should be an h2 element, not an h3 element."
- https://dequeuniversity.com/rules/axe/4.4/heading-order

web.dev - Headings and Sections:
> Screen reader gebruikers navigeren via headings: "Some screen reader users navigate by headings to understand page structure, making proper heading hierarchy essential for accessibility."
- https://web.dev/learn/html/headings-and-sections

---

## 7. "Belangrijkste punten" van H2 naar H3

**Probleem:** "Belangrijkste punten" stond als H2 maar is een subsectie van de sectie erboven. Het hoort een H3 te zijn in de hierarchie.

**Bronnen:** Dezelfde als punt 4 en 6 hierboven -- headings moeten de content-hierarchie weerspiegelen.

---

## 8. data-pm-slice attributen opschonen

**Probleem:** Headings bevatten `data-pm-slice="1 1 []"` attributen, dit zijn ProseMirror editor-artefacten die geen semantische waarde hebben.

**Voorgestelde fix:** Verwijderen. Deze attributen zijn onzichtbaar voor gebruikers maar maken de HTML onnodig romelig.

**Bron:**

Google Search Central:
> "id and class attributes used for styling provide no semantic value for screen readers and (for the most part) search engines."
- https://web.dev/learn/html/headings-and-sections

---

## 9. H4 captions met overmatige &nbsp; spaties

**Probleem:** De H4 "In het toilet" bevat 17 `&nbsp;` spaties voor de tekst als visuele positionering. Dit is geen valide manier om elementen te positioneren.

**Voorgestelde fix:** Spaties verwijderen, CSS gebruiken voor positionering.

---

## Overige bevindingen (niet gewijzigd, ter beoordeling)

### A. Alle content nog in Flatsome shortcodes
De homepage-content bestaat nog uit `[ux_banner]`, `[row]`, `[col]` shortcodes. De overhaul naar Gutenberg blocks is nog nodig voor schonere, semantische HTML.

### B. Twitter/X site handle ontbreekt
`twitter_site` staat leeg in Yoast Social instellingen. Als er een X/Twitter account is, kan deze worden ingevuld.

Bron: https://developer.x.com/en/docs/x-for-websites/cards/guides/getting-started

### C. Staging go-live checklist
Bij deployment van staging naar productie moeten deze stappen worden uitgevoerd:
1. `blog_public` van 0 naar 1 zetten
2. Fysieke `robots.txt` verwijderen
3. `X-Robots-Tag` header uit `.htaccess` verwijderen
4. Database search-replace: `staging.beton-cire-webshop.nl` -> `beton-cire-webshop.nl`
5. Ontbrekende plugins installeren (GTM, reviews, product feed, cookie consent)

---

## Nuance: Google over heading-structuur en ranking

Google's SEO Starter Guide zegt het volgende over headings en ranking:

> "Having your headings in semantic order is fantastic for screen readers."
> "There's also no magical, ideal amount of headings a given page should have."
> "From Google Search perspective, it doesn't matter if you're using them out of order."

Dit betekent dat heading-structuur geen directe ranking factor is, maar wel invloed heeft op:
- Toegankelijkheid (WCAG/screenreaders)
- Gebruikerservaring
- Hoe Google de content-structuur interpreteert voor featured snippets en title link generatie

Bron: https://developers.google.com/search/docs/fundamentals/seo-starter-guide
