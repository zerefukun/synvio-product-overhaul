<?php
/**
 * Front Page Template — Beton Ciré homepage v2
 *
 * Fully custom: does NOT call the_content(). All markup lives here.
 * WordPress template hierarchy picks this up for is_front_page().
 *
 * Sections follow the wireframe order (01-26). Flatsome header/footer preserved.
 *
 * @package OzTheme
 */

get_header();
do_action( 'oz_before_content' );

$up = home_url( '/wp-content/uploads' );
?>

<div id="content" class="oz-hp" role="main">

<?php /* S01 — Glass nav removed. Sitewide oz-header in header.php replaces it. */ ?>

<?php /* ================================================================
       S02 — HERO
       ================================================================ */ ?>
<section class="oz-hp-hero">
	<img class="oz-hp-hero-bg" src="<?php echo esc_url( "$up/2026/03/Beton-Badkamer-Placeholder-2-1.webp" ); ?>" alt="" loading="eager" fetchpriority="high" data-no-lazy="1">
	<div class="oz-hp-hero-inner">
		<div class="oz-hp-hero-text">
			<h1 class="oz-hp-hero-title">Een unieke stijl met Beton Ciré<span class="oz-hp-hero-title-tag">Dé trend voor vloeren en wanden</span></h1>
			<p class="oz-hp-hero-sub">Naadloze betonlook, zelf aangebracht.</p>
			<p class="oz-hp-hero-desc">Voor in alle ruimtes: badkamer, toilet, vloer, muur &amp; trap. Kant-en-klare pasta in 50+ kleuren &mdash; bestel online of bezoek onze showroom in Den Haag.</p>
			<div class="oz-hp-hero-ctas">
				<a href="/ruimtes/" class="oz-hp-btn oz-hp-btn--teal">Meer informatie</a>
				<a href="/beton-cire-all-in-one-easyline-standaard-kleuren/" class="oz-hp-btn oz-hp-btn--outline">All-In-One Kant &amp; Klaar</a>
			</div>
		</div>
		<div class="oz-hp-hero-glass">
			<div class="oz-hp-eyebrow">Gratis kleurstalen</div>
			<div class="oz-hp-hero-glass-title">Zeker van je kleur?</div>
			<p class="oz-hp-hero-glass-desc">Selecteer tot 4 kleuren uit onze lijn. We sturen ze gratis naar je toe.</p>
			<a href="/kleurstalen/" class="oz-hp-hero-glass-link">Stalen aanvragen <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
		</div>
	</div>
</section>

<?php /* ================================================================
       S03 — TRUST BAR
       ================================================================ */ ?>
<div class="oz-hp-trust" aria-label="USP balk">
	<div class="oz-hp-trust-track">
		<?php
		$usps = [
			'Voor 14:00 besteld, dezelfde werkdag verzonden',
			'Geen ervaring nodig',
			'Complete pakketten',
			'420.000+ m² door klanten aangebracht',
			'4.8/5.0 Google Reviews',
			'Altijd een specialist beschikbaar',
			'Project ondersteuning',
			'Showroom Den Haag',
		];
		/* Duplicate for seamless loop */
		for ( $i = 0; $i < 2; $i++ ) {
			foreach ( $usps as $usp ) {
				echo '<span class="oz-hp-trust-item"><span class="oz-hp-trust-dot"></span>' . esc_html( $usp ) . '</span>';
			}
		}
		?>
	</div>
</div>

<?php /* ================================================================
       S04 — PRODUCT LINES (3-col grid)
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Collectie</div>
		<h2 class="oz-hp-heading">Onze producten voor de <em>badkamer</em></h2>
		<p class="oz-hp-section-intro">Kies het product dat het beste bij jouw project past. Alle producten zijn waterdicht en geschikt voor natte ruimtes.</p>
	</div>
	<div class="oz-hp-products-3col" data-reveal-stagger>
		<div class="oz-hp-pcard">
			<a class="oz-hp-pcard-img-wrap" href="/beton-cire-easyline-all-in-one/">
				<img class="oz-hp-pcard-img" src="<?php echo esc_url( "$up/2024/01/All-In-One.png" ); ?>" alt="Beton Ciré All-In-One" loading="lazy">
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
				<img class="oz-hp-pcard-img" src="<?php echo esc_url( "$up/2026/03/beton-cire-original.webp" ); ?>" alt="Beton Ciré Original" loading="lazy">
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
				<img class="oz-hp-pcard-img" src="<?php echo esc_url( "$up/2026/03/lavasteen-epoxystone.avif" ); ?>" alt="Beton Ciré Lavasteen" loading="lazy">
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
       S05 — RUIMTES MOZAIEK
       ================================================================ */ ?>
<section class="oz-hp-ruimtes oz-hp-section" data-reveal>
	<div class="oz-hp-ruimtes-header">
		<div class="oz-hp-ruimtes-eyebrow">Toepassingen</div>
		<h2 class="oz-hp-ruimtes-heading">Waar wil je Beton Ciré <em>gebruiken?</em></h2>
		<p class="oz-hp-ruimtes-intro">Van badkamer tot keuken, van vloer tot meubel: beton cire geeft elke ruimte een naadloze, moderne betonlook. Kies je ruimte en ontdek wat er mogelijk is.</p>
	</div>
	<div class="oz-hp-ruimtes-wrap">
		<div class="oz-hp-ruimtes-row1" data-reveal-stagger>
			<a href="/ruimtes/beton-cire-badkamer/" class="oz-hp-ruimtes-card">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/02/ruimte-badkamer-2.webp" ); ?>" alt="Beton cire badkamer" loading="lazy">
				<div class="oz-hp-ruimtes-card-content">
					<div class="oz-hp-ruimtes-card-name">Badkamer</div>
					<div class="oz-hp-ruimtes-card-desc">Waterdichte betonlook voor douche, wand en vloer. Schimmelwerend en makkelijk te onderhouden.</div>
					<span class="oz-hp-ruimtes-card-cta">Meer informatie</span>
				</div>
			</a>
			<a href="/ruimtes/beton-cire-keuken/" class="oz-hp-ruimtes-card">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/02/Keuken-Marloes-daily.webp" ); ?>" alt="Beton cire keuken" loading="lazy">
				<div class="oz-hp-ruimtes-card-content">
					<div class="oz-hp-ruimtes-card-name">Keuken</div>
					<div class="oz-hp-ruimtes-card-desc">Keukenbladen, aanrecht en spatschermen in naadloze betonlook. Waterbestendig en vlekvrij.</div>
					<span class="oz-hp-ruimtes-card-cta">Meer informatie</span>
				</div>
			</a>
			<a href="/ruimtes/beton-cire-toilet/" class="oz-hp-ruimtes-card">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/01/Toilet-NA-Pim-Mossel.jpg" ); ?>" alt="Beton cire toilet" loading="lazy">
				<div class="oz-hp-ruimtes-card-content">
					<div class="oz-hp-ruimtes-card-name">Toilet</div>
					<div class="oz-hp-ruimtes-card-desc">Van wastafel tot wand: een naadloze betonlook waar geen tegel of voeg aan te pas komt.</div>
					<span class="oz-hp-ruimtes-card-cta">Meer informatie</span>
				</div>
			</a>
		</div>
		<div class="oz-hp-ruimtes-row2" data-reveal-stagger>
			<a href="/ruimtes/beton-cire-vloer/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2023/11/ruimte-vloer.webp" ); ?>" alt="Beton cire vloer" loading="lazy">
				<div class="oz-hp-ruimtes-card-content"><div class="oz-hp-ruimtes-card-name">Vloer</div></div>
			</a>
			<a href="/ruimtes/beton-cire-wand/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/02/Woonkamer-wand.webp" ); ?>" alt="Beton cire wand" loading="lazy">
				<div class="oz-hp-ruimtes-card-content"><div class="oz-hp-ruimtes-card-name">Wand</div></div>
			</a>
			<a href="/ruimtes/beton-cire-trappen/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/02/Beton-cire-open-trap.webp" ); ?>" alt="Beton cire trap" loading="lazy">
				<div class="oz-hp-ruimtes-card-content"><div class="oz-hp-ruimtes-card-name">Trap</div></div>
			</a>
			<a href="/ruimtes/beton-cire-meubel/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2023/11/ruimte-meubel.webp" ); ?>" alt="Beton cire meubels" loading="lazy">
				<div class="oz-hp-ruimtes-card-content"><div class="oz-hp-ruimtes-card-name">Meubels</div></div>
			</a>
		</div>
	</div>
</section>

<?php /* ================================================================
       S07b — ZO WERKT HET
       ================================================================ */ ?>
<section class="oz-hp-section" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">In 4 stappen klaar</div>
		<h2 class="oz-hp-heading">Zo werkt het</h2>
	</div>
	<div class="oz-hp-steps-grid" data-reveal-stagger>
		<div class="oz-hp-step">
			<div class="oz-hp-step-num">1</div>
			<div class="oz-hp-step-title">Kies je kleur</div>
			<p class="oz-hp-step-desc">Uit 50+ kleuren of bestel gratis kleurstalen om thuis te vergelijken.</p>
		</div>
		<div class="oz-hp-step">
			<div class="oz-hp-step-num">2</div>
			<div class="oz-hp-step-title">Bestel je pakket</div>
			<p class="oz-hp-step-desc">Compleet pakket met alles erin: pasta, primer, PU toplaag en gereedschap.</p>
		</div>
		<div class="oz-hp-step">
			<div class="oz-hp-step-num">3</div>
			<div class="oz-hp-step-title">Breng het aan</div>
			<p class="oz-hp-step-desc">Volg het stappenplan. Geen ervaring nodig -- wij helpen je telefonisch of in de showroom.</p>
		</div>
		<div class="oz-hp-step">
			<div class="oz-hp-step-num">4</div>
			<div class="oz-hp-step-title">Klaar</div>
			<p class="oz-hp-step-desc">Naadloze betonlook die jarenlang meegaat. Waterbestendig en onderhoudsvrij.</p>
		</div>
	</div>
</section>

<?php /* ================================================================
       S07c — BEFORE / AFTER
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Resultaat</div>
		<h2 class="oz-hp-heading">Voor &amp; na</h2>
	</div>
	<div class="oz-hp-ba-grid">
		<div class="oz-hp-ba-card">
			<img src="https://images.unsplash.com/photo-1552321554-5fefe8c9ef14?w=600&q=80" alt="Voor: kale tegelvloer" loading="lazy">
			<span class="oz-hp-ba-label oz-hp-ba-label--before">Voor</span>
		</div>
		<div class="oz-hp-ba-card">
			<img src="https://images.unsplash.com/photo-1600566752355-35792bedcfea?w=600&q=80" alt="Na: naadloze betonlook" loading="lazy">
			<span class="oz-hp-ba-label oz-hp-ba-label--after">Na</span>
		</div>
	</div>
	<div class="oz-hp-ba-quote">
		<p>"Zelf gedaan in een weekend. Geen ervaring, wel een prachtig resultaat."</p>
		<cite>Klant review</cite>
	</div>
</section>

<?php /* ================================================================
       S08 — MICROCEMENT + KLEURSTALEN
       ================================================================ */ ?>
<section class="oz-hp-section" style="padding:0" data-reveal>
	<div class="oz-hp-split">
		<div class="oz-hp-split-micro">
			<div class="oz-hp-eyebrow">Cementbasis</div>
			<h3>Microcement</h3>
			<p>Echt cement, ultradun, uit 1 emmer. De populairste keuze voor een strakke, moderne betonlook. Geschikt voor vloeren, wanden en badkamers.</p>
			<div class="oz-hp-meta">Harder / 4 stappen / 36 kleuren / Vanaf &euro;31/m&sup2;</div>
			<a href="/product-categorie/microcement/" class="oz-hp-btn oz-hp-btn--teal">Bekijk Microcement</a>
		</div>
		<div class="oz-hp-split-stalen">
			<div class="oz-hp-eyebrow">Gratis kleurstalen</div>
			<h3>Zeker zijn van je kleur?</h3>
			<p>Beton cire is een investering die je jarenlang ziet. Selecteer tot 4 kleuren uit onze lijn en wij sturen ze gratis naar je toe. Zo kun je thuis rustig vergelijken bij jouw lichtval en interieur.</p>
			<div class="oz-hp-meta">Gratis / Binnen 2 werkdagen / Tot 4 kleuren</div>
			<a href="/kleurstalen/" class="oz-hp-btn oz-hp-btn--teal">Stalen aanvragen</a>
		</div>
	</div>
</section>

<?php /* ================================================================
       S09 — VERGELIJKTABEL
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Vergelijken</div>
		<h2 class="oz-hp-heading">Welke past <em>bij jou?</em></h2>
	</div>
	<div class="oz-hp-table-wrap">
		<table class="oz-hp-table">
			<thead>
				<tr><th></th><th>Original</th><th>Micro</th><th>All-In</th><th>Easy</th><th>Metallic</th><th>Lava</th></tr>
			</thead>
			<tbody>
				<tr><td>Kenmerk</td><td>Fijnste korrel</td><td>Echt cement</td><td>Drukkere tek.</td><td>Grof+fijn</td><td>Parelmoer</td><td>Epoxy slijtvast</td></tr>
				<tr><td>Hardheid</td><td>Harder</td><td>Harder</td><td>Hard</td><td>Hard</td><td>Decoratief</td><td>Extreem</td></tr>
				<tr><td>Stappen</td><td>4</td><td>4</td><td>5</td><td>5</td><td>4</td><td>4</td></tr>
				<tr><td>Kleuren</td><td>50+</td><td>36</td><td>36</td><td>36</td><td>12</td><td>20</td></tr>
				<tr><td>Emmers</td><td>1</td><td>1</td><td>1</td><td>2</td><td>1</td><td>1</td></tr>
				<tr><td>Waterdicht</td><td>Met PU</td><td>Met PU</td><td>Met PU</td><td>Met PU</td><td>Met PU</td><td>Tot in kern</td></tr>
			</tbody>
		</table>
	</div>
</section>

<?php /* ================================================================
       S10 — ERVARINGEN
       ================================================================ */
	$oz_star_svg = '<svg class="oz-hp-star" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
	$oz_star_empty_svg = '<svg class="oz-hp-star oz-hp-star--empty" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>';
	$oz_reviews = array(
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

	$oz_render_stars = function ( $n ) use ( $oz_star_svg, $oz_star_empty_svg ) {
		$out = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$out .= $i <= $n ? $oz_star_svg : $oz_star_empty_svg;
		}
		return $out;
	};
?>
<section class="oz-hp-section oz-hp-reviews" data-reveal>
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
			<div class="oz-hp-reviews-stars-row" aria-label="4,8 van de 5 sterren">
				<?php echo $oz_render_stars( 5 ); ?>
			</div>
			<div class="oz-hp-reviews-count">Gebaseerd op <strong>200+</strong> Google reviews</div>
		</div>
	</div>

	<div class="oz-hp-reviews-grid">
		<?php foreach ( $oz_reviews as $r ) : ?>
			<article class="oz-hp-review">
				<header class="oz-hp-review-head">
					<div class="oz-hp-review-stars" aria-label="<?php echo esc_attr( $r['stars'] ); ?> van de 5 sterren">
						<?php echo $oz_render_stars( $r['stars'] ); ?>
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
</section>

<?php /* ================================================================
       S11 — SHOWROOM
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand" data-reveal>
	<div class="oz-hp-showroom">
		<div class="oz-hp-showroom-images">
			<div class="oz-hp-showroom-img oz-hp-showroom-img--tall">
				<img src="<?php echo esc_url( "$up/2024/02/Beton-Badkamer-Placeholder.webp" ); ?>" alt="Beton Cire showroom" loading="lazy">
			</div>
			<div class="oz-hp-showroom-img">
				<img src="<?php echo esc_url( "$up/2024/02/Tv-Meubel-1004-Original.webp" ); ?>" alt="Showroom meubel" loading="lazy">
			</div>
			<div class="oz-hp-showroom-img">
				<img src="<?php echo esc_url( "$up/2026/04/Landscape-All-In-One-Met-Onderkant-Donker-4.webp" ); ?>" alt="Beton cire vloer" loading="lazy">
			</div>
		</div>
		<div class="oz-hp-showroom-text">
			<div class="oz-hp-eyebrow">Showroom Den Haag</div>
			<h3>Onze Beton Cire showroom</h3>
			<p>Kom langs in Den Haag om onze Showroom te bezichtigen en ontdek de mogelijkheden. Hier kun je de bestelling afhalen, kleuren bekijken en een uitgebreide cursus krijgen van onze specialist! Je krijgt in ongeveer 1 uur alle informatie die je nodig hebt om met vertrouwen je project tot werkelijkheid te brengen.</p>
			<p>Kom langs in Den Haag! Je kunt ook het contact formulier invullen en we reageren snel op je vraag. Zou je langs willen komen, vergeet dan niet een afspraak te maken.</p>
			<a href="/beton-cire-showroom/" class="oz-hp-btn oz-hp-btn--teal">Bezoek de showroom</a>
		</div>
	</div>
</section>

<?php /* ================================================================
       S13 — BELANGRIJKSTE PUNTEN (3 TL;DR bullets)
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-keypoints" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Samengevat</div>
		<h2 class="oz-hp-heading">Belangrijkste <em>punten</em></h2>
	</div>
	<ul class="oz-hp-keypoints-list">
		<li>
			<strong>Waterdicht &amp; veelzijdig.</strong>
			Beton Cir&eacute; is geschikt voor vloeren, wanden, keukenbladen en badkamers. De 2-componenten PU-topcoat maakt het volledig bestand tegen water en vlekken.
		</li>
		<li>
			<strong>5000+ kleuren, vrijheid in afwerking.</strong>
			Via de kleurpigment is elke tint mogelijk. Kies glad of met structuur &mdash; aanpasbaar aan elk interieur.
		</li>
		<li>
			<strong>Zelf aanbrengen of laten doen.</strong>
			Kant-en-klare pakketten (All-In-One, Easyline) zijn DIY-vriendelijk; voor complexe projecten schakel je een professional in.
		</li>
	</ul>
</section>

<?php /* ================================================================
       S14 — MEER WETEN (alternating image/text rows with "Lees meer")
       ================================================================ */ ?>
<section class="oz-hp-section" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Achtergrond</div>
		<h2 class="oz-hp-heading">Meer weten over <em>Beton Ciré</em></h2>
		<p class="oz-hp-section-intro">Alles over onze producten, het aanbrengen, prijs en waterdichtheid &mdash; op &eacute;&eacute;n plek.</p>
	</div>
	<div class="oz-hp-learn-list" itemscope itemtype="https://schema.org/FAQPage">

		<?php
		$topics = [
			[
				'q'      => 'Wat is Beton Ciré?',
				'layout' => 'img-left',
				'img'    => '2024/02/Vloer-beton-cire-all-in-one.png',
				'alt'    => 'Beton Ciré All-In-One vloer met naadloze betonlook',
				'teaser' => '<p>Beton Cir&eacute; vindt zijn oorsprong in Frankrijk en wordt al ruim 25 jaar toegepast. De letterlijke vertaling is &lsquo;gewreven beton&rsquo;, wat verwijst naar de manier waarop het aangebracht wordt. Onze Beton Cir&eacute; is onderhoudsvrij en gaat jaren mee.</p>',
				'more'   => '<p>Beton Cir&eacute; is onder doe-het-zelvers een steeds populairder wordende vorm van betonstuc op basis van cement, met watervaste eigenschappen en een strakke betonlook. Het is geschikt voor bijna alle ondergronden &mdash; hout, cement of gips &mdash; en kan online worden gekocht bij ons. Het is de ideale keuze voor een moderne en stijlvolle afwerking. Of je nu op zoek bent naar een prachtige vloer of een waterdichte betonlook voor je badkamer, het product biedt een veelzijdige oplossing.</p>
				            <p>De decoratieve stuc kan ook worden aangebracht over tegels. De tegels hoeven niet te worden gesloopt en kunnen blijven zitten, zolang ze maar vast zitten. Er wordt eerst een egalisatielaag aangebracht over de tegels voordat Beton Cir&eacute; wordt aangebracht.</p>
				            <p><a href="/kennisbank/wat-is-beton-cire/" class="oz-hp-link">Lees het volledige artikel in de kennisbank &rarr;</a></p>',
			],
			[
				'q'      => 'Beton Ciré kopen, bestellen en prijsfactoren',
				'layout' => 'img-right',
				'img'    => '2024/02/ruimte-badkamer-2.webp',
				'alt'    => 'Beton Ciré badkamer uit onze webshop-collectie',
				'teaser' => '<p>Beton Cir&eacute; koop je in onze showroom in Den Haag &eacute;n via de online webshop. We hebben alles op voorraad &mdash; binnen 15 minuten loop je met je bestelling de deur uit. Bestellingen voor 14:00 op werkdagen gaan dezelfde werkdag nog de deur uit.</p>',
				'more'   => '<p>Klanten kunnen uit diverse kleuren kiezen. Er zijn traditionele varianten waarbij pigmenten zelf gemengd moeten worden en kant-en-klare varianten waarbij de pigmenten al gemengd zijn. Bij een online aankoop ontvang je een totaalpakket. In het keuzemenu selecteer je het pakket met de kleur naar wens en hoe waterdicht het object moet zijn &mdash; hier kies je uit meerdere PU-topcoatlagen. Denk aan een badkamer die zeer waterdicht moet zijn.</p>
				            <p>Bij de Beton Cir&eacute; Webshop in Den Haag vind je alles wat je nodig hebt voor jouw projecten: een veelzijdige en duurzame afwerking, ideaal voor vloeren, muren en meubels, zowel binnen als buiten. Je krijgt deskundig advies en ondersteuning bij je aankoop. Onze webshop levert door heel Europa &mdash; Belgi&euml;, Duitsland, Frankrijk, Spanje &mdash; maar ook daarbuiten.</p>',
			],
			[
				'q'      => 'Zelf aanbrengen of professioneel laten doen?',
				'layout' => 'full',
				'img'    => '2024/02/Woonkamer-wand.webp',
				'alt'    => 'Zelf aangebrachte Beton Ciré wand in een woonkamer',
				'teaser' => '<p>Beton Cir&eacute; aanbrengen kan zelf gedaan worden door doe-het-zelvers (DIY) of uitbesteed aan een professional &mdash; afhankelijk van ervaring en gewenst resultaat. Zelf aanbrengen biedt creatieve vrijheid en bespaart kosten.</p>',
				'more'   => '<p>Is het lastig om zelf Beton Cir&eacute; of Microcement aan te brengen? Het is goed te doen door iedereen die een beetje handig is. Afhankelijk van toepassing, kennis en ervaring kun je kiezen voor Beton Cir&eacute; All-In-One of Easyline &mdash; deze varianten zijn voorgemengd en eenvoudig aan te brengen op een schone, egale en gelijkzuigende ondergrond.</p>
				            <p class="oz-hp-tip"><strong>Tip van Patrick, onze specialist:</strong> bestel altijd iets meer dan je denkt nodig te hebben. Zo heb je altijd een beetje over om eventuele oneffenheden die tijdens het aanbrengen zijn ontstaan snel te kunnen verhelpen. En toch geven we genoeg mee voor het aantal vierkante meter die je bestelt.</p>
				            <p>Het inschakelen van een professional heeft voordelen: het bespaart tijd als je niet handig bent met een stucadoorsspaan, schuren of primer aanbrengen. Je bent verzekerd van een mooi en duurzaam resultaat en bespaart jezelf de moeite.</p>
				            <p><a href="/offerte/" class="oz-hp-btn oz-hp-btn--teal">Offerte aanvragen</a></p>',
			],
			[
				'q'      => 'Beton Ciré en Microcement waterdicht maken',
				'layout' => 'img-left',
				'img'    => '2023/12/foto-13.png',
				'alt'    => 'Waterdichte PU-topcoat laag over Beton Ciré',
				'teaser' => '<p>Het waterdicht maken van Beton Cir&eacute; en Microcement is het allerbelangrijkste aspect van het hele proces. Dat gebeurt met onze polyurethaan (PU) topcoat.</p>',
				'more'   => '<p>PU bestaat uit een A- en B-component die elkaar versterken. Deze zorgen ervoor dat het object volledig waterdicht wordt. Door die waterdichtheid biedt Beton Cir&eacute; de mogelijkheid om toegepast te worden in de badkamer &mdash; geen tegels meer nodig. Dat maakt het geschikt voor vele toepassingen.</p>',
			],
			[
				'q'      => 'Kies het juiste product voor je project',
				'layout' => 'img-right',
				'img'    => '2024/03/Beton-cire-wand-jpg-e1711016471264.webp',
				'alt'    => 'Beton Ciré wand met duidelijke tekening',
				'teaser' => '<p>Afhankelijk van je project kun je kiezen uit verschillende soorten. Wij bieden diverse Beton Cir&eacute;-varianten, maar ook Microcement en Lavasteen gietvloeren &mdash; allemaal kant-en-klaar gemixed op kleur, allemaal leverbaar met extra matte PU-topcoat.</p>',
				'more'   => '<ul class="oz-hp-learn-bullets">
				                <li><strong>Beton Cir&eacute; Original &amp; Microcement</strong> &mdash; fijne structuur, snel droog, hard. Wil je zelf aan de slag en het super glad hebben met een echte betonlook, dan is dit de juiste keuze. Met deze kant-en-klare pasta ben je snel klaar.</li>
				                <li><strong>Beton Cir&eacute; Easyline</strong> &mdash; drukke tekening door grof en fijn. Easyline heeft 2 lagen: de eerste grof, de tweede fijn. Daardoor schemert de grove laag door de fijne heen en ontstaat een drukke tekening. Langere droogtijd, met een extra presealer-stap voordat de PU aangebracht wordt.</li>
				                <li><strong>Beton Cir&eacute; All-In-One</strong> &mdash; drukke tekening door schuren. Net als Easyline een extra stap en langere droogtijd, maar eenvoudig aan te brengen.</li>
				                <li><strong>Lavasteen gietvloer</strong> &mdash; A/B-componenten, veruit het hardste product op de markt. Van zichzelf waterdicht, perfect voor doucheruimtes en buiten. Ook geschikt voor wanden.</li>
				            </ul>
				            <p>Met Microcement, Beton Cir&eacute; Original en Lavasteen kun je meer sturen op welke tekening je wilt maken. Met Easyline en All-In-One is de tekening vrijwel altijd druk. Wil je meer controle over de samenstelling, dan kun je kiezen voor Beton Cir&eacute; Original zelf mengen &mdash; meer of minder aanmaakvloeistof zorgt voor een hardere of zachtere pasta.</p>',
			],
		];

		foreach ( $topics as $topic ) :
			$layout = $topic['layout'] ?? 'img-left';
		?>
		<article class="oz-hp-learn-row oz-hp-learn-row--<?php echo esc_attr( $layout ); ?>" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
			<div class="oz-hp-learn-media">
				<img src="<?php echo esc_url( $up . '/' . $topic['img'] ); ?>" alt="<?php echo esc_attr( $topic['alt'] ); ?>" loading="lazy">
			</div>
			<div class="oz-hp-learn-text">
				<h3 class="oz-hp-learn-title" itemprop="name"><?php echo esc_html( $topic['q'] ); ?></h3>
				<div class="oz-hp-learn-body" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
					<div itemprop="text">
						<?php echo wp_kses_post( $topic['teaser'] ); ?>
						<?php if ( 'full' === $layout ) : ?>
							<?php echo wp_kses_post( $topic['more'] ); ?>
						<?php else : ?>
							<div class="oz-hp-learn-more">
								<?php echo wp_kses_post( $topic['more'] ); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( 'full' !== $layout ) : ?>
				<button type="button" class="oz-hp-learn-toggle" aria-expanded="false" onclick="const row=this.closest('.oz-hp-learn-row');const open=row.classList.toggle('is-open');this.setAttribute('aria-expanded',open);this.querySelector('.oz-hp-learn-toggle-label').textContent=open?'Minder tonen':'Lees meer';">
					<span class="oz-hp-learn-toggle-label">Lees meer</span>
					<span class="oz-hp-learn-toggle-icon" aria-hidden="true"></span>
				</button>
				<?php endif; ?>
			</div>
		</article>
		<?php endforeach; ?>

	</div>
</section>


<?php /* ================================================================
       S20 — INSPIRATIE
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Portfolio</div>
		<h2 class="oz-hp-heading">Beton cire <em>inspiratie</em></h2>
	</div>
	<div class="oz-hp-inspo-grid">
		<div class="oz-hp-inspo-card oz-hp-inspo-main">
			<img src="<?php echo esc_url( "$up/2024/02/ruimte-badkamer-2.webp" ); ?>" alt="Beton cire badkamer inspiratie" loading="lazy">
		</div>
		<div class="oz-hp-inspo-card">
			<img src="<?php echo esc_url( "$up/2024/02/Beton-cire-original-keuken-kleur-1006.webp" ); ?>" alt="Beton Ciré Original keuken in kleur 1006" loading="lazy">
		</div>
		<div class="oz-hp-inspo-card">
			<img src="<?php echo esc_url( "$up/2024/02/beton-cire-trapgat-wand-original-kleur1005.webp" ); ?>" alt="Beton Ciré Original wand in trapgat, kleur 1005" loading="lazy">
		</div>
	</div>
	<div style="text-align:center;margin-top:32px">
		<a href="/inspiratie/" class="oz-hp-btn oz-hp-btn--teal">Alle inspiratie bekijken</a>
	</div>
</section>


<?php /* ================================================================
       S24 — KENNISBANK
       ================================================================ */ ?>
<section class="oz-hp-section" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Kennisbank</div>
		<h2 class="oz-hp-heading">Lees meer in onze <em>kennisbank</em></h2>
	</div>
	<div class="oz-hp-kb-wrap">
		<button type="button" class="oz-hp-kb-nav oz-hp-kb-nav--prev" aria-label="Vorige artikelen" data-dir="-1">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
		</button>
		<div class="oz-hp-kb-carousel">
			<?php
			$kb_articles = get_posts([
				'post_type'      => 'post',
				'posts_per_page' => 10,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]);
			foreach ( $kb_articles as $article ) :
				$thumb = get_the_post_thumbnail_url( $article->ID, 'medium' );
			?>
			<a href="<?php echo esc_url( get_permalink( $article ) ); ?>" class="oz-hp-kb-card">
				<?php if ( $thumb ) : ?>
				<div class="oz-hp-kb-card-img">
					<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy">
				</div>
				<?php endif; ?>
				<div class="oz-hp-kb-card-body">
					<div class="oz-hp-kb-card-title"><?php echo esc_html( $article->post_title ); ?></div>
					<div class="oz-hp-kb-card-excerpt"><?php echo esc_html( wp_trim_words( $article->post_content, 20 ) ); ?></div>
					<span class="oz-hp-kb-card-link">Lees meer &rarr;</span>
				</div>
			</a>
			<?php endforeach; ?>
		</div>
		<button type="button" class="oz-hp-kb-nav oz-hp-kb-nav--next" aria-label="Volgende artikelen" data-dir="1">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8.59 16.59 10 18l6-6-6-6-1.41 1.41L13.17 12z"/></svg>
		</button>
	</div>
	<div style="text-align:center;margin-top:32px">
		<a href="/kennisbank/" class="oz-hp-btn oz-hp-btn--teal">Alle artikelen bekijken</a>
	</div>
</section>

<?php /* ================================================================
       S25 — FAQ
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand" id="faq" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">FAQ</div>
		<h2 class="oz-hp-heading">Veelgestelde <em>vragen</em></h2>
	</div>
	<div class="oz-hp-faq-list" itemscope itemtype="https://schema.org/FAQPage">

		<?php
		$faqs = [
			[
				'q' => 'Wat kost beton cire per m2?',
				'a' => 'De kosten van beton cire per vierkante meter kunnen varieren op basis van verschillende factoren, zoals de ondergrond, de complexiteit van het werk en de gewenste afwerking. Over het algemeen liggen de prijzen tussen &euro;80 en &euro;190 per m&sup2;, inclusief btw en plaatsing. Doe het zelf pakketten: van &euro;18 - &euro;52. Het waterdicht maken van een object drijft de prijs op.',
			],
			[
				'q' => 'Beton cire zelf doen of uitbesteden?',
				'a' => 'Het zelf aanbrengen is prima te doen als je de handleiding en technische pagina volgt. Ben je onzeker of heb je vragen, tijdens of na het aanbrengen van onze producten? Onze experts staan altijd voor je klaar. Wij zijn er van overtuigd dat je het zelf kunt doen, maar uiteraard kun je ook het project (deels) uitbesteden aan een ervaren professional.',
			],
			[
				'q' => 'Waar kan ik Beton cire kopen?',
				'a' => 'Je vindt het in onze showroom, gevestigd aan Laan van \'s-Gravenmade 42L, 2495 AJ Den Haag. Wij bieden echter ook wereldwijde bezorging naar elke bestemming. Bestellingen die op werkdagen voor 14.00 uur worden geplaatst, worden dezelfde dag nog verzonden.',
			],
			[
				'q' => 'Hoe lang blijft Beton cire mooi?',
				'a' => 'Met de juiste toepassing behoudt dit product zijn schoonheid gedurende een langere periode en heeft het een langdurige afwerking. Een belangrijke tip is om viltjes onder stoelen te gebruiken om te voorkomen dat zand en haar, krassen op het betonnen oppervlak maken.',
			],
			[
				'q' => 'Waarom kiezen voor Beton cire?',
				'a' => 'Beton Cire heeft een duurzame afwerking, mits het goed wordt aangebracht. Het blijft jarenlang mooi en geeft een stoere en industriele betonlook. De waterdichte eigenschappen en de functionaliteit maken het een makkelijke keuze. Bovendien ziet het er ook heel mooi uit.',
			],
			[
				'q' => 'Welke soorten Beton cire zijn er?',
				'a' => 'Er zijn 3 verschillende soorten en wij hebben ze alle drie. Bovendien hebben we microcement en Lavasteen. Het verschil zit hem in gemak, tekening, moeilijkheidsgraad en prijs. Onze pakketten bestaan altijd uit een aantal stappen met de daarbij behorende producten.',
			],
			[
				'q' => 'Is Beton cire kwetsbaar?',
				'a' => 'Het hangt af van de gebruikte topcoat en het merk Beton cire. Onze varianten zijn erg hard en worden afgewerkt met een 2-componenten PU-topcoat die krasongevoelig is. Die topcoat is getest op UV-bestendigheid, hittebestendigheid, krasgevoeligheid en verwerkbaarheid. Een toplak is nooit hufter-proof, maar bij normaal gebruik blijft Beton cire jarenlang mooi.',
			],
			[
				'q' => 'Wat zijn de nadelen van Beton cire?',
				'a' => 'Er zijn twee aandachtspunten. Zet geen hete pan direct op een Beton cire keukenblad &mdash; gebruik altijd een onderzetter. En zorg dat er geen open vuur tegen de betonlook aan komt, bijvoorbeeld brandend hout uit een openhaard dat op de vloer valt. Verder is Beton cire waterdicht, krasongevoelig en geschikt voor vrijwel elke ruimte.',
			],
		];

		foreach ( $faqs as $faq ) :
		?>
		<div class="oz-hp-faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
			<button class="oz-hp-faq-q" itemprop="name" onclick="this.parentElement.classList.toggle('is-open')">
				<?php echo esc_html( $faq['q'] ); ?>
				<span class="oz-hp-faq-icon" aria-hidden="true"></span>
			</button>
			<div class="oz-hp-faq-a" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
				<p itemprop="text"><?php echo wp_kses_post( $faq['a'] ); ?></p>
			</div>
		</div>
		<?php endforeach; ?>

	</div>
</section>

</div>

<!-- Scroll-reveal handled by theme-level oz-animations.js -->

<?php
do_action( 'oz_after_content' );
get_footer();
