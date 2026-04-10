<?php
/**
 * Front Page Template — Beton Ciré homepage v2
 *
 * Fully custom: does NOT call the_content(). The page's post_content is
 * ignored — all markup for the homepage lives in this file. This replaces
 * the legacy Flatsome UX Builder shortcode homepage.
 *
 * WordPress template hierarchy picks this up automatically for the
 * static front page (is_front_page() === true), so no admin assignment
 * is needed.
 *
 * @package OzTheme
 */

get_header();
do_action( 'flatsome_before_page' );
?>

<main id="content" class="oz-hp" role="main">

	<?php /* ============================================================
	       SECTION: Ruimtes mozaiek — editorial 6+1 card grid
	       ============================================================ */ ?>
	<section class="oz-hp-ruimtes">
		<div class="oz-hp-ruimtes-header">
			<div class="oz-hp-ruimtes-eyebrow">Toepassingen</div>
			<h2 class="oz-hp-ruimtes-heading">Waar wil je Beton Cire <em>gebruiken?</em></h2>
		</div>

		<div class="oz-hp-ruimtes-wrap">

			<?php /* Row 1 — top 3 featured: badkamer, keuken, toilet. Desc + CTA always visible. */ ?>
			<div class="oz-hp-ruimtes-row1">

				<a href="/ruimtes/beton-cire-badkamer/" class="oz-hp-ruimtes-card">
					<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( home_url( '/wp-content/uploads/2024/02/ruimte-badkamer-2.webp' ) ); ?>" alt="Beton cire badkamer" loading="lazy">
					<div class="oz-hp-ruimtes-card-content">
						<div class="oz-hp-ruimtes-card-name">Badkamer</div>
						<div class="oz-hp-ruimtes-card-desc">Waterdichte betonlook voor douche, wand en vloer. Schimmelwerend en makkelijk te onderhouden.</div>
						<span class="oz-hp-ruimtes-card-cta">Meer informatie</span>
					</div>
				</a>

				<a href="/ruimtes/beton-cire-keuken/" class="oz-hp-ruimtes-card">
					<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( home_url( '/wp-content/uploads/2024/02/Keuken-Marloes-daily.webp' ) ); ?>" alt="Beton cire keuken" loading="lazy">
					<div class="oz-hp-ruimtes-card-content">
						<div class="oz-hp-ruimtes-card-name">Keuken</div>
						<div class="oz-hp-ruimtes-card-desc">Aanrecht, spatscherm en vloer in naadloze betonlook. Waterbestendig en vlekvrij.</div>
						<span class="oz-hp-ruimtes-card-cta">Meer informatie</span>
					</div>
				</a>

				<a href="/ruimtes/beton-cire-toilet/" class="oz-hp-ruimtes-card">
					<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( home_url( '/wp-content/uploads/2024/01/Toilet-NA-Pim-Mossel.jpg' ) ); ?>" alt="Beton cire toilet" loading="lazy">
					<div class="oz-hp-ruimtes-card-content">
						<div class="oz-hp-ruimtes-card-name">Toilet</div>
						<div class="oz-hp-ruimtes-card-desc">Van wastafel tot wand: een naadloze betonlook waar geen tegel of voeg aan te pas komt.</div>
						<span class="oz-hp-ruimtes-card-cta">Meer informatie</span>
					</div>
				</a>

			</div>

			<?php /* Row 2 — compact name-only cards for the rest */ ?>
			<div class="oz-hp-ruimtes-row2">

				<a href="/ruimtes/beton-cire-vloer/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
					<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( home_url( '/wp-content/uploads/2024/02/ruimtes-vloer-voorbeeld-3-1.webp' ) ); ?>" alt="Beton cire vloer" loading="lazy">
					<div class="oz-hp-ruimtes-card-content">
						<div class="oz-hp-ruimtes-card-name">Vloer</div>
					</div>
				</a>

				<a href="/ruimtes/beton-cire-wand/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
					<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( home_url( '/wp-content/uploads/2024/02/Woonkamer-wand.webp' ) ); ?>" alt="Beton cire wand" loading="lazy">
					<div class="oz-hp-ruimtes-card-content">
						<div class="oz-hp-ruimtes-card-name">Wand</div>
					</div>
				</a>

				<a href="/ruimtes/beton-cire-trappen/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
					<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( home_url( '/wp-content/uploads/2024/02/Beton-cire-open-trap.webp' ) ); ?>" alt="Beton cire trap" loading="lazy">
					<div class="oz-hp-ruimtes-card-content">
						<div class="oz-hp-ruimtes-card-name">Trap</div>
					</div>
				</a>

				<a href="/ruimtes/beton-cire-meubel/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
					<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( home_url( '/wp-content/uploads/2024/02/Tv-Meubel-1004-Original.webp' ) ); ?>" alt="Beton cire meubels" loading="lazy">
					<div class="oz-hp-ruimtes-card-content">
						<div class="oz-hp-ruimtes-card-name">Meubels</div>
					</div>
				</a>

			</div>

		</div>
	</section>

</main>

<?php
do_action( 'flatsome_after_page' );
get_footer();
