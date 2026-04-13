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
do_action( 'flatsome_before_page' );

$up = home_url( '/wp-content/uploads' );
?>

<main id="content" class="oz-hp" role="main">

<?php /* ================================================================
       S01 — GLASS NAV
       Floating glassmorphism bar from wireframe. Replaces Flatsome header
       visually (CSS hides #header). Logo inverted to white via CSS filter.
       ================================================================ */ ?>
<nav class="oz-hp-nav" aria-label="Hoofdnavigatie">
	<div class="oz-hp-nav-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="oz-hp-nav-logo">
			<?php
			$custom_logo_id = get_theme_mod( 'custom_logo' );
			if ( $custom_logo_id ) :
				$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'medium' );
			?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php else : ?>
				<span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<?php endif; ?>
		</a>

		<div class="oz-hp-nav-links">
			<a href="/ruimtes/">Ruimtes</a>
			<a href="/product-categorie/">Producten</a>
			<a href="/kleurenoverzicht/">Kleuren</a>
			<a href="/inspiratie/">Inspiratie</a>
			<a href="/kennisbank/">Kennisbank</a>
		</div>

		<div class="oz-hp-nav-actions">
			<a href="/?s=" class="oz-hp-nav-icon" aria-label="Zoeken">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
			</a>
			<a href="/kleurstalen/" class="oz-hp-nav-stalen">Kleurstalen</a>
			<a href="/winkelwagen/" class="oz-hp-nav-icon" aria-label="Winkelwagen">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
			</a>
			<button class="oz-hp-nav-toggle" aria-label="Menu" onclick="this.closest('.oz-hp-nav').classList.toggle('is-open')">
				<span></span><span></span><span></span>
			</button>
		</div>
	</div>
</nav>

<?php /* ================================================================
       S02 — HERO
       ================================================================ */ ?>
<section class="oz-hp-hero">
	<img class="oz-hp-hero-bg" src="<?php echo esc_url( "$up/2026/03/Beton-Badkamer-Placeholder-2-1.webp" ); ?>" alt="" loading="eager">
	<div class="oz-hp-hero-inner">
		<div class="oz-hp-hero-text">
			<h1 class="oz-hp-hero-title">Beton Cire.</h1>
			<p class="oz-hp-hero-sub">Naadloze betonlook. Zelf aangebracht.</p>
			<p class="oz-hp-hero-desc">Kant-en-klare pasta in 50+ kleuren voor vloeren, wanden, badkamers en meubels. Vergelijk de lijnen, bestel online of bezoek onze showroom in Den Haag.</p>
			<div class="oz-hp-hero-ctas">
				<a href="/beton-cire-all-in-one-easyline-standaard-kleuren/" class="oz-hp-btn oz-hp-btn--teal">All-In-One Kant &amp; Klaar</a>
				<a href="/product-categorie/microcement/" class="oz-hp-btn oz-hp-btn--outline">Microcement</a>
			</div>
		</div>
		<div class="oz-hp-hero-glass">
			<div class="oz-hp-hero-glass-title">Zeker van je kleur?</div>
			<p class="oz-hp-hero-glass-desc">Selecteer tot 4 kleuren. Gratis thuisgestuurd.</p>
			<a href="/kleurstalen/" class="oz-hp-btn oz-hp-btn--teal" style="width:100%;text-align:center">Stalen aanvragen</a>
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
			'Geen ervaring nodig',
			'Complete pakketten',
			'420.000+ m² door klanten aangebracht',
			'4.8/5.0 Google Reviews',
			'Zelfde dag verzonden',
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
       S04 — RUIMTES MOZAIEK
       ================================================================ */ ?>
<section class="oz-hp-ruimtes oz-hp-section">
	<div class="oz-hp-ruimtes-header">
		<div class="oz-hp-ruimtes-eyebrow">Toepassingen</div>
		<h2 class="oz-hp-ruimtes-heading">Waar wil je Beton Cire <em>gebruiken?</em></h2>
	</div>
	<div class="oz-hp-ruimtes-wrap">
		<div class="oz-hp-ruimtes-row1">
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
					<div class="oz-hp-ruimtes-card-desc">Aanrecht, spatscherm en vloer in naadloze betonlook. Waterbestendig en vlekvrij.</div>
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
		<div class="oz-hp-ruimtes-row2">
			<a href="/ruimtes/beton-cire-vloer/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/02/ruimtes-vloer-voorbeeld-3-1.webp" ); ?>" alt="Beton cire vloer" loading="lazy">
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
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/02/Tv-Meubel-1004-Original.webp" ); ?>" alt="Beton cire meubels" loading="lazy">
				<div class="oz-hp-ruimtes-card-content"><div class="oz-hp-ruimtes-card-name">Meubels</div></div>
			</a>
		</div>
	</div>
</section>

<?php /* ================================================================
       S05 — PRODUCT: ALL-IN-ONE
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand">
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Onze lijnen</div>
		<h2 class="oz-hp-heading">Welke Beton Cire past <em>bij jou?</em></h2>
	</div>

	<div class="oz-hp-product">
		<div class="oz-hp-product-visual">
			<img src="<?php echo esc_url( "$up/2026/04/Landscape-All-In-One-Met-Onderkant-Donker-4.webp" ); ?>" alt="Beton Cire All-In-One" loading="lazy">
		</div>
		<div class="oz-hp-product-info">
			<div class="oz-hp-product-label">Kant &amp; Klaar</div>
			<h3 class="oz-hp-product-name">Beton Cire All-In-One</h3>
			<div class="oz-hp-product-rating">Makkelijkheid: 5/5 &nbsp;|&nbsp; Duurzaamheid: 5/5 &nbsp;|&nbsp; Betaalbaarheid: 4/5</div>
			<ul class="oz-hp-product-features">
				<li>Badkamerwanden, natte cellen</li>
				<li>Huiskamervloeren en meubels</li>
				<li>Hard met een fijne structuur</li>
				<li>Slechts twee dagen werk</li>
				<li>36 kleuren en RAL en NCS</li>
				<li>Voor binnen</li>
			</ul>
			<div class="oz-hp-product-price">&euro;28 <span>per 1m&sup2;</span></div>
			<a href="/beton-cire-all-in-one-easyline-standaard-kleuren/" class="oz-hp-btn oz-hp-btn--teal">Beton Cire All-In-One</a>
		</div>
	</div>

<?php /* ================================================================
       S06 — PRODUCT: ORIGINAL (reversed)
       ================================================================ */ ?>
	<div class="oz-hp-product oz-hp-product--reverse">
		<div class="oz-hp-product-visual">
			<img src="<?php echo esc_url( "$up/2024/01/All-In-One.png" ); ?>" alt="Beton Cire Original" loading="lazy">
		</div>
		<div class="oz-hp-product-info">
			<div class="oz-hp-product-label">Kant &amp; Klaar</div>
			<h3 class="oz-hp-product-name">Beton Cire Original</h3>
			<div class="oz-hp-product-rating">Makkelijkheid: 5/5 &nbsp;|&nbsp; Duurzaamheid: 5/5 &nbsp;|&nbsp; Betaalbaarheid: 5/5</div>
			<ul class="oz-hp-product-features">
				<li>Badkamerwanden, natte cellen</li>
				<li>Huiskamervloeren en meubels</li>
				<li>Zeer hard, fijne structuur</li>
				<li>Snelste klaar</li>
				<li>90 kleuren + RAL en NCS</li>
				<li>Voor binnen en buiten</li>
			</ul>
			<div class="oz-hp-product-price">&euro;31 <span>per 1m&sup2;</span></div>
			<a href="/beton-cire-original-kleuren/" class="oz-hp-btn oz-hp-btn--teal">Beton Cire Original</a>
		</div>
	</div>

<?php /* ================================================================
       S07 — PRODUCT: LAVASTEEN
       ================================================================ */ ?>
	<div class="oz-hp-product">
		<div class="oz-hp-product-visual">
			<img src="<?php echo esc_url( "$up/2024/02/ruimte-badkamer-2.webp" ); ?>" alt="Beton Cire Lavasteen" loading="lazy">
		</div>
		<div class="oz-hp-product-info">
			<div class="oz-hp-product-label">Kant &amp; Klaar</div>
			<h3 class="oz-hp-product-name">Beton Cire Lavasteen</h3>
			<div class="oz-hp-product-rating">Makkelijkheid: 5/5 &nbsp;|&nbsp; Duurzaamheid: 5/5 &nbsp;|&nbsp; Betaalbaarheid: 5/5</div>
			<ul class="oz-hp-product-features">
				<li>Badkamer vloeren en wanden</li>
				<li>Voor horeca, huiskamer vloeren</li>
				<li>Extreem hard door epoxy</li>
				<li>2 tot 3 dagen werk</li>
				<li>Keuze uit 20 nieuwe kleuren</li>
				<li>Voor binnen en buiten</li>
			</ul>
			<div class="oz-hp-product-price">&euro;47/m&sup2; <span>per 5m&sup2;</span></div>
			<a href="/product-categorie/lavasteen/" class="oz-hp-btn oz-hp-btn--teal">Beton Cire Lavasteen</a>
		</div>
	</div>
</section>

<?php /* ================================================================
       S07b — ZO WERKT HET
       ================================================================ */ ?>
<section class="oz-hp-section">
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">In 4 stappen klaar</div>
		<h2 class="oz-hp-heading">Zo werkt het</h2>
	</div>
	<div class="oz-hp-steps-grid">
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
<section class="oz-hp-section oz-hp-section--sand">
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
<section class="oz-hp-section" style="padding:0">
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
<section class="oz-hp-section oz-hp-section--sand">
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
       ================================================================ */ ?>
<section class="oz-hp-section">
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Ervaringen</div>
		<h2 class="oz-hp-heading">Wat klanten <em>zeggen</em></h2>
	</div>
	<div class="oz-hp-reviews-grid">
		<div class="oz-hp-review">
			<p class="oz-hp-review-text">"PU topcoat op ons aanrecht, 5 lagen. Na 2 weken nog steeds prachtig."</p>
			<div class="oz-hp-review-author">Raggy W. &mdash; Maart 2026</div>
		</div>
		<div class="oz-hp-review">
			<p class="oz-hp-review-text">"Ontzettend fijne zaak. Vriendelijk, zeer goede uitleg."</p>
			<div class="oz-hp-review-author">Jacqueline H. &mdash; Feb 2026</div>
		</div>
		<div class="oz-hp-review">
			<p class="oz-hp-review-text">"Prachtige eettafel gemaakt. Mooi materiaal, fijn te verwerken."</p>
			<div class="oz-hp-review-author">Dennis S. &mdash; Jan 2026</div>
		</div>
		<div class="oz-hp-review">
			<p class="oz-hp-review-text">"Ongelofelijke service. Professioneel en vriendelijk advies op maat."</p>
			<div class="oz-hp-review-author">Elisabeth P. &mdash; Feb 2026</div>
		</div>
	</div>
	<div class="oz-hp-reviews-score">
		<div class="oz-hp-reviews-score-num">4.8 / 5.0</div>
		<div class="oz-hp-reviews-score-label">200+ Google Reviews</div>
	</div>
</section>

<?php /* ================================================================
       S11 — SHOWROOM
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand">
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
       S12 — WAT IS BETON CIRE?
       ================================================================ */ ?>
<section class="oz-hp-section">
	<div class="oz-hp-text-block">
		<h2>Wat is Beton Cire?</h2>
		<p>Beton Cire vindt zijn oorsprong in Frankrijk en wordt al ruim 25 jaar toegepast. De letterlijke vertaling is 'gewreven beton', wat verwijst naar de manier waarop het aangebracht wordt. Onze Beton cire is onderhoud vrij en gaat jaren mee.</p>
		<p>Beton cire is onder de doe het zelvers een steeds populairder wordende vorm van betonstuc, op basis van cement, met watervaste eigenschappen en een strakke betonlook. Het is geschikt voor bijna alle ondergronden, zoals hout, cement of gips, en kan online worden gekocht bij ons. Het is de ideale keuze voor een moderne en stijlvolle afwerking.</p>
		<p>Of je nu op zoek bent naar een prachtige vloer of een waterdichte betonlook voor je badkamer, het product biedt een veelzijdige oplossing. Bezoek onze showroom voor meer informatie en ontdek ons mooie product voor alle objecten. De decoratieve stuc kan ook worden aangebracht over tegels. De tegels hoeven niet te worden gesloopt en kunnen blijven zitten, zolang ze maar vast zitten. Er wordt een egalisatie laag aangebracht over de tegels voordat het kan worden aangebracht.</p>
	</div>
</section>

<?php /* ================================================================
       S13 — KOPEN & PRIJSFACTOREN
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand">
	<div class="oz-hp-text-block">
		<h2>Beton cire kopen, of bestellen en prijsfactoren</h2>
		<p>Beton Cire kopen in onze fysieke winkels als via de online webshop. We hebben alles op voorraad. Binnen 15 minuten loop je met je bestelling de deur uit. Klanten hebben de mogelijkheid om uit diverse kleuren te kiezen, en er zijn traditionele varianten waarbij pigmenten zelf gemengd moeten worden als ook kant-en-klare varianten waarbij de pigmenten al gemengd zijn.</p>
		<p>Bij een online aankoop bij de Beton cire webshop ontvangt de klant een totaalpakket. In het keuze menu kun je een totaal pakket selecteren, met de kleur naar wens en hoe waterdicht het object moet zijn. Hier kun je kiezen uit meerdere pu topcoat lagen. Denk aan een badkamer die zeer waterdicht moet zijn.</p>
		<p>Bij de Beton Cire Webshop in Den Haag vind je alles wat je nodig hebt voor jouw beton cire projecten. Beton cire, een veelzijdige en duurzame afwerking, is ideaal voor vloeren, muren, en meubels, zowel binnen als buiten. De webshop biedt een breed scala aan kleuren en afwerkingen, zodat je altijd het perfecte product vindt dat aansluit bij jouw wensen.</p>
		<p>Onze webshop levert door heel Europa, Belgie, Duitsland, Frankrijk en Spanje. Maar ook buiten de EU. Bestellingen die op werkdagen voor 14.00 uur worden gedaan worden dezelfde dag nog uitgeleverd aan onze logistieke partij.</p>
	</div>
</section>

<?php /* ================================================================
       S14 — POPULAIRE PRODUCTEN (carousel)
       ================================================================ */ ?>
<section class="oz-hp-section">
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Bestsellers</div>
		<h2 class="oz-hp-heading">Populaire <em>producten</em></h2>
	</div>
	<div class="oz-hp-carousel-track">
		<?php
		$popular = wc_get_products([
			'limit'   => 8,
			'orderby' => 'popularity',
			'status'  => 'publish',
		]);
		if ( $popular ) :
			foreach ( $popular as $prod ) :
				$img  = wp_get_attachment_image_url( $prod->get_image_id(), 'woocommerce_thumbnail' );
				$link = $prod->get_permalink();
		?>
		<a href="<?php echo esc_url( $link ); ?>" class="oz-hp-carousel-card">
			<div class="oz-hp-carousel-card-img">
				<?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $prod->get_name() ); ?>" loading="lazy"><?php endif; ?>
			</div>
			<div class="oz-hp-carousel-card-body">
				<div class="oz-hp-carousel-card-title"><?php echo esc_html( $prod->get_name() ); ?></div>
				<div class="oz-hp-carousel-card-price"><?php echo $prod->get_price_html(); ?></div>
			</div>
		</a>
		<?php endforeach; endif; ?>
	</div>
</section>

<?php /* ================================================================
       S16 — DIY OF PROFESSIONEEL
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand">
	<div class="oz-hp-text-block">
		<h2>Beton cire aanbrengen DIY, of toch professioneel laten aanbrengen</h2>
		<p>Het Beton cire aanbrengen kan zelf gedaan worden door doe-het-zelvers of uitbesteed aan een professional, afhankelijk van ervaring en gewenste resultaten. Het zelf aanbrengen biedt de vrijheid om creatief te zijn in de toepassing en zelf voor een groot deel bepalen hoe het er uit komt te zien en veel geld te besparen. Nagenoeg voor alle oppervlakte en de vele mogelijkheden de juiste keuze voor je renovatie.</p>
		<p>Is het lastig om zelf Beton cire of Microcement aan te brengen? Het is goed te doen door iedereen die een beetje handig is. Afhankelijk van je toepassing, kennis en ervaring kun je kiezen voor beton cire All in one, en EasyLine Kant-en-klare Beton cire. Deze varianten zijn reeds voorgemengd en kan makkelijk worden aangebracht op een schone, egale en gelijkzuigende ondergrond.</p>
		<p>Tip van Patrick, onze specialist: Bestel altijd iets meer dan je denkt nodig te hebben! Zo heb je altijd een beetje over om eventuele oneffenheden die tijdens het aanbrengen van zijn ontstaan snel te kunnen verhelpen.</p>
	</div>
</section>

<?php /* ================================================================
       S17 — WATERDICHT MAKEN
       ================================================================ */ ?>
<section class="oz-hp-section">
	<div class="oz-hp-text-block">
		<h2>Beton Cire en Microcement waterdicht maken</h2>
		<p>Het waterdicht maken van Beton cire en microcement is het allerbelangrijkste aspect van het hele proces. Het waterdicht maken gebeurd door middel van onze polyurtehane top coat. Pu is de afkorting. Pu bestaat uit een A en een B komponent die elkaar versterken en deze pu zorgt er voor dat het object volledig waterdicht wordt. Door de waterdichtheid biedt het de mogelijkheid om het toe te passen in de badkamer. Dat maakt het geschikt voor vele toepassingen. Dan wil je toch nooit meer tegels hebben.</p>
	</div>
</section>

<?php /* ================================================================
       S18 — PRODUCTKEUZE GIDS
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand">
	<div class="oz-hp-text-block">
		<h2>Kies het juiste product voor je doe-het-zelf project</h2>
		<p>Afhankelijk van je project kun je kiezen uit verschillende soorten. Wij bieden verschillende soorten Beton cire aan, maar ook Microcement en Lavasteen gietvloeren. Ze zijn allemaal kant en klaar gemixed op kleur. Bovendien kunnen ze allemaal met een extra matte pu topcoat geleverd worden.</p>

		<h3>Beton Cire Original en microcement kant-en-klaar</h3>
		<p>Fijne structuur. Snel droog en klaar. Hard. Wil je zelf aan de slag en wil je het super glad hebben en de echte betonlook krijgen, dan raden wij jou zeker aan te kiezen voor Beton cire Original of microcement. Met deze kant en klare pasta ben je super snel klaar.</p>

		<h3>Beton Cire Easyline</h3>
		<p>Drukke tekening door grof en fijn. Easyline heeft 2 verschillende lagen. De eerste laag is grof en de tweede laag is fijn. Daardoor zie je de eerste grove laag door de fijne tweede laag heen en dat geeft een drukke tekening.</p>

		<h3>Beton Cire All in one</h3>
		<p>Drukke tekening door schuren. Bij de All in one heb je ook dezelfde extra stap als bij de Easyline. Deze heeft ook langer de tijd nodig om te drogen.</p>

		<h3>Lavasteen gietvloeren</h3>
		<p>Ook voor wanden. Extreem hard. De lavasteen gietvloeren bestaan uit een A en een B component. Deze variant is veruit het hardste product op de markt. Bovendien is deze van zich zelf waterdicht, dus perfect voor douche vloeren en buiten.</p>
	</div>
</section>

<?php /* ================================================================
       S19 — PROFESSIONEEL AANBRENGEN
       ================================================================ */ ?>
<section class="oz-hp-section">
	<div class="oz-hp-text-block">
		<h2>Professioneel laten aanbrengen</h2>
		<p>Het inschakelen van een professional voor het aanbrengen heeft verschillende voordelen. Beton cire waterdicht krijgen is een handigheid die we je of je specialist bij kunnen brengen met een workshop op maat.</p>
		<ul class="oz-hp-product-features" style="max-width:400px">
			<li>Het bespaart tijd</li>
			<li>Je bent verzekerd van een mooi en duurzaam resultaat</li>
			<li>Je bespaart jezelf de moeite</li>
		</ul>
		<p style="margin-top:20px"><a href="/offerte/" class="oz-hp-btn oz-hp-btn--teal">Offerte aanvragen</a></p>
	</div>
</section>

<?php /* ================================================================
       S20 — INSPIRATIE
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand">
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Portfolio</div>
		<h2 class="oz-hp-heading">Beton cire <em>inspiratie</em></h2>
	</div>
	<div class="oz-hp-inspo-grid">
		<div class="oz-hp-inspo-card oz-hp-inspo-main">
			<img src="<?php echo esc_url( "$up/2024/02/ruimte-badkamer-2.webp" ); ?>" alt="Beton cire badkamer inspiratie" loading="lazy">
		</div>
		<div class="oz-hp-inspo-card">
			<img src="<?php echo esc_url( "$up/2024/02/Keuken-Marloes-daily.webp" ); ?>" alt="Beton cire keuken inspiratie" loading="lazy">
		</div>
		<div class="oz-hp-inspo-card">
			<img src="<?php echo esc_url( "$up/2024/02/Tv-Meubel-1004-Original.webp" ); ?>" alt="Beton cire meubel inspiratie" loading="lazy">
		</div>
	</div>
	<div style="text-align:center;margin-top:32px">
		<a href="/inspiratie/" class="oz-hp-btn oz-hp-btn--teal">Alle inspiratie bekijken</a>
	</div>
</section>

<?php /* ================================================================
       S21 — BELANGRIJKSTE PUNTEN
       ================================================================ */ ?>
<section class="oz-hp-section">
	<div class="oz-hp-points">
		<div class="oz-hp-text-block" style="padding:0">
			<h2>Creeer een unieke stijl met Beton Cire</h2>
			<p>Beton cire is een duurzame keuze voor zowel vloeren, muur, badkamers of toilet. Lees hier hoe je het kunt toepassen en onderhouden.</p>
			<p>Waterbestendig, veelzijdig materiaal geschikt voor vloeren, muren en keukenbladen, en biedt een moderne, stijlvolle uitstraling. Het waterdicht afwerken gebeurd met een 2 componenten pu coating. Je object is bestand tegen water en vlekken.</p>
			<p>Het materiaal is verkrijgbaar door de kleurpigment in meer dan 5000 kleuren en verschillende afwerkingsmogelijkheden, zoals structuur of juist glad, wat aanpassing aan persoonlijke voorkeuren mogelijk maakt.</p>
		</div>
		<div class="oz-hp-points-img">
			<img src="<?php echo esc_url( "$up/2024/01/Toilet-NA-Pim-Mossel.jpg" ); ?>" alt="Beton cire in toilet en douche" loading="lazy">
		</div>
	</div>
</section>

<?php /* ================================================================
       S22 — RUIMTE-TOEPASSINGEN (4 SEO texts)
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand">
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Ruimtes</div>
		<h2 class="oz-hp-heading">Toepassingen per <em>ruimte</em></h2>
	</div>
	<div class="oz-hp-ruimte-grid">
		<div class="oz-hp-ruimte-item">
			<h3>Beton Cire badkamer</h3>
			<p>Beton Cire is bij een juiste behandeling volledig waterdicht, waardoor het ideaal is voor natte ruimtes zoals badkamers. Dankzij de vochtwerende en niet-poreuze eigenschappen is het bestand tegen de groei van schimmels en bacterien, waardoor een hygienischer omgeving ontstaat. De naadloze toepassing vermindert het onderhoud en verbetert de hygiene, omdat er geen voegen zijn waar vuil of bacterien zich kunnen ophopen.</p>
		</div>
		<div class="oz-hp-ruimte-item">
			<h3>Beton Cire vloeren</h3>
			<p>Het is geschikt voor vloeren en kan op vrijwel ieder type dekvloer gelegd worden, mits de vloer stevig, vlak, droog, stof- en vetvrij is. Het biedt met gepast onderhoud en bescherming een hoge duurzaamheid. Een Beton cire vloer in je huiskamer gecombineerd met minimalistisch aantal meubels geeft een geweldige luxe uitstraling voor het gehele oppervlak.</p>
		</div>
		<div class="oz-hp-ruimte-item">
			<h3>Beton Cire wanden</h3>
			<p>Als wanden en muren is het de juiste keuze en biedt het slijtvaste en vloeistofdichte oppervlakken die onderhoudsvriendelijk zijn. Het is geschikt voor wanden in diverse ruimtes, zoals in je badkamer, woonkamer of trappengat en kan gecombineerd worden met andere materialen voor esthetische contrasten.</p>
		</div>
		<div class="oz-hp-ruimte-item">
			<h3>Beton Cire keukenbladen en spatschermen</h3>
			<p>Beton Cire is een zeer gewild materiaal voor gebruik voor keukens aanrechtbladen en spatschermen dankzij zijn waterdichte karakteristieken. Het biedt een naadloze en vochtwerende betonlook afwerking waarmee het risico op lekkage en vochtschade vermindert.</p>
		</div>
	</div>
</section>

<?php /* ================================================================
       S24 — KENNISBANK
       ================================================================ */ ?>
<section class="oz-hp-section">
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Kennisbank</div>
		<h2 class="oz-hp-heading">Lees meer in onze <em>kennisbank</em></h2>
	</div>
	<div class="oz-hp-kb-grid">
		<?php
		$kb_articles = get_posts([
			'post_type'      => 'post',
			'posts_per_page' => 6,
			'orderby'        => 'date',
			'order'          => 'DESC',
		]);
		foreach ( $kb_articles as $article ) :
		?>
		<a href="<?php echo esc_url( get_permalink( $article ) ); ?>" class="oz-hp-kb-card">
			<div class="oz-hp-kb-card-title"><?php echo esc_html( $article->post_title ); ?></div>
			<div class="oz-hp-kb-card-excerpt"><?php echo esc_html( wp_trim_words( $article->post_content, 18 ) ); ?></div>
			<span class="oz-hp-kb-card-link">Lees meer &rarr;</span>
		</a>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ================================================================
       S25 — FAQ
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand" id="faq">
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

</main>

<?php
do_action( 'flatsome_after_page' );
get_footer();
