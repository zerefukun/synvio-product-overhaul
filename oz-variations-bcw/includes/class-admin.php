<?php
/**
 * Admin Settings for BCW
 *
 * Adds a WooCommerce settings page under WooCommerce > BCW Opties with:
 * - Reprocess all products button (rebuilds color extraction + variant links)
 * - Plugin status overview (product counts per line)
 *
 * @package OZ_Variations_BCW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_BCW_Admin {

    /**
     * Initialize admin hooks.
     */
    public static function init() {
        // Add admin menu page under WooCommerce
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);

        // Enqueue admin scripts on our settings page only
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
    }

    /**
     * Add submenu page under WooCommerce.
     */
    public static function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            'BCW Opties',
            'BCW Opties',
            'manage_woocommerce',
            'oz-bcw-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin JS only on our settings page.
     */
    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_oz-bcw-settings') {
            return;
        }

        wp_enqueue_script(
            'oz-bcw-admin',
            OZ_BCW_PLUGIN_URL . 'assets/js/oz-bcw-admin.js',
            [],
            OZ_BCW_VERSION,
            true
        );

        wp_localize_script('oz-bcw-admin', 'ozBcwAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('oz_bcw_reprocess'),
        ]);
    }

    /**
     * Render the settings page.
     */
    public static function render_settings_page() {
        // Get product counts per line for status overview
        $line_counts = self::get_line_counts();
        ?>
        <div class="wrap">
            <h1>OZ Variations BCW</h1>
            <p>Plugin versie: <?php echo esc_html(OZ_BCW_VERSION); ?></p>

            <hr>

            <!-- Reprocess Section -->
            <h2>Producten herverwerken</h2>
            <p>
                Herverwerk alle producten om kleuren te extraheren en variantlinks bij te werken.
                Dit is nodig na het importeren van nieuwe producten of het wijzigen van categorieën.
            </p>
            <button id="oz-bcw-reprocess" class="button button-primary">
                Alle producten herverwerken
            </button>
            <div id="oz-bcw-reprocess-result" style="margin-top:10px;"></div>

            <hr>

            <!-- Status Overview -->
            <h2>Productlijnen overzicht</h2>
            <table class="widefat fixed striped" style="max-width:600px;">
                <thead>
                    <tr>
                        <th>Productlijn</th>
                        <th>Aantal producten</th>
                        <th>Met kleurvarianten</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($line_counts as $line_key => $counts) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($line_key); ?></strong></td>
                            <td><?php echo intval($counts['total']); ?></td>
                            <td><?php echo intval($counts['with_variants']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($line_counts)) : ?>
                <p><em>Nog geen producten gevonden. Klik op "Alle producten herverwerken" om te starten.</em></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get product counts per product line.
     *
     * @return array  [line_key => ['total' => int, 'with_variants' => int]]
     */
    private static function get_line_counts() {
        $counts = [];

        foreach (OZ_Product_Line_Config::get_all_lines() as $line_key) {
            $config = OZ_Product_Line_Config::get_config($line_key);
            if (!$config) {
                continue;
            }

            $total = 0;
            $with_variants = 0;

            // Category-based lines
            if (!empty($config['cats'])) {
                $args = [
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'tax_query'      => [[
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => $config['cats'],
                    ]],
                    'no_found_rows'  => true,
                ];

                $query = new WP_Query($args);
                $total = count($query->posts);

                foreach ($query->posts as $pid) {
                    $variants = get_post_meta($pid, '_oz_variants', true);
                    if (!empty($variants) && is_array($variants)) {
                        $with_variants++;
                    }
                }
            }

            // Single-product lines (e.g. betonlook-verf)
            if (isset($config['product_id'])) {
                $product = wc_get_product($config['product_id']);
                if ($product && $product->get_status() === 'publish') {
                    $total = 1;
                }
            }

            $counts[$line_key] = [
                'total'         => $total,
                'with_variants' => $with_variants,
            ];
        }

        return $counts;
    }
}
