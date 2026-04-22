<?php
/**
 * Template Name: Reviews Hub
 *
 * Full custom page for /reviews/. Mirrors the homepage section language
 * (.oz-hp-*) so the two pages read as one brand system.
 *
 * Assigned to post 29590 (/reviews/) via _wp_page_template meta.
 *
 * @package OzTheme
 */

get_header();
do_action( 'oz_before_content' );

$up = home_url( '/wp-content/uploads' );

$aggregate = get_option( 'oz_reviews_google_aggregate' );
$rating    = is_array( $aggregate ) && ! empty( $aggregate['rating'] ) ? (float) $aggregate['rating'] : 4.8;
$count     = is_array( $aggregate ) && ! empty( $aggregate['rating_count'] ) ? (int) $aggregate['rating_count'] : 169;
$rating_str = number_format_i18n( $rating, 1 );

$star_svg = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
?>

<div id="content" class="oz-hp oz-reviews-page" role="main">

<?php /* ================================================================
       S01 — HERO with trust-glass
       ================================================================ */ ?>
<section class="oz-hp-hero oz-hp-hero--reviews">
	<img class="oz-hp-hero-bg" src="<?php echo esc_url( "$up/2026/03/Beton-Badkamer-Placeholder-2-1-1024x683.avif" ); ?>" srcset="<?php echo esc_url( "$up/2026/03/Beton-Badkamer-Placeholder-2-1-768x512.avif" ); ?> 768w, <?php echo esc_url( "$up/2026/03/Beton-Badkamer-Placeholder-2-1-1024x683.avif" ); ?> 1024w, <?php echo esc_url( "$up/2026/03/Beton-Badkamer-Placeholder-2-1.avif" ); ?> 1536w" sizes="100vw" alt="" width="1024" height="683" loading="eager" fetchpriority="high" decoding="async" data-no-lazy="1">
	<div class="oz-hp-hero-inner">
		<div class="oz-hp-hero-text">
			<h1 class="oz-hp-hero-title">Echte ervaringen, <em>echte klanten</em><span class="oz-hp-hero-title-tag">169+ Google reviews &middot; 4,8 / 5 sterren</span></h1>
			<p class="oz-hp-hero-sub">De stem van onze klanten, ongefilterd.</p>
			<p class="oz-hp-hero-desc">Lees wat honderden klanten zeggen over hun bestelling, advies en eindresultaat. Of deel zelf je ervaring &mdash; je helpt anderen bij de keuze voor hun project.</p>
			<div class="oz-hp-hero-ctas">
				<a href="#oz-reviews-lijst" class="oz-hp-btn oz-hp-btn--teal">Lees alle reviews</a>
				<a href="#oz-review-formulier" class="oz-hp-btn oz-hp-btn--outline">Deel je ervaring</a>
			</div>
		</div>
		<div class="oz-hp-hero-glass">
			<div class="oz-hp-eyebrow">Google reviews</div>
			<div class="oz-hp-hero-glass-title"><?php echo esc_html( $rating_str ); ?> / 5,0</div>
			<div class="oz-hp-reviews-stars-row" role="img" aria-label="<?php echo esc_attr( $rating_str ); ?> van de 5 sterren" style="margin: 4px 0 10px;">
				<?php for ( $i = 1; $i <= 5; $i++ ) :
					$cls = $i <= (int) round( $rating ) ? 'oz-hp-star' : 'oz-hp-star oz-hp-star--empty';
					echo '<span class="' . esc_attr( $cls ) . '">' . $star_svg . '</span>';
				endfor; ?>
			</div>
			<p class="oz-hp-hero-glass-desc">Gebaseerd op <strong><?php echo esc_html( $count ); ?>+</strong> geverifieerde Google reviews van echte klanten door heel Nederland.</p>
			<a href="#oz-reviews-lijst" class="oz-hp-hero-glass-link">Bekijk de reviews <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
		</div>
	</div>
</section>

<?php /* ================================================================
       S02 — TRUST BAR (identical to homepage)
       ================================================================ */ ?>
<div class="oz-hp-trust" aria-label="USP balk">
	<div class="oz-hp-trust-track">
		<?php
		$usps = array(
			'Voor 14:00 besteld, dezelfde werkdag verzonden',
			'Geen ervaring nodig',
			'Complete pakketten',
			'420.000+ m² door klanten aangebracht',
			'4.8/5.0 Google Reviews',
			'Altijd een specialist beschikbaar',
			'Project ondersteuning',
			'Showroom Den Haag',
		);
		for ( $i = 0; $i < 2; $i++ ) {
			foreach ( $usps as $usp ) {
				echo '<span class="oz-hp-trust-item"><span class="oz-hp-trust-dot"></span>' . esc_html( $usp ) . '</span>';
			}
		}
		?>
	</div>
</div>

<?php /* ================================================================
       S03 — REVIEWS LIJST (summary + carousel)
       ================================================================ */ ?>
<section id="oz-reviews-lijst" class="oz-hp-section oz-hp-reviews" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Alle reviews</div>
		<h2 class="oz-hp-heading">Honderden klanten zijn je <em>voorgegaan</em></h2>
		<p class="oz-hp-section-intro">Al onze Google reviews op één plek. Swipe door de ervaringen van echte klanten die kozen voor Beton Ciré Webshop.</p>
	</div>

	<?php echo do_shortcode( '[oz_reviews_summary]' ); ?>

	<?php echo do_shortcode( '[oz_reviews count="200" layout="carousel"]' ); ?>
</section>

<?php /* ================================================================
       S04 — PRODUCT LINES (3-col) — mirror of homepage S04
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Collectie</div>
		<h2 class="oz-hp-heading">Ook aan de slag? <em>Kies je product</em></h2>
		<p class="oz-hp-section-intro">Dezelfde producten die onze 169+ klanten gebruikten. Compleet pakket, inclusief primer, pasta, PU en gereedschap.</p>
	</div>
	<div class="oz-hp-products-3col" data-reveal-stagger>
		<div class="oz-hp-pcard">
			<a class="oz-hp-pcard-img-wrap" href="/beton-cire-easyline-all-in-one/">
				<img class="oz-hp-pcard-img" src="<?php echo esc_url( "$up/2024/01/All-In-One-510x319.webp" ); ?>" alt="Beton Ciré All-In-One" width="510" height="319" loading="lazy" decoding="async">
			</a>
			<h3 class="oz-hp-pcard-name">Beton Ciré All-In-One</h3>
			<div class="oz-hp-pcard-label">Kant &amp; Klaar</div>
			<div class="oz-hp-pcard-rating">Makkelijkheid: ★★★★★ | Duurzaamheid: ★★★★★ | Betaalbaarheid: ★★★★☆</div>
			<ul class="oz-hp-pcard-features">
				<li>Badkamerwanden, natte cellen</li>
				<li>Huiskamervloeren en meubels</li>
				<li>Hard met een fijne structuur</li>
				<li>Slechts twee dagen werk</li>
				<li>36 kleuren en RAL en NCS</li>
				<li>Voor binnen</li>
			</ul>
			<div class="oz-hp-pcard-price"><strong>Vanaf &euro;28</strong> per 1m&sup2;</div>
			<a href="/beton-cire-easyline-all-in-one/" class="oz-hp-btn oz-hp-btn--teal">Beton Ciré All-In-One</a>
		</div>
		<div class="oz-hp-pcard">
			<a class="oz-hp-pcard-img-wrap" href="/beton-cire-original/">
				<img class="oz-hp-pcard-img" src="<?php echo esc_url( "$up/2026/03/beton-cire-original-550.webp" ); ?>" alt="Beton Ciré Original" width="549" height="366" loading="lazy" decoding="async">
			</a>
			<h3 class="oz-hp-pcard-name">Beton Ciré Original</h3>
			<div class="oz-hp-pcard-label">Kant &amp; Klaar</div>
			<div class="oz-hp-pcard-rating">Makkelijkheid: ★★★★★ | Duurzaamheid: ★★★★★ | Betaalbaarheid: ★★★★★</div>
			<ul class="oz-hp-pcard-features">
				<li>Badkamerwanden, natte cellen</li>
				<li>Huiskamervloeren en meubels</li>
				<li>Zeer hard, fijne structuur</li>
				<li>Snelste klaar</li>
				<li>90 kleuren + RAL en NCS</li>
				<li>Voor binnen en buiten</li>
			</ul>
			<div class="oz-hp-pcard-price"><strong>Vanaf &euro;31</strong> per 1m&sup2;</div>
			<a href="/beton-cire-original/" class="oz-hp-btn oz-hp-btn--teal">Beton Ciré Original</a>
		</div>
		<div class="oz-hp-pcard">
			<a class="oz-hp-pcard-img-wrap" href="/lavasteen-gietvloer/">
				<img class="oz-hp-pcard-img" src="<?php echo esc_url( "$up/2026/03/lavasteen-epoxystone-550.webp" ); ?>" alt="Beton Ciré Lavasteen" width="549" height="366" loading="lazy" decoding="async">
			</a>
			<h3 class="oz-hp-pcard-name">Beton Ciré Lavasteen</h3>
			<div class="oz-hp-pcard-label">Kant &amp; Klaar</div>
			<div class="oz-hp-pcard-rating">Makkelijkheid: ★★★★★ | Duurzaamheid: ★★★★★ | Betaalbaarheid: ★★★★★</div>
			<ul class="oz-hp-pcard-features">
				<li>Badkamer vloeren en wanden</li>
				<li>Voor horeca, huiskamer vloeren</li>
				<li>Extreem hard door epoxy</li>
				<li>2 tot 3 dagen werk</li>
				<li>Keuze uit 20 nieuwe kleuren</li>
				<li>Voor binnen en buiten</li>
			</ul>
			<div class="oz-hp-pcard-price"><strong>Vanaf &euro;47/1m&sup2;</strong> per 5m&sup2;</div>
			<a href="/lavasteen-gietvloer/" class="oz-hp-btn oz-hp-btn--teal">Beton Ciré Lavasteen</a>
		</div>
	</div>
</section>

<?php /* ================================================================
       S05 — KLEURSTALEN split (mirror of homepage S08)
       ================================================================ */ ?>
<section class="oz-hp-section" style="padding:0" data-reveal>
	<div class="oz-hp-split">
		<div class="oz-hp-split-micro" style="padding:0;position:relative;overflow:hidden;">
			<img src="<?php echo esc_url( "$up/2024/03/Beton-cire-wand-jpg-e1711016471264.webp" ); ?>" alt="" aria-hidden="true" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
		</div>
		<div class="oz-hp-split-stalen">
			<div class="oz-hp-eyebrow">Gratis kleurstalen</div>
			<h3>Zeker zijn van je kleur?</h3>
			<p>Beton ciré is een investering die je jarenlang ziet. Selecteer tot 4 kleuren uit onze lijn en wij sturen ze gratis naar je toe. Zo kun je thuis rustig vergelijken bij jouw lichtval en interieur.</p>
			<div class="oz-hp-meta">Gratis / Binnen 2 werkdagen / Tot 4 kleuren</div>
			<a href="/kleurstalen/" class="oz-hp-btn oz-hp-btn--teal">Stalen aanvragen</a>
		</div>
	</div>
</section>

<?php /* ================================================================
       S06 — DEEL JE ERVARING (compact form)
       ================================================================ */ ?>
<section id="oz-review-formulier" class="oz-hp-section oz-hp-section--sand oz-hp-reviews" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Deel je ervaring</div>
		<h2 class="oz-hp-heading">Help anderen met jouw <em>review</em></h2>
		<p class="oz-hp-section-intro">Je review staat meestal binnen 24 uur online en helpt honderden toekomstige klanten bij hun keuze. Liever via Google? Dat kan ook.</p>
	</div>

	<div class="oz-form-wrap oz-form-wrap--review">
		<?php echo \OZ_Forms\Form::render( 'shop-review' ); ?>
	</div>

	<div class="oz-reviews-secondary-cta">
		<span>Of schrijf je review rechtstreeks op Google &rarr;</span>
		<a href="https://search.google.com/local/writereview?placeid=ChIJj-6B3Xe3xUcRmjg1lTEhkIM" target="_blank" rel="noopener" class="oz-hp-btn oz-hp-btn--outline">Review op Google</a>
	</div>
</section>

<?php /* ================================================================
       S07 — SHOWROOM CTA compact
       ================================================================ */ ?>
<section class="oz-hp-section" data-reveal>
	<div class="oz-hp-showroom">
		<div class="oz-hp-showroom-images">
			<div class="oz-hp-showroom-img oz-hp-showroom-img--tall">
				<img src="<?php echo esc_url( "$up/2024/02/Beton-Badkamer-Placeholder.webp" ); ?>" alt="Beton Ciré showroom" loading="lazy">
			</div>
			<div class="oz-hp-showroom-img">
				<img src="<?php echo esc_url( "$up/2024/02/Tv-Meubel-1004-Original.webp" ); ?>" alt="Showroom meubel" loading="lazy">
			</div>
			<div class="oz-hp-showroom-img">
				<img src="<?php echo esc_url( "$up/2026/04/Landscape-All-In-One-Met-Onderkant-Donker-4.webp" ); ?>" alt="Beton ciré vloer" loading="lazy">
			</div>
		</div>
		<div class="oz-hp-showroom-text">
			<div class="oz-hp-eyebrow">Showroom Den Haag</div>
			<h3>Zie het zelf in de showroom</h3>
			<p>Twijfel je nog? Kom langs in onze Haagse showroom. Je ziet kleuren en afwerkingen in het echt, krijgt een korte cursus van een specialist en vertrekt met alle antwoorden die je nodig hebt.</p>
			<a href="/beton-cire-showroom/" class="oz-hp-btn oz-hp-btn--teal">Plan je bezoek</a>
		</div>
	</div>
</section>

</div>

<?php
do_action( 'oz_after_content' );
get_footer();
