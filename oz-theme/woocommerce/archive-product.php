<?php
/**
 * Shop / product category page.
 * Single grid layout: sidebar left, main content right.
 * Breadcrumb, header, toolbar, and product grid all live in the
 * main column so they share the same left edge.
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

<!-- Sidebar + main content in one grid so breadcrumb/title/grid all align -->
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

        <!-- Kleurstalen CTA banner -->
        <div class="oz-shop__stalen-banner">
            <img src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/img/kleurstalen-sidebar.jpg" alt="Kleurstalen beton cire" class="oz-shop__stalen-img" loading="lazy" width="280" height="160">
            <div class="oz-shop__stalen-body">
                <span class="oz-eyebrow">Gratis kleurstalen</span>
                <h3 class="oz-shop__stalen-title">Zeker van je kleur?</h3>
                <p class="oz-shop__stalen-text">Selecteer tot 4 kleuren en wij sturen ze gratis naar je toe.</p>
                <a href="<?php echo esc_url( home_url( '/kleurstalen-aanvragen/' ) ); ?>" class="oz-btn oz-btn--primary oz-btn--sm">Stalen aanvragen</a>
            </div>
        </div>
    </aside>

    <!-- Main: breadcrumb, header, toolbar, product grid — all aligned -->
    <div class="oz-shop__main">

        <?php do_action( 'woocommerce_before_main_content' ); ?>

        <header class="oz-shop__header">
            <span class="oz-eyebrow">Collectie</span>
            <?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
                <h1 class="oz-shop__title"><?php woocommerce_page_title(); ?></h1>
            <?php endif; ?>
            <?php do_action( 'woocommerce_archive_description' ); ?>
        </header>

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

<!-- Fixed decorative image for wide desktops (right gutter) -->
<div class="oz-shop__deco" aria-hidden="true">
    <img src="<?php echo esc_url( content_url( '/uploads/2024/02/Badkamer-vloer-en-wand-Original-1050-Homebystuart.webp' ) ); ?>" alt="" loading="lazy" width="600" height="800">
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

    /* Persist sort order across category navigation */
    var orderbySelect = document.querySelector('select.orderby');
    var SORT_KEY = 'oz_shop_orderby';

    if (orderbySelect) {
        /* Save selection to sessionStorage on change */
        orderbySelect.addEventListener('change', function() {
            sessionStorage.setItem(SORT_KEY, this.value);
        });

        /* On page load: restore saved sort if URL doesn't already have one */
        var urlParams = new URLSearchParams(window.location.search);
        var saved = sessionStorage.getItem(SORT_KEY);
        if (saved && !urlParams.has('orderby') && saved !== orderbySelect.value) {
            window.location.search = '?orderby=' + saved;
        }
    }

    /* Hide deco image when footer comes into view */
    var deco = document.querySelector('.oz-shop__deco');
    var footer = document.getElementById('oz-footer');
    if (deco && footer && window.IntersectionObserver) {
        new IntersectionObserver(function(entries) {
            deco.classList.toggle('is-hidden', entries[0].isIntersecting);
        }, { threshold: 0 }).observe(footer);
    }

    /* Append current orderby to sidebar category links */
    var currentOrder = new URLSearchParams(window.location.search).get('orderby')
                    || sessionStorage.getItem(SORT_KEY);
    if (currentOrder && currentOrder !== 'menu_order') {
        document.querySelectorAll('.oz-cat-nav__link').forEach(function(link) {
            var url = new URL(link.href, window.location.origin);
            url.searchParams.set('orderby', currentOrder);
            link.href = url.toString();
        });
    }
})();
</script>

<?php get_footer(); ?>
