<?php
/**
 * Shop / product category page.
 * Sidebar sits outside the boxed content area (left edge of viewport).
 * Product grid stays inside the normal container.
 * On mobile the sidebar collapses behind a toggle button.
 *
 * Sidebar uses the 'oz-shop-sidebar' nav menu (Appearance > Menus)
 * so the structure is fully editable without code changes.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

/* Current page URL for active-state matching */
$current_url = trailingslashit( strtok( $_SERVER['REQUEST_URI'], '?' ) );
?>

<!-- Boxed content: breadcrumbs + page header get container padding -->
<div class="oz-shop__top oz-container">
    <?php do_action( 'woocommerce_before_main_content' ); ?>

    <header class="oz-shop__header">
        <?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
            <h1 class="oz-shop__title"><?php woocommerce_page_title(); ?></h1>
        <?php endif; ?>
        <?php do_action( 'woocommerce_archive_description' ); ?>
    </header>
</div>

<!-- Full-width layout: sidebar outside container, products inside -->
<div class="oz-shop__layout">

    <!-- Sidebar: curated nav menu -->
    <aside class="oz-shop__sidebar" id="shop-sidebar">
        <button class="oz-shop__filter-close" id="filter-close" type="button" aria-label="Filters sluiten">&times;</button>
        <nav class="oz-shop__categories" aria-label="Productcategorieën">
            <h2 class="oz-shop__sidebar-title">Categorieën</h2>
            <?php
            if ( has_nav_menu( 'oz-shop-sidebar' ) ) {
                wp_nav_menu([
                    'theme_location' => 'oz-shop-sidebar',
                    'container'      => false,
                    'menu_class'     => 'oz-cat-nav',
                    'walker'         => new Oz_Shop_Sidebar_Walker( $current_url ),
                    'depth'          => 4,
                ]);
            } else {
                echo '<p class="oz-shop__no-menu">Ga naar Weergave &rarr; Menu&rsquo;s en wijs een menu toe aan &ldquo;Shop Sidebar Categories&rdquo;.</p>';
            }
            ?>
        </nav>

        <?php if ( is_active_sidebar( 'shop-sidebar' ) ) { dynamic_sidebar( 'shop-sidebar' ); } ?>
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

<script>
(function() {
    /* Mobile sidebar toggle */
    var toggle = document.getElementById('filter-toggle');
    var close  = document.getElementById('filter-close');
    var sidebar = document.getElementById('shop-sidebar');
    if (toggle && sidebar) {
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
    }

    /* Category sub-list expand/collapse toggles */
    document.querySelectorAll('.oz-cat-nav__toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            btn.closest('.oz-cat-nav__item').classList.toggle('is-open');
        });
    });
})();
</script>

<?php get_footer(); ?>
