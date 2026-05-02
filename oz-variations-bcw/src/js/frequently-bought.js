/**
 * Frequently Bought Together — carousel + quick add + inline option overlay.
 *
 * Card behaviour:
 *   - data-has-options="0" (single product)
 *       Click cart icon → POST product_id → cart drawer opens.
 *
 *   - data-has-options="1" (grouped size siblings, separate WC products
 *     consolidated server-side; data-variants holds the array)
 *       Click + → reveal overlay with size pills (rendered from data-variants).
 *       Pick pill → enable "Toevoegen" CTA.
 *       Click CTA → POST product_id of chosen sibling → cart drawer opens.
 *       Click X / outside / Esc → close overlay.
 *
 * NOTE: grouped cards do NOT use variation_id — each "size" is its own
 * simple WC product. The selected pill's product_id is sent directly.
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
  // ozLoadSwiper is guaranteed by the WP enqueue dep ('oz-swiper-loader'
  // listed as a dep of oz-product-page in class-frontend-display.php).
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
    // Close button (overlay)
    var closeBtn = e.target.closest('.oz-fbt-overlay-close');
    if (closeBtn) {
      e.preventDefault();
      e.stopPropagation();
      closeBtn.closest('.oz-fbt-card').classList.remove('is-open');
      return;
    }

    // Option pill (overlay)
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
        cta.dataset.chosenProductId = optBtn.dataset.productId;
      }
      return;
    }

    // Overlay CTA (Toevoegen): add the chosen size's product_id
    var ctaBtn = e.target.closest('.oz-fbt-overlay-cta');
    if (ctaBtn && !ctaBtn.disabled) {
      e.preventDefault();
      e.stopPropagation();
      var ctaCard      = ctaBtn.closest('.oz-fbt-card');
      var chosenPid    = parseInt(ctaBtn.dataset.chosenProductId, 10);
      if (!chosenPid) return;
      sendAdd(ctaCard, ctaBtn, chosenPid, function () {
        ctaCard.classList.remove('is-open');
      });
      return;
    }

    // Main action button (cart for single, + for group)
    var addBtn = e.target.closest('.oz-fbt-action');
    if (!addBtn) return;
    var card2 = addBtn.closest('.oz-fbt-card');
    if (!card2) return;
    e.preventDefault();
    e.stopPropagation();

    var hasOptions = card2.dataset.hasOptions === '1';

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

    // Simple product → quick add using the card's product_id
    var pid = parseInt(card2.dataset.productId, 10);
    if (!pid || addBtn.disabled) return;
    sendAdd(card2, addBtn, pid);
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

  // ── Helpers ────────────────────────────────────────────────

  function buildOptionsIfNeeded(card) {
    var box = card.querySelector('.oz-fbt-options');
    if (!box || box.dataset.built === '1') return;

    var raw = card.dataset.variants;
    if (!raw) return;
    var variants;
    try { variants = JSON.parse(raw); } catch (_) { return; }
    if (!variants || !variants.length) return;

    var html = '';
    for (var i = 0; i < variants.length; i++) {
      var v = variants[i];
      // Server-side already sanitised label via wp_strip_all_tags + esc_attr.
      // Defense-in-depth: escape via DOM here too.
      var safeLabel = escapeText(v.label);
      html += '<button type="button" class="oz-fbt-option" data-product-id="' + v.id + '">' +
              safeLabel +
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

  function sendAdd(card, btn, productId, onSuccess) {
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

      // Brief checkmark feedback on the originating button (only the
      // main action button gets the swap; the overlay CTA stays as
      // text since it's about to disappear with the overlay).
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
