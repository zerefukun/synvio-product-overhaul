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

	$reviews = array(
		array(
			'stars' => 5,
			'date'  => 'Donderdag 9 april 2026',
			'body'  => 'PU topcoat op ons aanrecht, 5 lagen. Na 2 weken nog steeds prachtig &mdash; water parelt er zo af.',
			'name'  => 'Raggy Woesthoff',
		),
		array(
			'stars' => 5,
			'date'  => 'Woensdag 26 maart 2026',
			'body'  => 'Ontzettend fijne zaak. Vriendelijk personeel en zeer goede uitleg over het hele proces.',
			'name'  => 'Jacqueline Hazewinkel',
		),
		array(
			'stars' => 5,
			'date'  => 'Maandag 17 februari 2026',
			'body'  => 'Prachtige eettafel gemaakt met Beton Cir&eacute;. Mooi materiaal, fijn te verwerken voor doe-het-zelvers.',
			'name'  => 'Dennis Schrier',
		),
		array(
			'stars' => 5,
			'date'  => 'Donderdag 6 februari 2026',
			'body'  => 'Ongelofelijke service. Professioneel en vriendelijk advies op maat, precies wat ik zocht.',
			'name'  => 'Elisabeth P.',
		),
		array(
			'stars' => 5,
			'date'  => 'Zaterdag 18 januari 2026',
			'body'  => 'Super resultaat op onze badkamervloer. Complete kit met duidelijke uitleg, alles wat je nodig hebt.',
			'name'  => 'Frank van Leuven',
		),
		array(
			'stars' => 4,
			'date'  => 'Dinsdag 14 januari 2026',
			'body'  => 'Mooi product, goede kleuren om uit te kiezen. Levering liep een dag uit maar verder top.',
			'name'  => 'Nico Kanters',
		),
	);

	$is_ruimte    = ( $context === 'ruimte' );
	$section_cls  = $is_ruimte ? 'oz-section oz-hp-reviews' : 'oz-hp-section oz-hp-reviews';
	$header_wrap_open  = $is_ruimte ? '<div class="oz-container">' : '';
	$header_wrap_close = $is_ruimte ? '</div>' : '';
	?>
	<section class="<?php echo esc_attr( $section_cls ); ?>" data-reveal>
		<?php echo $header_wrap_open; ?>
		<div class="oz-hp-section-header">
			<div class="oz-hp-eyebrow">Ervaringen</div>
			<h2 class="oz-hp-heading">Echte ervaringen, <em>echte klanten</em></h2>
		</div>

		<div class="oz-hp-reviews-summary">
			<div class="oz-hp-reviews-rating">
				<span class="oz-hp-reviews-big-num">4,8</span>
				<span class="oz-hp-reviews-rating-label">van de 5</span>
			</div>
			<div class="oz-hp-reviews-meta">
				<div class="oz-hp-reviews-stars-row" role="img" aria-label="4,8 van de 5 sterren">
					<?php echo $render_stars( 5 ); ?>
				</div>
				<div class="oz-hp-reviews-count">Gebaseerd op <strong>200+</strong> Google reviews</div>
			</div>
		</div>

		<div class="oz-hp-reviews-grid">
			<?php foreach ( $reviews as $r ) : ?>
				<article class="oz-hp-review">
					<header class="oz-hp-review-head">
						<div class="oz-hp-review-stars" role="img" aria-label="<?php echo esc_attr( $r['stars'] ); ?> van de 5 sterren">
							<?php echo $render_stars( $r['stars'] ); ?>
						</div>
						<span class="oz-hp-review-date"><?php echo esc_html( $r['date'] ); ?></span>
					</header>
					<p class="oz-hp-review-body"><?php echo $r['body']; ?></p>
					<footer class="oz-hp-review-author">
						<span class="oz-hp-review-avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $r['name'], 0, 1 ) ); ?></span>
						<span class="oz-hp-review-author-info">
							<span class="oz-hp-review-author-name"><?php echo esc_html( $r['name'] ); ?></span>
							<span class="oz-hp-review-verified">Google review</span>
						</span>
					</footer>
				</article>
			<?php endforeach; ?>
		</div>
		<?php echo $header_wrap_close; ?>
	</section>
	<?php
}
