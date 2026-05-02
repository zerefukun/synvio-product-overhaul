/**
 * Frequently Bought Together — carousel + quick add + inline options overlay.
 *
 * Behaviour by card type:
 *   - Simple product (data-has-options="0")
 *       Click cart icon → POST product_id → cart drawer opens.
 *
 *   - Variable product (data-has-options="1")
 *       Click + icon  → reveal inline overlay with option pills (variations
 *                       pre-rendered in data-variations JSON, no AJAX).
 *       Click pill    → mark selected, enable "Toevoegen" CTA.
 *       Click CTA     → POST product_id + variation_id → cart drawer opens.
 *       Click X / outside → close overlay.
 *
 * Reuses:
 *   - window.ozLoadSwiper for carousel init (no extra script tag).
 *   - oz_cart_drawer_add AJAX endpoint (extended to accept variation_id).
 *   - 'oz-added-to-cart' DOM event so the cart drawer opens itself.
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
  // ozLoadSwiper is guaranteed by the WP enqueue dep ('oz-swiper-loader'
  // listed as a dep of oz-product-page).
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

  // ── Click delegation: one handler covers every card action ──
  section.addEventListener('click', function (e) {
    // Close button (variable card overlay)
    var closeBtn = e.target.closest('.oz-fbt-overlay-close');
    if (closeBtn) {
      e.preventDefault();
      e.stopPropagation();
      closeBtn.closest('.oz-fbt-card').classList.remove('is-open');
      return;
    }

    // Option pill (variable card overlay)
    var optBtn = e.target.closest('.oz-fbt-option');
    if (optBtn) {
      e.preventDefault();
      e.stopPropagation();
      var card = optBtn.closest('.oz-fbt-card');
      card.querySelectorAll('.oz-fbt-option').forEach(function (b) {
        b.classList.remove('is-selected');
      });
      optBtn.classList.add('is-selected');
      var cta = card.querySelector('.oz-fbt-overlay-cta');
      if (cta) {
        cta.disabled = false;
        cta.dataset.variationId = optBtn.dataset.variationId;
      }
      return;
    }

    // Overlay CTA: add the chosen variation
    var ctaBtn = e.target.closest('.oz-fbt-overlay-cta');
    if (ctaBtn && !ctaBtn.disabled) {
      e.preventDefault();
      e.stopPropagation();
      var ctaCard       = ctaBtn.closest('.oz-fbt-card');
      var ctaProductId  = parseInt(ctaCard.dataset.productId, 10);
      var ctaVariation  = parseInt(ctaBtn.dataset.variationId, 10);
      if (!ctaProductId || !ctaVariation) return;
      sendAdd(ctaCard, ctaBtn, ctaProductId, ctaVariation, function () {
        // Close overlay on success — feedback comes from the cart drawer opening
        ctaCard.classList.remove('is-open');
      });
      return;
    }

    // Main + / cart icon
    var addBtn = e.target.closest('.oz-fbt-add');
    if (!addBtn) return;
    var card2       = addBtn.closest('.oz-fbt-card');
    if (!card2) return;
    e.preventDefault();
    e.stopPropagation();

    var hasOptions = card2.dataset.hasOptions === '1';
    var productId  = parseInt(card2.dataset.productId, 10);

    if (hasOptions) {
      // Open overlay (lazy-build option pills the first time)
      buildOptionsIfNeeded(card2);
      // Close any other open card first — only one overlay at a time
      section.querySelectorAll('.oz-fbt-card.is-open').forEach(function (c) {
        if (c !== card2) c.classList.remove('is-open');
      });
      card2.classList.toggle('is-open');
      return;
    }

    // Simple product → quick add
    if (!productId || addBtn.disabled) return;
    sendAdd(card2, addBtn, productId, 0);
  });

  // Click outside an open card closes it
  document.addEventListener('click', function (e) {
    if (e.target.closest('.oz-fbt-card')) return;
    section.querySelectorAll('.oz-fbt-card.is-open').forEach(function (c) {
      c.classList.remove('is-open');
    });
  });

  // ── Helpers ────────────────────────────────────────────────

  function buildOptionsIfNeeded(card) {
    var box = card.querySelector('.oz-fbt-options');
    if (!box || box.dataset.built === '1') return;

    var raw = card.dataset.variations;
    if (!raw) return;
    var variations;
    try { variations = JSON.parse(raw); } catch (_) { return; }
    if (!variations || !variations.length) return;

    var html = '';
    for (var i = 0; i < variations.length; i++) {
      var v = variations[i];
      // textContent assignment via a tmp div would be safer; we control the
      // server-side label sanitization via wp_strip_all_tags + esc_attr.
      html += '<button type="button" class="oz-fbt-option" data-variation-id="' + v.id + '">' +
              escapeText(v.label) +
              '</button>';
    }
    box.innerHTML = html;
    box.dataset.built = '1';
  }

  function escapeText(s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  }

  function sendAdd(card, btn, productId, variationId, onSuccess) {
    if (btn.disabled) return;
    btn.disabled = true;

    var cfg = window.ozCartDrawer || {};
    if (!cfg.ajaxUrl || !cfg.nonce) {
      // Drawer config missing on this page (shouldn't happen on PDPs).
      // Fallback: redirect to PDP so the user can add normally.
      var url = card.dataset.productUrl;
      if (url) window.location.href = url;
      btn.disabled = false;
      return;
    }

    var body = 'action=oz_cart_drawer_add' +
               '&nonce=' + encodeURIComponent(cfg.nonce) +
               '&product_id=' + productId +
               '&qty=1';
    if (variationId) body += '&variation_id=' + variationId;

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
      } catch (_) { /* swallow */ }

      if (!ok) {
        btn.style.animation = 'oz-fbt-shake 0.4s';
        setTimeout(function () { btn.style.animation = ''; }, 400);
        return;
      }

      // Brief checkmark feedback on the originating button (for non-overlay flow)
      if (btn.classList.contains('oz-fbt-add')) {
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
          oz_variation_id: variationId || 0,
        });
      } catch (_) { /* swallow */ }

      if (onSuccess) onSuccess();
    };
    xhr.send(body);
  }
}
