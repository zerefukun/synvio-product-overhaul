<?php
/**
 * Reviews section — shared markup for homepage S10 and ruimte/stucsoorten pages.
 *
 * Class prefix .oz-hp-* is historical (originated on the homepage) but the CSS
 * now lives in oz-reviews.css and works anywhere the file is enqueued.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Echo a fully-wrapped reviews section.
 *
 * @param string $context 'home' for homepage (oz-hp-section wrapper),
 *                        'ruimte' for stucsoorten/ruimte pages (oz-section wrapper).
 */
function oz_render_reviews_section( $context = 'home' ) {
	$star        = '<svg class="oz-hp-star" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
	$star_empty  = '<svg class="oz-hp-star oz-hp-star--empty" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';

	$render_stars = function ( $n ) use ( $star, $star_empty ) {
		$out = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$out .= $i <= $n ? $star : $star_empty;
		}
		return $out;
	};

	$dtos = array();
	$aggregate_rating = 4.8;
	$aggregate_count  = 200;

	if ( class_exists( '\\OZ_Reviews\\CPT' ) && class_exists( '\\OZ_Reviews\\Review_DTO' ) && class_exists( '\\OZ_Reviews\\Renderer' ) ) {
		$posts = get_posts( array(
			'post_type'      => \OZ_Reviews\CPT::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 6,
			'meta_key'       => '_oz_publish_time',
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'     => '_oz_rating',
					'value'   => 4,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
		) );

		foreach ( $posts as $p ) {
			$dtos[] = \OZ_Reviews\Review_DTO::from_post( $p );
		}

		$agg = get_option( 'oz_reviews_google_aggregate' );
		if ( is_array( $agg ) && ! empty( $agg['rating_count'] ) ) {
			$aggregate_rating = (float) $agg['rating'];
			$aggregate_count  = (int) $agg['rating_count'];
		}
	}

	$is_ruimte    = ( $context === 'ruimte' );
	$section_cls  = $is_ruimte ? 'oz-section oz-hp-reviews' : 'oz-hp-section oz-hp-reviews';
	$header_wrap_open  = $is_ruimte ? '<div class="oz-container">' : '';
	$header_wrap_close = $is_ruimte ? '</div>' : '';

	$rating_str  = number_format_i18n( $aggregate_rating, 1 );
	$stars_round = (int) round( $aggregate_rating );
	?>
	<section class="<?php echo esc_attr( $section_cls ); ?>" data-reveal>
		<?php echo $header_wrap_open; ?>
		<div class="oz-hp-section-header">
			<div class="oz-hp-eyebrow">Ervaringen</div>
			<h2 class="oz-hp-heading">Echte ervaringen, <em>echte klanten</em></h2>
		</div>

		<div class="oz-hp-reviews-summary">
			<div class="oz-hp-reviews-rating">
				<span class="oz-hp-reviews-big-num"><?php echo esc_html( $rating_str ); ?></span>
				<span class="oz-hp-reviews-rating-label">van de 5</span>
			</div>
			<div class="oz-hp-reviews-meta">
				<div class="oz-hp-reviews-stars-row" role="img" aria-label="<?php echo esc_attr( $rating_str ); ?> van de 5 sterren">
					<?php echo $render_stars( $stars_round ); ?>
				</div>
				<div class="oz-hp-reviews-count">Gebaseerd op <strong><?php echo esc_html( $aggregate_count ); ?>+</strong> Google reviews</div>
			</div>
		</div>

		<?php if ( ! empty( $dtos ) ) : ?>
			<?php echo \OZ_Reviews\Renderer::grid( $dtos ); ?>
		<?php else : ?>
			<div class="oz-hp-reviews-grid"></div>
		<?php endif; ?>

		<div class="oz-hp-reviews-footer">
			<a class="oz-hp-reviews-all" href="/reviews/">
				Bekijk alle reviews
				<svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16"><path fill="currentColor" d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
			</a>
			<div class="oz-hp-reviews-cta">
				<div class="oz-hp-reviews-cta-text">
					<strong>Nog vragen?</strong>
					<span>Bekijk onze veelgestelde vragen of neem contact op.</span>
				</div>
				<div class="oz-hp-reviews-cta-buttons">
					<a class="oz-hp-reviews-btn oz-hp-reviews-btn--outline" href="/veelgestelde-vragen/">Bekijk FAQ</a>
					<a class="oz-hp-reviews-btn" href="/contact/">Contact</a>
				</div>
			</div>
		</div>
		<?php echo $header_wrap_close; ?>
	</section>
	<?php
}
