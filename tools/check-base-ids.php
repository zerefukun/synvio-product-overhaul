<?php
// Check base product IDs for all lines
define('ABSPATH', '/home/betoncire/public_html/');
require_once ABSPATH . 'wp-load.php';

$ids = [11161, 11165, 11191, 11160, 11162, 22760, 27736, 11135];

foreach ($ids as $id) {
    $p = wc_get_product($id);
    if ($p) {
        $cats = wp_get_post_terms($id, 'product_cat', ['fields' => 'names']);
        echo sprintf(
            "%d: %s [slug=%s] status=%s cats=[%s]\n",
            $id,
            $p->get_name(),
            $p->get_slug(),
            $p->get_status(),
            implode(', ', $cats)
        );
    } else {
        echo "$id: NOT FOUND\n";
    }
}
