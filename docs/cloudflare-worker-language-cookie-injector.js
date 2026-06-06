/**
 * Cloudflare Worker: Language Cookie Injector
 *
 * Purpose: solve the "every cookieless first visit triggers 20-30s PHP render"
 * problem on epoxystone-gietvloer.nl + beton-cire-webshop.nl.
 *
 * What it does, per incoming request:
 *   1. If the visitor already has an `epoxy_lang` cookie → pass through.
 *   2. Else look at the `CF-IPCountry` header that Cloudflare attaches to
 *      every request and pick the right language for that country.
 *   3. Inject `Cookie: epoxy_lang=<lang>` into the request that goes to
 *      origin. The PHP `nocache_for_cookieless` guard now sees a cookie,
 *      skips its no-cache path, and the response becomes cacheable.
 *   4. On the response, also send `Set-Cookie: epoxy_lang=<lang>` so the
 *      visitor's browser keeps the cookie for subsequent requests
 *      (whether they come through us again or hit the origin directly).
 *
 * Exclusions: wp-admin, wp-login, wp-json, wp-cron, and explicit
 * /<lang>/ URLs are left untouched - they already know what language to
 * serve and don't need the cookie hint.
 *
 * Deploy: paste into a Cloudflare Worker, attach to routes:
 *   epoxystone-gietvloer.nl/*
 *   *.epoxystone-gietvloer.nl/*
 *   beton-cire-webshop.nl/*
 *   *.beton-cire-webshop.nl/*
 *
 * Cost: free tier (100k requests/day). Well within scope.
 */

// Country → language mapping. Mirrors OZ_GeoIP::detect_default_language()
// in /wp-content/plugins/oz-variations/includes/translation/class-oz-geoip.php
// Keep in sync if Patrick's dev adds more languages or countries.
const COUNTRY_TO_LANG = {
  // Dutch (default - but still set it so the cookie is present and PHP cache works)
  NL: "nl", BE: "nl", SR: "nl", AW: "nl", CW: "nl",

  // English
  GB: "en", US: "en", CA: "en", AU: "en", IE: "en", NZ: "en",
  IN: "en", ZA: "en", SG: "en", MY: "en", PH: "en",

  // German
  DE: "de", AT: "de", CH: "de", LI: "de", LU: "de",

  // French
  FR: "fr", MC: "fr", SN: "fr", CI: "fr", CD: "fr",

  // Spanish
  ES: "es", MX: "es", AR: "es", CO: "es", CL: "es",
  PE: "es", VE: "es", EC: "es", GT: "es", CU: "es",

  // Italian
  IT: "it", SM: "it", VA: "it",

  // Portuguese
  PT: "pt", BR: "pt", AO: "pt", MZ: "pt",

  // Polish
  PL: "pl",

  // Turkish
  TR: "tr", CY: "tr",

  // Ukrainian
  UA: "uk",

  // Bulgarian
  BG: "bg",

  // Arabic
  SA: "ar", AE: "ar", EG: "ar", MA: "ar", DZ: "ar",
  IQ: "ar", SY: "ar", JO: "ar", LB: "ar", KW: "ar",
  QA: "ar", BH: "ar", OM: "ar", YE: "ar", LY: "ar",
  TN: "ar", SD: "ar",
};

// Paths where we DON'T want to inject the cookie (let origin handle natively).
// Note: this still counts as 1 Worker invocation each. To truly skip Worker,
// configure these as exclusion Routes in the CF dashboard.
const SKIP_PATH_PREFIXES = [
  "/wp-admin",
  "/wp-login",
  "/wp-json",
  "/wp-content",     // static assets (CSS, JS, images, uploads)
  "/wp-includes",    // core static assets
  "/wp-cron.php",
  "/xmlrpc.php",
  "/feed",
  "/sitemap",        // sitemap_index.xml + sitemaps (Yoast)
  "/robots.txt",
  "/.well-known",    // Let's Encrypt + security.txt
  "/favicon.ico",
];

// Path suffixes (file extensions) for which Worker should pass through.
// Static asset extensions never need language detection.
const SKIP_PATH_SUFFIXES = [
  ".css", ".js", ".jpg", ".jpeg", ".png", ".gif", ".webp", ".svg",
  ".woff", ".woff2", ".ttf", ".eot", ".ico", ".pdf", ".xml", ".txt",
  ".mp4", ".webm", ".mp3", ".zip",
];

// Language URL prefixes already present in URL. If user is already on
// /en/foo/ they don't need cookie hint - origin sees the prefix and
// routes correctly.
const KNOWN_LANG_PREFIXES = new Set([
  "en", "de", "fr", "es", "it", "pt", "pl", "tr", "ar", "bg", "uk",
]);

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const path = url.pathname;

    // 1. Skip non-cacheable paths - origin handles these directly.
    for (const prefix of SKIP_PATH_PREFIXES) {
      if (path.startsWith(prefix)) {
        return fetch(request);
      }
    }

    // 1b. Skip static asset file extensions - never need language detection.
    const lowerPath = path.toLowerCase();
    for (const suffix of SKIP_PATH_SUFFIXES) {
      if (lowerPath.endsWith(suffix)) {
        return fetch(request);
      }
    }

    // 2. URL already carries a language prefix - origin's own router handles it.
    const firstSeg = path.split("/")[1] || "";
    if (KNOWN_LANG_PREFIXES.has(firstSeg)) {
      return fetch(request);
    }

    // 3. Visitor already has epoxy_lang cookie - pass through unchanged.
    const incomingCookies = request.headers.get("cookie") || "";
    if (/(?:^|;\s*)epoxy_lang=[a-z]{2}/i.test(incomingCookies)) {
      return fetch(request);
    }

    // 4. Determine language from CF-IPCountry. CF attaches this header to
    //    every request. Fall back to "nl" if country is unknown.
    const country = (request.headers.get("cf-ipcountry") || "").toUpperCase();
    const lang = COUNTRY_TO_LANG[country] || "nl";

    // 5. Build modified request with Cookie injected.
    const newHeaders = new Headers(request.headers);
    const updatedCookie = incomingCookies
      ? `${incomingCookies}; epoxy_lang=${lang}`
      : `epoxy_lang=${lang}`;
    newHeaders.set("cookie", updatedCookie);

    const modifiedRequest = new Request(request.url, {
      method: request.method,
      headers: newHeaders,
      body: request.body,
      redirect: "manual", // let origin redirects pass through
    });

    const response = await fetch(modifiedRequest);

    // 6. Also send Set-Cookie on the response so the BROWSER keeps the
    //    cookie. Subsequent requests will then have the cookie natively,
    //    even if they bypass us (DNS-only, alternate edge POP, etc.).
    const responseHeaders = new Headers(response.headers);
    responseHeaders.append(
      "set-cookie",
      `epoxy_lang=${lang}; Path=/; Max-Age=31536000; SameSite=Lax; Secure`
    );

    // Surface what we did, useful for debugging via curl -I.
    responseHeaders.set("x-edge-lang", lang);
    responseHeaders.set("x-edge-country", country || "unknown");

    return new Response(response.body, {
      status: response.status,
      statusText: response.statusText,
      headers: responseHeaders,
    });
  },
};
