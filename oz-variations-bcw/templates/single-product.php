<?php
/**
 * Universal single product template.
 *
 * Works for all page modes:
 * - configured_line: BCW product line with full options (PU, primer, etc.)
 * - generic_simple:  Any product with our shell (no addons)
 * - generic_addons:  Custom addon groups (future)
 *
 * Layout: gallery (left) + summary sidebar (right) in a 2-column grid.
 * Mobile: single column with sticky bar + bottom sheet.
 *
 * Missing blocks are hidden cleanly — no empty containers, no empty USP boxes.
 * Price summary / qty / CTA / delivery / payments / trust always render.
 *
 * @package OZ_Variations_BCW
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Resolve product + page mode
$product = wc_get_product(get_the_ID());
if (!$product) {
    get_footer();
    return;
}

$page_mode = OZ_Product_Line_Config::get_page_mode($product);

// Line config — real config for configured_line, generic defaults otherwise
$line_info  = OZ_Product_Line_Config::for_product($product);
$line_key   = $line_info['line'];
$config     = $line_info['config'] ?: OZ_Product_Line_Config::get_generic_config();

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
// WC stores prices incl-tax (woocommerce_prices_include_tax = yes)
// get_price() already returns the incl-tax amount
$price         = floatval($product->get_price());
$regular_price = floatval($product->get_regular_price());
$on_sale       = $product->is_on_sale();

// Gallery images
$main_image_id  = get_post_thumbnail_id($product_id);
$main_image_url = $main_image_id ? wp_get_attachment_image_url($main_image_id, 'large') : '';
$main_image_full = $main_image_id ? wp_get_attachment_image_url($main_image_id, 'full') : '';
$gallery_ids    = $product->get_gallery_image_ids();

// Is this a base (main) product? Base products are not purchasable —
// visitors must pick a color variant first.
$is_base = OZ_Product_Processor::is_base_product($product);

// Option data — only populated for configured_line mode
$has_options = ($page_mode === 'configured_line' && $line_key);
if ($has_options) {
    $pu_options      = OZ_Product_Line_Config::get_pu_options($line_key);
    $primer_options  = OZ_Product_Line_Config::get_primer_options($line_key);
    $colorfresh_opts = OZ_Product_Line_Config::get_colorfresh_options($line_key);
    $toepassing_opts = OZ_Product_Line_Config::get_toepassing_options($line_key);
    $pakket_opts     = OZ_Product_Line_Config::get_pakket_options($line_key);
    $option_order    = OZ_Product_Line_Config::get_option_order($line_key);
    $has_ral_ncs     = !empty($config['ral_ncs']);
    $ral_ncs_only    = !empty($config['ral_ncs_only']);
    $variants        = OZ_Product_Processor::get_variant_display_data($product_id);
} else {
    $pu_options = $primer_options = $colorfresh_opts = $toepassing_opts = $pakket_opts = false;
    $option_order = [];
    $has_ral_ncs = $ral_ncs_only = false;
    $variants = [];
}

// Generic addon groups — per-product option groups (replaces YITH WAPO)
$has_addon_groups = ($page_mode === 'generic_addons');
$addon_groups = $has_addon_groups ? OZ_Product_Line_Config::get_addon_groups($product_id) : false;

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
               crossorigin="anonymous"
               loading="eager">
        </div>

        <!-- Thumbnails (always rendered — JS rebuilds on pushState navigation) -->
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
      </div>

      <!-- Product description -->
      <?php
      $description = $product->get_description();
      if (!empty($description)) :
      ?>
      <div class="oz-product-info-section" id="sectionInfo">
        <h2 class="oz-section-title">Productinformatie</h2>
        <div class="oz-description-wrapper">
          <div class="oz-description-content" id="descContent">
            <?php echo apply_filters('the_content', $description); ?>
          </div>
          <button class="oz-read-more" id="readMoreBtn">Lees meer</button>
        </div>
      </div>
      <?php endif; ?>

      <!-- Per-section override flags — legacy shared override remains a fallback -->
      <?php
      $is_line_variant = !empty($config['base_id']) && $config['base_id'] !== $product_id;
      $legacy_override = $is_line_variant && get_post_meta($product_id, '_oz_override_shared', true) === 'yes';
      $ovr_usps  = $is_line_variant && (
          get_post_meta($product_id, '_oz_override_usps', true) === 'yes' || $legacy_override
      );
      $ovr_specs = $is_line_variant && (
          get_post_meta($product_id, '_oz_override_specs', true) === 'yes' || $legacy_override
      );
      $ovr_faq   = $is_line_variant && (
          get_post_meta($product_id, '_oz_override_faq', true) === 'yes' || $legacy_override
      );
      ?>

      <!-- Specifications table — shared from base product across all colors -->
      <?php
      // Specs fallback: variant override (if enabled) → base product → line config → empty
      if ($ovr_specs) {
          $oz_specs = get_post_meta($product_id, '_oz_specs', true);
      } else {
          $specs_source = !empty($config['base_id']) ? $config['base_id'] : $product_id;
          $oz_specs = get_post_meta($specs_source, '_oz_specs', true);
      }
      if (empty($oz_specs) || !is_array($oz_specs)) {
          $oz_specs = isset($config['specs']) ? $config['specs'] : [];
      }
      ?>
      <?php if (!empty($oz_specs)) : ?>
      <div class="oz-product-info-section" id="sectionSpecs">
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

      <?php
      // Comparison table — "Welk product past bij mij?"
      // Shows all 6 main product lines with key differentiators.
      // Current product row highlighted, others link to their base product.
      if ($page_mode === 'configured_line' && $line_key && current_user_can('manage_options')) :
          $cmp_data = OZ_Product_Line_Config::get_comparison_data();
          $cmp_cols = $cmp_data['columns'];
          $cmp_lines = $cmp_data['lines'];
      ?>
      <div class="oz-product-info-section" id="sectionCompare">
        <details class="oz-compare-details">
          <summary class="oz-section-title--toggle">
            <span class="oz-section-title">Welk product past bij mij?</span>
            <span class="oz-toggle-icon"></span>
          </summary>
          <div class="oz-compare-scroll">
            <table class="oz-compare-table">
              <thead>
                <tr>
                  <th class="oz-compare-th-product">Product</th>
                  <?php foreach ($cmp_cols as $col) : ?>
                    <th><?php echo esc_html($col['label']); ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($cmp_lines as $cmp_key => $cmp) :
                  $is_current = ($cmp_key === $line_key);
                  $cmp_url = (!$is_current && $cmp['base_id']) ? get_permalink($cmp['base_id']) : '';
                ?>
                <tr class="<?php echo $is_current ? 'oz-compare-current' : ''; ?>">
                  <td class="oz-compare-product">
                    <?php if ($cmp_url) : ?>
                      <a href="<?php echo esc_url($cmp_url); ?>"><?php echo esc_html($cmp['name']); ?></a>
                    <?php else : ?>
                      <strong><?php echo esc_html($cmp['name']); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($cmp['note'])) : ?>
                      <small class="oz-compare-note"><?php echo esc_html($cmp['note']); ?></small>
                    <?php endif; ?>
                  </td>
                  <?php foreach ($cmp_cols as $col) : ?>
                    <td><?php echo esc_html($cmp[$col['key']]); ?></td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </details>
      </div>
      <?php endif; ?>

      <!-- FAQ accordion — shared from base product across all colors -->
      <?php
      // FAQ fallback: variant override (if enabled) → base product → line config → empty
      if ($ovr_faq) {
          $oz_faq = get_post_meta($product_id, '_oz_faq', true);
      } else {
          $faq_source = !empty($config['base_id']) ? $config['base_id'] : $product_id;
          $oz_faq = get_post_meta($faq_source, '_oz_faq', true);
      }
      if (empty($oz_faq) || !is_array($oz_faq)) {
          $oz_faq = isset($config['faq']) ? $config['faq'] : [];
      }
      if (!empty($oz_faq) && is_array($oz_faq)) : ?>
      <div class="oz-product-info-section" id="sectionFaq">
        <h2 class="oz-section-title">Veelgestelde vragen</h2>
        <div class="oz-faq-list">
          <?php foreach ($oz_faq as $faq) : ?>
            <details class="oz-faq-item">
              <summary class="oz-faq-question"><?php echo esc_html($faq['q']); ?></summary>
              <div class="oz-faq-answer"><?php echo wp_kses_post($faq['a']); ?></div>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- .oz-left-column -->


    <?php /* ═══ RIGHT COLUMN: SUMMARY SIDEBAR ═══ */ ?>
    <div class="oz-product-summary">

      <?php
      // Color label above title — shown for color variants and shared-color products.
      // Always rendered for configured_line products (pushState may navigate from
      // base to variant, so the element must exist in DOM even when initially empty).
      $has_shared_colors = !empty($config['share_colors_from']);
      $has_color_variants = ($page_mode === 'configured_line' && !empty($config['base_id']));
      if ($current_color || $has_shared_colors || $has_color_variants) : ?>
        <div class="oz-color-label" id="colorLabel"
             <?php if (!$current_color) echo 'style="display:none"'; ?>
        ><?php echo esc_html($current_color ?: ''); ?></div>
      <?php endif; ?>

      <h1 class="oz-product-title"><?php echo esc_html($product_name); ?></h1>

      <div class="oz-product-base-price">
        <?php if ($on_sale) : ?>
          <del><?php echo esc_html($fmt_price($regular_price)); ?></del>
        <?php endif; ?>
        <span id="displayBasePrice"><?php echo esc_html($fmt_price($price)); ?></span>
        <span class="oz-per-unit">per <?php echo esc_html($config['unit']); ?></span>
      </div>

      <!-- USP chips — from config, per-product override, or WC short description -->
      <?php
      // USPs fallback: variant override (if enabled) → base product → line config → empty
      if ($ovr_usps) {
          $oz_usps = get_post_meta($product_id, '_oz_usps', true);
      } else {
          $usps_source = !empty($config['base_id']) ? $config['base_id'] : $product_id;
          $oz_usps = get_post_meta($usps_source, '_oz_usps', true);
      }
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

      <?php
      // Cross-link: suggest an alternative product line (e.g. "Liever kant & klaar? Bekijk Microcement")
      // Configured per line in class-product-line-config.php → 'cross_link' key
      if (!empty($config['cross_link']) && current_user_can('manage_options')) :
          $cl = $config['cross_link'];
          $cl_url = !empty($cl['base_id']) ? get_permalink($cl['base_id']) : (!empty($cl['url']) ? $cl['url'] : '#');
      ?>
        <div class="oz-cross-link">
          <span class="oz-cross-link-text"><?php echo esc_html($cl['text']); ?></span>
          <a href="<?php echo esc_url($cl_url); ?>" class="oz-cross-link-btn"><?php echo esc_html($cl['label']); ?> &rarr;</a>
        </div>
      <?php endif; ?>

      <!-- ═══ OPTIONS WIDGET (moves between page and sheet) ═══ -->
      <div id="optionsDesktopHome">
      <div id="optionsWidget">
        <div id="optionsSlotDesktop"></div>

        <?php
        // ─── GENERIC ADDON GROUPS ───
        // Rendered for generic_addons mode — per-product option groups
        if ($has_addon_groups && !empty($addon_groups)) :
          foreach ($addon_groups as $group) :
            $group_key = $group['key'];
            // Find default option
            $default_label = '';
            foreach ($group['options'] as list($opt_label, $opt_price, $opt_default)) {
                if ($opt_default) { $default_label = $opt_label; break; }
            }
            if (!$default_label && !empty($group['options'])) {
                $default_label = $group['options'][0][0];
            }
        ?>
            <div class="oz-option-group" data-option="addon_<?php echo esc_attr($group_key); ?>">
              <div class="oz-option-header">
                <?php echo esc_html($group['label']); ?>
              </div>
              <div class="oz-option-labels">
                <?php foreach ($group['options'] as list($opt_label, $opt_price, $opt_default)) : ?>
                  <button class="oz-option-label-btn<?php echo ($opt_label === $default_label) ? ' selected' : ''; ?>"
                          data-addon-key="<?php echo esc_attr($group_key); ?>"
                          data-addon-value="<?php echo esc_attr($opt_label); ?>">
                    <?php echo esc_html($opt_label); ?>
                    <?php if ($opt_price > 0) : ?>
                      <span class="oz-price-add">+<?php echo esc_html($fmt_price($opt_price)); ?></span>
                    <?php endif; ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
        <?php
          endforeach;
        endif;
        ?>

        <?php
        // Render option sections — only for configured_line mode with options.
        // Base products show ALL options (same as variant pages).
        if ($has_options && !empty($option_order)) :
          foreach ($option_order as $section) :
            switch ($section) :

              /* ─── COLOR SWATCHES ─── */
              case 'color': ?>
                <div class="oz-option-group" data-option="color">
                  <div class="oz-option-header">
                    Kleur: <span class="oz-selected-value" id="selectedColorLabel"><?php echo esc_html($current_color ?: 'Kies eerst uw kleur'); ?></span>
                  </div>

                  <?php if ($has_ral_ncs) : ?>
                    <div id="colorModeSlot"><!-- Built by JS: mode buttons + custom input --></div>
                  <?php endif; ?>

                  <?php if (!$ral_ncs_only) : ?>
                    <?php echo OZ_Frontend_Display::render_color_swatches($product); ?>
                  <?php endif; ?>

                  <?php /* "Gratis kleurstalen aanvragen" link — each line has its own page.
                         Lavasteen links to a paid product, so it uses custom text without "Gratis". */ ?>
                  <?php if (!empty($config['kleurstalen_url'])) :
                    $sample_text = !empty($config['kleurstalen_text'])
                      ? $config['kleurstalen_text']
                      : 'Gratis kleurstalen aanvragen';
                  ?>
                    <a href="<?php echo esc_url($config['kleurstalen_url']); ?>"
                       class="oz-sample-link" target="_blank">
                      <?php echo esc_html($sample_text); ?> &rarr;
                    </a>
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
                        <?php if (!empty($opt['price'])) : ?>
                          <span class="oz-price-add"><?php echo esc_html(($opt['price'] > 0 ? '+' : '-') . $fmt_price(abs($opt['price']))); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($opt['recommended'])) : ?>
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
                        <?php if (!empty($opt['price'])) : ?>
                          <span class="oz-price-add"><?php echo esc_html(($opt['price'] > 0 ? '+' : '-') . $fmt_price(abs($opt['price']))); ?></span>
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
                        <?php if (!empty($opt['price'])) : ?>
                          <span class="oz-price-add"><?php echo esc_html(($opt['price'] > 0 ? '+' : '-') . $fmt_price(abs($opt['price']))); ?></span>
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
        endif;
        ?>

      <!-- Price Breakdown -->
      <div class="oz-price-summary" id="priceSummary">
        <div class="oz-price-line" id="priceBaseLine">
          <span id="priceBaseLabel"><?php echo esc_html($product_name); ?></span>
          <span id="priceBase"><?php echo esc_html($fmt_price($price)); ?></span>
        </div>
        <?php if ($has_options) : ?>
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
        <?php endif; ?>
        <?php if ($has_addon_groups && !empty($addon_groups)) : ?>
          <?php foreach ($addon_groups as $group) : ?>
        <div class="oz-price-line" id="priceAddon_<?php echo esc_attr($group['key']); ?>Line" style="display:none;">
          <span id="priceAddon_<?php echo esc_attr($group['key']); ?>Label"><?php echo esc_html($group['label']); ?></span>
          <span id="priceAddon_<?php echo esc_attr($group['key']); ?>"></span>
        </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <div class="oz-price-line oz-price-subtotal" id="priceQtyLine" style="display:none;">
          <span id="priceQtyLabel"></span>
          <span id="priceQty"></span>
        </div>
        <?php if ($has_options && !empty($config['has_tools'])) : ?>
        <div class="oz-price-line" id="priceToolsLine" style="display:none;">
          <span id="priceToolsLabel">Gereedschapsset</span>
          <span id="priceTools"></span>
        </div>
        <?php endif; ?>
        <div class="oz-price-line oz-price-total">
          <span>Totaal</span>
          <span id="priceTotal"><?php echo esc_html($fmt_price($price)); ?></span>
        </div>

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
            <button class="oz-add-to-cart<?php echo $is_base ? ' oz-disabled' : ''; ?>" id="addToCartBtn"
                    <?php if ($is_base) : ?>data-base-product="1"<?php endif; ?>>In winkelmand</button>
          </div>
        </div>

        <!-- Payment method icons — dynamically from WooCommerce active gateways -->
        <?php
        // Only show the most relevant payment methods (not all 18 Mollie gateways)
        $oz_show_gateways = [
            'mollie_wc_gateway_ideal',
            'mollie_wc_gateway_creditcard',
            'mollie_wc_gateway_paypal',
            'mollie_wc_gateway_applepay',
            'mollie_wc_gateway_bancontact',
            'mollie_wc_gateway_klarnapaylater',
        ];
        $oz_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        $oz_payment_icons = [];
        foreach ($oz_show_gateways as $gw_id) {
            if (isset($oz_gateways[$gw_id])) {
                $icon_html = $oz_gateways[$gw_id]->get_icon();
                if ($icon_html) {
                    $oz_payment_icons[] = $icon_html;
                }
            }
        }
        ?>
        <?php if (!empty($oz_payment_icons)) : ?>
          <div class="oz-payment-section">
            <div class="oz-payment-label">Veilig betalen</div>
            <div class="oz-payment-methods">
              <?php foreach ($oz_payment_icons as $icon) : ?>
                <div class="oz-payment-icon"><?php echo $icon; ?></div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div><!-- #priceSummary -->
      </div><!-- #optionsWidget -->
      </div><!-- #optionsDesktopHome -->

      <!-- M² advice tip — below payment, above delivery -->
      <?php if ($config['unitM2'] > 0) : ?>
      <div class="oz-m2-advice">
        <!-- Filled info icon for visibility on tinted background -->
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
        </svg>
        <span>Tip van onze specialist: bestel altijd extra materiaal. Oneffenheden in de ondergrond, reparaties en verwerking vragen meer product dan de berekende vierkante meters. Tekort komen is geen optie!</span>
      </div>
      <?php endif; ?>

      <!-- Delivery Timeline — below the buy box, above trust badges -->
      <?php
      // Calculate shipping & delivery dates
      // Before 14:00 = shipped same day, after 14:00 = shipped next day
      // Carrier (PostNL/DHL via Sendcloud) delivers in 1-2 business days
      $oz_now = new DateTime('now', new DateTimeZone('Europe/Amsterdam'));
      $oz_hour = (int) $oz_now->format('H');

      // Ship date logic:
      // - Weekday before 14:00 → shipped today
      // - Weekday after 14:00 → shipped next business day
      // - Weekend (Sat/Sun) → shipped Monday
      $oz_ship = clone $oz_now;
      $oz_dow = (int) $oz_now->format('N'); // 1=Mon .. 7=Sun
      if ($oz_dow >= 6) {
          // Weekend — next Monday
          $oz_ship->modify('next Monday');
      } elseif ($oz_hour >= 14) {
          // Weekday after 14:00 — next business day
          $oz_ship->modify('+1 weekday');
      }

      // Delivery: 1-2 business days after ship date (carriers don't deliver on weekends)
      $oz_del_from = clone $oz_ship;
      $oz_del_from->modify('+1 weekday');
      $oz_del_to = clone $oz_ship;
      $oz_del_to->modify('+2 weekday');

      // Dutch day/month formatting
      $oz_d = ['', 'ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];
      $oz_m = ['', 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
      $oz_dfmt = function($d) use ($oz_d, $oz_m) {
          return $oz_d[(int)$d->format('N')] . ' ' . (int)$d->format('j') . ' ' . $oz_m[(int)$d->format('n')];
      };

      $oz_ship_txt = ($oz_ship->format('Y-m-d') === $oz_now->format('Y-m-d')) ? 'Vandaag' : $oz_dfmt($oz_ship);
      $oz_del_txt = $oz_dfmt($oz_del_from) . ' - ' . $oz_dfmt($oz_del_to);
      ?>
      <div class="oz-delivery-timeline">
        <div class="oz-delivery-step completed">
          <div class="oz-step-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
          </div>
          <span class="oz-step-label">Besteld</span>
          <span class="oz-step-date">Vandaag</span>
        </div>
        <div class="oz-delivery-step">
          <div class="oz-step-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="1"></rect><path d="M16 8h4l3 3v5h-7V8z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
          </div>
          <span class="oz-step-label">Verzonden</span>
          <span class="oz-step-date"><?php echo esc_html($oz_ship_txt); ?></span>
        </div>
        <div class="oz-delivery-step">
          <div class="oz-step-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><path d="M3.27 6.96L12 12.01l8.73-5.05"></path><path d="M12 22.08V12"></path></svg>
          </div>
          <span class="oz-step-label">Bezorgd</span>
          <span class="oz-step-date"><?php echo esc_html($oz_del_txt); ?></span>
        </div>
      </div>

      <!-- Trust Checks — compact inline checkmarks -->
      <div class="oz-trust-checks">
        <span class="oz-trust-check">&#10003; Op werkdagen voor 14:00 besteld, dezelfde dag verzonden</span>
        <span class="oz-trust-check">&#10003; Achteraf betalen</span>
        <span class="oz-trust-check">&#10003; 420.000+ m² aangebracht door klanten</span>
        <span class="oz-trust-check">&#10003; Gratis workshop mogelijk bij bestelling</span>
      </div>

      <?php /* Short description removed — client decision 2026-03-07 */ ?>

    </div><!-- .oz-product-summary -->

  </div><!-- .oz-product-grid -->

  <?php
  // Showcase sections — editorial image + text blocks below the product grid.
  // Checks admin meta first, falls back to config defaults per line.
  if (current_user_can('manage_options')) :
      $showcase_source = !empty($config['base_id']) ? $config['base_id'] : $product_id;
      $showcase_sections = get_post_meta($showcase_source, '_oz_showcase_sections', true);

      // Fallback: use config defaults for this product line
      if (empty($showcase_sections) || !is_array($showcase_sections)) {
          $showcase_sections = $line_key
              ? OZ_Product_Line_Config::get_showcase_defaults($line_key)
              : [];
      }

      if (!empty($showcase_sections)) :
  ?>
  <div class="oz-showcase">
    <?php foreach ($showcase_sections as $si => $section) :
        $img_url = !empty($section['image_id'])
            ? wp_get_attachment_image_url($section['image_id'], 'full')
            : '';
        $has_content = !empty($section['title']) || !empty($section['text']);
        if (!$img_url && !$has_content) continue;
        $reverse = ($si % 2 !== 0);
    ?>
    <section class="oz-showcase-block <?php echo $reverse ? 'oz-showcase-block--reverse' : ''; ?> oz-reveal">
      <div class="oz-showcase-block__inner">
        <?php if ($img_url) : ?>
        <div class="oz-showcase-block__media oz-reveal-child">
          <div class="oz-showcase-block__img-wrap">
            <img src="<?php echo esc_url($img_url); ?>"
                 alt="<?php echo esc_attr($section['title'] ?? ''); ?>"
                 loading="lazy">
          </div>
        </div>
        <?php endif; ?>
        <div class="oz-showcase-block__content oz-reveal-child">
          <?php if (!empty($section['subtitle'])) : ?>
            <span class="oz-showcase-block__eyebrow"><?php echo esc_html($section['subtitle']); ?></span>
          <?php endif; ?>
          <?php if (!empty($section['title'])) : ?>
            <h2 class="oz-showcase-block__heading"><?php echo esc_html($section['title']); ?></h2>
          <?php endif; ?>
          <?php if (!empty($section['text'])) : ?>
            <p class="oz-showcase-block__body"><?php echo nl2br(esc_html($section['text'])); ?></p>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php endforeach; ?>
  </div>
  <?php endif; endif; ?>

</div><!-- .oz-product-page -->


<?php /* ═══ STICKY BAR (mobile: full info + sheet, desktop: title · color · options · price+qty+CTA) ═══ */ ?>
<div class="oz-sticky-bar" id="stickyBar">

  <!-- ── Mobile layout ── -->
  <div class="oz-sticky-mobile">
    <div class="oz-sticky-info">
      <img class="oz-sticky-thumb" id="stickyThumb"
           src="<?php echo esc_url(wp_get_attachment_image_url($main_image_id, 'thumbnail')); ?>"
           alt="">
      <div class="oz-sticky-details">
        <div class="oz-sticky-product-name"><?php echo esc_html($product_name); ?></div>
        <?php if ($current_color || $has_shared_colors || $has_color_variants) : ?>
        <div class="oz-sticky-color" id="stickyColorWrap"
             <?php if (!$current_color) echo 'style="display:none"'; ?>>
          <span class="oz-sticky-color-dot" id="stickyColorDot"></span>
          <span id="stickyColorName"><?php echo esc_html($current_color ?: ''); ?></span>
        </div>
        <?php endif; ?>
      </div>
      <div class="oz-sticky-price oz-sticky-price-mobile">
        <span id="stickyPriceMobile"><?php echo esc_html($fmt_price($price)); ?></span>
        <?php if (!empty($config['unitM2'])) : ?>
        <span class="oz-sticky-price-unit">per <?php echo esc_html($config['unitM2']); ?>m²</span>
        <?php endif; ?>
      </div>
    </div>
    <button class="oz-sticky-btn" id="stickyBtn"><?php echo $is_base ? 'Kies kleur' : 'In winkelmand'; ?></button>
  </div>

  <!-- ── Desktop layout: [Nav links] [Name · Options] [Price+Qty+CTA] ── -->
  <div class="oz-sticky-desktop">

    <!-- Left: page section nav links -->
    <div class="oz-sticky-d-nav">
      <?php if (!empty($description)) : ?>
        <a href="#sectionInfo" class="oz-sticky-d-link" data-scroll="sectionInfo">Productinfo</a>
      <?php endif; ?>
      <?php if (!empty($oz_specs)) : ?>
        <a href="#sectionSpecs" class="oz-sticky-d-link" data-scroll="sectionSpecs">Specificaties</a>
      <?php endif; ?>
      <?php if ($page_mode === 'configured_line' && $line_key && current_user_can('manage_options')) : ?>
        <a href="#sectionCompare" class="oz-sticky-d-link" data-scroll="sectionCompare">Vergelijken</a>
      <?php endif; ?>
      <?php if (!empty($oz_faq) && is_array($oz_faq)) : ?>
        <a href="#sectionFaq" class="oz-sticky-d-link" data-scroll="sectionFaq">FAQ</a>
      <?php endif; ?>
    </div>

    <!-- Middle: product name + selected options (clickable → scrolls to options) -->
    <div class="oz-sticky-d-mid">
      <span class="oz-sticky-d-title"><?php echo esc_html($product_name); ?></span>
      <span class="oz-sticky-d-color" id="stickyDColor"><?php echo esc_html($current_color ?: ''); ?></span>
      <span class="oz-sticky-d-options" id="stickyDOptions" role="button" tabindex="0" title="Ga naar opties"></span>
    </div>

    <!-- Right: price + qty + CTA -->
    <div class="oz-sticky-d-right">
      <span class="oz-sticky-d-price">
        <span id="stickyPrice"><?php echo esc_html($fmt_price($price)); ?></span><?php if (!empty($config['unitM2'])) : ?><sup class="oz-sticky-d-unit"><?php echo esc_html($config['unitM2']); ?>m²</sup><?php endif; ?>
      </span>
      <span class="oz-sticky-d-qty" id="stickyDQty">1×</span>
      <button class="oz-sticky-d-btn" id="stickyDBtn"><?php echo $is_base ? 'Kies kleur' : 'In winkelmand'; ?></button>
    </div>

  </div>

</div>

<?php /* ═══ BOTTOM SHEET ═══ */ ?>
<div class="oz-sheet-overlay" id="sheetOverlay"></div>
<div class="oz-bottom-sheet" id="bottomSheet">
  <div class="oz-sheet-handle"></div>
  <div class="oz-sheet-title">Kies je opties</div>
  <div id="optionsSlotSheet"></div>

</div>


<?php if ($has_options && !empty($config['has_tools'])) : ?>
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
<?php endif; ?>

<?php get_footer(); ?>
