<?php
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

function load_font_awesome() {
    wp_enqueue_style('font-awesome', '//use.fontawesome.com/releases/v5.8.1/css/all.css');
}
add_action('wp_enqueue_scripts', 'load_font_awesome');
add_action('admin_enqueue_scripts', 'load_font_awesome');

function add_m2_calculator_meta_box() {
    add_meta_box(
        'm2_calculator_meta_box',
        __('Enable m² Calculator', 'textdomain'),
        'm2_calculator_meta_box_html',
        'product',
        'side',
        'high'
    );
}

add_action('add_meta_boxes', 'add_m2_calculator_meta_box');

function m2_calculator_meta_box_html($post) {
    $value = get_post_meta($post->ID, '_enable_m2', true);
    ?>
    <label for="enable_m2"><?php esc_html_e('Enable m² Calculator', 'textdomain'); ?></label>
    <input type="checkbox" name="enable_m2" id="enable_m2" value="1" <?php checked($value, '1'); ?>>
    <?php
}

function save_m2_calculator_meta_box_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['enable_m2'])) {
        update_post_meta($post_id, '_enable_m2', '1');
    } else {
        update_post_meta($post_id, '_enable_m2', '0');
    }
}

add_action('save_post', 'save_m2_calculator_meta_box_data');

function my_enqueue_product_scripts() {
    if (is_product()) {
        global $post;

        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $product_id = $post->ID;
        $enable_m2_calculator = get_post_meta($product_id, '_enable_m2', true);

        // Only enqueue if m² calculator is enabled
        if ('1' === $enable_m2_calculator) {
            wp_enqueue_script('my-m2-calculator-script', get_stylesheet_directory_uri() . '/js/my-m2-calculator-script.js', array('jquery'), time(), true);
            wp_localize_script('my-m2-calculator-script', 'M2CalculatorParams', array(
                'isEnabled' => true
            ));
        }
    }
}

add_action('wp_enqueue_scripts', 'my_enqueue_product_scripts');

function add_dynamic_content_meta_box() {
    add_meta_box(
        'dynamic_content_meta_box',
        __('Oz Handleiding', 'textdomain'),
        'dynamic_content_meta_box_html',
        'product',
        'normal',
        'high'
    );
}

add_action('add_meta_boxes', 'add_dynamic_content_meta_box');

function dynamic_content_meta_box_html($post) {
    $dynamic_content = get_post_meta($post->ID, '_dynamic_content', true);
    $enable_oz_handleiding_value = get_post_meta($post->ID, '_enable_oz_handleiding', true);

    $image_url = isset($dynamic_content['image_url']) ? esc_url($dynamic_content['image_url']) : '';
    $title = isset($dynamic_content['title']) ? esc_attr($dynamic_content['title']) : '';
    $text = isset($dynamic_content['text']) ? esc_html($dynamic_content['text']) : '';
    $link = isset($dynamic_content['link']) ? esc_url($dynamic_content['link']) : '';
    ?>
    <div class="oz-handleiding">
        <label for="enable_oz_handleiding"><?php esc_html_e('Enable Oz Handleiding', 'textdomain'); ?></label>
        <input type="checkbox" name="enable_oz_handleiding" id="enable_oz_handleiding" value="1" <?php checked($enable_oz_handleiding_value, '1'); ?>>

        <div id="image-preview" class="image-preview">
            <?php if (!empty($image_url)): ?>
                <img src="<?php echo $image_url; ?>" alt="Selected Image" style="max-width:100%;">
            <?php else: ?>
                <i class="fa fa-camera fa-3x"></i>
            <?php endif; ?>
        </div>
        <div class="button-styles">
            <div class="buttons">
                <input type="button" id="upload_dynamic_image_button" class="buttonr" value="Upload Image">
                <button type="button" id="remove_image_button" class="button"<?php echo empty($image_url) ? ' style="display: none;"' : ''; ?>>Remove Image</button>
            </div>
        </div>
        <input type="hidden" id="dynamic_image_url" name="dynamic_content[image_url]" value="<?php echo esc_url($image_url); ?>">
        </div>

        <label for="dynamic_title">Titel:</label>
        <input type="text" id="dynamic_title" name="dynamic_content[title]" value="<?php echo $title; ?>" style="width: 100%;">

        <label for="dynamic_text">Text:</label>
        <textarea id="dynamic_text" name="dynamic_content[text]" class="no-scroll" value="<?php echo $text; ?>" style="width: 100%;"><?php echo $text; ?></textarea>

        <label for="dynamic_link">Link:</label>
        <input type="text" id="dynamic_link" name="dynamic_content[link]" value="<?php echo $link; ?>" style="width: 100%;">
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#upload_dynamic_image_button').click(function(e) {
            e.preventDefault();
            var custom_uploader = wp.media({
                title: 'Choose an Image',
                button: {
                    text: 'Use This Image'
                },
                multiple: false
            }).on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $('#image-preview').html('<img src="' + attachment.url + '" alt="Selected Image" style="max-width:100%;">');
                $('#dynamic_image_url').val(attachment.url);
                $('#remove_image_button').show();
            });
            custom_uploader.open();
        });

        $('#remove_image_button').click(function(e) {
            e.preventDefault();
            $('#image-preview').html('<i class="fa fa-camera fa-3x"></i>'); // Placeholder FA icon
            $('#dynamic_image_url').val('');
            $(this).hide();
        });
    });
    jQuery(document).ready(function($) {
    // Function to resize textarea
    function resizeTextarea(id) {
        var textarea = $(id);
        textarea.height('auto'); // Reset the height
        textarea.height(textarea.prop('scrollHeight'));
    }

    // Resize on input
    $('textarea').on('input', function() {
        resizeTextarea(this);
    });

    // Initial resize
    $('textarea').each(function() {
        resizeTextarea(this);
    });
    });
    jQuery(document).ready(function($) {
    $('form').on('submit', function() {
        var text = $('#dynamic_text').val();
        var cleanText = text.replace(/<[^>]*>/g, "");
        $('#dynamic_text').val(cleanText);
    });
    });
    </script>
    <?php
}



function get_custom_content_data($product_id) {
    $dynamic_content = get_post_meta($product_id, '_dynamic_content', true);

    return array(
        'image' => isset($dynamic_content['image_url']) ? esc_url($dynamic_content['image_url']) : '',
        'title' => isset($dynamic_content['title']) ? esc_attr($dynamic_content['title']) : '',
        'text' => isset($dynamic_content['text']) ? esc_html($dynamic_content['text']) : '',
        'link' => isset($dynamic_content['link']) ? esc_url($dynamic_content['link']) : ''
    );
}

function save_dynamic_content_meta_box_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['enable_oz_handleiding'])) {
        update_post_meta($post_id, '_enable_oz_handleiding', '1');
    } else {
        update_post_meta($post_id, '_enable_oz_handleiding', '0');
    }

    if (isset($_POST['dynamic_content']['text'])) {
        $text = sanitize_textarea_field($_POST['dynamic_content']['text']);
        update_post_meta($post_id, '_dynamic_content_text', $text);
    }

    if (isset($_POST['dynamic_content'])) {
        $dynamic_content = $_POST['dynamic_content'];
        update_post_meta($post_id, '_dynamic_content', $dynamic_content);
    }
}
add_action('save_post', 'save_dynamic_content_meta_box_data');


function enqueue_custom_meta_box_styles() {
    wp_enqueue_style('custom-meta-box-styles', get_stylesheet_directory_uri() . '/css/meta-box-styles.css');
}
add_action('admin_enqueue_scripts', 'enqueue_custom_meta_box_styles');

function enqueue_custom_content_script() {
    if (is_product()) {
        global $post;

        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $product_id = $post->ID;
        $enable_oz_handleiding_value = get_post_meta($product_id, '_enable_oz_handleiding', true);

        wp_enqueue_script('custom-content-script', get_stylesheet_directory_uri() . '/js/oz-handleiding.js', array('jquery'), null, true);

        wp_localize_script('custom-content-script', 'customContentParams', array(
            'isEnabled' => true,
            'enableOzHandleiding' => '1' === $enable_oz_handleiding_value,
            'dynamicContent' => get_custom_content_data($product_id)
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_custom_content_script');

function independent_function_to_process_data($data){
    // Check if the data is not empty and is an array
    if (empty($data) || !is_array($data)) {
        return $data;
    }

    // Check if the order ID is set and is numeric
    if (!isset($data['orderNR']) || !is_numeric($data['orderNR'])) {
        return $data;
    }

    $order_id = $data['orderNR'];
    $order = wc_get_order($order_id);

    // Check if the order object is valid
    if (!$order) {
        return $data;
    }

    // Assuming the street name and house number are set as post meta
    $streetname = $order->get_shipping_address_1();
    $housenumber = $order->get_meta('shipping_housenumber');

    // Check if both street name and house number are not empty
    if (!empty($streetname) && !empty($housenumber)) {
        // Concatenate street name and house number
        $data['shipping_address']['address_1'] = trim($streetname . ' ' . $housenumber);
    }

    return $data;
}
add_action('parcelpro_format_order_data', 'independent_function_to_process_data');


// function custom_checkout_field_update_order_meta($order_id){
//     if(!empty($_POST['shipping_address_1'])){
//         update_post_meta($order_id, 'shipping_address_1', sanitize_text_field($_POST['shipping_address_1']));
//     }
//     if(!empty($_POST['shipping_housenumber'])){
//         update_post_meta($order_id, 'shipping_housenumber', sanitize_text_field($_POST['shipping_housenumber']));
//     }
// }
// add_action('woocommerce_checkout_update_order_meta', 'custom_checkout_field_update_order_meta');

?>
<?php
/**
 * Server-side Order Attribution Fallback
 * Captures UTM parameters when Cookiebot blocks JS tracking
 * Added by Claude Code - 2026-01-09
 */

// Start session early to store UTM params
add_action('init', 'oz_start_attribution_session', 1);
function oz_start_attribution_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
    
    // Capture UTM params on first visit
    $utm_params = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term');
    
    foreach ($utm_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $_SESSION['oz_' . $param] = sanitize_text_field($_GET[$param]);
        }
    }
    
    // Capture referrer if not already set
    if (!isset($_SESSION['oz_referrer']) && isset($_SERVER['HTTP_REFERER'])) {
        $referrer = $_SERVER['HTTP_REFERER'];
        $site_url = home_url();
        
        // Only store external referrers
        if (strpos($referrer, $site_url) === false) {
            $_SESSION['oz_referrer'] = esc_url_raw($referrer);
        }
    }
    
    // Track session start time
    if (!isset($_SESSION['oz_session_start'])) {
        $_SESSION['oz_session_start'] = current_time('mysql');
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
    
    // Determine source type based on available data
    $source_type = 'typein'; // Default: direct visit
    $utm_source = isset($_SESSION['oz_utm_source']) ? $_SESSION['oz_utm_source'] : '';
    $utm_medium = isset($_SESSION['oz_utm_medium']) ? $_SESSION['oz_utm_medium'] : '';
    $utm_campaign = isset($_SESSION['oz_utm_campaign']) ? $_SESSION['oz_utm_campaign'] : '';
    $referrer = isset($_SESSION['oz_referrer']) ? $_SESSION['oz_referrer'] : '';
    
    // Determine source type
    if (!empty($utm_source) || !empty($utm_campaign)) {
        $source_type = 'utm';
    } elseif (!empty($referrer)) {
        // Check if organic search
        $search_engines = array('google', 'bing', 'yahoo', 'duckduckgo', 'ecosia');
        foreach ($search_engines as $engine) {
            if (stripos($referrer, $engine) !== false) {
                $source_type = 'organic';
                $utm_source = $engine;
                break;
            }
        }
        if ($source_type !== 'organic') {
            $source_type = 'referral';
        }
    }
    
    // Apply attribution meta (server-side fallback)
    $order->update_meta_data('_wc_order_attribution_source_type', $source_type);
    $order->update_meta_data('_wc_order_attribution_utm_source', $utm_source ?: 'direct');
    $order->update_meta_data('_wc_order_attribution_utm_medium', $utm_medium);
    $order->update_meta_data('_wc_order_attribution_utm_campaign', $utm_campaign);
    $order->update_meta_data('_wc_order_attribution_referrer', $referrer);
    $order->update_meta_data('_wc_order_attribution_session_start_time', isset($_SESSION['oz_session_start']) ? $_SESSION['oz_session_start'] : '');
    $order->update_meta_data('_oz_attribution_fallback', 'yes'); // Mark as fallback
    
    $order->save();
}


/* Disable Flatsome's built-in mini-cart dropdown entirely.
 * When true, Flatsome renders the cart icon as a plain <a> link.
 * Our JS intercepts .header-cart-link clicks to open our drawer instead. */
add_filter('flatsome_disable_mini_cart', '__return_true');

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

    // Google Fonts (may already be enqueued by plugin, but wp_enqueue is idempotent by handle)
    wp_enqueue_style(
        'oz-google-fonts-drawer',
        'https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Raleway:wght@400;500;600;700&display=swap',
        [],
        null
    );

    // JS
    wp_enqueue_script(
        'oz-cart-drawer',
        get_stylesheet_directory_uri() . '/js/cart-drawer.js',
        [],
        filemtime(get_stylesheet_directory() . '/js/cart-drawer.js'),
        true
    );

    // Pass AJAX URL, nonce, page context, and free shipping threshold to JS
    wp_localize_script('oz-cart-drawer', 'ozCartDrawer', [
        'ajaxUrl'           => admin_url('admin-ajax.php'),
        'nonce'             => wp_create_nonce('oz_cart_drawer'),
        'isCartOrCheckout'  => (is_cart() || is_checkout()) ? '1' : '0',
        'freeShipThreshold' => oz_get_free_shipping_threshold(),
    ]);
}
add_action('wp_enqueue_scripts', 'oz_cart_drawer_enqueue');

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

        // Get product thumbnail URL
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

        // Build meta string from cart item data (color, options, etc.)
        $meta_parts = [];

        // Use our cart manager's build_addon_details for configured products
        if (class_exists('OZ_Cart_Manager') && (isset($cart_item['oz_line']) || isset($cart_item['oz_page_mode']))) {
            // Pass product_id so generic addon groups can resolve labels
            $addon_data = $cart_item;
            $addon_data['product_id'] = $product->get_id();
            $meta_parts = OZ_Cart_Manager::get_addon_details($addon_data);
        }

        // Kit contents label — show what's inside toolset products
        // so customers know at a glance what they're getting
        $kit_labels = [
            11177 => 'Spaan, kwast, PU garde, 3× roller, tape, 2× verfbak, vachtroller',
            25550 => 'Spaan, kwast, garde, PU garde, 3× roller, tape, 2× verfbak, vachtroller',
        ];
        $item_pid = $product->get_id();
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
            'name'       => $product->get_name(),
            'price'      => floatval($product->get_price()),
            'qty'        => $cart_item['quantity'],
            'image'      => $image_url,
            'meta'       => implode(' · ', $meta_parts),
            'line_total' => floatval($cart_item['line_total']) + floatval($cart_item['line_tax']),
            'product_id' => $product->get_id(),
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

    $cart = WC()->cart;

    // Check if this product is already in the cart (any line item with same product_id).
    // If found, bump its qty instead of creating a duplicate.
    foreach ($cart->get_cart() as $cart_key => $cart_item) {
        if ($cart_item['product_id'] === $product_id && empty($cart_item['variation_id'])) {
            $new_qty = $cart_item['quantity'] + $qty;
            $cart->set_quantity($cart_key, $new_qty);
            wp_send_json_success(['cart_key' => $cart_key, 'merged' => true]);
            return;
        }
    }

    // Product not in cart yet — add fresh
    $result = $cart->add_to_cart($product_id, $qty);
    if ($result) {
        wp_send_json_success(['cart_key' => $result]);
    } else {
        wp_send_json_error('Could not add product');
    }
}
add_action('wp_ajax_oz_cart_drawer_add', 'oz_cart_drawer_add');
add_action('wp_ajax_nopriv_oz_cart_drawer_add', 'oz_cart_drawer_add');

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
        'original'       => [11177, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'all-in-one'     => [11177, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'easyline'       => [11177, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'microcement'    => [11177, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'metallic'       => [11177, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
        'lavasteen'      => [25550, 11025, 11175, 11018, 11020, 11164, 11022, 11015, 11017, 11023, 11016],
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

    $image_id  = $product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

    return [
        'id'        => $product_id,
        'name'      => $product->get_name(),
        'price'     => floatval($product->get_price()),
        'image'     => $image_url,
        'permalink' => $product->get_permalink(),
    ];
}
