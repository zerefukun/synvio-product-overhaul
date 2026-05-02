/**
 * Frequently Bought Together — carousel + quick add-to-cart.
 *
 * Why a separate file: single responsibility. Init runs only when the
 * carousel exists on the page (PDPs with enough order signal). Costs
 * nothing on pages without it.
 *
 * Swiper: piggy-backs on the shared window.ozLoadSwiper loader that's
 * already on the page (cart drawer, USP ticker, inspiratie, reviews
 * carousel all use it). Zero extra network requests for the library.
 *
 * Quick add: posts to the existing oz_cart_drawer_add endpoint and
 * dispatches the same 'oz-added-to-cart' event the rest of the site
 * uses, so the cart drawer opens automatically (no new UX surface).
 *
 * Variable products: redirect to the PDP via the existing card-link.
 * Inline options overlay is intentionally V1.5 — most tools/accessories
 * are simple products, the inline overlay is added later when data
 * shows variable cards in the carousel actually exist + matter.
 *
 * @package OZ_Variations_BCW
 * @since 2.3.0
 */

export function initFrequentlyBought() {
  var section = document.querySelector('.oz-fbt');
  if (!section) return;

  var swiperEl = section.querySelector('.oz-fbt-swiper');
  var prevBtn  = section.querySelector('.oz-fbt-prev');
  var nextBtn  = section.querySelector('.oz-fbt-next');
  if (!swiperEl) return;

  // ── Swiper init via shared loader ──────────────────────────
  // window.ozLoadSwiper is guaranteed by the WP enqueue dependency
  // ('oz-swiper-loader' is listed as a dep of oz-product-page in
  // class-frontend-display.php), so the loader script always runs first.
  // No race, no fallback needed.
  window.ozLoadSwiper(function () {
    new Swiper(swiperEl, {
      slidesPerView: 1.4,
      spaceBetween: 12,
      navigation: {
        prevEl: prevBtn,
        nextEl: nextBtn,
      },
      breakpoints: {
        480:  { slidesPerView: 2.3, spaceBetween: 12 },
        768:  { slidesPerView: 3.3, spaceBetween: 16 },
        1024: { slidesPerView: 4,   spaceBetween: 20 },
        1280: { slidesPerView: 5,   spaceBetween: 20 },
      },
    });
  });

  // ── Quick add-to-cart ──────────────────────────────────────
  // Delegate so we don't bind a listener per card. One handler on the
  // section catches every .oz-fbt-add click.
  section.addEventListener('click', function (e) {
    var btn = e.target.closest('.oz-fbt-add');
    if (!btn) return;

    var card = btn.closest('.oz-fbt-card');
    if (!card) return;

    e.preventDefault();
    e.stopPropagation();

    var hasOptions = card.dataset.hasOptions === '1';
    var productId  = parseInt(card.dataset.productId, 10);
    var productUrl = card.dataset.productUrl || '';

    if (hasOptions) {
      // Variable product — redirect to PDP for V1. The card itself is
      // already a link, but the button click was caught here; navigate.
      if (productUrl) window.location.href = productUrl;
      return;
    }

    if (!productId || btn.disabled) return;
    btn.disabled = true;

    // Use the same nonce the cart drawer already passes via wp_localize_script.
    var cfg = window.ozCartDrawer || {};
    if (!cfg.ajaxUrl || !cfg.nonce) {
      // Drawer config missing (shouldn't happen on PDPs) — safe redirect.
      if (productUrl) window.location.href = productUrl;
      btn.disabled = false;
      return;
    }

    var body = 'action=oz_cart_drawer_add' +
               '&nonce=' + encodeURIComponent(cfg.nonce) +
               '&product_id=' + productId +
               '&qty=1';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', cfg.ajaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      btn.disabled = false;
      var ok = false;
      try {
        var json = JSON.parse(xhr.responseText);
        ok = json && json.success;
      } catch (err) { /* swallow */ }

      if (!ok) {
        // Visible failure feedback — quick shake, no alert popup.
        btn.style.animation = 'oz-fbt-shake 0.4s';
        setTimeout(function () { btn.style.animation = ''; }, 400);
        return;
      }

      // Success: switch to checkmark briefly, fire the event the cart
      // drawer listens for. The drawer opens itself + refreshes its
      // own state via that event — no extra plumbing here.
      var originalHTML = btn.innerHTML;
      btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7.5l3 3 5-6"/></svg>';
      btn.classList.add('is-added');

      document.dispatchEvent(new CustomEvent('oz-added-to-cart'));

      // Lightweight analytics tag (no-op when dataLayer absent — ad blockers).
      try {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
          event: 'oz_fbt_added',
          oz_product_id: productId,
        });
      } catch (err) { /* swallow */ }

      setTimeout(function () {
        btn.innerHTML = originalHTML;
        btn.classList.remove('is-added');
      }, 1800);
    };
    xhr.send(body);
  });
}
