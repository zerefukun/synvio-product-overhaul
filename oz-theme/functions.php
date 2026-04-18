<?php

/* ================================================================
   OZ THEME — Standalone WordPress + WooCommerce theme
   No parent theme. All styling via oz-design-system.css + component CSS.
   ================================================================ */

/**
 * Theme setup — register supports, menus, image sizes.
 * Runs on after_setup_theme so WordPress is ready.
 */
function oz_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', ['height' => 80, 'width' => 240, 'flex-height' => true, 'flex-width' => true]);
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('editor-styles');
    add_theme_support('wp-block-styles');

    /* WooCommerce */
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    /* Editor styles — mirrors frontend design system */
    add_editor_style('css/oz-editor.css');

    /* Block color palette matching design tokens */
    add_theme_support('editor-color-palette', [
        ['name' => 'Accent (Teal)',   'slug' => 'oz-accent',       'color' => '#135350'],
        ['name' => 'Accent Hover',    'slug' => 'oz-accent-hover', 'color' => '#0E3E3C'],
        ['name' => 'Accent Light',    'slug' => 'oz-accent-light', 'color' => '#E8F0F0'],
        ['name' => 'CTA (Orange)',    'slug' => 'oz-cta',          'color' => '#E67C00'],
        ['name' => 'Text Primary',    'slug' => 'oz-text-primary', 'color' => '#1A1A1A'],
        ['name' => 'Text Body',       'slug' => 'oz-text-body',    'color' => '#555555'],
        ['name' => 'Background Warm', 'slug' => 'oz-bg-warm',      'color' => '#F5F4F0'],
        ['name' => 'Background Page', 'slug' => 'oz-bg-page',      'color' => '#FFFFFF'],
        ['name' => 'Border',          'slug' => 'oz-border',       'color' => '#E5E5E3'],
    ]);

    /* Block font sizes matching type scale */
    add_theme_support('editor-font-sizes', [
        ['name' => 'Small',   'slug' => 'small',   'size' => 12],
        ['name' => 'Normal',  'slug' => 'normal',  'size' => 16],
        ['name' => 'Medium',  'slug' => 'medium',  'size' => 20],
        ['name' => 'Large',   'slug' => 'large',   'size' => 25],
        ['name' => 'X-Large', 'slug' => 'x-large', 'size' => 31],
        ['name' => 'Huge',    'slug' => 'huge',    'size' => 39],
    ]);
}
add_action('after_setup_theme', 'oz_theme_setup');

/**
 * Register widget areas.
 */
function oz_widgets_init() {
    register_sidebar([
        'name'          => 'Shop Sidebar',
        'id'            => 'shop-sidebar',
        'description'   => 'Widgets below the category navigation on shop pages (e.g. price filter).',
        'before_widget' => '<div id="%1$s" class="oz-sidebar-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="oz-sidebar-widget__title">',
        'after_title'   => '</h3>',
    ]);
}
add_action('widgets_init', 'oz_widgets_init');

/**
 * Enqueue design system CSS on all frontend pages.
 * Loads first so component CSS can rely on the tokens and reset.
 */
function oz_design_system_enqueue() {
    if (is_admin()) return;

    wp_enqueue_style(
        'oz-design-system',
        get_stylesheet_directory_uri() . '/css/oz-design-system.css',
        [],
        filemtime(get_stylesheet_directory() . '/css/oz-design-system.css')
    );

    /* Google Fonts — Raleway + DM Serif Display */
    wp_enqueue_style(
        'oz-google-fonts',
        'https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Raleway:wght@400;500;600;700&display=swap',
        [],
        null
    );

    /* Block styles for Gutenberg content */
    wp_enqueue_style(
        'oz-blocks',
        get_stylesheet_directory_uri() . '/css/oz-blocks.css',
        ['oz-design-system'],
        filemtime(get_stylesheet_directory() . '/css/oz-blocks.css')
    );
}
add_action('wp_enqueue_scripts', 'oz_design_system_enqueue', 5);

/**
 * Enqueue scroll-reveal animation CSS + JS on all frontend pages.
 * Unified system: watches [data-reveal], [data-reveal-stagger], [data-reveal-img].
 * Adds .oz-visible via IntersectionObserver.
 */
function oz_animations_enqueue() {
    if (is_admin()) return;

    wp_enqueue_style(
        'oz-animations',
        get_stylesheet_directory_uri() . '/css/oz-animations.css',
        ['oz-design-system'],
        filemtime(get_stylesheet_directory() . '/css/oz-animations.css')
    );

    wp_enqueue_script(
        'oz-animations',
        get_stylesheet_directory_uri() . '/js/oz-animations.js',
        [],
        filemtime(get_stylesheet_directory() . '/js/oz-animations.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'oz_animations_enqueue', 6);

/**
 * Remove ALL WooCommerce default CSS sitewide — we provide our own via oz-blocks.css.
 * WC's layout, general, and smallscreen styles all conflict with our design system.
 */
function oz_dequeue_wc_layout_styles() {
    wp_dequeue_style('woocommerce-layout');
    wp_dequeue_style('woocommerce-smallscreen');
    wp_dequeue_style('woocommerce-general');
}
add_action('wp_enqueue_scripts', 'oz_dequeue_wc_layout_styles', 20);

/**
 * Dequeue WooCommerce + WC Blocks styles on non-WC pages.
 * Runs at priority 100 because WC Blocks re-enqueues after priority 20.
 */
function oz_dequeue_wc_on_non_shop_pages() {
    if ( is_admin() ) return;

    $is_wc_page = function_exists('is_woocommerce') && (
        is_woocommerce() || is_cart() || is_checkout() || is_account_page()
    );
    if ( $is_wc_page ) return;

    wp_dequeue_style('woocommerce-general');
    wp_dequeue_style('wc-blocks-style');
    wp_dequeue_style('wc-blocks-vendors-style');
}
add_action('wp_enqueue_scripts', 'oz_dequeue_wc_on_non_shop_pages', 100);

/**
 * Remove WooCommerce default content wrappers.
 * Our archive-product.php has its own layout, and header.php already provides <main>.
 * Without this, WC outputs a nested <main class="site-main"> that breaks DOM nesting
 * and causes double padding on the shop grid.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

/**
 * Shop sidebar walker — collapsible category nav from a curated WP menu.
 */
require_once get_stylesheet_directory() . '/inc/class-shop-sidebar-walker.php';

/**
 * Mega menu walker — desktop horizontal nav with dropdown panels.
 */
require_once get_stylesheet_directory() . '/inc/class-mega-menu-walker.php';

/**
 * Load Flatsome shortcode compatibility layer.
 * Existing pages use Flatsome UX Builder shortcodes extensively.
 * These stubs output semantic HTML with our design classes.
 */
if (file_exists(get_stylesheet_directory() . '/inc/flatsome-shortcodes.php')) {
    require_once get_stylesheet_directory() . '/inc/flatsome-shortcodes.php';
}

/**
 * Load block patterns for Gutenberg.
 */
if (file_exists(get_stylesheet_directory() . '/inc/block-patterns.php')) {
    require_once get_stylesheet_directory() . '/inc/block-patterns.php';
}

/**
 * Load block-section renderer used by page-ruimte.php and single.php
 * (for stucsoorten category posts).
 */
require_once get_stylesheet_directory() . '/inc/block-sections-renderer.php';

/**
 * Microsoft Clarity — session recordings, heatmaps, user journey tracking.
 * Free tool, loads async, no performance impact.
 * Dashboard: https://clarity.microsoft.com
 */
/**
 * Preload the LCP hero image on the homepage.
 * Eliminates the ~960ms "resource load delay" where the browser waits for
 * render-blocking CSS before discovering the <img> in the DOM.
 * With preload, the image downloads in parallel with CSS.
 */
function oz_preload_hero_image() {
    if (!is_front_page()) return;
    $hero_url = wp_get_attachment_image_url(28735, 'medium_large');
    if ($hero_url) {
        echo '<link rel="preload" as="image" href="' . esc_url($hero_url) . '" fetchpriority="high">' . "\n";
    }
}
add_action('wp_head', 'oz_preload_hero_image', 1);

function oz_clarity_tracking() {
    // Skip admin pages and logged-in admins (don't pollute data with our own sessions)
    if (is_admin() || current_user_can('manage_options')) return;
    ?>
    <script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "vunpx49rhr");
    </script>
    <?php
}
add_action('wp_head', 'oz_clarity_tracking', 1);

function oz_custom_scripts() {
    wp_enqueue_script(
        'oz-scripts-js',
        get_stylesheet_directory_uri() . '/oz-scripts.js',
        array('jquery'),
        time(),
        true
    );

    // Add a custom query string to exclude from LiteSpeed Cache
    wp_add_inline_script('oz-scripts-js', 'var script = document.querySelector("script[src*=\'oz-scripts.js\']"); if(script) { script.src = script.src + "?nocache=" + new Date().getTime(); }', 'before');
}
add_action('wp_enqueue_scripts', 'oz_custom_scripts');

/**
 * Defer non-critical CSS by switching rel to preload + onload swap.
 * Prevents render-blocking for stylesheets that aren't needed above the fold.
 */
function oz_defer_non_critical_css($tag, $handle) {
    $defer_handles = ['fue-followups'];
    if (in_array($handle, $defer_handles, true)) {
        $tag = str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag);
        $tag .= '<noscript>' . str_replace("rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", "rel='stylesheet'", $tag) . '</noscript>';
    }
    return $tag;
}
add_filter('style_loader_tag', 'oz_defer_non_critical_css', 10, 2);

/**
 * Add defer attribute to non-critical JS that blocks rendering.
 * These scripts don't need to run before first paint.
 */
function oz_defer_non_critical_js($tag, $handle) {
    $defer_handles = [
        'wp-consent-api',     // WP Consent API (1 KB)
        'cookiebot-wp-consent-level-api-integration', // Cookiebot integration (0.9 KB)
        'wc-js-cookie',       // js.cookie (1.2 KB)
    ];
    if (in_array($handle, $defer_handles, true)) {
        if (strpos($tag, 'defer') === false) {
            $tag = str_replace(' src=', ' defer src=', $tag);
        }
    }
    return $tag;
}
add_filter('script_loader_tag', 'oz_defer_non_critical_js', 10, 2);

/**
 * Defer the Swiper CSS too — it's only needed after sliders initialize.
 */
/**
 * Defer non-critical CSS: Swiper, Follow-Up Emails, swmodal, wc-blocks, small plugin CSS.
 * WP appends -css to the ID but the handle passed here does NOT include it.
 */
function oz_defer_swiper_css($tag, $handle) {
    $defer_handles = [
        'follow-up-emails', // WC Follow-Ups (0.7 KB)
        'wc-blocks',        // WooCommerce blocks CSS (2.6 KB)
    ];
    if (in_array($handle, $defer_handles, true)) {
        $tag = str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag);
    }
    return $tag;
}
add_filter('style_loader_tag', 'oz_defer_swiper_css', 10, 2);

/* Flatsome banner aria-labels removed — shortcode stubs handle accessibility. */

/* M² Calculator removed — Phase 4 cleanup (was dead code, no products use it) */
/* Oz Handleiding removed — no longer used */

?>
<?php
/**
 * Server-side Order Attribution Fallback
 * Captures UTM parameters via cookies when Cookiebot blocks JS tracking.
 * Uses cookies instead of PHP sessions to avoid cache-killing headers.
 */

// Capture UTM params into cookies on first visit (no session_start needed)
add_action('init', 'oz_capture_attribution_cookies', 1);
function oz_capture_attribution_cookies() {
    if (is_admin()) return;

    // Only capture on the landing request (when UTM params are in the URL)
    $utm_params = ['utm_source', 'utm_medium', 'utm_campaign'];
    foreach ($utm_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $cookie_name = 'oz_' . $param;
            // Don't overwrite existing — first touch wins
            if (!isset($_COOKIE[$cookie_name])) {
                $value = sanitize_text_field($_GET[$param]);
                setcookie($cookie_name, $value, time() + 86400 * 30, '/', '', true, false);
                $_COOKIE[$cookie_name] = $value; // Available in same request
            }
        }
    }
}

// Apply fallback attribution when order is created
add_action('woocommerce_checkout_order_created', 'oz_apply_attribution_fallback', 20);
function oz_apply_attribution_fallback($order) {
    // Check if WooCommerce attribution already set
    $existing_source = $order->get_meta('_wc_order_attribution_source_type');
    if (!empty($existing_source)) {
        return; // WC attribution worked, don't override
    }

    $utm_source  = isset($_COOKIE['oz_utm_source']) ? sanitize_text_field($_COOKIE['oz_utm_source']) : '';
    $utm_medium  = isset($_COOKIE['oz_utm_medium']) ? sanitize_text_field($_COOKIE['oz_utm_medium']) : '';
    $utm_campaign = isset($_COOKIE['oz_utm_campaign']) ? sanitize_text_field($_COOKIE['oz_utm_campaign']) : '';

    // Determine source type
    $source_type = 'typein';
    if (!empty($utm_source) || !empty($utm_campaign)) {
        $source_type = 'utm';
    }

    $order->update_meta_data('_wc_order_attribution_source_type', $source_type);
    $order->update_meta_data('_wc_order_attribution_utm_source', $utm_source ?: 'direct');
    $order->update_meta_data('_wc_order_attribution_utm_medium', $utm_medium);
    $order->update_meta_data('_wc_order_attribution_utm_campaign', $utm_campaign);
    $order->update_meta_data('_oz_attribution_fallback', 'yes');

    $order->save();
}


/* Flatsome mini-cart filter removed — no parent theme, no Flatsome mini-cart.
 * Our header.php renders its own cart icon; cart-drawer.js opens the drawer. */

/**
 * Get the WooCommerce free shipping minimum amount.
 * Scans all shipping zones for a free_shipping method with a min_amount.
 * Returns the lowest threshold found, or 0 if none configured.
 *
 * @return float  Threshold amount (0 = no free shipping offer)
 */
function oz_get_free_shipping_threshold() {
    if (!class_exists('WC_Shipping_Zones')) return 0;

    $threshold = 0;

    // Check all shipping zones (including zone 0 = "Rest of the World")
    $zones = WC_Shipping_Zones::get_zones();
    $zones[0] = ['shipping_methods' => (new WC_Shipping_Zone(0))->get_shipping_methods()];

    foreach ($zones as $zone) {
        $methods = isset($zone['shipping_methods']) ? $zone['shipping_methods'] : [];
        foreach ($methods as $method) {
            if ($method->id !== 'free_shipping' || $method->enabled !== 'yes') continue;

            $min = floatval($method->get_option('min_amount', 0));
            if ($min > 0 && ($threshold === 0 || $min < $threshold)) {
                $threshold = $min;
            }
        }
    }

    return $threshold;
}

/* ══════════════════════════════════════════════════════════════════
 * CART DRAWER — slide-in cart panel (replaces Flatsome cart sidebar)
 *
 * Components:
 * 1. Enqueue CSS + JS on all frontend pages
 * 2. Output drawer HTML template via wp_footer
 * 3. AJAX endpoints: get cart, update qty, remove item, add product
 * ══════════════════════════════════════════════════════════════════ */

/**
 * Enqueue cart drawer CSS and JS on all frontend pages.
 */
function oz_cart_drawer_enqueue() {
    if (is_admin()) return;

    // CSS
    wp_enqueue_style(
        'oz-cart-drawer',
        get_stylesheet_directory_uri() . '/css/cart-drawer.css',
        [],
        filemtime(get_stylesheet_directory() . '/css/cart-drawer.css')
    );

    /* Google Fonts now loaded by oz_design_system_enqueue() */

    // Shared Swiper CDN loader — used by cart drawer and product page USP ticker
    wp_enqueue_script(
        'oz-swiper-loader',
        get_stylesheet_directory_uri() . '/js/swiper-loader.js',
        [],
        filemtime(get_stylesheet_directory() . '/js/swiper-loader.js'),
        true
    );

    // JS
    wp_enqueue_script(
        'oz-cart-drawer',
        get_stylesheet_directory_uri() . '/js/cart-drawer.min.js',
        ['oz-swiper-loader'],
        filemtime(get_stylesheet_directory() . '/js/cart-drawer.min.js'),
        true
    );

    // Pass AJAX URL, nonce, page context, and free shipping threshold to JS
    wp_localize_script('oz-cart-drawer', 'ozCartDrawer', [
        'ajaxUrl'           => admin_url('admin-ajax.php'),
        'nonce'             => wp_create_nonce('oz_cart_drawer'),
        'analyticsNonce'    => wp_create_nonce('oz_analytics'),
        'isCartOrCheckout'  => (is_cart() || is_checkout()) ? '1' : '0',
        'freeShipThreshold' => oz_get_free_shipping_threshold(),
        'isAdmin'           => current_user_can('manage_options') ? '1' : '0',
        'currentProductId'  => is_product() ? get_the_ID() : 0,
    ]);
}
add_action('wp_enqueue_scripts', 'oz_cart_drawer_enqueue');

/**
 * Enqueue homepage v2 CSS only on the front page.
 * Styles are namespaced with .oz-hp- prefix so they can't bleed into other pages.
 */
function oz_homepage_v2_enqueue() {
    if (is_admin() || ! is_front_page()) return;

    wp_enqueue_style(
        'oz-homepage-v2',
        get_stylesheet_directory_uri() . '/css/homepage-v2.css',
        [],
        filemtime(get_stylesheet_directory() . '/css/homepage-v2.css')
    );

    wp_enqueue_script(
        'oz-homepage-v2',
        get_stylesheet_directory_uri() . '/js/homepage-v2.js',
        [],
        filemtime(get_stylesheet_directory() . '/js/homepage-v2.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'oz_homepage_v2_enqueue');

/**
 * Enqueue ruimte page styles when the Ruimte template is active OR when
 * viewing a stucsoorten-category single post (same block-section layout).
 */
function oz_ruimte_enqueue() {
    if (is_admin()) return;

    $needs_ruimte_css = is_page_template('page-ruimte.php')
        || ( is_single() && has_category( 'stucsoorten' ) );

    if ( ! $needs_ruimte_css ) return;

    wp_enqueue_style(
        'oz-ruimte',
        get_stylesheet_directory_uri() . '/css/oz-ruimte.css',
        ['oz-design-system', 'oz-animations'],
        filemtime(get_stylesheet_directory() . '/css/oz-ruimte.css')
    );
}
add_action('wp_enqueue_scripts', 'oz_ruimte_enqueue');

/**
 * Enqueue custom header CSS + JS on all frontend pages.
 * Replaces Flatsome's header entirely — our header.php provides the markup.
 */
function oz_header_enqueue() {
    if (is_admin()) return;

    wp_enqueue_style(
        'oz-header',
        get_stylesheet_directory_uri() . '/css/oz-header.css',
        [],
        filemtime(get_stylesheet_directory() . '/css/oz-header.css')
    );

    wp_enqueue_script(
        'oz-header',
        get_stylesheet_directory_uri() . '/js/oz-header.js',
        [],
        filemtime(get_stylesheet_directory() . '/js/oz-header.js'),
        true
    );

    wp_localize_script('oz-header', 'ozHeaderData', [
        'siteUrl' => home_url(),
    ]);
}
add_action('wp_enqueue_scripts', 'oz_header_enqueue');

/**
 * Register nav menus used by our custom header + drawer.
 */
register_nav_menus([
    'oz-primary'        => 'Primary Menu (OZ Header)',
    'oz-drawer-footer'  => 'Drawer Footer Links',
    'oz-shop-sidebar'   => 'Shop Sidebar Categories',
]);

/**
 * Customizer: drawer banner image + overlay text.
 * Appearance > Customize > Menu Drawer Banner
 */
function oz_drawer_banner_customizer( $wp_customize ) {
    $wp_customize->add_section('oz_drawer_banner', [
        'title'    => 'Menu Drawer Banner',
        'priority' => 35,
    ]);

    /* Banner image */
    $wp_customize->add_setting('oz_drawer_banner_image', [
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
    ]);
    $wp_customize->add_control( new WP_Customize_Image_Control($wp_customize, 'oz_drawer_banner_image', [
        'label'   => 'Banner Image',
        'section' => 'oz_drawer_banner',
    ]));

    /* Overlay line 1 (small text, e.g. brand name) */
    $wp_customize->add_setting('oz_drawer_banner_line1', [
        'default'           => 'Beton Cire Webshop',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('oz_drawer_banner_line1', [
        'label'   => 'Line 1 (small)',
        'section' => 'oz_drawer_banner',
        'type'    => 'text',
    ]);

    /* Overlay line 2 (large tagline) */
    $wp_customize->add_setting('oz_drawer_banner_line2', [
        'default'           => 'Voor elke ruimte',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wp_customize->add_control('oz_drawer_banner_line2', [
        'label'   => 'Line 2 (large)',
        'section' => 'oz_drawer_banner',
        'type'    => 'text',
    ]);
}
add_action('customize_register', 'oz_drawer_banner_customizer');

/**
 * Output cart drawer HTML template in the footer of every page.
 */
function oz_cart_drawer_template() {
    if (is_admin()) return;
    include get_stylesheet_directory() . '/templates/cart-drawer.php';
}
add_action('wp_footer', 'oz_cart_drawer_template');

/**
 * AJAX: Get full cart data as JSON.
 * Returns items (key, name, price, qty, image, meta, line_total),
 * upsells, subtotal.
 */
function oz_cart_drawer_get() {
    check_ajax_referer('oz_cart_drawer', 'nonce');

    $cart = WC()->cart;
    if (!$cart) {
        wp_send_json_error('Cart not available');
        return;
    }

    // Recalculate totals to ensure accuracy
    $cart->calculate_totals();

    $items = [];
    foreach ($cart->get_cart() as $cart_key => $cart_item) {
        $product = $cart_item['data'];
        if (!$product) continue;

        $item_pid = $product->get_id();

        // --- Display name ---
        // Strip color suffix from product name (same logic as single-product.php).
        // "Microcement Sand 2" with _oz_color "Sand 2" → "Microcement"
        $display_name = $product->get_name();
        $current_color = get_post_meta($item_pid, '_oz_color', true);
        if ($current_color) {
            // Pattern 1: parenthesized color — "Beton Ciré Original (Stone White 1000)"
            $stripped = preg_replace('/\s*\([^)]+\)\s*$/', '', $display_name);
            // Pattern 2: suffix match — "Microcement Sand 2" with color "Sand 2"
            if ($stripped === $display_name) {
                $stripped = preg_replace('/\s+' . preg_quote($current_color, '/') . '\s*$/i', '', $display_name);
            }
            $display_name = trim($stripped);
        }

        // For RAL/NCS orders: append the custom color code to the base name
        // e.g. "Microcement" → "Microcement RAL 7104"
        $is_ral_ncs = isset($cart_item['oz_color_mode']) && $cart_item['oz_color_mode'] === 'ral_ncs';
        if ($is_ral_ncs && !empty($cart_item['oz_custom_color'])) {
            $display_name .= ' ' . $cart_item['oz_custom_color'];
        }

        // --- Thumbnail ---
        // Priority: oz_cart_image (ZM color) > RAL/NCS base > product image
        if (!empty($cart_item['oz_cart_image'])) {
            // ZM products pass the selected color's image from JS
            $image_url = esc_url($cart_item['oz_cart_image']);
        } else {
            $image_id = $product->get_image_id();
            // For RAL/NCS products: use the base (line) product image, not the color variant's.
            if ($is_ral_ncs && !empty($cart_item['oz_line']) && class_exists('OZ_Product_Line_Config')) {
                $base_id = OZ_Product_Line_Config::get_base_product_id($cart_item['oz_line']);
                if ($base_id) {
                    $base_product = wc_get_product($base_id);
                    if ($base_product) {
                        $image_id = $base_product->get_image_id();
                    }
                }
            }
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        }

        // Build meta string from cart item data (color, options, etc.)
        $meta_parts = [];

        // Use our cart manager's build_addon_details for configured products
        if (class_exists('OZ_Cart_Manager') && (isset($cart_item['oz_line']) || isset($cart_item['oz_page_mode']))) {
            // Pass product_id so generic addon groups can resolve labels
            $addon_data = $cart_item;
            $addon_data['product_id'] = $item_pid;
            $meta_parts = OZ_Cart_Manager::get_addon_details($addon_data);
        }

        // Kit contents label — show what's inside toolset products
        // so customers know at a glance what they're getting
        $kit_labels = [
            11177 => 'Spaan, kwast, PU garde, 3× roller, tape, 2× verfbak, vachtroller',
            25550 => 'Spaan, kwast, garde, PU garde, 3× roller, tape, 2× verfbak, vachtroller',
        ];
        if (isset($kit_labels[$item_pid]) && empty($meta_parts)) {
            $meta_parts[] = $kit_labels[$item_pid];
        }

        // Tool size label
        if (!empty($cart_item['oz_tool_size'])) {
            $meta_parts[] = 'Maat: ' . $cart_item['oz_tool_size'];
        }

        // WC variation attributes (fallback for non-oz products)
        if (empty($meta_parts) && !empty($cart_item['variation']) && is_array($cart_item['variation'])) {
            foreach ($cart_item['variation'] as $attr => $val) {
                if (!empty($val)) {
                    $meta_parts[] = ucfirst(str_replace(['attribute_pa_', 'attribute_', '-', '_'], ['', '', ' ', ' '], $attr)) . ': ' . $val;
                }
            }
        }

        $items[] = [
            'key'        => $cart_key,
            'name'       => $display_name,
            'price'      => floatval($product->get_price()),
            'qty'        => $cart_item['quantity'],
            'image'      => $image_url,
            'meta'       => implode(' · ', $meta_parts),
            'line_total' => floatval($cart_item['line_total']) + floatval($cart_item['line_tax']),
            'product_id' => $item_pid,
        ];
    }

    // Get upsell/cross-sell products
    $upsells = oz_cart_drawer_get_upsells($cart);

    wp_send_json_success([
        'items'    => $items,
        'upsells'  => $upsells,
        'subtotal' => floatval($cart->get_subtotal()) + floatval($cart->get_subtotal_tax()),
        'total'    => floatval($cart->get_total('edit')),
    ]);
}
add_action('wp_ajax_oz_cart_drawer_get', 'oz_cart_drawer_get');
add_action('wp_ajax_nopriv_oz_cart_drawer_get', 'oz_cart_drawer_get');

/**
 * AJAX: Update cart item quantity.
 */
function oz_cart_drawer_update() {
    check_ajax_referer('oz_cart_drawer', 'nonce');

    $cart_key = isset($_POST['cart_key']) ? sanitize_text_field($_POST['cart_key']) : '';
    $qty      = isset($_POST['qty']) ? absint($_POST['qty']) : 1;

    if (empty($cart_key)) {
        wp_send_json_error('Missing cart key');
        return;
    }

    $cart = WC()->cart;
    if ($qty < 1) {
        $cart->remove_cart_item($cart_key);
    } else {
        $cart->set_quantity($cart_key, $qty);
    }

    wp_send_json_success();
}
add_action('wp_ajax_oz_cart_drawer_update', 'oz_cart_drawer_update');
add_action('wp_ajax_nopriv_oz_cart_drawer_update', 'oz_cart_drawer_update');

/**
 * AJAX: Remove cart item.
 */
function oz_cart_drawer_remove() {
    check_ajax_referer('oz_cart_drawer', 'nonce');

    $cart_key = isset($_POST['cart_key']) ? sanitize_text_field($_POST['cart_key']) : '';

    if (empty($cart_key)) {
        wp_send_json_error('Missing cart key');
        return;
    }

    WC()->cart->remove_cart_item($cart_key);
    wp_send_json_success();
}
add_action('wp_ajax_oz_cart_drawer_remove', 'oz_cart_drawer_remove');
add_action('wp_ajax_nopriv_oz_cart_drawer_remove', 'oz_cart_drawer_remove');

/**
 * AJAX: Add product to cart (for upsells in drawer).
 */
function oz_cart_drawer_add() {
    check_ajax_referer('oz_cart_drawer', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $qty        = isset($_POST['qty']) ? absint($_POST['qty']) : 1;

    if (!$product_id) {
        wp_send_json_error('Missing product ID');
        return;
    }

    // Optional cart item meta — used by option families (e.g. Stuco Paste with primer).
    // Only allow known meta keys to prevent arbitrary data injection.
    $allowed_meta_keys = ['oz_line', 'oz_primer'];
    $cart_item_meta = [];
    foreach ($allowed_meta_keys as $meta_key) {
        if (isset($_POST[$meta_key]) && $_POST[$meta_key] !== '') {
            $cart_item_meta[$meta_key] = sanitize_text_field($_POST[$meta_key]);
        }
    }

    $cart = WC()->cart;

    // Check if this product is already in the cart with matching meta.
    // Meta must match exactly — "Stuco Paste without primer" and "with primer"
    // are different line items because calculate_addon_prices() reads oz_primer.
    foreach ($cart->get_cart() as $cart_key => $cart_item) {
        if ($cart_item['product_id'] !== $product_id || !empty($cart_item['variation_id'])) {
            continue;
        }

        // Compare meta: both must have same oz_line and oz_primer values
        $meta_match = true;
        foreach ($allowed_meta_keys as $meta_key) {
            $cart_val = isset($cart_item[$meta_key]) ? $cart_item[$meta_key] : '';
            $new_val  = isset($cart_item_meta[$meta_key]) ? $cart_item_meta[$meta_key] : '';
            if ($cart_val !== $new_val) {
                $meta_match = false;
                break;
            }
        }

        if ($meta_match) {
            $new_qty = $cart_item['quantity'] + $qty;
            $cart->set_quantity($cart_key, $new_qty);
            wp_send_json_success(['cart_key' => $cart_key, 'merged' => true]);
            return;
        }
    }

    // Product not in cart yet (or different meta) — add fresh.
    // Pass cart_item_meta as 3rd-party data so WC stores it on the line item.
    // This is picked up by calculate_addon_prices() for price adjustments.
    $result = $cart->add_to_cart($product_id, $qty, 0, [], $cart_item_meta);
    if ($result) {
        wp_send_json_success(['cart_key' => $result]);
    } else {
        wp_send_json_error('Could not add product');
    }
}
add_action('wp_ajax_oz_cart_drawer_add', 'oz_cart_drawer_add');
add_action('wp_ajax_nopriv_oz_cart_drawer_add', 'oz_cart_drawer_add');

/**
 * AJAX: Return product data for recently viewed IDs from localStorage.
 * Accepts JSON array of product IDs, returns name/price/image/permalink.
 * No nonce — read-only public data identical to what's on shop pages.
 */
function oz_recently_viewed_get() {
    $raw_ids = isset($_POST['ids']) ? $_POST['ids'] : '[]';
    $ids     = json_decode(stripslashes($raw_ids), true);

    if (!is_array($ids) || empty($ids)) {
        wp_send_json_success(['products' => []]);
        return;
    }

    // Sanitize: positive integers only, cap at 10
    $ids = array_slice(array_filter(array_map('absint', $ids)), 0, 10);

    // Batch-fetch all products in one query (warms WC object cache)
    wc_get_products(['include' => $ids, 'limit' => 10]);

    // Build response using shared formatter, preserving localStorage order
    $products = [];
    foreach ($ids as $pid) {
        $product = wc_get_product($pid); // served from cache after batch fetch
        if (!$product || $product->get_status() !== 'publish') continue;

        $products[] = oz_format_product_card($product);
    }

    wp_send_json_success(['products' => $products]);
}
add_action('wp_ajax_oz_recently_viewed_get', 'oz_recently_viewed_get');
add_action('wp_ajax_nopriv_oz_recently_viewed_get', 'oz_recently_viewed_get');

/**
 * Get upsell/cross-sell product suggestions for the cart drawer.
 *
 * 3-step priority model:
 * 1. Scan cart — detect product lines via OZ_Product_Line_Config::detect()
 * Smart 3-step upsell engine:
 * 1. Enhanced cart scan — reads oz_line, oz_color_mode, and collects cross-sell IDs
 * 2. Build candidate pool from completion rules (8-12 priority-ordered products per line)
 *    - RAL/NCS bonus: injects RAL Kleurenwaaier at position 2 if customer uses RAL/NCS
 *    - Multi-line carts: round-robin interleave from each line's list, deduplicating
 * 3. Cross-sell boost + pick top 3 — WC cross-sell matches promoted to front, then
 *    first 3 in-stock + purchasable candidates not already in cart are returned
 *
 * Rules: max 3, never suggest products already in cart, only in-stock + purchasable.
 *
 * @param WC_Cart $cart
 * @return array
 */
function oz_cart_drawer_get_upsells($cart) {
    // Project-completion rules: priority-ordered product IDs per line (8-12 items each).
    // The system walks down this list, skipping products already in cart,
    // so there's always something relevant to suggest.
    $completion_rules = [
        'original'       => [22436, 11177, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'all-in-one'     => [22436, 11177, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'easyline'       => [22436, 11177, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'microcement'    => [22436, 11177, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'metallic'       => [22436, 11177, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'lavasteen'      => [22436, 25550, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'betonlook-verf' => [11015, 22997, 22996, 11175, 11025, 11022, 11164, 11018],
        'stuco-paste'    => [11025, 22994, 11175, 11017, 11022, 11018],
        'pu-color'       => [11175, 11022, 11164],
    ];

    // Kit contents map: toolset product ID → array of individual product IDs inside it.
    // When a kit is in cart, its contents are excluded from upsell candidates
    // (no point suggesting items the customer already has inside their kit).
    $kit_contents = [
        11177 => [11025, 11022, 11020, 11175, 11018, 11164, 11015],  // Gereedschapset K&K
        25550 => [11025, 11022, 11020, 11175, 11018, 11164, 11015],  // Gereedschapset Lavasteen (same tools + grote garde)
    ];

    $cart_product_ids = [];
    $cross_sell_ids   = [];
    $detected_lines   = [];  // Preserves insertion order (first line detected = first priority)
    $has_ral_ncs      = false;

    // --- Step 1: Enhanced cart scan ---
    // Reads oz_line (fast) and oz_color_mode from cart item meta.
    // Falls back to OZ_Product_Line_Config::detect() if oz_line is missing.
    $can_detect = class_exists('OZ_Product_Line_Config');

    foreach ($cart->get_cart() as $cart_item) {
        $pid = $cart_item['product_id'];
        $cart_product_ids[$pid] = true;

        // If this product is a kit, mark all its contents as "in cart" too
        // so individual tools inside the kit won't be suggested as upsells
        if (isset($kit_contents[$pid])) {
            foreach ($kit_contents[$pid] as $kit_item_id) {
                $cart_product_ids[$kit_item_id] = true;
            }
        }

        $product = wc_get_product($pid);
        if (!$product) continue;

        // Detect product line — prefer oz_line meta (faster), fall back to class detection
        $line = isset($cart_item['oz_line']) ? $cart_item['oz_line'] : null;
        if (!$line && $can_detect) {
            $line = OZ_Product_Line_Config::detect($product);
        }
        if ($line) {
            $detected_lines[$line] = true;
        }

        // Check for RAL/NCS color mode — triggers RAL Kleurenwaaier bonus
        if (!$has_ral_ncs && isset($cart_item['oz_color_mode']) && $cart_item['oz_color_mode'] === 'ral_ncs') {
            $has_ral_ncs = true;
        }

        // Collect cross-sell IDs from WC product config
        $cs = $product->get_cross_sell_ids();
        foreach ($cs as $cs_id) {
            if (!isset($cart_product_ids[$cs_id])) {
                $cross_sell_ids[$cs_id] = true;
            }
        }
    }

    // --- Step 2: Build candidate pool from completion rules ---
    $candidates = [];

    if (count($detected_lines) === 1) {
        // Single line: just use that line's list directly
        $line_key = key($detected_lines);  // key() works on all PHP versions
        if (isset($completion_rules[$line_key])) {
            $candidates = $completion_rules[$line_key];
        }
    } elseif (count($detected_lines) > 1) {
        // Multi-line cart: round-robin interleave from each line, deduplicating
        $line_lists = [];
        foreach (array_keys($detected_lines) as $line_key) {
            if (isset($completion_rules[$line_key])) {
                $line_lists[] = $completion_rules[$line_key];
            }
        }

        // Round-robin: take one from each list in turn, skip duplicates.
        // Guard: if no lines matched completion rules, $line_lists is empty — skip to step 3.
        if (!empty($line_lists)) {
            $seen = [];
            $max_len = max(array_map('count', $line_lists));
            for ($i = 0; $i < $max_len; $i++) {
                foreach ($line_lists as $list) {
                    if ($i < count($list) && !isset($seen[$list[$i]])) {
                        $candidates[] = $list[$i];
                        $seen[$list[$i]] = true;
                    }
                }
            }
        }
    }

    // RAL/NCS bonus: inject RAL Kleurenwaaier (10998) at position 2 if customer uses RAL/NCS colors
    if ($has_ral_ncs && !in_array(10998, $candidates, true)) {
        array_splice($candidates, min(2, count($candidates)), 0, [10998]);
    }

    // --- Step 3: Cross-sell boost + pick top 3 ---
    // Candidates that also appear in WC cross-sell IDs get promoted to front.
    // This respects manual cross-sell config while still using our priority ordering.
    if (!empty($cross_sell_ids)) {
        $boosted   = [];
        $unboosted = [];
        foreach ($candidates as $cid) {
            if (isset($cross_sell_ids[$cid])) {
                $boosted[] = $cid;
            } else {
                $unboosted[] = $cid;
            }
        }
        $candidates = array_merge($boosted, $unboosted);

        // Also add any cross-sell IDs that aren't in our completion rules
        // (e.g. manually configured cross-sells for specific products)
        foreach (array_keys($cross_sell_ids) as $cs_id) {
            if (!in_array($cs_id, $candidates, true)) {
                $candidates[] = $cs_id;
            }
        }
    }

    // Sized product families: base product ID → all size variants.
    // When a sized product appears as a candidate, we return a "sized" upsell card
    // with all variant sizes so the customer can pick which size to add.
    // These cards persist in the upsell section (customer may want multiple sizes).
    $sized_families = [
        11175 => [  // PU Roller — base ID triggers sized card
            'name'  => 'PU Roller',
            'sizes' => [
                ['label' => '10cm', 'wcId' => 11175, 'price' => 2.50],
                ['label' => '18cm', 'wcId' => 17360, 'price' => 9.95],
                ['label' => '25cm', 'wcId' => 17361, 'price' => 12.95],
                ['label' => '50cm', 'wcId' => 19705, 'price' => 17.50],
            ],
        ],
        11164 => [  // Verfbak — base ID triggers sized card
            'name'  => 'Verfbak',
            'sizes' => [
                ['label' => '10cm', 'wcId' => 11164, 'price' => 2.95],
                ['label' => '18cm', 'wcId' => 28234, 'price' => 4.95],
                ['label' => '32cm', 'wcId' => 28235, 'price' => 5.95],
            ],
        ],
    ];

    // Option families: same product ID but with addon meta (e.g. primer Ja/Nee).
    // Unlike sized families (different WC product IDs per pill), option families
    // use the same product ID and pass cart item meta to control pricing.
    // The oz_cart_drawer_add AJAX handler passes these meta values to add_to_cart().
    $option_families = [
        22436 => [  // Stuco Paste — primer is an addon, not a separate product
            'name'    => 'Stuco Paste',
            'options' => [
                [
                    'label' => 'Zonder primer',
                    'price' => 59.95,
                    'meta'  => ['oz_line' => 'stuco-paste', 'oz_primer' => 'Nee'],
                ],
                [
                    'label' => 'Met primer',
                    'price' => 75.95,  // 59.95 + 16.00 primer addon
                    'meta'  => ['oz_line' => 'stuco-paste', 'oz_primer' => 'Ja'],
                ],
            ],
        ],
    ];

    // Also build a reverse map: any size wcId → base ID (for deduplication)
    $sized_member_to_base = [];
    foreach ($sized_families as $base_id => $family) {
        foreach ($family['sizes'] as $sz) {
            $sized_member_to_base[$sz['wcId']] = $base_id;
        }
    }

    // Pick top 3 candidates that aren't in cart and pass stock/purchasable checks.
    // Sized products expand into a multi-size card and don't block their slot
    // after adding (customer may want multiple sizes).
    $upsells = [];
    $used_families = [];  // Track which sized families we've already added

    foreach ($candidates as $cid) {
        if (count($upsells) >= 3) break;

        // Check if this candidate belongs to an option family (e.g. Stuco Paste with primer)
        if (isset($option_families[$cid])) {
            if (isset($used_families['opt_' . $cid])) continue;

            // Option families: same product ID, different meta. Show card if product not in cart.
            // (Unlike sized families, option families have one product ID — if it's in cart
            // with any option, we skip it since customer already has it.)
            if (isset($cart_product_ids[$cid])) continue;

            $family = $option_families[$cid];
            $product = wc_get_product($cid);
            if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) continue;

            $image_id  = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

            $upsells[] = [
                'id'      => $cid,
                'name'    => $family['name'],
                'price'   => $family['options'][0]['price'],  // Default option price
                'image'   => $image_url,
                'type'    => 'option',
                'options' => $family['options'],
            ];
            $used_families['opt_' . $cid] = true;
            continue;
        }

        // Check if this candidate belongs to a sized family
        $base_id = isset($sized_member_to_base[$cid]) ? $sized_member_to_base[$cid] : null;

        if ($base_id !== null && isset($sized_families[$base_id])) {
            // Skip if we already added this sized family
            if (isset($used_families[$base_id])) continue;

            // For sized families: show the card if at least one size is NOT in cart
            $family = $sized_families[$base_id];
            $any_available = false;
            foreach ($family['sizes'] as $sz) {
                if (!isset($cart_product_ids[$sz['wcId']])) {
                    $any_available = true;
                    break;
                }
            }
            if (!$any_available) continue;

            // Get image from the base product
            $base_product = wc_get_product($base_id);
            if (!$base_product) continue;
            $image_id  = $base_product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

            // Build sizes array with in_cart flags
            $size_data = [];
            foreach ($family['sizes'] as $sz) {
                $size_data[] = [
                    'label'   => $sz['label'],
                    'wcId'    => $sz['wcId'],
                    'price'   => $sz['price'],
                    'in_cart' => isset($cart_product_ids[$sz['wcId']]),
                ];
            }

            $upsells[] = [
                'id'      => $base_id,
                'name'    => $family['name'],
                'price'   => $family['sizes'][0]['price'],  // Base price (updates on size select)
                'image'   => $image_url,
                'type'    => 'sized',
                'sizes'   => $size_data,
            ];
            $used_families[$base_id] = true;
        } else {
            // Regular (non-sized) product
            if (isset($cart_product_ids[$cid])) continue;

            $formatted = oz_cart_drawer_format_upsell($cid);
            if ($formatted) {
                $upsells[] = $formatted;
            }
        }
    }

    return $upsells;
}

/**
 * Shared product card formatter — returns id, name, price, image, permalink.
 * Used by both upsell cards and recently-viewed carousel.
 *
 * @param WC_Product $product  A loaded WC product object.
 * @return array
 */
function oz_format_product_card($product) {
    $image_id  = $product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

    return [
        'id'        => $product->get_id(),
        'name'      => $product->get_name(),
        'price'     => floatval($product->get_price()),
        'image'     => $image_url,
        'permalink' => $product->get_permalink(),
    ];
}

/**
 * Format a single product ID into an upsell card data array.
 * Returns false if product is not purchasable or out of stock.
 *
 * @param int $product_id
 * @return array|false
 */
function oz_cart_drawer_format_upsell($product_id) {
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
        return false;
    }

    return oz_format_product_card($product);
}

/**
 * FAQ schema output.
 * Accordion shortcode stubs call oz_faq_schema_add() during rendering
 * to accumulate Q&A pairs. This footer hook outputs deduplicated FAQPage schema.
 * No redundant do_shortcode() — piggybacks on the normal render pass.
 */
function oz_faq_schema_add( $question, $answer ) {
    global $oz_faq_items;
    if ( ! is_array( $oz_faq_items ) ) $oz_faq_items = [];
    $oz_faq_items[] = [ 'q' => $question, 'a' => $answer ];
}

function oz_faq_schema_output() {
    global $oz_faq_items;
    if ( empty( $oz_faq_items ) ) return;

    $seen   = [];
    $unique = [];
    foreach ( $oz_faq_items as $faq ) {
        $q = wp_strip_all_tags( trim( $faq['q'] ) );
        $a = wp_kses_post( trim( $faq['a'] ) );
        if ( $q && $a && ! isset( $seen[ $q ] ) ) {
            $seen[ $q ] = true;
            $unique[]   = [ '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $a ] ];
        }
    }

    if ( empty( $unique ) ) return;

    $json = [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $unique ];
    echo '<script type="application/ld+json">' . wp_json_encode( $json ) . '</script>';
}
add_action( 'wp_footer', 'oz_faq_schema_output' );

/**
 * Remove block editor scripts from the frontend.
 * The product carousel plugin (woo-product-carousel-slider-and-grid-ultimate)
 * enqueues its Gutenberg JS on every page instead of only in admin.
 * This drags in 42 block editor dependencies (React, wp-blocks, etc.) ~500KB
 * that block the main thread and delay interactive elements like the mobile menu.
 */
function oz_dequeue_frontend_block_editor() {
    if (is_admin()) return;
    wp_dequeue_script('wcpcsup-gutenberg-js');
}
add_action('wp_enqueue_scripts', 'oz_dequeue_frontend_block_editor', 100);

/**
 * Remove plugin scripts that load on every page but are only needed in specific contexts.
 * Reduces blocking JS on non-product pages by removing scripts
 * that are only needed in specific contexts.
 */
function oz_dequeue_unnecessary_scripts() {
    if (is_admin()) return;

    // reCAPTCHA — only strip on pages that definitely have no forms.
    // CF7 forms (kleurstalen aanvraag) need wpcf7-recaptcha to validate submissions.
    // Detect pages with forms that need reCAPTCHA (CF7 or WPForms)
    $has_form = false;
    if (is_singular()) {
        $post = get_post();
        if ($post && (strpos($post->post_content, 'contact-form-7') !== false
                   || strpos($post->post_content, 'wpforms') !== false)) {
            $has_form = true;
        }
    }
    $needs_captcha = is_account_page() || is_checkout() || $has_form;
    if (!$needs_captcha) {
        wp_dequeue_script('wpcaptcha-recaptcha');
        wp_dequeue_script('google-recaptcha');
        wp_dequeue_script('wpcf7-recaptcha');
        remove_action('woocommerce_login_form', array('WPCaptcha_Functions', 'login_scripts_print'));
        remove_action('woocommerce_login_form', array('WPCaptcha_Functions', 'captcha_fields_print'));
        remove_action('woocommerce_register_form', array('WPCaptcha_Functions', 'login_scripts_print'));
        remove_action('woocommerce_register_form', array('WPCaptcha_Functions', 'captcha_fields_print'));
        remove_action('comment_form_after_fields', array('WPCaptcha_Functions', 'login_scripts_print'));
    }

    // Follow-Up Emails — only needed on My Account and product pages
    if (!is_product() && !is_account_page()) {
        wp_dequeue_script('fue-account-subscriptions');
        wp_dequeue_script('fue-front-script');
        wp_dequeue_style('fue-followups');
    }

    // Product category discount — only needed on product and shop pages
    if (!is_product() && !is_shop() && !is_product_category()) {
        wp_dequeue_script('woo-product-category-discount');
        wp_dequeue_style('woo-product-category-discount');
    }

    // Keuzehulp editor — only needed on pages that contain the shortcode
    $has_keuzehulp = false;
    if (is_singular()) {
        $post = get_post();
        if ($post && strpos($post->post_content, 'keuzehulp') !== false) {
            $has_keuzehulp = true;
        }
    }
    if (!$has_keuzehulp) {
        wp_dequeue_script('keuzehulp-v8-frontend');
        wp_dequeue_style('keuzehulp-v8-frontend');
    }

    // swmodal — only needed on pages that use it
    if (!is_product()) {
        wp_dequeue_style('swmodal');
        wp_dequeue_script('swmodal');
    }
}
add_action('wp_enqueue_scripts', 'oz_dequeue_unnecessary_scripts', 100);

/**
 * Remove WordPress/plugin bloat CSS from the frontend.
 * Runs at priority 9999 to catch late enqueues.
 */
function oz_remove_bloat_styles_from_frontend() {
    if (is_admin()) return;

    /* Dashicons: admin toolbar icons — only needed for logged-in admins */
    if (!is_user_logged_in()) {
        wp_dequeue_style('dashicons');
        wp_deregister_style('dashicons');
    }

    /* Gutenberg block editor styles leaked onto the frontend */
    wp_dequeue_style('wp-block-editor');
    wp_dequeue_style('wp-components');
    wp_dequeue_style('wp-preferences');
    wp_dequeue_style('popup-maker-block-library-style');

    /* WP classic theme compat — we use our own design system */
    wp_dequeue_style('classic-theme-styles');

    /* Font Awesome — not used in our theme, 4 handleiding pages
       used a fa-file-pdf icon but we'll replace with inline SVG */
    wp_dequeue_style('font-awesome');
    wp_deregister_style('font-awesome');

    /* Contact Form 7 — only load on pages with forms */
    $has_cf7 = false;
    if (is_singular()) {
        $post = get_post();
        if ($post && strpos($post->post_content, 'contact-form-7') !== false) {
            $has_cf7 = true;
        }
    }
    if (!$has_cf7) {
        wp_dequeue_style('contact-form-7');
    }

    /* Variation Price Display — only needed on product pages */
    if (!is_product()) {
        wp_dequeue_style('vpd-public');
        wp_dequeue_script('vpd-public');
    }

    /* WC Blocks may re-enqueue even after priority 100 — final catch */
    $is_wc_page = function_exists('is_woocommerce') && (
        is_woocommerce() || is_cart() || is_checkout() || is_account_page()
    );
    if (!$is_wc_page) {
        wp_dequeue_style('wc-blocks-style');
        wp_dequeue_style('wc-blocks-vendors-style');
    }
}
add_action('wp_enqueue_scripts', 'oz_remove_bloat_styles_from_frontend', 9999);

/**
 * Remove WP global styles (theme.json inline CSS).
 * WP generates these via wp_enqueue_global_styles which runs at wp_enqueue_scripts priority 10.
 * Dequeuing the handle at 9999 doesn't always work because it's inline CSS, so we
 * remove the action that generates it entirely.
 */
remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
remove_action('wp_footer', 'wp_enqueue_global_styles', 1);

/**
 * Final dequeue at wp_print_styles — catches styles that WC Blocks
 * re-enqueues after wp_enqueue_scripts.
 * Deregister forces WP to forget the handle entirely so it can't be re-enqueued.
 */
function oz_final_style_cleanup() {
    if (is_admin()) return;

    $is_wc_page = function_exists('is_woocommerce') && (
        is_woocommerce() || is_cart() || is_checkout() || is_account_page()
    );
    if (!$is_wc_page) {
        wp_dequeue_style('wc-blocks-style');
        wp_deregister_style('wc-blocks-style');
        wp_dequeue_style('wc-blocks-vendors-style');
        wp_deregister_style('wc-blocks-vendors-style');
    }

    wp_dequeue_style('global-styles');
}
add_action('wp_print_styles', 'oz_final_style_cleanup', 9999);
