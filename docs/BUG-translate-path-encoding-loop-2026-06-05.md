# Bug Report: Non-Latin slug 301-redirect loop on translated URLs

**Date discovered:** 2026-06-05
**Severity:** High (breaks all non-Latin language URLs for SEO indexing)
**Affected site:** epoxystone-gietvloer.nl (Patrick's multilang webshop)
**Affected plugin:** `oz-variations` (custom Synvio plugin)
**Affected languages:** ar, bg, uk (all languages with non-Latin scripts that get slug-translated)
**Existed prior to:** Hetzner migration (confirmed identical loop on Hoasted source)

---

## 1. Summary

Product URLs in Arabic (`/ar/...`), Bulgarian (`/bg/...`), and Ukrainian (`/uk/...`) end in an infinite 301-redirect loop between the fully-Arabic URL and a partially-translated mixed URL. Browser shows `ERR_TOO_MANY_REDIRECTS`. Google can't index. Patrick's international SEO is broken.

Latin-script languages (en, de, fr, es, it, pt, pl, tr) are NOT affected because their slugs don't require URL-encoding.

---

## 2. Symptoms

### Reproduction URL

```
https://epoxystone-gietvloer.nl/ar/%D9%85%D9%86%D8%AA%D8%AC/%d8%b9%d9%8a%d9%86%d8%a7%d8%aa-%d8%a3%d9%84%d9%88%d8%a7%d9%86-%d8%a7%d9%84%d9%85%d9%8a%d9%83%d8%b1%d9%88%d8%b3%d9%85%d9%86%d8%aa/
```

Decoded: `/ar/منتج/عينات-ألوان-الميكروسمنت/` (Arabic translation of Microcement kleurstalen, post ID 23238).

### Redirect chain (alternates forever)

```
/ar/منتج/عينات-ألوان-الميكروسمنت/        → 301 →
/ar/منتج/microcement-kleurstalen/         → 301 →
/ar/منتج/عينات-ألوان-الميكروسمنت/        → 301 →
... loops indefinitely
```

Each response carries `X-LiteSpeed-Tag: d19_HTTP.404`, indicating both intermediate states render as a WP 404 page that then fires a canonical redirect.

### Browser error

```
This page isn't working
epoxystone-gietvloer.nl redirected you too many times.
ERR_TOO_MANY_REDIRECTS
```

---

## 3. Code paths involved

All in the live theme + plugin (paths relative to WP root):

| File | Lines | Responsibility |
|---|---|---|
| `wp-content/themes/epoxystone-child-new/includes/language-routing.php` | ~200–340 | Language router that strips `/{lang}/` prefix, calls slug translator, computes canonical URL, fires 301 if non-canonical |
| `wp-content/plugins/oz-variations/includes/seo/class-oz-slug-translator.php` | 421+ | `translate_path()` — forward translation Dutch → target language |
| `wp-content/plugins/oz-variations/includes/seo/class-oz-slug-translator.php` | 793+ | `resolve_translated_path()` — reverse translation target language → Dutch |
| `wp-content/plugins/oz-variations/includes/seo/class-oz-slug-translator.php` | 341+ | `find_post_by_translated_slug()` — DB lookup against `_oz_slug_{lang}` postmeta |
| `wp-content/plugins/oz-variations/includes/seo/class-oz-slug-translator.php` | 578+ | `normalize_translated_slug_for_lookup()` — encoding normalization for DB matching |

---

## 4. Root cause: encoding inconsistency in `translate_path()` output

### How translated slugs are stored

For non-Latin languages, slugs are saved by `sanitize_title()` as lowercase URL-encoded form. Product 23238 (Microcement kleurstalen) has:

```
_oz_slug_ar = %d8%b9%d9%8a%d9%86%d8%a7%d8%aa-%d8%a3%d9%84%d9%88%d8%a7%d9%86-%d8%a7%d9%84%d9%85%d9%8a%d9%83%d8%b1%d9%88%d8%b3%d9%85%d9%86%d8%aa
_oz_slug_bg = %d0%bc%d0%be%d1%81%d1%82%d1%80%d0%b8-...
_oz_slug_de = farbmuster-aus-mikrozement           ← Latin, no encoding needed
_oz_slug_en = microcement-color-samples            ← Latin, no encoding needed
_oz_slug_uk = %d0%b7%d1%80%d0%b0%d0%b7%d0%ba%d0%b8-...
```

### Live tests against `OZ_Slug_Translator` methods

Running on the actual production data via `wp eval`:

```
Test 1: find_post_by_translated_slug("عينات-ألوان-الميكروسمنت", "ar")
       → 23238 ✓ (raw UTF-8 input lookups work)

Test 2: find_post_by_translated_slug("%d8%b9%d9%8a%d9%86%d8%a7%d8%aa-...", "ar")
       → 23238 ✓ (encoded input lookups work)

Test 3: resolve_translated_path("/منتج/عينات-ألوان-الميكروسمنت/", "ar")
       → /product/microcement-kleurstalen/ ✓ (full reverse works)

Test 4: translate_path("/product/microcement-kleurstalen/", "ar")
       → /منتج/%d8%b9%d9%8a%d9%86%d8%a7%d8%aa-%d8%a3%d9%84%d9%88%d8%a7%d9%86-%d8%a7%d9%84%d9%85%d9%8a%d9%83%d8%b1%d9%88%d8%b3%d9%85%d9%86%d8%aa/
       ↑ BASE in raw UTF-8        ↑ SLUG in URL-encoded lowercase form
       ✗ Mixed encoding output

Test 5: translate_path("/منتج/microcement-kleurstalen/", "ar")
       → /منتج/microcement-kleurstalen/ ← UNCHANGED, slug not translated
       ✗ Partial input is not detected as needing translation
```

### What happens in the canonical-redirect check

The theme (`language-routing.php`, ~lines 226–338) executes:

```php
$resolved_path = OZ_Slug_Translator::resolve_translated_path($rest_path, $lang);
if ($resolved_path !== $rest_path) {
    $rest_path = $resolved_path;
}
$canonical_path = OZ_Slug_Translator::translate_path($rest_path, $lang);
// ... slug-map overrides for cart/checkout/shop/account/terms/privacy ...
$canonical_target = '/' . $lang . $canonical_path;

$current_path = OZ_Current_Request::path();

if (rtrim($current_path, '/') !== rtrim($canonical_target, '/')) {
    wp_safe_redirect(home_url($canonical_target), 301);
    exit;
}
```

For the looping URL:

1. **Inbound:** `$current_path = /ar/منتج/عينات-ألوان-الميكروسمنت/` (raw UTF-8 form, depending on how the SAPI decoded `REQUEST_URI`)
2. **After resolve:** `$rest_path = /product/microcement-kleurstalen/` ✓
3. **After translate:** `$canonical_path = /منتج/%d8%b9%d9%8a%d9%86%d8%a7%d8%aa-.../` (mixed: raw base + encoded slug)
4. **After prefix:** `$canonical_target = /ar/منتج/%d8%b9%d9%8a%d9%86%d8%a7%d8%aa-.../`

**The comparison string `$current_path` and `$canonical_target` are logically the same URL but encoded differently** → comparison fails → 301 fires.

When the resulting `/ar/منتج/microcement-kleurstalen/` re-enters the router, `translate_path()` doesn't recognize the partially-translated input (Test 5 above) and returns it unchanged. WP then 404s, the rescue logic kicks in via the canonical path again, and the loop is established.

---

## 5. Fix proposals

### Option A — surgical, normalize the comparison only (~5 lines, safest)

In `wp-content/themes/epoxystone-child-new/includes/language-routing.php` around line 330, normalize both sides of the equality check before comparing:

```php
// Before:
if (rtrim($current_path, '/') !== rtrim($canonical_target, '/')) {

// After:
$current_cmp   = rtrim(rawurldecode($current_path), '/');
$canonical_cmp = rtrim(rawurldecode($canonical_target), '/');
if ($current_cmp !== $canonical_cmp) {
```

**Pros:** Minimal blast radius. Touches one comparison. Doesn't change any output. Doesn't change the cache key shape.
**Cons:** Leaves `translate_path()` returning mixed encoding — anything else that consumes that output (hreflang generator? sitemap?) inherits the same inconsistency. We're patching the symptom at the consumer, not the producer.

### Option B — normalize `translate_path()` output (recommended longer-term)

In `class-oz-slug-translator.php` `translate_path()`, after the slug segments are joined, force one consistent encoding for the entire path. Choose ONE direction and apply it throughout:

```php
// At the bottom of translate_path(), before returning:
$path = rawurldecode($path);   // produce raw UTF-8 throughout
// or:
$path = implode('/', array_map(function($seg) {
    return rawurlencode(rawurldecode($seg));
}, explode('/', $path)));      // produce lowercase URL-encoded throughout
```

Mirror the same normalization at the end of `resolve_translated_path()` to keep both directions symmetric.

**Pros:** Fixes the producer. Every caller of `translate_path()` (canonical redirect, hreflang, sitemap, language switcher links) gets consistent output.
**Cons:** Larger blast radius. Need to verify outgoing-link generators (the `language-urls.php` helper, hreflang tags) all expect the chosen form.

### Option C — make `translate_path()` accept partially-translated paths

The bug also surfaces in Test 5: `translate_path("/منتج/microcement-kleurstalen/", "ar")` returns input unchanged. After resolving any segments that look like target-language base words (e.g. منتج) back to Dutch, run the existing pipeline.

This would make the function idempotent and let the canonical-redirect comparison succeed even if intermediate caches mix forms.

**Pros:** Resilient to caches with mixed-encoding entries. No reliance on encoding normalization elsewhere.
**Cons:** More invasive — adds a "reverse-base" preprocessing step. The base map already exists (`self::$url_bases`), so the work is mostly hooking it into `translate_path()` the same way `resolve_translated_path()` already does.

### Recommended sequence

1. Ship **Option A** today to break the loop in production (Patrick's URLs become crawlable again).
2. Schedule **Option B** for the next iteration — it's the actual fix.
3. **Option C** as a safety net if mixed-form caches keep recurring.

---

## 6. Validation after fix

Test cases that must all return a 200 (no redirect) and serve the expected product page:

```
/ar/منتج/عينات-ألوان-الميكروسمنت/                 → product 23238
/bg/продукт/<bg-slug>/                              → same product
/uk/продукт/<uk-slug>/                              → same product
/en/product/microcement-color-samples/              → same product (regression check, Latin)
/de/produkt/farbmuster-aus-mikrozement/             → same product (regression check)
```

Bonus, must continue returning 301 (these legitimately need canonical correction):

```
/ar/product/microcement-kleurstalen/    (visitor used Dutch URL under /ar/)
   → 301 → /ar/منتج/عينات-ألوان-الميكروسمنت/

/ar/منتج/microcement-kleurstalen/       (partially translated, leaf still Dutch)
   → 301 → /ar/منتج/عينات-ألوان-الميكروسمنت/
```

The second case is the exact failure surface in Option C — verify it 301s **once** to the canonical and stops.

### Cache invalidation after deploying

```bash
sudo -u epoxystone -- wp --path=/home/epoxystone/public_html option update oz_slug_cache_version $(date +%s)
sudo find /var/cache/nginx/epoxystone -type f -delete
```

The first command bumps the `oz_slug_cache_version` option, making all `oz_tp_*` and `rtp_v*_*` transients unreachable. The second clears the nginx FastCGI cache so any cached redirect responses get re-rendered fresh.

---

## 7. Where the bug came from (timeline note)

This bug is NOT a migration regression — it reproduces identically when hitting Hoasted's old server directly (`curl --resolve epoxystone-gietvloer.nl:443:194.5.132.127`). The Hetzner migration preserved the bug 1:1, which is itself a positive signal that file + DB sync was faithful.

Likely introduced when slug translation was extended to non-Latin scripts (Arabic, Bulgarian, Ukrainian). The Latin-script branches happen to work because `rawurlencode("microcement-kleurstalen") === "microcement-kleurstalen"` (identity on ASCII-safe slugs), masking the encoding inconsistency throughout the rest of the call graph.

---

## 8. Context for whoever picks this up

- Production server (post-migration): `synvio-bcw-prod` at `5.9.62.15` (Hetzner Falkenstein).
- SSH access: `ssh synvio@5.9.62.15`. Sudo is passwordless.
- WP-CLI runs as: `sudo -u epoxystone -- wp --path=/home/epoxystone/public_html <command>`
- WP debug log: `/home/epoxystone/logs/php-error.log`
- Nginx access log: `/home/epoxystone/logs/access.log`
- Nginx error log: `/home/epoxystone/logs/error.log`
- FastCGI cache: `/var/cache/nginx/epoxystone/` (on tmpfs, RAM-backed)

The original `oz-variations` plugin source is presumably maintained in a separate Synvio repository — this file documents the bug for whoever owns that repo to apply the fix.

---

**End of report.**
