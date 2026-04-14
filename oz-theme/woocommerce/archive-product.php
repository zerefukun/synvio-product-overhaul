<?php
/**
 * Shop / product category page.
 * Sidebar sits outside the boxed content area (left edge of viewport).
 * Product grid stays inside the normal container.
 * On mobile the sidebar collapses behind a toggle button.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

/* Current category (if browsing a category) */
$current_cat    = is_product_category() ? get_queried_object() : null;
$current_cat_id = $current_cat ? $current_cat->term_id : 0;

/* Build category tree for sidebar */
$top_cats = get_terms([
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'parent'     => 0,
    'exclude'    => [ get_option('default_product_cat') ],
    'orderby'    => 'name',
]);
?>

<?php do_action( 'woocommerce_before_main_content' ); ?>

<!-- Page header stays boxed -->
<header class="oz-shop__header oz-container">
    <?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
        <h1 class="oz-shop__title"><?php woocommerce_page_title(); ?></h1>
    <?php endif; ?>
    <?php do_action( 'woocommerce_archive_description' ); ?>
</header>

<!-- Full-width layout: sidebar outside container, products inside -->
<div class="oz-shop__layout">

    <!-- Sidebar: flush left, outside the boxed content area -->
    <aside class="oz-shop__sidebar" id="shop-sidebar">
        <button class="oz-shop__filter-close" id="filter-close" type="button" aria-label="Filters sluiten">&times;</button>
        <nav class="oz-shop__categories" aria-label="Productcategorieën">
            <h2 class="oz-shop__sidebar-title">Categorieën</h2>
            <ul class="oz-cat-nav">
                <?php if ( ! empty( $top_cats ) && ! is_wp_error( $top_cats ) ) : ?>
                    <?php foreach ( $top_cats as $cat ) :
                        $is_active   = ( $current_cat_id === $cat->term_id );
                        $is_ancestor = $current_cat && term_is_ancestor_of( $cat->term_id, $current_cat_id, 'product_cat' );
                        $active_cls  = ( $is_active || $is_ancestor ) ? ' is-active' : '';

                        $children = get_terms([
                            'taxonomy'   => 'product_cat',
                            'hide_empty' => true,
                            'parent'     => $cat->term_id,
                            'orderby'    => 'name',
                        ]);
                    ?>
                        <li class="oz-cat-nav__item<?php echo esc_attr( $active_cls ); ?>">
                            <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="oz-cat-nav__link">
                                <?php echo esc_html( $cat->name ); ?>
                                <span class="oz-cat-nav__count"><?php echo esc_html( $cat->count ); ?></span>
                            </a>
                            <?php if ( ! empty( $children ) && ! is_wp_error( $children ) ) : ?>
                                <ul class="oz-cat-nav__sub">
                                    <?php foreach ( $children as $child ) :
                                        $child_active    = ( $current_cat_id === $child->term_id );
                                        $child_ancestor  = $current_cat && term_is_ancestor_of( $child->term_id, $current_cat_id, 'product_cat' );
                                        $child_cls       = ( $child_active || $child_ancestor ) ? ' is-active' : '';

                                        /* Grandchildren (3rd level: e.g. All-In-One > Beige) */
                                        $grandchildren = get_terms([
                                            'taxonomy'   => 'product_cat',
                                            'hide_empty' => true,
                                            'parent'     => $child->term_id,
                                            'orderby'    => 'name',
                                        ]);
                                    ?>
                                        <li class="oz-cat-nav__item<?php echo esc_attr( $child_cls ); ?>">
                                            <a href="<?php echo esc_url( get_term_link( $child ) ); ?>" class="oz-cat-nav__link">
                                                <?php echo esc_html( $child->name ); ?>
                                                <span class="oz-cat-nav__count"><?php echo esc_html( $child->count ); ?></span>
                                            </a>
                                            <?php if ( ! empty( $grandchildren ) && ! is_wp_error( $grandchildren ) ) : ?>
                                                <ul class="oz-cat-nav__sub">
                                                    <?php foreach ( $grandchildren as $gc ) :
                                                        $gc_cls = ( $current_cat_id === $gc->term_id ) ? ' is-active' : '';
                                                    ?>
                                                        <li class="oz-cat-nav__item<?php echo esc_attr( $gc_cls ); ?>">
                                                            <a href="<?php echo esc_url( get_term_link( $gc ) ); ?>" class="oz-cat-nav__link">
                                                                <?php echo esc_html( $gc->name ); ?>
                                                                <span class="oz-cat-nav__count"><?php echo esc_html( $gc->count ); ?></span>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </nav>

        <?php
        /*
         * Optional widget area for additional filters (e.g. price filter).
         * Category navigation is handled above — remove the WC "Product Categories"
         * widget from Appearance > Widgets if it was added there.
         */
        if ( is_active_sidebar( 'shop-sidebar' ) ) {
            dynamic_sidebar( 'shop-sidebar' );
        }
        ?>
    </aside>

    <!-- Main: boxed content area with toolbar + product grid -->
    <div class="oz-shop__main">

        <?php if ( woocommerce_product_loop() ) : ?>

            <div class="oz-shop__toolbar">
                <button class="oz-shop__filter-toggle oz-btn oz-btn--outline oz-btn--sm" id="filter-toggle" type="button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="20" y2="12"/><line x1="12" y1="18" x2="20" y2="18"/></svg>
                    Filter
                </button>
                <?php do_action( 'woocommerce_before_shop_loop' ); ?>
            </div>

            <?php woocommerce_product_loop_start(); ?>
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php wc_get_template_part( 'content', 'product' ); ?>
                <?php endwhile; ?>
            <?php woocommerce_product_loop_end(); ?>

            <?php do_action( 'woocommerce_after_shop_loop' ); ?>

        <?php else : ?>

            <?php do_action( 'woocommerce_no_products_found' ); ?>

        <?php endif; ?>

    </div>

</div>

<?php do_action( 'woocommerce_after_main_content' ); ?>

<!-- Sidebar toggle for mobile -->
<script>
(function() {
    var toggle = document.getElementById('filter-toggle');
    var close  = document.getElementById('filter-close');
    var sidebar = document.getElementById('shop-sidebar');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', function() {
        sidebar.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    });

    if (close) {
        close.addEventListener('click', function() {
            sidebar.classList.remove('is-open');
            document.body.style.overflow = '';
        });
    }
})();
</script>

<?php get_footer(); ?>
