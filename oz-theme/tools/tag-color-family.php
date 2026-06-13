<?php
/**
 * One-off (idempotent) bulk-tagger that:
 *   1. Creates the 9 oz_kleurfamilie terms if absent.
 *   2. Looks at every product's existing pa_kleur* attribute terms, decides
 *      which family they belong to via a name-pattern map, and attaches the
 *      matching oz_kleurfamilie terms to the product.
 *
 * Safe to re-run: wp_set_object_terms() with append=true means we never
 * remove existing tags. To do a full reset, run with --reset.
 *
 * Run with WP-CLI: sudo -u bcwstaging wp eval-file tools/tag-color-family.php --path=...
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ──────────────────────────────────────────────────────────────
// 1. Create the 9 oz_kleurfamilie terms (idempotent).
// ──────────────────────────────────────────────────────────────
$families = [
	'wit'   => 'Wit',
	'beige' => 'Beige',
	'grijs' => 'Grijs',
	'zwart' => 'Zwart',
	'bruin' => 'Bruin',
	'blauw' => 'Blauw',
	'groen' => 'Groen',
	'roze'  => 'Roze',
	'geel'  => 'Geel',
];
foreach ( $families as $slug => $name ) {
	if ( ! term_exists( $slug, 'oz_kleurfamilie' ) ) {
		wp_insert_term( $name, 'oz_kleurfamilie', [ 'slug' => $slug ] );
		WP_CLI::log( "  + created family: $name" );
	}
}

// ──────────────────────────────────────────────────────────────
// 2. Family detection: explicit overrides first, then keyword fallback.
//    Each color name is matched in priority order; first match wins.
// ──────────────────────────────────────────────────────────────
$overrides = [
	// Force-mapped colors that the keyword fallback would misclassify.
	'geen'              => null,        // "none" sentinel — skip
	'atmos'             => 'beige',
	'belbird'           => 'beige',
	'bellbird'          => 'beige',
	'bricks'            => 'bruin',
	'brilliant'         => 'wit',
	'champagne'         => 'beige',
	'cloudy'            => 'grijs',
	'coconut grove'     => 'bruin',
	'dark night'        => 'zwart',
	'dark shades'       => 'grijs',
	'duna'              => 'beige',
	'egypt'             => 'beige',
	'gloria'            => 'beige',
	'griseo'            => 'grijs',
	'hippo'             => 'grijs',
	'island stone'      => 'grijs',
	'mermaid'           => 'blauw',
	'new york'          => 'grijs',
	'octo'              => 'grijs',
	'oro'               => 'geel',
	'pale'              => 'beige',
	'pandora'           => 'beige',
	'pearl white'       => 'wit',
	'pole'              => 'beige',
	'pure'              => 'wit',
	'ribbon'            => 'beige',
	'royal flush'       => 'beige',
	'shades'            => 'grijs',
	'silk'              => 'wit',
	'silver lining'     => 'grijs',
	'stonehenge'        => 'grijs',
	'platinum'          => 'grijs',
	'silver'            => 'grijs',
	// Lavasteen color names
	'agave'             => 'groen',
	'anise'             => 'groen',
	'aquamarine'        => 'blauw',
	'ash'               => 'grijs',
	'cream peony'       => 'roze',
	'fennel'            => 'groen',
	'graphite'          => 'grijs',
	'hazel'             => 'bruin',
	'hillflower'        => 'groen',
	'mellise'           => 'beige',
	'morning dew'       => 'wit',
	'mushroom'          => 'bruin',
	'portobello'        => 'bruin',
	'reindeer moss'     => 'groen',
	'seakale'           => 'groen',
	'shiitaki'          => 'bruin',
	'sterling'          => 'grijs',
	'wool'              => 'beige',
	'seashell'          => 'wit',
	'linnen'            => 'beige',
];

// Keyword patterns in priority order. The first one to match wins.
$patterns = [
	'zwart' => '/black|zwart|pepper|smoke|jack(\s|$)/i',
	'wit'   => '/white|wit|brilliant|porcelain|silk|stone\s*white|china\s*clay/i',
	'blauw' => '/blue|blauw|azul|sky|mermaid|navy|royal\s*blue|teal\s*blue/i',
	'groen' => '/green|groen|olive|basil|sage|emerald|mud|pistache|shadow\s*green|hunter|bit\s*of\s*green|camouflage|ground\s*cover/i',
	'roze'  => '/rose|roze|rosé|pink|peach|lavendel|nude|dusty\s*rose|ashes\s*of\s*rose/i',
	'geel'  => '/yellow|geel|gold|goldzand|havanna|sunday|champagne|oro/i',
	'bruin' => '/brown|bruin|copper|brick|hippo|camel|nutmeg|coconut|deep\s*earth|sahara|dark\s*night|earth\s*stone/i',
	'grijs' => '/grey|gray|grijs|silver|platinum|urban|storm|silky|pebble|cloudy|shades|stonehenge|teal\s*grey|france\s*grey|tin\s*grey|army\s*grey|simply\s*grey|smooth\s*grey|stone\s*grey|island\s*stone|black\s*pearl|elephant|griseo/i',
	'beige' => '/beige|taupe|nude|skin|powder|almond|antique|sand|stone|clay|dusty|dust|romance|linnen|peachblossem|atmos|duna|ribbon|pale|canyon|pandora|new\s*york|shabby|island|sahara|earth|champagne|gold/i',
];

/**
 * Decide the family for a single color term name. Returns slug or null.
 */
function oz_detect_family( $name, $overrides, $patterns ) {
	// Strip leading "1234 - " prefix from numbered colors so the keywords match
	$clean = preg_replace( '/^\d{3,4}\s*-\s*/', '', $name );
	$clean = trim( strtolower( $clean ) );
	if ( $clean === '' ) {
		return null;
	}
	// Explicit override?
	if ( array_key_exists( $clean, $overrides ) ) {
		return $overrides[ $clean ]; // null = skip
	}
	// Keyword pattern match (priority order)
	foreach ( $patterns as $family_slug => $regex ) {
		if ( preg_match( $regex, $clean ) ) {
			return $family_slug;
		}
	}
	return null;
}

// ──────────────────────────────────────────────────────────────
// 3. Loop every product and tag it based on its color attributes.
// ──────────────────────────────────────────────────────────────
$color_taxonomies = [
	'pa_kleur',
	'pa_beton-cire-kleur',
	'pa_easyline-kleur',
	'pa_velvet-kleur',
	'pa_standaard-kleuren',
];

$products = get_posts( [
	'post_type'      => 'product',
	'post_status'    => [ 'publish', 'private', 'draft' ],
	'posts_per_page' => -1,
	'fields'         => 'ids',
] );

// Wipe existing oz_kleurfamilie tags up-front so that products our detector
// no longer matches end up correctly untagged. (Without this, leftovers from
// a prior, looser run linger.)
foreach ( $products as $pid ) {
	wp_set_object_terms( $pid, [], 'oz_kleurfamilie', false );
}

$tagged   = 0;
$untagged = 0;
$family_counts = array_fill_keys( array_keys( $families ), 0 );

foreach ( $products as $pid ) {
	$detected = [];
	// Signal 1: pa_kleur* term_relationships (the few products that have them).
	foreach ( $color_taxonomies as $tax ) {
		$terms = wp_get_object_terms( $pid, $tax, [ 'fields' => 'names' ] );
		if ( is_wp_error( $terms ) ) { continue; }
		foreach ( $terms as $term_name ) {
			$fam = oz_detect_family( $term_name, $overrides, $patterns );
			if ( $fam ) {
				$detected[ $fam ] = true;
			}
		}
	}
	// Signal 2: product title — most colored products have the color in the
	// post_title (e.g. "Lavasteen gietvloer Cream Peony 5m2", "Beton Ciré
	// Original Stone White 1000"). Run the same family detector on it.
	$title = get_the_title( $pid );
	if ( $title ) {
		// Strip generic product-line / pack words so they don't drown out the color name.
		$cleaned = preg_replace( '/\b(lavasteen|beton\s*cir[eé]|microcement|metallic|velvet|easyline|all-?in-?one|original|kant\s*en\s*klaar|pakket|5m2|5\s*m²|gietvloer|en\s*wand|wand|vloer|coating)\b/i', ' ', $title );
		$cleaned = trim( preg_replace( '/\s+/', ' ', $cleaned ) );
		if ( $cleaned !== '' ) {
			$fam = oz_detect_family( $cleaned, $overrides, $patterns );
			if ( $fam ) {
				$detected[ $fam ] = true;
			}
		}
	}
	if ( empty( $detected ) ) {
		$untagged++;
		continue;
	}
	$family_slugs = array_keys( $detected );
	wp_set_object_terms( $pid, $family_slugs, 'oz_kleurfamilie', false );
	$tagged++;
	foreach ( $family_slugs as $s ) {
		$family_counts[ $s ]++;
	}
}

WP_CLI::log( "" );
WP_CLI::log( "─── Result ───" );
WP_CLI::log( "Products tagged:   $tagged" );
WP_CLI::log( "Products skipped:  $untagged (no color attributes)" );
WP_CLI::log( "" );
WP_CLI::log( "Per family:" );
foreach ( $family_counts as $slug => $cnt ) {
	WP_CLI::log( sprintf( "  %-7s %d products", $slug, $cnt ) );
}
