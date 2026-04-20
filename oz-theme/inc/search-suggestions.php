<?php
/**
 * Search suggestions — "Bedoelde je misschien …" typo correction.
 *
 * Builds a cached index of searchable terms (product titles, category names,
 * category slugs) and uses PHP's built-in levenshtein() to suggest close
 * matches when a user's query has typos or no results.
 *
 * Public API:
 *   - oz_get_search_suggestions( string $query, int $limit = 3 ) : array
 *   - oz_render_search_suggestions( ?string $query = null ) : void
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Transient key. Bump the suffix to force a rebuild.
 */
const OZ_SEARCH_INDEX_TRANSIENT = 'oz_search_term_index_v1';

/**
 * Max characters we'll Levenshtein — PHP's built-in caps at 255, and long
 * inputs aren't typos anyway.
 */
const OZ_SEARCH_MAX_TERM_LEN = 80;

/**
 * Build the term index. One token per unique word pulled from product
 * titles and product category names/slugs. Dutch stopwords stripped so
 * "voor" / "van" / "met" don't drown out real matches.
 *
 * Returns an array<int, string> of lowercase terms.
 */
function oz_build_search_term_index() : array {
	global $wpdb;

	// Product titles — only published products.
	$titles = $wpdb->get_col(
		"SELECT post_title FROM {$wpdb->posts}
		 WHERE post_type = 'product' AND post_status = 'publish' LIMIT 2000"
	);

	// Category names + slugs.
	$cats = get_terms(
		array(
			'taxonomy'   => array( 'product_cat', 'category' ),
			'hide_empty' => false,
			'fields'     => 'all',
			'number'     => 500,
		)
	);
	$cat_strings = array();
	if ( is_array( $cats ) ) {
		foreach ( $cats as $t ) {
			$cat_strings[] = $t->name;
			$cat_strings[] = str_replace( '-', ' ', $t->slug );
		}
	}

	$pool = array_merge( (array) $titles, $cat_strings );

	// Stopwords that should never surface as suggestions.
	$stopwords = array(
		'de','het','een','en','of','van','voor','met','op','in','aan','bij','te','door','als','dat','die','is','zijn','was','waren','ook','per','om','uit','naar','tot','over','tegen','dan','ook','niet','wel','ja','nee','er','nog','al','maar','zo',
	);
	$stopwords = array_fill_keys( $stopwords, true );

	$tokens = array();
	foreach ( $pool as $str ) {
		$str = wp_strip_all_tags( (string) $str );
		// Split on non-letters (keep accented chars for Dutch).
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( $str ) );
		if ( ! $parts ) {
			continue;
		}
		foreach ( $parts as $p ) {
			if ( $p === '' || mb_strlen( $p ) < 3 || mb_strlen( $p ) > OZ_SEARCH_MAX_TERM_LEN ) {
				continue;
			}
			if ( ctype_digit( $p ) ) {
				continue;
			}
			if ( isset( $stopwords[ $p ] ) ) {
				continue;
			}
			$tokens[ $p ] = true;
		}
	}

	return array_keys( $tokens );
}

/**
 * Get the cached term index, rebuilding if needed.
 *
 * @return array<int, string>
 */
function oz_get_search_term_index() : array {
	$cached = get_transient( OZ_SEARCH_INDEX_TRANSIENT );
	if ( is_array( $cached ) && ! empty( $cached ) ) {
		return $cached;
	}
	$index = oz_build_search_term_index();
	set_transient( OZ_SEARCH_INDEX_TRANSIENT, $index, 12 * HOUR_IN_SECONDS );
	return $index;
}

/**
 * Invalidate the index whenever products or categories change.
 */
function oz_invalidate_search_index() : void {
	delete_transient( OZ_SEARCH_INDEX_TRANSIENT );
}
add_action( 'save_post_product', 'oz_invalidate_search_index' );
add_action( 'deleted_post',      'oz_invalidate_search_index' );
add_action( 'edited_product_cat','oz_invalidate_search_index' );
add_action( 'created_product_cat','oz_invalidate_search_index' );

/**
 * Return a short list of suggested queries for a user query.
 *
 * Tokenizes the query. For each token that isn't already an exact index hit,
 * finds the closest index term (Levenshtein distance) and proposes replacing
 * the token. Distance budget scales with token length so we don't propose
 * wild rewrites for short words.
 *
 * @param string $query User search query.
 * @param int    $limit Max suggestions to return.
 * @return array<int, array{query: string, label: string, url: string}>
 */
function oz_get_search_suggestions( string $query, int $limit = 3 ) : array {
	$query = trim( $query );
	if ( mb_strlen( $query ) < 3 ) {
		return array();
	}

	$index     = oz_get_search_term_index();
	if ( empty( $index ) ) {
		return array();
	}
	$index_map = array_fill_keys( $index, true );

	$lower_query = mb_strtolower( $query );
	$tokens      = preg_split( '/[^\p{L}\p{N}]+/u', $lower_query ) ?: array();
	$tokens      = array_values( array_filter( $tokens, function ( $t ) { return $t !== ''; } ) );
	if ( empty( $tokens ) ) {
		return array();
	}

	$suggestions = array();
	foreach ( $tokens as $t_idx => $token ) {
		$len = mb_strlen( $token );
		if ( $len < 3 || $len > OZ_SEARCH_MAX_TERM_LEN ) {
			continue;
		}
		// Already a real term — no correction needed.
		if ( isset( $index_map[ $token ] ) ) {
			continue;
		}

		// Distance budget: short words get ≤1, medium ≤2, long ≤3.
		$max_dist = $len <= 4 ? 1 : ( $len <= 7 ? 2 : 3 );

		$best = null;
		$best_dist = PHP_INT_MAX;
		foreach ( $index as $term ) {
			// Cheap length prefilter — impossible to match if length delta exceeds budget.
			if ( abs( mb_strlen( $term ) - $len ) > $max_dist ) {
				continue;
			}
			// Skip if first char differs AND distance budget is ≤1 — huge speedup.
			if ( $max_dist <= 1 && $term[0] !== $token[0] ) {
				continue;
			}
			$d = levenshtein( $token, $term );
			if ( $d < $best_dist && $d <= $max_dist ) {
				$best_dist = $d;
				$best      = $term;
				if ( $d === 1 ) {
					break;
				}
			}
		}

		if ( $best !== null && $best !== $token ) {
			$new_tokens = $tokens;
			$new_tokens[ $t_idx ] = $best;
			$corrected = implode( ' ', $new_tokens );
			if ( $corrected !== $lower_query ) {
				$suggestions[ $corrected ] = array(
					'query' => $corrected,
					'label' => $corrected,
					'url'   => add_query_arg( array( 's' => $corrected, 'post_type' => 'product' ), home_url( '/' ) ),
					'_dist' => $best_dist,
				);
			}
		}

		if ( count( $suggestions ) >= $limit ) {
			break;
		}
	}

	if ( empty( $suggestions ) ) {
		return array();
	}

	// Sort by distance ascending, then alphabetical.
	usort( $suggestions, function ( $a, $b ) {
		if ( $a['_dist'] !== $b['_dist'] ) {
			return $a['_dist'] - $b['_dist'];
		}
		return strcmp( $a['query'], $b['query'] );
	} );

	// Strip internal sort key before returning.
	foreach ( $suggestions as &$s ) {
		unset( $s['_dist'] );
	}
	unset( $s );

	return array_slice( $suggestions, 0, $limit );
}

/**
 * Render the suggestion banner. Safe to call on any page; no-ops when
 * there's nothing useful to say.
 */
function oz_render_search_suggestions( ?string $query = null ) : void {
	if ( $query === null ) {
		$query = (string) get_search_query();
	}
	$suggestions = oz_get_search_suggestions( $query );
	if ( empty( $suggestions ) ) {
		return;
	}
	?>
	<aside class="oz-search-suggestions" role="note">
		<span class="oz-search-suggestions__lead">Bedoelde je misschien:</span>
		<?php foreach ( $suggestions as $i => $s ) : ?>
			<a class="oz-search-suggestions__link" href="<?php echo esc_url( $s['url'] ); ?>"><em><?php echo esc_html( $s['label'] ); ?></em></a><?php echo $i < count( $suggestions ) - 1 ? ' <span class="oz-search-suggestions__sep">·</span> ' : ''; ?>
		<?php endforeach; ?>
	</aside>
	<?php
}
