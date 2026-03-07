<?php
/**
 * Custom single product template for BCW product lines.
 *
 * Replaces the theme's default single-product.php when the product
 * belongs to a detected BCW product line. Uses Flatsome's header/footer.
 *
 * Layout: gallery (left) + options sidebar (right) in a 2-column grid.
 * Mobile: single column with sticky bar + bottom sheet.
 *
 * @package OZ_Variations_BCW
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Resolve product + line config
$product = wc_get_product(get_the_ID());
if (!$product) {
    get_footer();
    return;
}

$line_info  = OZ_Product_Line_Config::for_product($product);
$line_key   = $line_info['line'];
$config     = $line_info['config'];

// Product data
$product_id    = $product->get_id();
$current_color = get_post_meta($product_id, '_oz_color', true);
$product_name_full = $product->get_name();
// Strip color from display name. Two patterns:
// 1. Parenthesized: "Beton Ciré Original (Stone White 1000)" → "Beton Ciré Original"
// 2. Suffix match: "Microcement cement 2" with _oz_color "Cement 2" → "Microcement"
$product_name = preg_replace('/\s*\([^)]+\)\s*$/', '', $product_name_full);
if ($current_color && $product_name === $product_name_full) {
    // No parenthetical was stripped — try removing color as a case-insensitive suffix
    $product_name = preg_replace('/\s+' . preg_quote($current_color, '/') . '\s*$/i', '', $product_name_full);
}
$product_name = trim($product_name);
$price         = floatval($product->get_price());
$regular_price = floatval($product->get_regular_price());
$on_sale       = $product->is_on_sale();

// Gallery images
$main_image_id  = get_post_thumbnail_id($product_id);
$main_image_url = $main_image_id ? wp_get_attachment_image_url($main_image_id, 'large') : '';
$main_image_full = $main_image_id ? wp_get_attachment_image_url($main_image_id, 'full') : '';
$gallery_ids    = $product->get_gallery_image_ids();

// Option data from config
$pu_options      = OZ_Product_Line_Config::get_pu_options($line_key);
$primer_options  = OZ_Product_Line_Config::get_primer_options($line_key);
$colorfresh_opts = OZ_Product_Line_Config::get_colorfresh_options($line_key);
$toepassing_opts = OZ_Product_Line_Config::get_toepassing_options($line_key);
$pakket_opts     = OZ_Product_Line_Config::get_pakket_options($line_key);
$option_order    = OZ_Product_Line_Config::get_option_order($line_key);
$has_ral_ncs     = !empty($config['ral_ncs']);
$ral_ncs_only    = !empty($config['ral_ncs_only']);

// Variant swatches
$variants = OZ_Product_Processor::get_variant_display_data($product_id);

// Format price for display
$fmt_price = function($p) { return '€' . number_format($p, 2, ',', '.'); };
?>

<div id="oz-product-page" class="oz-product-page">

  <?php /* ─── BREADCRUMB ─── */ ?>
  <div class="oz-breadcrumb">
    <?php woocommerce_breadcrumb(['wrap_before' => '<nav class="oz-breadcrumb-nav">', 'wrap_after' => '</nav>']); ?>
  </div>

  <div class="oz-product-grid">

    <?php /* ═══ LEFT COLUMN: GALLERY ═══ */ ?>
    <div class="oz-left-column">

      <div class="oz-product-gallery">
        <!-- Main image -->
        <div class="oz-gallery-main">
          <img id="mainImg"
               src="<?php echo esc_url($main_image_url); ?>"
               alt="<?php echo esc_attr($product_name); ?>"
               loading="eager">
        </div>

        <!-- Thumbnails -->
        <?php if (!empty($gallery_ids) || $main_image_id) : ?>
        <div class="oz-gallery-thumbs">
          <?php if ($main_image_id) : ?>
            <div class="oz-gallery-thumb selected"
                 data-full-src="<?php echo esc_url(wp_get_attachment_image_url($main_image_id, 'large')); ?>">
              <img src="<?php echo esc_url(wp_get_attachment_image_url($main_image_id, 'thumbnail')); ?>"
                   alt="<?php echo esc_attr($product_name); ?>">
            </div>
          <?php endif; ?>
          <?php foreach ($gallery_ids as $gid) :
            $thumb = wp_get_attachment_image_url($gid, 'thumbnail');
            $large = wp_get_attachment_image_url($gid, 'large');
            if (!$thumb) continue;
          ?>
            <div class="oz-gallery-thumb"
                 data-full-src="<?php echo esc_url($large); ?>">
              <img src="<?php echo esc_url($thumb); ?>" alt="">
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Product description -->
      <div class="oz-product-info-section">
        <h2 class="oz-section-title">Productinformatie</h2>
        <div class="oz-description-wrapper">
          <div class="oz-description-content" id="descContent">
            <?php echo wp_kses_post($product->get_description()); ?>
          </div>
          <button class="oz-read-more" id="readMoreBtn">Lees meer</button>
        </div>
      </div>

      <!-- Specifications table — from product line config, with per-product override -->
      <?php
      $oz_specs = get_post_meta($product_id, '_oz_specs', true);
      if (empty($oz_specs) || !is_array($oz_specs)) {
          $oz_specs = isset($config['specs']) ? $config['specs'] : [];
      }
      ?>
      <?php if (!empty($oz_specs)) : ?>
      <div class="oz-product-info-section">
        <h2 class="oz-section-title">Specificaties</h2>
        <table class="oz-specs-table">
          <tbody>
            <?php foreach ($oz_specs as $spec_key => $spec_val) : ?>
              <tr>
                <th><?php echo esc_html($spec_key); ?></th>
                <td><?php echo esc_html($spec_val); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    </div><!-- .oz-left-column -->


    <?php /* ═══ RIGHT COLUMN: OPTIONS SIDEBAR ═══ */ ?>
    <div class="oz-product-summary">

      <?php if ($current_color) : ?>
        <div class="oz-color-label" id="colorLabel"><?php echo esc_html($current_color); ?></div>
      <?php endif; ?>

      <h1 class="oz-product-title"><?php echo esc_html($product_name); ?></h1>

      <div class="oz-product-base-price">
        <?php if ($on_sale) : ?>
          <del><?php echo esc_html($fmt_price($regular_price)); ?></del>
        <?php endif; ?>
        <span id="displayBasePrice"><?php echo esc_html($fmt_price($price)); ?></span>
        <span class="oz-per-unit">per <?php echo esc_html($config['unit']); ?></span>
      </div>

      <!-- USP chips — from product line config, with per-product override -->
      <?php
      $oz_usps = get_post_meta($product_id, '_oz_usps', true);
      if (empty($oz_usps) || !array_filter($oz_usps)) {
          $oz_usps = isset($config['usps']) ? $config['usps'] : [];
      }
      ?>
      <?php if (!empty($oz_usps) && array_filter($oz_usps)) : ?>
        <div class="oz-short-desc">
          <ul>
            <?php foreach ($oz_usps as $usp) : if (empty($usp)) continue; ?>
              <li>
                <svg class="oz-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"></path></svg>
                <?php echo esc_html($usp); ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- ═══ OPTIONS WIDGET (moves between page and sheet) ═══ -->
      <div id="optionsDesktopHome">
      <div id="optionsWidget">
        <div id="optionsSlotDesktop"></div>

        <?php
        // Render option sections in the configured order
        foreach ($option_order as $section) :
          switch ($section) :

            /* ─── COLOR SWATCHES ─── */
            case 'color': ?>
              <div class="oz-option-group" data-option="color">
                <div class="oz-option-header">
                  Kleur: <span class="oz-selected-value" id="selectedColorLabel"><?php echo esc_html($current_color ?: ''); ?></span>
                </div>

                <?php if ($has_ral_ncs) : ?>
                  <div id="colorModeSlot"><!-- Built by JS: mode buttons + custom input --></div>
                <?php endif; ?>

                <?php if (!$ral_ncs_only) : ?>
                  <?php echo OZ_Frontend_Display::render_color_swatches($product); ?>
                <?php endif; ?>
              </div>
            <?php break;

            /* ─── PAKKET ─── */
            case 'pakket':
              if ($pakket_opts) : ?>
              <div class="oz-option-group" data-option="pakket">
                <div class="oz-option-header">Pakket</div>
                <div class="oz-option-labels">
                  <?php foreach ($pakket_opts as $opt) : ?>
                    <button class="oz-option-label-btn<?php echo $opt['default'] ? ' selected' : ''; ?>"
                            data-pakket="<?php echo esc_attr($opt['label']); ?>">
                      <?php echo esc_html($opt['label']); ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif;
            break;

            /* ─── TOEPASSING ─── */
            case 'toepassing':
              if ($toepassing_opts) : ?>
              <div class="oz-option-group" data-option="toepassing">
                <div class="oz-option-header">
                  Toepassing: <span class="oz-selected-value" id="selectedToepassingLabel"></span>
                </div>
                <div class="oz-option-labels">
                  <?php foreach ($toepassing_opts as $i => $label) : ?>
                    <button class="oz-option-label-btn<?php echo $i === 0 ? ' selected' : ''; ?>"
                            data-toepassing="<?php echo esc_attr($label); ?>">
                      <?php echo esc_html($label); ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif;
            break;

            /* ─── PRIMER ─── */
            case 'primer':
              if ($primer_options) : ?>
              <div class="oz-option-group" data-option="primer">
                <div class="oz-option-header">
                  Primer
                  <button class="oz-info-btn" data-info-target="primer-info">i</button>
                </div>
                <div class="oz-info-tooltip" id="primer-info">
                  Primer zorgt voor een goede hechting op de ondergrond. Kies de juiste primer op basis van je ondergrond.
                </div>
                <div class="oz-option-labels">
                  <?php foreach ($primer_options as $opt) : ?>
                    <button class="oz-option-label-btn<?php echo $opt['default'] ? ' selected' : ''; ?>"
                            data-primer="<?php echo esc_attr($opt['label']); ?>">
                      <?php echo esc_html($opt['label']); ?>
                      <?php if ($opt['price'] > 0) : ?>
                        <span class="oz-price-add">+<?php echo esc_html($fmt_price($opt['price'])); ?></span>
                      <?php endif; ?>
                      <?php if (!empty($opt['layers']) && $opt['layers'] == 2) : ?>
                        <span class="oz-recommended">Advies</span>
                      <?php endif; ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif;
            break;

            /* ─── COLORFRESH ─── */
            case 'colorfresh':
              if ($colorfresh_opts) : ?>
              <div class="oz-option-group" data-option="colorfresh">
                <div class="oz-option-header">
                  Colorfresh
                  <button class="oz-info-btn" data-info-target="colorfresh-info">i</button>
                </div>
                <div class="oz-info-tooltip" id="colorfresh-info">
                  Colorfresh geeft een extra beschermlaag en frist de kleur op na verloop van tijd.
                </div>
                <div class="oz-option-labels">
                  <?php foreach ($colorfresh_opts as $opt) : ?>
                    <button class="oz-option-label-btn<?php echo $opt['default'] ? ' selected' : ''; ?>"
                            data-colorfresh="<?php echo esc_attr($opt['label']); ?>">
                      <?php echo esc_html($opt['label']); ?>
                      <?php if ($opt['price'] > 0) : ?>
                        <span class="oz-price-add">+<?php echo esc_html($fmt_price($opt['price'])); ?></span>
                      <?php endif; ?>
                      <?php if (!empty($opt['layers']) && $opt['layers'] == 2) : ?>
                        <span class="oz-recommended">Advies</span>
                      <?php endif; ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif;
            break;

            /* ─── PU TOPLAGEN ─── */
            case 'pu':
              if ($pu_options) : ?>
              <div class="oz-option-group" data-option="pu">
                <div class="oz-option-header">
                  PU Toplaag
                  <button class="oz-info-btn" data-info-target="pu-info">i</button>
                </div>
                <div class="oz-info-tooltip" id="pu-info">
                  PU coating beschermt het oppervlak tegen slijtage, vlekken en vocht. Meer lagen = meer bescherming.
                </div>
                <div class="oz-option-labels">
                  <?php foreach ($pu_options as $opt) : ?>
                    <button class="oz-option-label-btn<?php echo $opt['default'] ? ' selected' : ''; ?>"
                            data-pu="<?php echo esc_attr($opt['layers'] ?? ''); ?>">
                      <?php echo esc_html($opt['label']); ?>
                      <?php if ($opt['price'] > 0) : ?>
                        <span class="oz-price-add">+<?php echo esc_html($fmt_price($opt['price'])); ?></span>
                      <?php endif; ?>
                      <?php if (!empty($opt['layers']) && $opt['layers'] == 2) : ?>
                        <span class="oz-recommended">Advies</span>
                      <?php endif; ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif;
            break;


            /* ─── TOOLS / GEREEDSCHAP ─── */
            case "tools":
              if (!empty($config["has_tools"])) : ?>
              <div class="oz-option-group" data-option="tools">
                <div class="oz-option-header">
                  Gereedschap
                  <button class="oz-info-btn" data-info-target="tools-info">i</button>
                </div>
                <div class="oz-info-tooltip" id="tools-info">Kies een complete set of stel je eigen gereedschap samen. Een PU roller gaat ~2 uur mee, een spaan veel langer — zo bestel je precies wat je nodig hebt.</div>
                <div id="toolSection">
                  <!-- Built by buildToolSectionV2() in JS -->
                </div>
              </div>
              <?php endif;
            break;

          endswitch;
        endforeach;
        ?>

        <!-- M² advice tip — only for m²-based products -->
        <?php if ($config['unitM2'] > 0) : ?>
        <div class="oz-m2-advice">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>
          </svg>
          <span>Tip van onze specialist: bestel altijd extra materiaal. Oneffenheden in de ondergrond, reparaties en verwerking vragen meer product dan de berekende vierkante meters. Tekort komen is geen optie!</span>
        </div>
        <?php endif; ?>

        <!-- Quantity + Add to Cart -->
        <div class="oz-option-group">
          <div class="oz-option-header">
            Aantal
            <?php if ($config['unitM2'] > 0) : ?>
              <span class="oz-m2-note" id="optionsM2Note">per <?php echo esc_html($config['unit']); ?></span>
            <?php endif; ?>
          </div>
          <div class="oz-cart-row">
            <div class="oz-quantity-wrapper">
              <button class="oz-qty-btn" data-qty-delta="-1">−</button>
              <input type="number" class="oz-qty-input" id="qtyInput" value="1" min="1" max="99">
              <button class="oz-qty-btn" data-qty-delta="1">+</button>
            </div>
            <button class="oz-add-to-cart" id="addToCartBtn">In winkelmand</button>
          </div>
        </div>

      </div><!-- #optionsWidget -->
      </div><!-- #optionsDesktopHome -->

      <!-- Price Breakdown -->
      <div class="oz-price-summary" id="priceSummary">
        <div class="oz-price-line" id="priceBaseLine">
          <span id="priceBaseLabel"><?php echo esc_html($product_name); ?></span>
          <span id="priceBase"><?php echo esc_html($fmt_price($price)); ?></span>
        </div>
        <div class="oz-price-line" id="pricePuLine" style="display:none;">
          <span id="pricePuLabel">PU Toplaag</span>
          <span id="pricePu"></span>
        </div>
        <div class="oz-price-line" id="pricePrimerLine" style="display:none;">
          <span id="pricePrimerLabel">Primer</span>
          <span id="pricePrimer"></span>
        </div>
        <div class="oz-price-line" id="priceColorfreshLine" style="display:none;">
          <span id="priceColorfreshLabel">Colorfresh</span>
          <span id="priceColorfresh"></span>
        </div>
        <div class="oz-price-line oz-price-subtotal" id="priceQtyLine" style="display:none;">
          <span id="priceQtyLabel"></span>
          <span id="priceQty"></span>
        </div>
        <div class="oz-price-line" id="priceToolsLine" style="display:none;">
          <span id="priceToolsLabel">Gereedschapsset</span>
          <span id="priceTools"></span>
        </div>
        <div class="oz-price-line oz-price-total">
          <span>Totaal</span>
          <span id="priceTotal"><?php echo esc_html($fmt_price($price)); ?></span>
        </div>
      </div>

      <!-- Trust Badges -->
      <div class="oz-trust-badges">
                <div class="oz-trust-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="1"></rect><path d="M16 8h4l3 3v5h-7V8z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    Verzending NL, BE en DE
                </div>
                <div class="oz-trust-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Achteraf betalen mogelijk
                </div>
                <div class="oz-trust-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    20.000+ klanten
                </div>
                <div class="oz-trust-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Vragen? Wij helpen je!
                </div>
      </div>

    </div><!-- .oz-product-summary -->

  </div><!-- .oz-product-grid -->

</div><!-- .oz-product-page -->


<?php /* ═══ MOBILE STICKY BAR ═══ */ ?>
<div class="oz-sticky-bar" id="stickyBar">
  <div class="oz-sticky-info">
    <img class="oz-sticky-thumb" id="stickyThumb"
         src="<?php echo esc_url(wp_get_attachment_image_url($main_image_id, 'thumbnail')); ?>"
         alt="">
    <div class="oz-sticky-details">
      <div class="oz-sticky-product-name"><?php echo esc_html($product_name); ?></div>
      <?php if ($current_color) : ?>
      <div class="oz-sticky-color">
        <span class="oz-sticky-color-dot" id="stickyColorDot"></span>
        <span id="stickyColorName"><?php echo esc_html($current_color); ?></span>
      </div>
      <?php endif; ?>
    </div>
    <div class="oz-sticky-price">
      <span id="stickyPrice"><?php echo esc_html($fmt_price($price)); ?></span>
      <?php if (!empty($config['unitM2'])) : ?>
      <span class="oz-sticky-price-unit" id="stickyPriceUnit">per <?php echo esc_html($config['unitM2']); ?>m²</span>
      <?php endif; ?>
    </div>
  </div>
  <button class="oz-sticky-btn" id="stickyBtn">In winkelmand</button>
</div>

<?php /* ═══ BOTTOM SHEET ═══ */ ?>
<div class="oz-sheet-overlay" id="sheetOverlay"></div>
<div class="oz-bottom-sheet" id="bottomSheet">
  <div class="oz-sheet-handle"></div>
  <div class="oz-sheet-title">Kies je opties</div>
  <div id="optionsSlotSheet"></div>

  <div class="oz-sheet-footer">
    <div class="oz-sheet-breakdown">
      <div class="oz-sheet-price-line">
        <span><?php echo esc_html($product_name); ?> <span id="sheetColorName"><?php echo esc_html($current_color); ?></span></span>
        <span id="sheetPriceBase"><?php echo esc_html($fmt_price($price)); ?></span>
      </div>
      <div class="oz-sheet-price-line" id="sheetPricePuLine" style="display:none">
        <span id="sheetPricePuLabel">PU Toplaag</span>
        <span id="sheetPricePu"></span>
      </div>
      <div class="oz-sheet-price-line" id="sheetPricePrimerLine" style="display:none">
        <span>Primer</span>
        <span id="sheetPricePrimer"></span>
      </div>
      <div class="oz-sheet-price-line oz-price-subtotal" id="sheetPriceQtyLine" style="display:none">
        <span id="sheetPriceQtyLabel"></span>
        <span class="oz-sheet-m2-small" id="sheetPriceQtyNote"></span>
      </div>
      <div class="oz-sheet-price-line" id="sheetPriceToolsLine" style="display:none">
        <span id="sheetPriceToolsLabel">Gereedschapsset</span>
        <span id="sheetPriceTools"></span>
      </div>
    </div>
    <div class="oz-sheet-total">
      <span class="oz-sheet-total-label">Totaal</span>
      <span class="oz-sheet-total-price" id="sheetTotal"><?php echo esc_html($fmt_price($price)); ?></span>
    </div>
  </div>
</div>


<!-- Upsell modal — appears after add-to-cart if no tools selected -->
<div class="oz-upsell-overlay" id="upsellOverlay">
  <div class="oz-upsell-modal" id="upsellModal">
    <div class="oz-upsell-icon">
      <svg viewBox="0 -960 960 960" fill="currentColor"><path d="m620-284 56-56q6-6 6-14t-6-14L540-505q4-11 6-22t2-25q0-57-40.5-97.5T410-690q-17 0-34 4.5T343-673l94 94-56 56-94-94q-8 16-12.5 33t-4.5 34q0 57 40.5 97.5T408-412q13 0 24.5-2t22.5-6l137 136q6 6 14 6t14-6ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>
    </div>
    <div class="oz-upsell-title">Gereedschap nodig?</div>
    <div class="oz-upsell-text">Voor een perfect resultaat heb je het juiste gereedschap nodig. Voeg een complete set toe aan je bestelling.</div>
    <div style="background:var(--oz-bg-subtle); border:1.5px solid var(--oz-border); border-radius:10px; padding:16px; margin-bottom:16px; text-align:left;">
      <div style="font-size:15px; font-weight:600; color:var(--oz-text-primary); margin-bottom:6px;">Gereedschapset Kant &amp; Klaar <span style="font-weight:700; color:var(--oz-accent); float:right;">+&euro;89,99</span></div>
      <div style="font-size:12px; color:var(--oz-text-muted); line-height:1.5;">Spaan, 3x PU roller, kwast, PU garde, tape, 2x verfbak, vachtroller</div>
    </div>
    <button class="oz-upsell-add" id="upsellAddBtn">Set toevoegen &amp; doorgaan</button>
    <button class="oz-upsell-skip" id="upsellSkipBtn">Nee bedankt, doorgaan zonder gereedschap</button>
  </div>
</div>

<?php get_footer(); ?>
