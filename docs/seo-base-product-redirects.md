# SEO Issue: Base Product URL Redirects (2026-03-09)

## Background

Base products are the "parent" landing pages for each product line:

| Product Line       | Base Product URL                   | Product ID |
|--------------------|-------------------------------------|------------|
| Original           | `/beton-cire-original/`             | 11161      |
| Easyline           | `/beton-cire-easyline-kant-klaar/`  | 11160      |
| All-in-One         | `/beton-cire-all-in-one-kant-klaar/`| 11165      |
| Microcement        | `/microcement-performance/`         | 22760      |
| Metallic Stuc      | `/metallic-stuc/`                   | 11162      |
| Lavasteen          | `/lavasteen-gietvloer/`             | 27736      |

## What Happened

### 1. Our plugin had 301 redirects (original code)
`class-frontend-display.php` had a `redirect_base_products()` method that 301-redirected
base product URLs to the "most popular" color variant. It also excluded base products from
the Yoast XML sitemap via `wpseo_exclude_from_sitemap_by_post_ids` filter.

This was **removed on 2026-03-09** because it loses SEO traffic on high-value URLs.

### 2. SEO guy changed Permalink Manager slugs
The SEO person changed the Permalink Manager custom URIs for 3 products:

| Product    | Original slug (WP)                  | SEO guy changed PM URI to        |
|------------|-------------------------------------|-----------------------------------|
| Easyline   | `beton-cire-easyline-kant-klaar`    | `beton-cire-easyline-kant-en-klaar` |
| All-in-One | `beton-cire-all-in-one-kant-klaar`  | `beton-cire-easyline-all-in-one`    |
| Microcement| `microcement-performance`           | `microcement`                       |

These were **reverted on 2026-03-09** back to the original WP slugs.

### 3. .htaccess redirects added for old SEO slugs
To avoid 404s on the SEO guy's old URLs (which may have been indexed), 301 redirects
were added in `.htaccess`:

```apache
# 301 Redirects for SEO guy slug changes reverted (2026-03-09)
RedirectMatch 301 ^/microcement/?$ /microcement-performance/
RedirectMatch 301 ^/beton-cire-easyline-kant-en-klaar/?$ /beton-cire-easyline-kant-klaar/
RedirectMatch 301 ^/beton-cire-easyline-all-in-one/?$ /beton-cire-all-in-one-kant-klaar/
```

### 4. Redirection plugin entries (can be cleaned up)
Three entries were also added to the Redirection plugin DB (IDs 812-814) for the same
URLs. These are redundant since .htaccess handles them first. They can be deleted from
WP Admin > Tools > Redirection if desired.

## Current State (after fix)

- All 6 base product URLs return HTTP 200 and show our product page template
- Base products show color swatches but no add-to-cart (users must pick a color)
- Base products appear in the Yoast XML sitemap
- The 3 old SEO guy URLs 301 to the correct canonical URLs via .htaccess
- No more `redirect_base_products()` or `exclude_base_products_from_sitemap()` in plugin code

## Files Changed

- `oz-variations-bcw/includes/class-frontend-display.php` — removed redirect + sitemap exclusion
- `oz-variations-bcw/templates/single-product.php` — base product page mode (no cart, show swatches)
- `oz-variations-bcw/src/js/product-page.js` — JS bails on base products
- `oz-variations-bcw/src/css/options.css` — "choose color" prompt styling
- `/home/betoncire/public_html/.htaccess` — 3 redirect rules for old SEO slugs
- Permalink Manager `permalink-manager-uris` option — 3 URIs reverted to original slugs
