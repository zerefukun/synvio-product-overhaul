/**
 * Frequently Bought Together — carousel + quick add + inline option overlay.
 *
 * Card behaviour:
 *   - data-has-options="0" (single product)
 *       Click cart icon → POST product_id → cart drawer opens.
 *
 *   - data-has-options="1" (sized family — pre-rendered pills inside
 *     .oz-fbt-overlay over the body section)
 *       Click + → reveal overlay over the body (image stays visible).
 *       Click any size pill → POST that pill's product_id → cart drawer opens.
 *       Click X / outside / Esc → close overlay without adding.
 *
 * Each "size" is its own simple WC product, so the chosen pill's
 * data-product-id is sent directly. Reuses oz_cart_drawer_add (no
 * variation_id needed).
 *
 * Reuses:
 *   - window.ozLoadSwiper (no extra script tag)
 *   - oz_cart_drawer_add AJAX endpoint
 *   - 'oz-added-to-cart' DOM event so the cart drawer opens itself
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
  // ozLoadSwiper is guaranteed by the WP enqueue dep on 'oz-swiper-loader'.
  window.ozLoadSwiper(function () {
    new Swiper(swiperEl, {
      slidesPerView: 1.4,
      spaceBetween: 12,
      navigation: { prevEl: prevBtn, nextEl: nextBtn },
      breakpoints: {
        480:  { slidesPerView: 2.3, spaceBetween: 12 },
        768:  { slidesPerView: 3.3, spaceBetween: 16 },
        1024: { slidesPerView: 4,   spaceBetween: 20 },
        1280: { slidesPerView: 5,   spaceBetween: 20 },
      },
    });
  });

  // ── Click delegation: one handler covers every card action ──
  section.addEventListener('click', function (e) {
    // Close button (overlay)
    var closeBtn = e.target.closest('.oz-fbt-overlay-close');
    if (closeBtn) {
      e.preventDefault();
      e.stopPropagation();
      closeBtn.closest('.oz-fbt-card').classList.remove('is-open');
      return;
    }

    // Size pill (overlay) — one-shot add
    var optBtn = e.target.closest('.oz-fbt-option');
    if (optBtn) {
      e.preventDefault();
      e.stopPropagation();
      var optCard = optBtn.closest('.oz-fbt-card');
      var pid = parseInt(optBtn.dataset.productId, 10);
      if (!pid) return;
      sendAdd(optCard, optBtn, pid, function () {
        // Brief is-added pulse on the chosen pill, then close overlay.
        optBtn.classList.add('is-added');
        setTimeout(function () {
          optCard.classList.remove('is-open');
          optBtn.classList.remove('is-added');
        }, 900);
      });
      return;
    }

    // Main action button (cart for single, + for group)
    var addBtn = e.target.closest('.oz-fbt-action');
    if (!addBtn) return;
    var card = addBtn.closest('.oz-fbt-card');
    if (!card) return;
    e.preventDefault();
    e.stopPropagation();

    var hasOptions = card.dataset.hasOptions === '1';

    if (hasOptions) {
      // Close any other open card first — only one overlay at a time.
      section.querySelectorAll('.oz-fbt-card.is-open').forEach(function (c) {
        if (c !== card) c.classList.remove('is-open');
      });
      card.classList.toggle('is-open');
      return;
    }

    // Simple product → quick add using the card's product_id
    var pid2 = parseInt(card.dataset.productId, 10);
    if (!pid2 || addBtn.disabled) return;
    sendAdd(card, addBtn, pid2);
  });

  // Click outside an open card closes it
  document.addEventListener('click', function (e) {
    if (e.target.closest('.oz-fbt-card')) return;
    section.querySelectorAll('.oz-fbt-card.is-open').forEach(function (c) {
      c.classList.remove('is-open');
    });
  });

  // Esc closes any open overlay
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    section.querySelectorAll('.oz-fbt-card.is-open').forEach(function (c) {
      c.classList.remove('is-open');
    });
  });

  // ── AJAX add ──────────────────────────────────────────────

  function sendAdd(card, btn, productId, onSuccess) {
    if (btn.disabled) return;
    btn.disabled = true;
    btn.classList.add('is-loading');

    var cfg = window.ozCartDrawer || {};
    if (!cfg.ajaxUrl || !cfg.nonce) {
      // Drawer config missing on this page — fall back to PDP.
      var url = card.dataset.productUrl;
      if (url) window.location.href = url;
      btn.disabled = false;
      btn.classList.remove('is-loading');
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
      btn.classList.remove('is-loading');

      var ok = false;
      try {
        var json = JSON.parse(xhr.responseText);
        ok = json && json.success;
      } catch (_) { /* swallow */ }

      if (!ok) {
        btn.style.animation = 'oz-fbt-shake 0.4s';
        setTimeout(function () { btn.style.animation = ''; }, 400);
        return;
      }

      // Brief checkmark feedback on the originating action button (only
      // when it's the main cart/+ button — for pills the success state is
      // handled by the caller via onSuccess so the pill itself can pulse).
      if (btn.classList.contains('oz-fbt-action')) {
        var originalHTML = btn.innerHTML;
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7.5l3 3 5-6"/></svg>';
        btn.classList.add('is-added');
        setTimeout(function () {
          btn.innerHTML = originalHTML;
          btn.classList.remove('is-added');
        }, 1800);
      }

      document.dispatchEvent(new CustomEvent('oz-added-to-cart'));

      try {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
          event: 'oz_fbt_added',
          oz_product_id: productId,
        });
      } catch (_) { /* swallow */ }

      if (onSuccess) onSuccess();
    };
    xhr.send(body);
  }
}
