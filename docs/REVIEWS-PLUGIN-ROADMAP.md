# Reviews Plugin — Phased Roadmap

Goal: replace Trustindex with a custom `oz-reviews` plugin that handles collection, display, email automation, Google sync, and intelligence. Part of plugin-cleanup initiative (35→19).

Scope principles:
- Reuse `oz-forms` for submission UI (schema-driven forms, Turnstile, file upload, submission CPT all already exist).
- Use WC native review table (`wp_comments` with `comment_type=review`) for product reviews; custom CPT `oz_shop_review` only for shop-wide/Google-imported reviews.
- All email automation via Action Scheduler (bundled with WooCommerce).
- Schema.org markup from day 1 — SERP stars are the single biggest ROI lever.

Legend: **S** = ≤1d, **M** = 2–4d, **L** = 1–2w, **XL** = 2w+

---

## Phase 0 — Prep (parallel with Phase 1)

### TKT-0.1 — Start Google Business Profile API approval [M, blocks Phase 2]
OAuth app + verification takes 2–4 weeks. File now so Phase 2 isn't blocked.
- Register project in Google Cloud Console
- Request Business Profile API access (not Places — Places caps at 5 reviews)
- Document Place ID `ChIJj-6B3Xe3xUcRmjg1lTEhkIM` in plugin config

### TKT-0.2 — Plugin skeleton [S]
- `oz-reviews/` plugin folder, autoloader, settings page stub
- Register CPT `oz_shop_review` (not_public, show_in_rest, custom meta: rating, source, verified, photos, project_type, color_used, m2)
- Register review-specific meta on `product` comments (topics, photos, verified_order_id)
- Settings page: Google Place ID, reminder cadence, min-rating threshold for Slack alert

---

## Phase 1 — MVP (target: 6 weeks of dev)

Objective: fully own the review loop end-to-end. Users can submit, admins can moderate, visitors see rich PDP + /reviews hub, SERP shows stars.

### 1A. Collection

#### TKT-1.1 — Product review submission form [M]
Build on `oz-forms`. New form schema `product-review.php`:
- `rating` (1–5 star picker, required)
- `title` (text, required, ≤100 chars)
- `body` (textarea, required, 20–2000 chars)
- `photos` (multi-file, image/\*, max 4, 8MB each) — extend existing `file` field to array
- `project_type` (select: vloer / badkamer / keuken / meubel / trap / overig)
- `color_used` (text, autocomplete from BCW colors)
- `m2` (number, optional)
- Hidden: `order_id`, `product_id` (pre-filled from signed email link)
- Turnstile + honeypot (already in oz-forms)

Submission handler writes to `wp_comments` with `comment_type=review`, status=0 (unapproved).

#### TKT-1.2 — Shop-wide review submission [S]
Separate form schema `shop-review.php` — no product_id. Writes to `oz_shop_review` CPT with status=pending.

#### TKT-1.3 — Signed review links [M]
One-click deep-link in emails: `/review/?oid=<order_id>&pid=<product_id>&k=<hmac>`.
- Pre-fills product context, skips login
- HMAC over `order_id|product_id|user_id`, 30-day expiry
- Single-use token (nonce stored in order meta, invalidated on submit)

### 1B. Email automation

#### TKT-1.4 — Action Scheduler hooks [S]
- On `woocommerce_order_status_completed`: schedule `oz_reviews_send_request` at +3 days
- On `woocommerce_order_status_refunded` / cancelled: unschedule
- Settings: cadence offset (default 3d), reminder offset (default +7d)

#### TKT-1.5 — Review-request email template [M]
- Lists each product in the order with thumbnail + "Review dit product" button (signed link from TKT-1.3)
- Also includes generic "Review de winkel" button (shop-wide, signed with order_id only)
- Branded oz-forms style (reuses email partials)

#### TKT-1.6 — One reminder email [S]
Schedule second `oz_reviews_send_reminder` at +10d. Only send if no review for any product on the order. Different copy ("Nog niet toegekomen?").

#### TKT-1.7 — Ethical Google CTA [S]
Thank-you page after review submit shows Google CTA **universally** (no gating by rating) linking to `https://search.google.com/local/writereview?placeid=...`. Equal prominence to all submitters — Google ToS compliant.

### 1C. Moderation

#### TKT-1.8 — Unified moderation UI [M]
Admin screen showing both `wp_comments` product reviews and `oz_shop_review` CPT entries in one queue.
- Bulk approve / reject / mark spam
- Side panel: order link, product link, previous reviews by same customer
- Staff reply field (threaded for product reviews, direct for shop)
- Auto-flag: <3★ reviews, reviews with URLs, reviews from IPs with previous spam

#### TKT-1.9 — Slack webhook [S]
New review → Slack message with rating, product, excerpt, moderation link. Separate channel routing for <4★ (triggers "reply within 48h" SLA).

### 1D. Display — PDP

#### TKT-1.10 — PDP reviews block [L]
Replaces WC default review template. Server-rendered:
- Summary: big average, star row, count, rating histogram (5 bars: %5, %4, %3, %2, %1)
- Topic chips (aggregated from meta.topics — start with manual curation, AI in phase 2)
- Photo gallery (grid of review photos across all reviews for this product, lightbox on click)
- Filter controls: rating, has photo, project_type
- Per-review card: stars, title, verified-buyer badge, body, photos, author first name + city, date, staff reply (if any)
- "Helpful" counter (localStorage + lightweight AJAX increment, no login)

#### TKT-1.11 — Schema.org markup [S]
Server-side `AggregateRating` on product, `Review` items for top-N on PDP. Validated against Rich Results Test.

### 1E. Display — sitewide

#### TKT-1.12 — /reviews/ hub [M]
Replaces current Trustindex page. Server-rendered grid of latest approved reviews (both product + shop).
- Filters: rating, project_type, with_photos
- Infinite scroll / "load more"
- Summary strip at top (overall average across all sources, total count)
- Google-imported reviews appear alongside native (phase 2 fills this — phase 1 ships with native-only and placeholder)

#### TKT-1.13 — Homepage reviews section migration [S]
Swap current hardcoded `oz-theme/inc/reviews-section.php` dummy array → live query from new data source. Cached in transient, invalidated on comment_post / save_post.

### 1F. Ops

#### TKT-1.14 — Trustindex export/import [S]
CSV import of existing Google reviews currently in Trustindex so /reviews/ isn't empty on launch. One-shot migration, not ongoing sync (that's phase 2).

#### TKT-1.15 — Deprecate Trustindex [S]
After TKT-1.13 + 1.14 live and verified on staging → remove `[trustindex]` shortcode usage from /reviews/ page, disable plugin on prod. Part of plugin-cleanup count.

**Phase 1 exit criteria:** every Trustindex function replaced, SERP shows stars, email loop running on staging for 2 weeks with ≥50 reviews collected.

---

## Phase 2 — Amplification & intelligence

### TKT-2.1 — Google Business Profile sync [L] *(blocked on TKT-0.1)*
- Pull all Google reviews hourly via Business Profile API
- Upsert into `oz_shop_review` CPT with `source=google`, preserves all historical reviews (not 5-cap)
- Reply to Google reviews from our moderation UI (API supports it)

### TKT-2.2 — Photo/video upload for shop-wide [S]
Extend 1.2 to accept media.

### TKT-2.3 — AI topic extraction [M]
Nightly cron: batch unprocessed reviews, Claude Haiku API call to extract topics (e.g., "droogt snel", "kleurverloop", "goede service"). Store on meta.topics. Feeds PDP chips + intelligence dashboard.

### TKT-2.4 — Sentiment dashboard [M]
Admin-only page: rating over time, topic trends, alerts when avg rating drops >0.2 week-over-week, word cloud of negative topics per product.

### TKT-2.5 — Ambassador tier [M]
After 3rd approved review: auto-assign "ambassador" user role, 10% discount code, badge displayed on their reviews. Entry for referral program (future phase).

### TKT-2.6 — 1-year project follow-up [S]
Second Action Scheduler job at +365d on completed orders: "Hoe houdt je project zich?" email inviting updated review + photos.

### TKT-2.7 — Project map [M]
Showcase map of reviews with photos pinned by postcode (opt-in per reviewer). Builds social proof for "beton-cire in mijn regio" searches.

### TKT-2.8 — Trade vs DIY toggle [S]
Extra field on submission + filter on display. Lets prospects filter for reviews from their own buyer segment.

### TKT-2.9 — Color-matched reviews [M]
On each product color variant: show reviews where `color_used` matches. Cross-reference BCW color taxonomy.

### TKT-2.10 — Kiyoh / external distribution [M]
Outbound sync: push native reviews to Kiyoh (Dutch buyer-trust badge, common in NL e-commerce) and Google Shopping feed.

### TKT-2.11 — Pro installer directory [L]
Separate CPT for installers; reviews tagged with installer gain a searchable directory. Revenue opportunity (paid pro listings).

---

## Dependencies & critical path

```
TKT-0.1 (Google API approval) ──────────────── blocks ──→ TKT-2.1
TKT-0.2 (skeleton) → TKT-1.1/1.2 (forms) → TKT-1.3 (signed links) → TKT-1.5 (email)
                                      ↓
                                 TKT-1.8 (moderation) ← required before launch
                                      ↓
                          TKT-1.10/1.11/1.12/1.13 (display) — parallelizable
                                      ↓
                          TKT-1.14 → TKT-1.15 (cutover)
```

## Reuse from existing code

- `oz-forms`: form schema, multi-step, Turnstile, file upload, submission CPT, REST handler, email transport — do not rebuild.
- `oz-theme/inc/reviews-section.php`: markup stays, data source swaps (TKT-1.13).
- `oz-theme/css/oz-reviews.css`: keep the card visual; widen selectors past `.ti-*` to new classes.

## Risks

- Google Business Profile API approval can slip past 4 weeks → start TKT-0.1 immediately, even if Phase 1 kickoff is later.
- Review-request emails hitting spam on cold domain → use transactional provider (Postmark/Resend) rather than wp_mail; add to `oz-forms` mailer abstraction.
- Moderation load once volume ramps → TKT-1.8 must ship with keyboard shortcuts + bulk actions from day 1, or we'll bottleneck on manual queue.
