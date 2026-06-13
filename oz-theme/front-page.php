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

$up    = home_url( '/wp-content/uploads' );
$theme = get_stylesheet_directory_uri();
?>

<div id="content" class="oz-hp" role="main">



<?php /* S01 — Glass nav removed. Sitewide oz-header in header.php replaces it. */ ?>

<?php /* ================================================================
       S02 — HERO (v2 — material editorial, replaces glass-card layout)
       ================================================================ */ ?>
<style id="bcw-hero-v2-styles">
/* ============================================================
   BCW HERO V2 — re-tokened naar het OzTheme design-system (2026-06-10).
   Layout-richting van Patrick behouden (foto links / tekst rechts,
   kicker "01", photo-meta caption, meta-rij, secondary link).
   Wijzigingen t.o.v. de eerste versie:
   - Kleuren: oklch-bruintinten → var(--oz-accent) teal + systeemtokens
   - Fonts: Fraunces/ui-sans → var(--oz-ff-heading) + var(--oz-ff-body)
   - CTA: zwart vierkant (radius 2px) → oranje pill var(--oz-cta)
   - Dark scrim op de foto verwijderd (fris/licht brand-richting)
   - Hero-hoogte gelijkgetrokken met ruimte-pages: clamp(440px, 56vh, 560px)
   - Foto loopt tot bovenaan door ONDER de fixed glass-nav (overlay-page),
     tekstkolom krijgt nav-clearance padding — glass-over-foto effect terug
   - ≥1700px "frame" (padding + box-shadow) verwijderd
   ============================================================ */
.bcw-hero-v2 {
	background: var(--oz-bg-warm, #F5F4F0);
	font-family: var(--oz-ff-body, 'Raleway', system-ui, sans-serif);
	color: var(--oz-text-primary, #1A1A1A);
}
.bcw-hero-v2__inner {
	display: grid;
	grid-template-columns: 7fr 5fr;
	max-width: 1600px;
	margin: 0 auto;
	min-height: clamp(440px, 56vh, 560px);
}
.bcw-hero-v2__photo {
	position: relative;
	overflow: hidden;
	background: var(--oz-bg-warm, #F5F4F0);
}
.bcw-hero-v2__photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	object-position: center center;
	display: block;
}
.bcw-hero-v2__photo-meta {
	position: absolute;
	bottom: 32px;
	left: 32px;
	color: #FFF;
	font-size: 12px;
	letter-spacing: 0.12em;
	text-transform: uppercase;
	z-index: 2;
	/* Leesbaar op lichte foto's zonder dark scrim over de hele foto */
	text-shadow: 0 1px 14px rgba(0, 0, 0, 0.5), 0 1px 3px rgba(0, 0, 0, 0.35);
}
.bcw-hero-v2__photo-meta strong {
	display: block;
	font-family: var(--oz-ff-heading, 'DM Serif Display', Georgia, serif);
	font-style: italic;
	font-weight: 400;
	font-size: 20px;
	text-transform: none;
	letter-spacing: -0.01em;
	margin-top: 8px;
	color: #FFF;
}
.bcw-hero-v2__text {
	/* Top-padding: nav-clearance (fixed glass-nav is 60-76px hoog) */
	padding: clamp(80px, 11vh, 100px) clamp(24px, 4vw, 56px) clamp(28px, 4vh, 48px);
	display: flex;
	flex-direction: column;
	justify-content: center;
	gap: 24px;
}
.bcw-hero-v2__kicker {
	font-size: 12px;
	letter-spacing: 0.2em;
	text-transform: uppercase;
	color: var(--oz-text-muted, #6B6B6B);
	display: flex;
	align-items: center;
	gap: 12px;
	font-weight: 600;
}
.bcw-hero-v2__headline {
	font-family: var(--oz-ff-heading, 'DM Serif Display', Georgia, serif);
	font-weight: 400;
	font-size: clamp(32px, 3vw, 48px);
	line-height: 1.05;
	letter-spacing: -0.01em;
	color: var(--oz-text-primary, #1A1A1A);
	margin: 0;
	max-width: 14ch;
}
.bcw-hero-v2__headline em {
	font-style: italic;
	color: var(--oz-accent, #135350);
	font-weight: 400;
}
.bcw-hero-v2__sub {
	font-size: 18px;
	line-height: 1.6;
	color: var(--oz-text-body, #555);
	max-width: 42ch;
	margin: 0;
}
.bcw-hero-v2__sub strong {
	color: var(--oz-text-primary, #1A1A1A);
	font-weight: 600;
	border-bottom: 2px solid var(--oz-accent, #135350);
	padding-bottom: 1px;
}
/* Badge zonder pill — zelfde patroon als .oz-hp-hero-badge op de
   ruimte-pages (svg teal + muted tekst, geen achtergrond/border). */
.bcw-hero-v2__no-skill {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	font-weight: 600;
	color: var(--oz-text-muted, #6B6B6B);
	margin-top: 4px;
	align-self: flex-start;
}
.bcw-hero-v2__no-skill svg {
	width: 16px;
	height: 16px;
	flex-shrink: 0;
	color: var(--oz-accent, #135350);
}
.bcw-hero-v2__actions {
	margin-top: 8px;
}
/* Main hero-CTA = product/navigatie (teal). De kleurstalen-conversie zit in
   de glass-card op de foto (oranje) — geen dubbele kleurstalen-knoppen. */
.bcw-hero-v2__cta {
	background: var(--oz-btn-product-bg, #135350);
	color: #FFF;
	padding: 16px 24px;
	text-decoration: none;
	font-size: 14px;
	font-weight: 600;
	letter-spacing: 0.01em;
	display: inline-flex;
	align-items: center;
	gap: 16px;
	transition: background var(--oz-t-fast, 0.2s) var(--oz-ease, cubic-bezier(.22,1,.36,1)), gap var(--oz-t-fast, 0.2s) var(--oz-ease, cubic-bezier(.22,1,.36,1));
	border-radius: var(--oz-radius, 8px);
}
.bcw-hero-v2__cta:hover, .bcw-hero-v2__cta:focus-visible {
	background: var(--oz-btn-product-bg-hover, #1A6B67);
	gap: 20px;
	color: #FFF;
	text-decoration: none;
}
.bcw-hero-v2__cta-arrow {
	width: 24px;
	height: 1px;
	background: currentColor;
	position: relative;
	flex-shrink: 0;
}
.bcw-hero-v2__cta-arrow::after {
	content: "";
	position: absolute;
	right: 0;
	top: -4px;
	width: 8px;
	height: 8px;
	border-top: 1px solid currentColor;
	border-right: 1px solid currentColor;
	transform: rotate(45deg);
}
.bcw-hero-v2__meta {
	display: flex;
	gap: 32px;
	margin-top: 16px;
	font-size: 13px;
	color: var(--oz-text-muted, #6B6B6B);
	flex-wrap: wrap;
}
.bcw-hero-v2__meta-item {
	display: flex;
	flex-direction: column;
	gap: 4px;
}
.bcw-hero-v2__meta-item strong {
	font-family: var(--oz-ff-heading, 'DM Serif Display', Georgia, serif);
	font-weight: 400;
	font-size: 20px;
	color: var(--oz-accent, #135350);
	letter-spacing: -0.01em;
}

/* Mobile-only kleurstalen-CTA: de glass-card op de foto is verborgen op
   kleine schermen (zie homepage-v2.css), dus daar komt de kleurstalen-
   conversie terug als tweede knop onder de hoofd-CTA. Oranje = transactie. */
.bcw-hero-v2__cta--stalen-mobile {
	display: none;
	background: var(--oz-btn-cta-bg, #E67C00);
	margin-top: 8px;
}
.bcw-hero-v2__cta--stalen-mobile:hover,
.bcw-hero-v2__cta--stalen-mobile:focus-visible {
	background: var(--oz-btn-cta-bg-hover, #D36F00);
}

/* Responsive */
@media (max-width: 900px) {
	.bcw-hero-v2__inner {
		grid-template-columns: 1fr;
		min-height: auto;
	}
	.bcw-hero-v2__photo {
		min-height: 56vh;
		order: 1;
	}
	.bcw-hero-v2__text {
		order: 2;
		padding: 40px 24px 48px;
	}
	.bcw-hero-v2__actions {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 8px;
	}
	.bcw-hero-v2__cta--stalen-mobile { display: inline-flex; margin-top: 0; }
	.bcw-hero-v2__meta { gap: 24px; }
}
</style>

<section class="bcw-hero-v2" aria-label="Beton Ciré — naadloze betonlook">
  <div class="bcw-hero-v2__inner">
	<div class="bcw-hero-v2__photo">
		<img
			src="<?php echo esc_url( "$up/2026/03/Beton-Badkamer-Placeholder-2-1-1024x683.avif" ); ?>"
			srcset="<?php echo esc_url( "$up/2026/03/Beton-Badkamer-Placeholder-2-1-768x512.avif" ); ?> 768w, <?php echo esc_url( "$up/2026/03/Beton-Badkamer-Placeholder-2-1-1024x683.avif" ); ?> 1024w, <?php echo esc_url( "$up/2026/03/Beton-Badkamer-Placeholder-2-1.avif" ); ?> 1536w"
			sizes="(max-width: 900px) 100vw, 58vw"
			alt="Beton Ciré badkamer met naadloze wand en vrijstaand bad — geen voegen, geen tegels"
			width="1536" height="1024"
			loading="eager" fetchpriority="high" decoding="async" data-no-lazy="1">
		<div class="bcw-hero-v2__photo-meta">
			Project &middot; Badkamer<strong>Microcement wand, Cream Peony</strong>
		</div>
		<div class="oz-hp-hero-glass">
			<div class="oz-hp-eyebrow">Gratis kleurstalen</div>
			<div class="oz-hp-hero-glass-title">Zeker van je kleur?</div>
			<p class="oz-hp-hero-glass-desc">Selecteer tot 4 kleuren uit onze lijn. We sturen ze gratis naar je toe.</p>
			<a href="/kleurstalen-aanvragen/" class="oz-hp-hero-glass-link">Stalen aanvragen <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
		</div>
	</div>
	<div class="bcw-hero-v2__text">
		<div class="bcw-hero-v2__kicker">Beton ciré &middot; doe het zelf</div>
		<h1 class="bcw-hero-v2__headline">Een wand <em>zonder voegen</em>.<br>Een vloer <em>zonder naden</em>.</h1>
		<p class="bcw-hero-v2__sub">Voor badkamer, vloer, keuken, trap en meubels.<br>Kant-en-klaar pakket. Kies uit 100+ kleuren of bezoek de showroom in Den Haag.</p>
		<div class="bcw-hero-v2__actions">
			<a href="/ruimtes/" class="bcw-hero-v2__cta">Bekijk per ruimte <span class="bcw-hero-v2__cta-arrow" aria-hidden="true"></span></a>
			<a href="/kleurstalen-aanvragen/" class="bcw-hero-v2__cta bcw-hero-v2__cta--stalen-mobile">Gratis stalen aanvragen</a>
		</div>
		<div class="bcw-hero-v2__no-skill">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
			<span>Geen ervaring nodig &mdash; doe het zelf</span>
		</div>
		<div class="bcw-hero-v2__meta">
			<div class="bcw-hero-v2__meta-item"><strong>100+</strong>kleuren op staal</div>
			<div class="bcw-hero-v2__meta-item"><strong>4,8 / 5</strong>op 480 reviews</div>
			<div class="bcw-hero-v2__meta-item"><strong>Den Haag</strong>showroom &amp; advies</div>
		</div>
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
			'5000+ kleuren via RAL en NCS',
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
		<h2 class="oz-hp-heading">Onze producten voor <em>elke ruimte</em></h2>
		<p class="oz-hp-section-intro">Kies het product dat het beste bij jouw project past. Alle producten zijn waterdicht en geschikt voor natte ruimtes.</p>
	</div>
	<div class="oz-hp-products-3col" data-reveal-stagger>
		<div class="oz-hp-pcard">
			<a class="oz-hp-pcard-img-wrap" href="/beton-cire-all-in-one/">
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
			<a href="/beton-cire-all-in-one/" class="oz-hp-btn oz-hp-btn--teal">Beton Ciré All-In-One</a>
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
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/02/ruimte-badkamer-2-600.webp" ); ?>" alt="Beton cire badkamer" width="600" height="875" loading="lazy" decoding="async">
				<div class="oz-hp-ruimtes-card-content">
					<div class="oz-hp-ruimtes-card-name">Badkamer</div>
					<div class="oz-hp-ruimtes-card-desc">Waterdichte betonlook voor douche, wand en vloer. Schimmelwerend en makkelijk te onderhouden.</div>
					<span class="oz-hp-ruimtes-card-cta">Bekijk badkamer</span>
				</div>
			</a>
			<a href="/ruimtes/beton-cire-keuken/" class="oz-hp-ruimtes-card">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/02/Keuken-Marloes-daily-700.webp" ); ?>" alt="Beton cire keuken" width="700" height="933" loading="lazy" decoding="async">
				<div class="oz-hp-ruimtes-card-content">
					<div class="oz-hp-ruimtes-card-name">Keuken</div>
					<div class="oz-hp-ruimtes-card-desc">Keukenbladen, aanrecht en spatschermen in naadloze betonlook. Waterbestendig en vlekvrij.</div>
					<span class="oz-hp-ruimtes-card-cta">Bekijk keuken</span>
				</div>
			</a>
			<a href="/ruimtes/beton-cire-toilet/" class="oz-hp-ruimtes-card">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/01/Toilet-NA-Pim-Mossel-700.webp" ); ?>" alt="Beton cire toilet" width="700" height="525" loading="lazy" decoding="async">
				<div class="oz-hp-ruimtes-card-content">
					<div class="oz-hp-ruimtes-card-name">Toilet</div>
					<div class="oz-hp-ruimtes-card-desc">Van wastafel tot wand: een naadloze betonlook waar geen tegel of voeg aan te pas komt.</div>
					<span class="oz-hp-ruimtes-card-cta">Bekijk toilet</span>
				</div>
			</a>
		</div>
		<div class="oz-hp-ruimtes-row2" data-reveal-stagger>
			<a href="/ruimtes/beton-cire-vloer/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2023/11/ruimte-vloer-450.webp" ); ?>" alt="Beton cire vloer" width="450" height="656" loading="lazy" decoding="async">
				<div class="oz-hp-ruimtes-card-content"><div class="oz-hp-ruimtes-card-name">Vloer</div></div>
			</a>
			<a href="/ruimtes/beton-cire-wand/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/02/Woonkamer-wand-450.webp" ); ?>" alt="Beton cire wand" width="450" height="600" loading="lazy" decoding="async">
				<div class="oz-hp-ruimtes-card-content"><div class="oz-hp-ruimtes-card-name">Wand</div></div>
			</a>
			<a href="/ruimtes/beton-cire-trappen/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2024/02/Beton-cire-open-trap-450.webp" ); ?>" alt="Beton cire trap" width="450" height="600" loading="lazy" decoding="async">
				<div class="oz-hp-ruimtes-card-content"><div class="oz-hp-ruimtes-card-name">Trap</div></div>
			</a>
			<a href="/ruimtes/beton-cire-meubel/" class="oz-hp-ruimtes-card oz-hp-ruimtes-card--compact">
				<img class="oz-hp-ruimtes-card-img" src="<?php echo esc_url( "$up/2023/11/ruimte-meubel-450.webp" ); ?>" alt="Beton cire meubels" width="450" height="656" loading="lazy" decoding="async">
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
			<p class="oz-hp-step-desc">Uit 100+ kleuren of bestel gratis kleurstalen om thuis te vergelijken.</p>
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
       S07c — VOOR & NA: drag slider + 5-stage cycler
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand oz-vn" data-reveal>
	<div class="oz-hp-section-header">
		<div class="oz-hp-eyebrow">Resultaat</div>
		<h2 class="oz-hp-heading">Voor &amp; <em>na</em></h2>
	</div>
	<div class="oz-vn-grid">
		<div class="oz-vn-card">
			<div class="oz-vn-drag" data-vn-drag role="img" aria-label="Sleep om voor en na te vergelijken">
				<img class="oz-vn-drag-base" src="<?php echo esc_url( "$up/2026/05/beton-cire-badkamer-voor.webp" ); ?>" alt="Beton Ciré badkamer vóór de renovatie — tegels en voegen" loading="lazy" decoding="async" draggable="false">
				<div class="oz-vn-drag-overlay">
					<img src="<?php echo esc_url( "$up/2026/05/beton-cire-badkamer-na.webp" ); ?>" alt="Beton Ciré badkamer ná de renovatie — naadloze betonlook" loading="lazy" decoding="async" draggable="false">
				</div>
				<div class="oz-vn-drag-handle" aria-hidden="true"><div class="oz-vn-drag-handle-knob">&lsaquo; &rsaquo;</div></div>
				<span class="oz-vn-label oz-vn-label--na">Na</span>
				<span class="oz-vn-label oz-vn-label--voor">Voor</span>
			</div>
			<p class="oz-vn-caption">Badkamer-renovatie — sleep om te vergelijken</p>
		</div>
		<div class="oz-vn-card">
			<div class="oz-vn-stages" data-vn-stages>
				<img class="oz-vn-stage is-active" src="<?php echo esc_url( "$up/2026/05/1-beton-cire-keukeneiland-geraamte.webp" ); ?>" alt="Stap 1: keukeneiland geraamte" loading="lazy" decoding="async">
				<img class="oz-vn-stage" src="<?php echo esc_url( "$up/2026/05/2-beton-cire-keukeneiland-grondlaag.webp" ); ?>" alt="Stap 2: keukeneiland grondlaag aanbrengen" loading="lazy" decoding="async">
				<img class="oz-vn-stage" src="<?php echo esc_url( "$up/2026/05/3-beton-cire-keukeneiland-schuren.webp" ); ?>" alt="Stap 3: keukeneiland schuren" loading="lazy" decoding="async">
				<img class="oz-vn-stage" src="<?php echo esc_url( "$up/2026/05/4-beton-cire-keukeneiland-wasbak.webp" ); ?>" alt="Stap 4: keukeneiland wasbak installeren" loading="lazy" decoding="async">
				<img class="oz-vn-stage" src="<?php echo esc_url( "$up/2026/05/5-beton-cire-keukeneiland-eindresultaat.webp" ); ?>" alt="Stap 5: keukeneiland eindresultaat — naadloze betonlook" loading="lazy" decoding="async">
				<div class="oz-vn-stages-dots" aria-hidden="true"><span class="is-active"></span><span></span><span></span><span></span><span></span></div>
			</div>
			<p class="oz-vn-caption">Keukeneiland in 5 stappen — van geraamte tot resultaat</p>
		</div>
	</div>
	<div class="oz-vn-quote">
		<p>"Stap voor stap zelf aangepakt. Geen ervaring, wel een prachtig resultaat."</p>
		<cite>Klant review</cite>
	</div>
	<style>
	.oz-vn-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; max-width: 600px; margin: 24px auto 0; padding: 0 var(--oz-gap, 20px); }
	@media (max-width: 820px) {
		.oz-vn-grid { grid-template-columns: 1fr; gap: 16px; max-width: 480px; padding: 0 16px; }
		.oz-vn-drag, .oz-vn-stages { aspect-ratio: 3 / 4; border-radius: 8px; }
		.oz-vn-drag-handle-knob { width: 34px; height: 34px; font-size: 13px; }
		.oz-vn-label { font-size: 11px; padding: 4px 8px; bottom: 8px; }
		.oz-vn-label--na { left: 8px; } .oz-vn-label--voor { right: 8px; }
		.oz-vn-stages-dots { bottom: 8px; gap: 4px; }
		.oz-vn-stages-dots span { width: 5px; height: 5px; }
		.oz-vn-caption { font-size: 11px; }
		.oz-vn-quote { margin-top: 20px; }
		.oz-vn-quote p { font-size: 14px; }
	}
	@media (max-width: 600px) {
		.oz-vn-grid { gap: 12px; padding: 0 12px; }
		.oz-vn-drag, .oz-vn-stages { aspect-ratio: 3 / 4; }
	}
	/* Touch-target uitbreiden zonder de visuele handle te vergroten. */
	.oz-vn-drag-handle::before {
		content: "";
		position: absolute;
		top: 0; bottom: 0;
		left: -22px; right: -22px;
	}
	.oz-vn-card { display: flex; flex-direction: column; gap: 8px; }
	.oz-vn-caption { font-size: 12px; color: var(--oz-text-muted, #6B6B6B); text-align: center; margin: 0; }
	.oz-vn-drag { position: relative; width: 100%; aspect-ratio: 4 / 5; border-radius: 8px; overflow: hidden; cursor: ew-resize; user-select: none; background: var(--oz-teal-dark, #1F4543); touch-action: none; --vn-pos: 50%; }
	.oz-vn-drag-base { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center center; display: block; }
	/* Overlay is FULL-SIZE en wordt onthuld via clip-path (--vn-pos). Zo rendert
	   de "voor" foto op exact dezelfde afmetingen als de "na" foto (beide inset:0
	   van dezelfde container) en blijven ze pixel-aligned tijdens het slepen.
	   Zelfde mechanisme als .oz-rp2-vn-slider op de ruimte-pages.
	   NB: width-based clipping (overlay width 50% + img width 100% van de overlay)
	   schaalde de voor-foto mee met de drag-positie — dat was de misalign-bug. */
	.oz-vn-drag-overlay { position: absolute; inset: 0; clip-path: inset(0 calc(100% - var(--vn-pos)) 0 0); -webkit-clip-path: inset(0 calc(100% - var(--vn-pos)) 0 0); will-change: clip-path; }
	.oz-vn-drag-overlay img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center center; }
	.oz-vn-drag-handle { position: absolute; top: 0; bottom: 0; left: var(--vn-pos); width: 2px; background: #FFF; transform: translateX(-50%); pointer-events: none; box-shadow: 0 0 14px rgba(0,0,0,0.35); }
	.oz-vn-drag-handle-knob { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #FFF; color: var(--oz-teal-dark, #1F4543); width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; box-shadow: 0 2px 10px rgba(0,0,0,0.35); }
	.oz-vn-label { position: absolute; bottom: 10px; background: rgba(0,0,0,.6); color: #FFF; padding: 4px 12px; border-radius: 999px; font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; font-weight: 600; pointer-events: none; }
	.oz-vn-label--na { left: 10px; }
	.oz-vn-label--voor { right: 10px; }
	.oz-vn-stages { position: relative; width: 100%; aspect-ratio: 4 / 5; border-radius: 8px; overflow: hidden; background: var(--oz-teal-dark, #1F4543); }
	.oz-vn-stage { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center center; opacity: 0; transition: opacity 0.7s ease-in-out; }
	.oz-vn-stage.is-active { opacity: 1; }
	.oz-vn-stages-dots { position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; }
	.oz-vn-stages-dots span { width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,.5); transition: background var(--oz-t-fast, 0.2s), transform var(--oz-t-fast, 0.2s); }
	.oz-vn-stages-dots span.is-active { background: #FFF; transform: scale(1.3); }
	.oz-vn-quote { max-width: 520px; margin: 24px auto 0; text-align: center; padding: 0 var(--oz-gap, 20px); }
	.oz-vn-quote p { font-size: 16px; font-style: italic; color: var(--oz-text-body, #555); margin: 0 0 8px; line-height: 1.5; }
	.oz-vn-quote cite { font-size: 13px; color: var(--oz-text-muted, #6B6B6B); font-style: normal; letter-spacing: 0.4px; }
	</style>
	<script>
	(function(){
		'use strict';
		function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
		ready(function(){
			document.querySelectorAll('[data-vn-drag]').forEach(initDrag);
			document.querySelectorAll('[data-vn-stages]').forEach(initStages);
		});
		function initDrag(el){
			/* Guard tegen dubbele init: homepage-v2.js heeft ook een initDrag
			   voor [data-vn-drag] die op dataset.vnInited checkt. Zonder deze
			   marker draaiden BEIDE handlers op dezelfde slider. */
			if (el.dataset.vnInited === '1') return;
			el.dataset.vnInited = '1';
			var overlay = el.querySelector('.oz-vn-drag-overlay');
			var handle  = el.querySelector('.oz-vn-drag-handle');
			if (!overlay || !handle) return;
			var pos = 50, dir = 1, step = 0.35;
			var auto = true, dragging = false, resumeTimer = null;
			/* Positie via CSS custom property --vn-pos: de overlay is full-size
			   en wordt onthuld met clip-path, dus voor- en na-foto renderen
			   altijd identiek (geen img-width sync in px meer nodig). */
			function setPos(p){
				p = Math.max(0, Math.min(100, p));
				pos = p;
				el.style.setProperty('--vn-pos', p + '%');
			}
			function tick(){
				if (auto && !dragging) {
					pos += step * dir;
					if (pos >= 92) { pos = 92; dir = -1; }
					if (pos <= 8)  { pos = 8;  dir = 1; }
					setPos(pos);
				}
				requestAnimationFrame(tick);
			}
			function getX(e){ return e.touches ? e.touches[0].clientX : e.clientX; }
			function startDrag(e){
				dragging = true; auto = false;
				if (resumeTimer) clearTimeout(resumeTimer);
				/* preventDefault stopt de native image-drag van de browser
				   (anders "draag" je de foto i.p.v. de handle te slepen). */
				if (e.cancelable && e.type !== 'touchstart') e.preventDefault();
				move(e);
				el.style.cursor = 'grabbing';
			}
			function endDrag(){
				if (!dragging) return;
				dragging = false;
				el.style.cursor = 'ew-resize';
				resumeTimer = setTimeout(function(){ auto = true; }, 3500);
			}
			function move(e){
				if (!dragging) return;
				var rect = el.getBoundingClientRect();
				setPos(((getX(e) - rect.left) / rect.width) * 100);
				if (e.cancelable) e.preventDefault();
			}
			el.addEventListener('dragstart', function(e){ e.preventDefault(); });
			el.addEventListener('mousedown', startDrag);
			el.addEventListener('touchstart', startDrag, { passive: true });
			window.addEventListener('mouseup', endDrag);
			window.addEventListener('touchend', endDrag);
			window.addEventListener('mousemove', move);
			window.addEventListener('touchmove', move, { passive: false });
			setPos(50);
			requestAnimationFrame(tick);
		}
		function initStages(wrap){
			var stages = wrap.querySelectorAll('.oz-vn-stage');
			var dots   = wrap.querySelectorAll('.oz-vn-stages-dots span');
			if (stages.length < 2) return;
			var idx = 0, paused = false;
			function next(){
				if (paused) return;
				idx = (idx + 1) % stages.length;
				stages.forEach(function(s, i){ s.classList.toggle('is-active', i === idx); });
				if (dots.length) dots.forEach(function(d, i){ d.classList.toggle('is-active', i === idx); });
			}
			wrap.addEventListener('mouseenter', function(){ paused = true; });
			wrap.addEventListener('mouseleave', function(){ paused = false; });
			setInterval(next, 2200);
		}
	})();
	</script>
</section>

<?php /* ================================================================
       S08 — MICROCEMENT + KLEURSTALEN
       ================================================================ */ ?>
<section class="oz-hp-section" style="padding:0" data-reveal>
	<div class="oz-hp-split">
		<?php /* Microcement panel temporarily disabled — content hidden from Google
		          until the new URL structure is finalized. To re-enable, replace the
		          <div> below with the commented block.

		<div class="oz-hp-split-micro">
			<div class="oz-hp-eyebrow">Cementbasis</div>
			<h3>Microcement</h3>
			<p>Echt cement, ultradun, uit 1 emmer. De populairste keuze voor een strakke, moderne betonlook. Geschikt voor vloeren, wanden en badkamers.</p>
			<div class="oz-hp-meta">Harder / 4 stappen / 36 kleuren / Vanaf &euro;31/m&sup2;</div>
			<a href="/product-categorie/microcement/" class="oz-hp-btn oz-hp-btn--teal">Bekijk Microcement</a>
		</div>

		*/ ?>
		<div class="oz-hp-split-micro" style="padding:0;position:relative;overflow:hidden;">
			<img src="<?php echo esc_url( "$up/2024/03/Beton-cire-wand-jpg-e1711016471264.webp" ); ?>" alt="" aria-hidden="true" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
		</div>
		<div class="oz-hp-split-stalen">
			<div class="oz-hp-eyebrow">Gratis kleurstalen</div>
			<h3>Zeker zijn van je kleur?</h3>
			<p>Beton cire is een investering die je jarenlang ziet. Selecteer tot 4 kleuren uit onze lijn en wij sturen ze gratis naar je toe. Zo kun je thuis rustig vergelijken bij jouw lichtval en interieur.</p>
			<div class="oz-hp-meta">Gratis / Binnen 2 werkdagen / Tot 4 kleuren</div>
			<a href="/kleurstalen-aanvragen/" class="oz-hp-btn oz-hp-btn--cta">Stalen aanvragen</a>
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
				<tr><th></th><th><a href="/beton-cire-original/">Beton Ciré Original</a></th><th><a href="/microcement/">Microcement</a></th><th><a href="/beton-cire-all-in-one/">Beton Ciré All-In-One</a></th><th><a href="/beton-cire-easyline-kant-en-klaar/">Beton Ciré Easyline</a></th><th><a href="/metallic-velvet/">Metallic Velvet</a></th><th><a href="/lavasteen-gietvloer/">Lavasteen Gietvloer</a></th></tr>
			</thead>
			<tbody>
				<tr><td>Kenmerk</td><td>Echt cement</td><td>Echt cement</td><td>Drukkere tek.</td><td>Drukste tekening</td><td>Parelmoer</td><td>Epoxy slijtvast</td></tr>
				<tr><td>Hardheid</td><td>Harder</td><td>Harder</td><td>Hard</td><td>Hard</td><td>Decoratief</td><td>Extreem</td></tr>
				<tr><td>Stappen</td><td>4</td><td>4</td><td>5</td><td>5</td><td>4</td><td>4</td></tr>
				<tr><td>Kleuren</td><td>100+</td><td>36</td><td>36</td><td>36</td><td>12</td><td>20</td></tr>
				<tr><td>Emmers</td><td>1</td><td>1</td><td>1</td><td>2</td><td>1</td><td>2</td></tr>
				<tr><td>Waterdicht</td><td>Met PU</td><td>Met PU</td><td>Met PU</td><td>Met PU</td><td>Met PU</td><td>Tot in kern</td></tr>
			</tbody>
		</table>
	</div>
</section>

<?php /* S10 — ERVARINGEN (shared helper, also used on ruimte/stucsoorten pages) */
	oz_render_reviews_section( 'home' );
?>

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
				<img src="<?php echo esc_url( "$up/2024/02/beton-cire-trapgat-wand-original-kleur1005.webp" ); ?>" alt="Beton Ciré wand in trapgat, kleur 1005" loading="lazy">
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
			Kant-en-klare pakketten in Beton Cir&eacute;, Microcement of Lavasteen &mdash; DIY-vriendelijk. Complex of liever laten aanbrengen? Schakel een professional in.
		</li>
	</ul>
</section>

<?php /* ================================================================
       S14 — MEER WETEN (alternating image/text rows with "Lees meer")
       ================================================================ */ ?>
<section class="oz-hp-section oz-hp-section--sand" data-reveal>
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
				'img'    => '2024/02/Badkamer-1041-Original.webp',
				'alt'    => 'Beton Ciré badkamer kleur 1041 Original',
				'teaser' => '<p>Beton Cir&eacute; koop je in onze showroom in Den Haag &eacute;n via de online webshop. We hebben alles op voorraad &mdash; binnen 15 minuten loop je met je bestelling de deur uit. Bestellingen voor 14:00 op werkdagen gaan dezelfde werkdag nog de deur uit.</p>',
				'more'   => '<p>Klanten kunnen uit diverse kleuren kiezen. Er zijn traditionele varianten waarbij pigmenten zelf gemengd moeten worden en kant-en-klare varianten waarbij de pigmenten al gemengd zijn. Bij een online aankoop ontvang je een totaalpakket. In het keuzemenu selecteer je het pakket met de kleur naar wens en hoe waterdicht het object moet zijn &mdash; hier kies je uit meerdere PU-topcoatlagen. Denk aan een badkamer die zeer waterdicht moet zijn.</p>
				            <p>Bij de Beton Cir&eacute; Webshop in Den Haag vind je alles wat je nodig hebt voor jouw projecten: een veelzijdige en duurzame afwerking, ideaal voor vloeren, muren en meubels, zowel binnen als buiten. Je krijgt deskundig advies en ondersteuning bij je aankoop. Onze webshop levert door heel Europa &mdash; Belgi&euml;, Duitsland, Frankrijk, Spanje &mdash; maar ook daarbuiten.</p>',
			],
			[
				'q'      => 'Zelf aanbrengen of professioneel laten doen?',
				'layout' => 'full',
				'img'    => '2024/02/Wand-Metallic-velvet-Rose-T.-Brandsma.webp',
				'alt'    => 'Beton Ciré wand Metallic velvet in Rose',
				'teaser' => '<p>Beton Cir&eacute; aanbrengen kan zelf gedaan worden door doe-het-zelvers (DIY) of uitbesteed aan een professional &mdash; afhankelijk van ervaring en gewenst resultaat. Zelf aanbrengen biedt creatieve vrijheid en bespaart kosten.</p>',
				'more'   => '<p>Is het lastig om zelf Beton Cir&eacute; of Microcement aan te brengen? Het is goed te doen door iedereen die een beetje handig is. Afhankelijk van toepassing, kennis en ervaring kun je kiezen voor Beton Cir&eacute; All-In-One of Easyline &mdash; deze varianten zijn voorgemengd en eenvoudig aan te brengen op een schone, egale en gelijkzuigende ondergrond.</p>
				            <p class="oz-hp-tip"><strong>Tip van Patrick, onze specialist:</strong> bestel altijd iets meer dan je denkt nodig te hebben. Zo heb je altijd een beetje over om eventuele oneffenheden die tijdens het aanbrengen zijn ontstaan snel te kunnen verhelpen. En toch geven we genoeg mee voor het aantal vierkante meter die je bestelt.</p>
				            <p>Het inschakelen van een professional heeft voordelen: het bespaart tijd als je niet handig bent met een stucadoorsspaan, schuren of primer aanbrengen. Je bent verzekerd van een mooi en duurzaam resultaat en bespaart jezelf de moeite.</p>
				            <p><a href="/offerte/" class="oz-hp-btn oz-hp-btn--cta">Offerte aanvragen</a></p>',
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
				'img'    => '2024/02/Beton-cire-wand-original-1006.webp',
				'alt'    => 'Beton Ciré Original wand in kleur 1006',
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
			<img src="<?php echo esc_url( "$up/2024/02/beton-cire-original-badkamer-kleur-1002.webp" ); ?>" alt="Beton Ciré Original badkamer kleur 1002" loading="lazy">
		</div>
		<div class="oz-hp-inspo-card">
			<img src="<?php echo esc_url( "$up/2024/02/Beton-cire-original-keuken-kleur-1006.webp" ); ?>" alt="Beton Ciré Original keuken in kleur 1006" loading="lazy">
		</div>
		<div class="oz-hp-inspo-card">
			<img src="<?php echo esc_url( "$up/2026/02/Lavasteen-Hillflower-vloer-2-rot90.webp" ); ?>" alt="Lavasteen gietvloer Hillflower inspiratie" loading="lazy">
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
				'q' => 'Welke verschillende soorten Beton cire zijn er?',
				'a' => 'Er zijn 3 verschillende soorten en wij hebben ze alle drie. Bovendien hebben we microcement en Lavasteen. Het verschil zit hem in gemak, tekening, moeilijkheidsgraad en prijs. Onze pakketten bestaan altijd uit een aantal stappen met de daarbij behorende producten.',
			],
			[
				'q' => 'Kun je boren in Beton cire?',
				'a' => 'Ja, maar let op wat eronder zit. In de Beton cire zelf kun je prima boren &mdash; een onvormvaste ondergrond of tegelwand kan echter scheuren. Begin met een klein boortje zodat je punt niet wegloopt, en zet je boormachine niet op kloppen voordat je door de glazuurlaag heen bent. Kit het boorgaatje na afloop af.',
			],
			[
				'q' => 'Is Beton cire kwetsbaar?',
				'a' => 'Het hangt af van de gebruikte topcoat en het merk Beton cire. Onze varianten zijn erg hard en worden afgewerkt met een 2-componenten PU-topcoat die krasongevoelig is. Die topcoat is getest op UV-bestendigheid, hittebestendigheid, krasgevoeligheid en verwerkbaarheid. Een toplak is nooit hufter-proof, maar bij normaal gebruik blijft Beton cire jarenlang mooi.',
			],
			[
				'q' => 'Welke stuc onder Beton cire?',
				'a' => 'In droge ruimtes zoals woonkamer of toilet werkt een gipsgebonden stuc (roodband of MP75) prima. In natte ruimtes is een cementgebonden stuc verplicht &mdash; cement verzeept niet onder vocht, gips wel. Onze egalisatie-stuc hecht over tegels en hout en is geschikt voor extreem natte ruimtes.',
			],
			[
				'q' => 'Hoe dik is Beton cire?',
				'a' => 'Beton Cir&eacute; wordt in 1 tot 3 mm aangebracht, afhankelijk van de spaanslagen die de verwerker laat staan. De lager gelegen delen zijn meestal 1&ndash;2 mm, hoger gelegen delen tot 3 mm dik. Het is een decoratieve afwerking &mdash; geen opvulmiddel. Gaten en oneffenheden eerst vullen met een daarvoor geschikt product.',
			],
			[
				'q' => 'Welke kleuren Beton cire zijn er?',
				'a' => 'We hebben 80 standaardkleuren en kunnen bijna elke RAL- of NCS-kleur op maat maken &mdash; samen zo\'n 8000 mogelijkheden. Vraag gratis kleurstalen aan om de kleur in je eigen ruimte te beoordelen; kleuren zien er op locatie vaak anders uit door licht en omgeving.',
			],
			[
				'q' => 'Heb ik kimband nodig voor Beton cire?',
				'a' => 'Kimband is nodig in natte ruimtes, vooral bij aansluitingen van wand naar vloer. Als die aansluiting toch scheurt, scheurt het achter de kimband &mdash; je Beton cire afwerking blijft intact en waterdicht.',
			],
			[
				'q' => 'Hoe kan ik mijn Beton cire schoonmaken en onderhouden?',
				'a' => 'Stofzuig of stofwis minstens twee keer per week en dweil wekelijks met een PH-neutraal schoonmaakmiddel. Gebruik nooit een schuurspons of agressief reinigingsmiddel &mdash; deze beschadigen de topcoat. Voor dieper onderhoud staat een uitgebreide gids in onze kennisbank.',
			],
			[
				'q' => 'Waar moet je opletten als je Beton cire of Microcement gaat aanbrengen?',
				'a' => 'Werk altijd in een schone, droge, stofvrije en tochtvrije ruimte. Tocht laat de pasta te snel drogen &mdash; dan kun je niet meer netjes afwerken. Zorg daarnaast voor voldoende licht om oneffenheden op tijd te zien en bij te werken. Lees vooraf onze voorbereidingspagina en technische handleiding.',
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
