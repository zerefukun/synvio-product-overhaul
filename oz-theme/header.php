<?php
/**
 * Custom header — replaces Flatsome's header.php entirely.
 * Gulcanhome-inspired: hamburger + search | logo | account + cart.
 * Includes menu drawer + search drawer markup.
 *
 * @package OzTheme
 */

$is_home    = is_front_page();
$logo_id    = get_theme_mod( 'site_logo' ) ?: get_theme_mod( 'custom_logo' );
$logo_url   = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
$site_name  = get_bloginfo( 'name' );
$cart_count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;

/* Drawer banner (Customizer: Appearance > Customize > Menu Drawer Banner) */
$banner_img   = get_theme_mod( 'oz_drawer_banner_image', '' );
$banner_line1 = get_theme_mod( 'oz_drawer_banner_line1', 'Beton Cire Webshop' );
$banner_line2 = get_theme_mod( 'oz_drawer_banner_line2', 'Voor elke ruimte' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="wrapper">

<?php /* ================================================================
       HEADER BAR
       On homepage: transparent overlay mode (--overlay). Elsewhere: solid.
       ================================================================ */ ?>
<header class="oz-header<?php echo $is_home ? ' oz-header--overlay' : ''; ?>" id="oz-header">
	<div class="oz-header__inner">

		<!-- Left: hamburger + search -->
		<div class="oz-header__left">
			<button type="button" class="oz-header__icon" id="oz-menu-trigger" aria-label="Menu openen" aria-expanded="false" aria-controls="oz-menu-drawer">
				<svg class="oz-header__svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
				<svg class="oz-header__svg oz-header__svg--close" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>
			<button type="button" class="oz-header__icon" id="oz-search-trigger" aria-label="Zoeken">
				<svg class="oz-header__svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
			</button>
		</div>

		<!-- Center: logo -->
		<div class="oz-header__center">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="oz-header__logo" aria-label="<?php echo esc_attr( $site_name ); ?>">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="150" height="auto" loading="eager">
				<?php else : ?>
					<span class="oz-header__logo-text"><?php echo esc_html( $site_name ); ?></span>
				<?php endif; ?>
			</a>
		</div>

		<!-- Right: account (desktop) + cart -->
		<div class="oz-header__right">
			<a href="<?php echo esc_url( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); ?>" class="oz-header__icon oz-header__desktop-only" aria-label="Mijn account">
				<svg class="oz-header__svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
			</a>
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="oz-header__icon" id="oz-cart-icon" aria-label="Winkelwagen">
				<svg class="oz-header__svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
				<?php if ( $cart_count > 0 ) : ?>
					<span class="oz-header__badge" id="oz-cart-count"><?php echo esc_html( $cart_count ); ?></span>
				<?php else : ?>
					<span class="oz-header__badge" id="oz-cart-count" style="display:none">0</span>
				<?php endif; ?>
			</a>
		</div>

	</div>
</header>

<?php /* ================================================================
       MENU DRAWER — slides from left, max-width 400px
       ================================================================ */ ?>
<div class="oz-menu-drawer" id="oz-menu-drawer" aria-hidden="true">
	<div class="oz-menu-drawer__overlay" id="oz-menu-overlay"></div>
	<div class="oz-menu-drawer__panel">

		<!-- Drawer header: close + logo + account -->
		<div class="oz-menu-drawer__header">
			<button type="button" class="oz-menu-drawer__close" id="oz-menu-close" aria-label="Menu sluiten">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>
			<?php if ( $logo_url ) : ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="oz-menu-drawer__logo">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="120" height="auto" loading="lazy">
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); ?>" class="oz-menu-drawer__account" aria-label="Mijn account">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
			</a>
		</div>

		<!-- Scrollable menu content -->
		<div class="oz-menu-drawer__content" id="oz-menu-content">

			<?php /* Banner image with overlay text (configurable in Customizer) */ ?>
			<?php if ( $banner_img ) : ?>
			<div class="oz-menu-drawer__banner">
				<img src="<?php echo esc_url( $banner_img ); ?>" alt="" loading="lazy">
				<div class="oz-menu-drawer__banner-text">
					<?php if ( $banner_line1 ) : ?>
						<span class="oz-menu-drawer__banner-line1"><?php echo esc_html( $banner_line1 ); ?></span>
					<?php endif; ?>
					<?php if ( $banner_line2 ) : ?>
						<span class="oz-menu-drawer__banner-line2"><?php echo esc_html( $banner_line2 ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<ul class="oz-menu-drawer__categories" id="oz-menu-main">
				<?php
				$menu_items = wp_get_nav_menu_items( 'header-menu' );
				if ( $menu_items ) :
					/* Build parent → children map */
					$children_map = [];
					$top_items    = [];
					foreach ( $menu_items as $item ) {
						if ( (int) $item->menu_item_parent === 0 ) {
							$top_items[] = $item;
						} else {
							$children_map[ $item->menu_item_parent ][] = $item;
						}
					}

					$submenu_index = 0;
					foreach ( $top_items as $item ) :
						$has_children = ! empty( $children_map[ $item->ID ] );

						/* Get image: try page featured image, then term thumbnail */
						$img_url = '';
						if ( $item->object === 'page' || $item->object === 'post' ) {
							$img_url = get_the_post_thumbnail_url( (int) $item->object_id, 'thumbnail' );
						} elseif ( in_array( $item->object, [ 'product_cat', 'category' ], true ) ) {
							$thumb_id = get_term_meta( (int) $item->object_id, 'thumbnail_id', true );
							if ( $thumb_id ) {
								$img_url = wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
							}
						}
						/* Fallback: first letter circle handled by CSS */
				?>
				<li class="oz-menu-drawer__category">
					<?php if ( $has_children ) :
						$submenu_index++;
					?>
						<button type="button" class="oz-menu-drawer__category-link oz-menu-has-children" data-submenu="<?php echo $submenu_index; ?>" aria-expanded="false">
							<div class="oz-menu-drawer__category-icon">
								<?php if ( $img_url ) : ?>
									<img src="<?php echo esc_url( $img_url ); ?>" alt="" width="50" height="50" loading="lazy">
								<?php else : ?>
									<span class="oz-menu-drawer__category-letter"><?php echo esc_html( mb_substr( $item->title, 0, 1 ) ); ?></span>
								<?php endif; ?>
							</div>
							<span class="oz-menu-drawer__category-title"><?php echo esc_html( $item->title ); ?></span>
							<span class="oz-menu-drawer__category-arrow">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
							</span>
						</button>
					<?php else : ?>
						<a href="<?php echo esc_url( $item->url ); ?>" class="oz-menu-drawer__category-link">
							<div class="oz-menu-drawer__category-icon">
								<?php if ( $img_url ) : ?>
									<img src="<?php echo esc_url( $img_url ); ?>" alt="" width="50" height="50" loading="lazy">
								<?php else : ?>
									<span class="oz-menu-drawer__category-letter"><?php echo esc_html( mb_substr( $item->title, 0, 1 ) ); ?></span>
								<?php endif; ?>
							</div>
							<span class="oz-menu-drawer__category-title"><?php echo esc_html( $item->title ); ?></span>
						</a>
					<?php endif; ?>
				</li>
				<?php endforeach; endif; ?>
			</ul>

			<?php /* Footer links (WP menu: Drawer Footer Links) */ ?>
			<?php if ( has_nav_menu( 'oz-drawer-footer' ) ) : ?>
			<nav class="oz-menu-drawer__footer" aria-label="Extra links">
				<?php wp_nav_menu([
					'theme_location' => 'oz-drawer-footer',
					'container'      => false,
					'menu_class'     => 'oz-menu-drawer__footer-list',
					'depth'          => 1,
					'fallback_cb'    => false,
				]); ?>
			</nav>
			<?php endif; ?>

		</div>

		<?php
		/* Submenu drill-down panels — cover the full __panel.
		   Row 1 of back-header is a spacer matching main header height
		   (the floating X button lives there). Row 2 is the actual back button. */
		if ( ! empty( $top_items ) ) :
			$submenu_index = 0;
			foreach ( $top_items as $item ) :
				if ( empty( $children_map[ $item->ID ] ) ) continue;
				$submenu_index++;
		?>
		<div class="oz-menu-drawer__subcategory" data-submenu-panel="<?php echo $submenu_index; ?>">
			<div class="oz-menu-drawer__back-header">
				<button type="button" class="oz-menu-drawer__back-button" data-back-button>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
					<span class="oz-menu-drawer__back-title"><?php echo esc_html( $item->title ); ?></span>
				</button>
			</div>
			<ul class="oz-menu-drawer__subcategory-list">
				<!-- "View all" link -->
				<li class="oz-menu-drawer__subcategory-item">
					<a href="<?php echo esc_url( $item->url ); ?>" class="oz-menu-drawer__subcategory-link">
						<span>Alles bekijken</span>
					</a>
				</li>
				<?php foreach ( $children_map[ $item->ID ] as $child ) : ?>
				<li class="oz-menu-drawer__subcategory-item">
					<a href="<?php echo esc_url( $child->url ); ?>" class="oz-menu-drawer__subcategory-link">
						<span><?php echo esc_html( $child->title ); ?></span>
						<?php if ( ! empty( $children_map[ $child->ID ] ) ) : ?>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
						<?php endif; ?>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endforeach; endif; ?>

	</div>
</div>

<?php /* ================================================================
       SEARCH DRAWER — slides from left, max-width 480px desktop
       ================================================================ */ ?>
<div class="oz-search-drawer" id="oz-search-drawer" aria-hidden="true" role="dialog">
	<div class="oz-search-drawer__overlay" id="oz-search-overlay"></div>
	<div class="oz-search-drawer__panel" id="oz-search-panel">

		<!-- Search header: back + input -->
		<div class="oz-search-drawer__header">
			<button type="button" class="oz-search-drawer__close" id="oz-search-close" aria-label="Zoeken sluiten">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
			</button>
			<form action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" class="oz-search-drawer__form" role="search">
				<div class="oz-search-drawer__input-wrapper">
					<svg class="oz-search-drawer__search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
					<input type="search" name="s" class="oz-search-drawer__input" id="oz-search-input" placeholder="Waar ben je naar op zoek?" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
					<input type="hidden" name="post_type" value="product">
					<button type="button" class="oz-search-drawer__clear" id="oz-search-clear" style="display:none" aria-label="Wissen">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</button>
				</div>
			</form>
		</div>

		<!-- Scrollable content -->
		<div class="oz-search-drawer__content" id="oz-search-content">

			<!-- Recent searches (JS populated) -->
			<div class="oz-search-drawer__section oz-search-drawer__recent" id="oz-search-recent" style="display:none">
				<div class="oz-search-drawer__section-header">
					<h3 class="oz-search-drawer__section-title">Recente zoekopdrachten</h3>
					<button type="button" class="oz-search-drawer__clear-all" id="oz-search-clear-recent">Wissen</button>
				</div>
				<ul class="oz-search-drawer__recent-list" id="oz-search-recent-list"></ul>
			</div>

			<!-- Quick categories -->
			<div class="oz-search-drawer__section oz-search-drawer__categories">
				<h3 class="oz-search-drawer__section-title">Categorieën</h3>
				<ul class="oz-search-drawer__categories-list">
					<li><a href="/ruimtes/" class="oz-search-drawer__category-link">Ruimtes</a></li>
					<li><a href="/producten/" class="oz-search-drawer__category-link">Producten</a></li>
					<li><a href="/kleuren/" class="oz-search-drawer__category-link">Kleuren</a></li>
					<li><a href="/kleurstalen/" class="oz-search-drawer__category-link">Kleurstalen</a></li>
					<li><a href="/inspiratie/" class="oz-search-drawer__category-link">Inspiratie</a></li>
					<li><a href="/kennisbank/" class="oz-search-drawer__category-link">Kennisbank</a></li>
				</ul>
			</div>

			<!-- Search results (JS populated) -->
			<div class="oz-search-drawer__section oz-search-drawer__results" id="oz-search-results" style="display:none">
				<h3 class="oz-search-drawer__section-title">Top resultaten</h3>
				<div class="oz-search-drawer__results-grid" id="oz-search-results-grid"></div>
				<a href="#" class="oz-search-drawer__view-all" id="oz-search-view-all">
					Bekijk alle resultaten
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
				</a>
			</div>

			<!-- No results (JS populated) -->
			<div class="oz-search-drawer__section oz-search-drawer__no-results" id="oz-search-no-results" style="display:none">
				<p class="oz-search-drawer__no-results-text">Geen resultaten voor "<span id="oz-search-query-text"></span>"</p>
				<p class="oz-search-drawer__no-results-hint">Probeer een andere zoekterm.</p>
			</div>

			<!-- Loading -->
			<div class="oz-search-drawer__section oz-search-drawer__loading" id="oz-search-loading" style="display:none">
				<div class="oz-search-drawer__spinner"></div>
				<p>Zoeken...</p>
			</div>

			<!-- Placeholder: popular kleurstalen pakketten (future) -->
			<div class="oz-search-drawer__section">
				<h3 class="oz-search-drawer__section-title">Populaire kleurstalen pakketten</h3>
				<p class="oz-search-drawer__placeholder-text">Binnenkort beschikbaar</p>
			</div>

		</div>
	</div>
</div>

<main id="main">
