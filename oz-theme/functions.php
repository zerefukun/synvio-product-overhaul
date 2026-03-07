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
