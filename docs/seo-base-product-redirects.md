# SEO Issue: Base Product URL Redirects (2026-03-09)

## Background

Base products are the "parent" landing pages for each product line:

| Product Line       | Base Product URL                   | Product ID |
|--------------------|-------------------------------------|------------|
| Original           | `/beton-cire-original/`             | 11161      |
| Easyline           | `/beton-cire-easyline-kant-klaar/`  | 11160      |
| All-in-One         | `/beton-cire-all-in-one-kant-klaar/`| 11165      |
| Microcement        | `/microcement/`                     | 22760      |
| Metallic Stuc      | `/metallic-stuc/`                   | 11162      |
| Lavasteen          | `/lavasteen-gietvloer/`             | 27736      |

## What Happened

### 1. Our plugin had 301 redirects (original code)
`class-frontend-display.php` had a `redirect_base_products()` method that 301-redirected
base product URLs to the "most popular" color variant. It also excluded base products from
the Yoast XML sitemap via `wpseo_exclude_from_sitemap_by_post_ids` filter.

This was **removed on 2026-03-09** because it loses SEO traffic on high-value URLs.

### 2. SEO guy changed Permalink Manager slugs
The SEO person changed the Permalink Manager custom URIs for 2 products:

| Product    | Original PM URI                     | SEO guy changed PM URI to           |
|------------|-------------------------------------|--------------------------------------|
| Easyline   | `beton-cire-easyline-kant-klaar`    | `beton-cire-easyline-kant-en-klaar`  |
| All-in-One | `beton-cire-all-in-one-kant-klaar`  | `beton-cire-easyline-all-in-one`     |

**Note:** Microcement (22760) was always `/microcement/` — the SEO person did NOT change
this slug. The WP post_name is `microcement-performance` but the PM URI is `microcement`,
and that is the original/correct state.

These two were **reverted on 2026-03-09** back to the original PM URIs.

### 3. .htaccess redirects for old SEO slugs
To avoid 404s on the SEO guy's old URLs (which may have been indexed), 301 redirects
were added in `.htaccess` for the two changed slugs:

```apache
RedirectMatch 301 ^/beton-cire-easyline-kant-en-klaar/?$ /beton-cire-easyline-kant-klaar/
RedirectMatch 301 ^/beton-cire-easyline-all-in-one/?$ /beton-cire-all-in-one-kant-klaar/
```

There are also redirects for two microcement color variant typos (added 2026-03-03):
```apache
RedirectMatch 301 ^/microcement-blue2/?$ /microcement-blue-2/
RedirectMatch 301 ^/microcement-sand1/?$ /microcement-sand-1/
```

### 4. Redirection plugin entries (can be cleaned up)
Entries were added to the Redirection plugin DB for the same old SEO URLs.
These are redundant since .htaccess handles them first. They can be deleted from
WP Admin > Tools > Redirection if desired.

## Current State (after fix)

- All 6 base product URLs return HTTP 200 and show our product page template
- Base products show all options (PU, primer, color, etc.) but add-to-cart is greyed out
- Users must pick a color before they can buy
- Base products appear in the Yoast XML sitemap
- The 2 old SEO guy URLs 301 to the correct canonical URLs via .htaccess
- No more `redirect_base_products()` or `exclude_base_products_from_sitemap()` in plugin code

## Files Changed

- `oz-variations-bcw/includes/class-frontend-display.php` — removed redirect + sitemap exclusion
- `oz-variations-bcw/templates/single-product.php` — base product page mode (greyed-out cart)
- `oz-variations-bcw/src/js/product-page.js` — blocks add-to-cart on base products with error
- `oz-variations-bcw/src/css/options.css` — greyed-out button styling
- `/home/betoncire/public_html/.htaccess` — 2 redirect rules for old SEO slugs
- Permalink Manager `permalink-manager-uris` option — 2 URIs reverted to original slugs
