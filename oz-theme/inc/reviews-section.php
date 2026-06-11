<?php
/**
 * Reviews section — shared markup for homepage S10 and ruimte/stucsoorten pages.
 *
 * Class prefix .oz-hp-* is historical (originated on the homepage) but the CSS
 * now lives in oz-reviews.css and works anywhere the file is enqueued.
 *
 * Data source: live Trustindex table. Per project rule the homepage + ruimte
 * pages show only what Trustindex currently has cached. The /reviews/ hub is
 * the ONLY place that reads the accumulated archive (via [oz_reviews source="cpt"]).
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
	$dtos             = array();
	$aggregate_rating = 4.8;
	$aggregate_count  = 159;

	if ( class_exists( '\\OZ_Reviews\\Trustindex_Sync' ) && class_exists( '\\OZ_Reviews\\Review_DTO' ) && class_exists( '\\OZ_Reviews\\Renderer' ) ) {
		$rows = \OZ_Reviews\Trustindex_Sync::fetch_rows();
		foreach ( $rows as $row ) {
			$dto = \OZ_Reviews\Review_DTO::from_trustindex_row( $row );
			if ( $dto['rating'] < 4 ) {
				continue; // homepage/ruimte stay positive; /reviews/ shows all
			}
			$dtos[] = $dto;
			if ( count( $dtos ) >= 6 ) {
				break;
			}
		}

		$agg              = \OZ_Reviews\Trustindex_Sync::get_resolved_aggregate( $aggregate_rating, $aggregate_count );
		$aggregate_rating = (float) $agg['rating'];
		$aggregate_count  = (int) $agg['rating_count'];
	}

	$is_ruimte         = ( $context === 'ruimte' );
	$section_cls       = $is_ruimte ? 'oz-section oz-hp-reviews' : 'oz-hp-section oz-hp-reviews';
	$header_wrap_open  = $is_ruimte ? '<div class="oz-container">' : '';
	$header_wrap_close = $is_ruimte ? '</div>' : '';
	?>
	<section class="<?php echo esc_attr( $section_cls ); ?>" data-reveal>
		<?php echo $header_wrap_open; ?>
		<div class="oz-hp-section-header">
			<div class="oz-hp-eyebrow">Ervaringen</div>
			<h2 class="oz-hp-heading">Echte ervaringen, <em>echte klanten</em></h2>
		</div>

		<?php echo \OZ_Reviews\Renderer::summary( $aggregate_rating, $aggregate_count ); ?>

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
