<?php
/**
 * Shop filter: faceted-search sidebar for /producten/ and /product-categorie/*.
 *
 * Replaces the curated category-tree sidebar with a real filter: productlijn
 * (curated product_cat checkboxes), kleurfamilie (oz_kleurfamilie taxonomy
 * swatches), prijs (range), and zoeken. Reads URL params and mutates the
 * WC main query via pre_get_posts. Active filters render as chips above the
 * results grid; clicking a chip removes that filter.
 *
 * Kleurfamilie data is bulk-tagged on every product by tools/tag-color-family.php
 * based on the product's existing pa_kleur* attribute terms.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the oz_kleurfamilie taxonomy on init. Not a pa_* WC attribute
 * because we drive it from existing pa_kleur* terms, not from WC's attribute
 * UI. Public + queryable so the filter can use it in tax_query.
 */
add_action( 'init', function () {
	register_taxonomy(
		'oz_kleurfamilie',
		[ 'product' ],
		[
			'label'             => 'Kleurfamilie',
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'rewrite'           => [ 'slug' => 'kleurfamilie' ],
			'query_var'         => true,
		]
	);
}, 5 );

/**
 * Enqueue filter CSS + JS only on shop / product category pages, after the
 * design system CSS so we can override its base styles cleanly.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! function_exists( 'is_shop' ) ) {
		return;
	}
	if ( ! ( is_shop() || is_product_taxonomy() ) ) {
		return;
	}
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	wp_enqueue_style(
		'oz-shop-filter',
		$uri . '/css/shop-filter.css',
		[ 'oz-blocks' ],
		filemtime( $dir . '/css/shop-filter.css' )
	);
	wp_enqueue_script(
		'oz-shop-filter',
		$uri . '/js/shop-filter.js',
		[],
		filemtime( $dir . '/js/shop-filter.js' ),
		true
	);
}, 30 );

/**
 * Curated productlijn list. We bypass product_cat directly because the WC
 * taxonomy is polluted with legacy slugs (Geen categorie, Microcement-2,
 * Kleurenpakket, etc). Each entry maps a filter slug to one or more
 * product_cat slugs that should be included.
 *
 * @return array<string, array{label: string, cats: string[]}>
 */
function oz_shop_filter_productlijnen() {
	return [
		'beton-cire'      => [ 'label' => 'Beton Ciré',      'cats' => [ 'beton-cire', 'all-in-one', 'easyline', 'original' ] ],
		'lavasteen'       => [ 'label' => 'Lavasteen',        'cats' => [ 'lavasteen-gietvloer' ] ],
		'microcement'     => [ 'label' => 'Microcement',      'cats' => [ 'microcement' ] ],
		'metallic-velvet' => [ 'label' => 'Metallic Velvet',  'cats' => [ 'metallic-velvet' ] ],
		'kant-en-klaar'   => [ 'label' => 'Kant & Klaar',     'cats' => [ 'kant-en-klaar' ] ],
		'gereedschap'     => [ 'label' => 'Gereedschap',      'cats' => [ 'gereedschap' ] ],
		'materialen'      => [ 'label' => 'Losse materialen', 'cats' => [ 'losse-materialen', 'pu-color', 'stuco-paste' ] ],
	];
}

/**
 * 9 color families with swatch hex codes. Hex is a representative tone, not
 * the literal product color, used only as a visual anchor in the filter UI.
 *
 * @return array<string, array{label: string, hex: string}>
 */
function oz_shop_filter_kleurfamilies() {
	return [
		'wit'   => [ 'label' => 'Wit',   'hex' => '#F4F1EA' ],
		'beige' => [ 'label' => 'Beige', 'hex' => '#D6C5A8' ],
		'grijs' => [ 'label' => 'Grijs', 'hex' => '#9A9A95' ],
		'zwart' => [ 'label' => 'Zwart', 'hex' => '#2A2A2A' ],
		'bruin' => [ 'label' => 'Bruin', 'hex' => '#8A6A4A' ],
		'blauw' => [ 'label' => 'Blauw', 'hex' => '#5C7B8A' ],
		'groen' => [ 'label' => 'Groen', 'hex' => '#7B8A5C' ],
		'roze'  => [ 'label' => 'Roze',  'hex' => '#D8AFA8' ],
		'geel'  => [ 'label' => 'Geel',  'hex' => '#D8C078' ],
	];
}

/**
 * Read the filter state from $_GET. Each param is a comma-separated slug list
 * except prijs which is "min-max".
 *
 * @return array{lijn: string[], kleur: string[], prijs_min: ?float, prijs_max: ?float, q: string}
 */
function oz_shop_filter_state() {
	static $cached = null;
	if ( $cached !== null ) {
		return $cached;
	}
	$slugs = function ( $key ) {
		$raw = isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : '';
		if ( ! is_string( $raw ) || $raw === '' ) {
			return [];
		}
		$parts = array_filter( array_map( 'sanitize_title', explode( ',', $raw ) ) );
		return array_values( array_unique( $parts ) );
	};
	$prijs_min = null;
	$prijs_max = null;
	if ( isset( $_GET['prijs'] ) && is_string( $_GET['prijs'] ) && preg_match( '/^(\d+)-(\d+)$/', $_GET['prijs'], $m ) ) {
		$prijs_min = (float) $m[1];
		$prijs_max = (float) $m[2];
	}
	$q = isset( $_GET['q'] ) && is_string( $_GET['q'] ) ? wp_unslash( $_GET['q'] ) : '';
	$q = sanitize_text_field( $q );

	$cached = [
		'lijn'      => $slugs( 'lijn' ),
		'kleur'     => $slugs( 'kleur' ),
		'prijs_min' => $prijs_min,
		'prijs_max' => $prijs_max,
		'q'         => $q,
	];
	return $cached;
}

/**
 * Detect whether the current request is a shop / category archive that
 * should run through our filter. We deliberately exclude single-product
 * pages and admin requests.
 */
function oz_shop_filter_is_shop_context() {
	if ( is_admin() || wp_doing_ajax() ) {
		return false;
	}
	return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
}

/**
 * Inject the filter state into the WC main query. Productlijn maps to a
 * tax_query on product_cat. Kleurfamilie maps to oz_kleurfamilie. Prijs and
 * search are handled with meta_query + s.
 */
add_action( 'pre_get_posts', function ( $query ) {
	if ( ! $query->is_main_query() || ! oz_shop_filter_is_shop_context() ) {
		return;
	}
	$state = oz_shop_filter_state();
	$tax_query  = (array) $query->get( 'tax_query' );
	$meta_query = (array) $query->get( 'meta_query' );

	if ( ! empty( $state['lijn'] ) ) {
		$lijnen = oz_shop_filter_productlijnen();
		$cats   = [];
		foreach ( $state['lijn'] as $slug ) {
			if ( isset( $lijnen[ $slug ] ) ) {
				$cats = array_merge( $cats, $lijnen[ $slug ]['cats'] );
			}
		}
		$cats = array_values( array_unique( $cats ) );
		if ( $cats ) {
			$tax_query[] = [
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $cats,
				'operator' => 'IN',
			];
		}
	}
	if ( ! empty( $state['kleur'] ) ) {
		$tax_query[] = [
			'taxonomy' => 'oz_kleurfamilie',
			'field'    => 'slug',
			'terms'    => $state['kleur'],
			'operator' => 'IN',
		];
	}
	if ( $state['prijs_min'] !== null && $state['prijs_max'] !== null ) {
		$meta_query[] = [
			'key'     => '_price',
			'value'   => [ $state['prijs_min'], $state['prijs_max'] ],
			'compare' => 'BETWEEN',
			'type'    => 'NUMERIC',
		];
	}
	if ( $state['q'] !== '' ) {
		$query->set( 's', $state['q'] );
	}
	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}
	if ( $tax_query ) {
		$query->set( 'tax_query', $tax_query );
	}
	if ( $meta_query ) {
		$query->set( 'meta_query', $meta_query );
	}
} );

/**
 * Build a shop URL with the given state applied. Used for both the form
 * action and for chip-removal links.
 */
function oz_shop_filter_url( array $overrides = [] ) {
	$state = array_merge( oz_shop_filter_state(), $overrides );
	$base  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/producten/' );
	// Preserve product_cat archive URLs when on one.
	if ( is_product_category() ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) {
			$base = get_term_link( $term );
		}
	}
	$qs = [];
	if ( ! empty( $state['lijn'] ) )    { $qs['lijn']  = implode( ',', $state['lijn'] ); }
	if ( ! empty( $state['kleur'] ) )   { $qs['kleur'] = implode( ',', $state['kleur'] ); }
	if ( $state['prijs_min'] !== null && $state['prijs_max'] !== null ) {
		$qs['prijs'] = (int) $state['prijs_min'] . '-' . (int) $state['prijs_max'];
	}
	if ( ! empty( $state['q'] ) )       { $qs['q']     = $state['q']; }
	return $qs ? ( $base . '?' . http_build_query( $qs ) ) : $base;
}

/**
 * Render the filter sidebar. Replaces the old curated-menu render in
 * archive-product.php.
 */
function oz_shop_filter_render_sidebar() {
	$state    = oz_shop_filter_state();
	$lijnen   = oz_shop_filter_productlijnen();
	$families = oz_shop_filter_kleurfamilies();
	?>
	<form class="oz-shop-filter" method="get" action="<?php echo esc_url( oz_shop_filter_url( [ 'lijn' => [], 'kleur' => [], 'prijs_min' => null, 'prijs_max' => null, 'q' => '' ] ) ); ?>" data-oz-shop-filter>
		<div class="oz-shop-filter__head">
			<div class="oz-shop-filter__title" role="heading" aria-level="2">Filters</div>
			<button class="oz-shop-filter__close" id="filter-close" type="button" aria-label="Filters sluiten">&times;</button>
		</div>

		<label class="oz-shop-filter__search">
			<span class="oz-shop-filter__group-title">Zoeken</span>
			<input type="search" name="q" value="<?php echo esc_attr( $state['q'] ); ?>" placeholder="Vind een kleur of product" autocomplete="off">
		</label>

		<fieldset class="oz-shop-filter__group">
			<legend class="oz-shop-filter__group-title">Productlijn</legend>
			<?php foreach ( $lijnen as $slug => $row ) :
				$checked = in_array( $slug, $state['lijn'], true );
				?>
				<label class="oz-shop-filter__row<?php echo $checked ? ' is-checked' : ''; ?>">
					<input type="checkbox" name="lijn[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $checked ); ?>>
					<span class="oz-shop-filter__row-label"><?php echo esc_html( $row['label'] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<fieldset class="oz-shop-filter__group">
			<legend class="oz-shop-filter__group-title">Kleurfamilie</legend>
			<div class="oz-shop-filter__swatches">
				<?php foreach ( $families as $slug => $row ) :
					$checked = in_array( $slug, $state['kleur'], true );
					?>
					<label class="oz-shop-filter__swatch<?php echo $checked ? ' is-checked' : ''; ?>" title="<?php echo esc_attr( $row['label'] ); ?>">
						<input type="checkbox" name="kleur[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $checked ); ?>>
						<span class="oz-shop-filter__swatch-dot" style="background:<?php echo esc_attr( $row['hex'] ); ?>"></span>
						<span class="oz-shop-filter__swatch-label"><?php echo esc_html( $row['label'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<fieldset class="oz-shop-filter__group">
			<legend class="oz-shop-filter__group-title">Prijs</legend>
			<div class="oz-shop-filter__price">
				<label>
					<span>Van</span>
					<input type="number" name="prijs_min" min="0" max="400" step="5" value="<?php echo $state['prijs_min'] !== null ? (int) $state['prijs_min'] : ''; ?>" placeholder="0">
				</label>
				<label>
					<span>Tot</span>
					<input type="number" name="prijs_max" min="0" max="400" step="5" value="<?php echo $state['prijs_max'] !== null ? (int) $state['prijs_max'] : ''; ?>" placeholder="400">
				</label>
			</div>
		</fieldset>

		<noscript>
			<button class="oz-shop-filter__apply oz-btn oz-btn--primary oz-btn--sm" type="submit">Filteren</button>
		</noscript>

		<div class="oz-shop-filter__bottom">
			<a class="oz-shop-filter__reset" href="<?php echo esc_url( oz_shop_filter_url( [ 'lijn' => [], 'kleur' => [], 'prijs_min' => null, 'prijs_max' => null, 'q' => '' ] ) ); ?>">Reset filters</a>
		</div>
	</form>
	<?php
}

/**
 * Render the active-filter chips strip above the product grid. Each chip is
 * a link that, when clicked, navigates to the same page with that one filter
 * dimension removed.
 */
function oz_shop_filter_render_chips() {
	$state = oz_shop_filter_state();
	$has_any = ! empty( $state['lijn'] ) || ! empty( $state['kleur'] ) || $state['prijs_min'] !== null || ! empty( $state['q'] );
	if ( ! $has_any ) {
		return;
	}
	$lijnen   = oz_shop_filter_productlijnen();
	$families = oz_shop_filter_kleurfamilies();
	echo '<div class="oz-shop-chips" aria-label="Actieve filters">';
	foreach ( $state['lijn'] as $slug ) {
		if ( ! isset( $lijnen[ $slug ] ) ) { continue; }
		$new = array_values( array_diff( $state['lijn'], [ $slug ] ) );
		printf(
			'<a class="oz-shop-chip" href="%s">%s <span aria-hidden="true">&times;</span><span class="screen-reader-text">verwijder filter</span></a>',
			esc_url( oz_shop_filter_url( [ 'lijn' => $new ] ) ),
			esc_html( $lijnen[ $slug ]['label'] )
		);
	}
	foreach ( $state['kleur'] as $slug ) {
		if ( ! isset( $families[ $slug ] ) ) { continue; }
		$new = array_values( array_diff( $state['kleur'], [ $slug ] ) );
		printf(
			'<a class="oz-shop-chip" href="%s"><span class="oz-shop-chip-dot" style="background:%s"></span>%s <span aria-hidden="true">&times;</span></a>',
			esc_url( oz_shop_filter_url( [ 'kleur' => $new ] ) ),
			esc_attr( $families[ $slug ]['hex'] ),
			esc_html( $families[ $slug ]['label'] )
		);
	}
	if ( $state['prijs_min'] !== null && $state['prijs_max'] !== null ) {
		printf(
			'<a class="oz-shop-chip" href="%s">€%d – €%d <span aria-hidden="true">&times;</span></a>',
			esc_url( oz_shop_filter_url( [ 'prijs_min' => null, 'prijs_max' => null ] ) ),
			(int) $state['prijs_min'],
			(int) $state['prijs_max']
		);
	}
	if ( $state['q'] !== '' ) {
		printf(
			'<a class="oz-shop-chip" href="%s">"%s" <span aria-hidden="true">&times;</span></a>',
			esc_url( oz_shop_filter_url( [ 'q' => '' ] ) ),
			esc_html( $state['q'] )
		);
	}
	printf(
		'<a class="oz-shop-chip oz-shop-chip--reset" href="%s">Reset alles</a>',
		esc_url( oz_shop_filter_url( [ 'lijn' => [], 'kleur' => [], 'prijs_min' => null, 'prijs_max' => null, 'q' => '' ] ) )
	);
	echo '</div>';
}
