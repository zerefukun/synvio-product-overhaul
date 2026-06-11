<?php
/**
 * Block Patterns — pre-built section layouts for Gutenberg.
 * Users can insert these patterns when composing pages.
 *
 * @package OzTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register block pattern category and patterns.
 */
function oz_register_block_patterns() {

	register_block_pattern_category( 'oz-sections', [
		'label' => 'OZ Secties',
	] );

	/* Hero section — cover image with heading, text, and CTA buttons */
	register_block_pattern( 'oz/hero-section', [
		'title'       => 'Hero Sectie',
		'description' => 'Grote afbeelding met overlay tekst en knoppen',
		'categories'  => [ 'oz-sections' ],
		'content'     => '<!-- wp:cover {"dimRatio":50,"minHeight":500} -->
<div class="wp-block-cover" style="min-height:500px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">Uw titel hier</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Beschrijving van uw sectie. Pas deze tekst aan naar uw eigen inhoud.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Primaire knop</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button">Secundaire knop</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->',
	] );

	/* Feature grid — 3 columns with icons and text */
	register_block_pattern( 'oz/feature-grid', [
		'title'       => 'Feature Grid (3 kolommen)',
		'description' => 'Drie kolommen met iconen en tekst',
		'categories'  => [ 'oz-sections' ],
		'content'     => '<!-- wp:group {"backgroundColor":"oz-bg-warm","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-oz-bg-warm-background-color has-background"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">Feature 1</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Korte beschrijving van deze feature.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">Feature 2</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Korte beschrijving van deze feature.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">Feature 3</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Korte beschrijving van deze feature.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->',
	] );

	/* CTA section — teal background with heading and button */
	register_block_pattern( 'oz/cta-section', [
		'title'       => 'CTA Sectie',
		'description' => 'Gecentreerde call-to-action met achtergrondkleur',
		'categories'  => [ 'oz-sections' ],
		'content'     => '<!-- wp:group {"backgroundColor":"oz-accent","textColor":"oz-bg-page","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-oz-bg-page-color has-oz-accent-background-color has-text-color has-background"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Klaar om te beginnen?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Neem contact met ons op voor persoonlijk advies.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"oz-bg-page","textColor":"oz-accent"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-oz-accent-color has-oz-bg-page-background-color has-text-color has-background wp-element-button">Contact opnemen</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
	] );

	/* FAQ accordion — details/summary pattern */
	register_block_pattern( 'oz/faq-accordion', [
		'title'       => 'FAQ Accordion',
		'description' => 'Veelgestelde vragen met inklapbare antwoorden',
		'categories'  => [ 'oz-sections' ],
		'content'     => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Veelgestelde vragen</h2>
<!-- /wp:heading -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Hoe lang duurt de levering?</summary><!-- wp:paragraph -->
<p>Bij bestellingen voor 14:00 uur verzenden wij dezelfde werkdag. Levering is doorgaans binnen 1-2 werkdagen.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Kan ik gratis kleurstalen aanvragen?</summary><!-- wp:paragraph -->
<p>Ja, u kunt tot 4 gratis kleurstalen aanvragen via onze kleurstalen pagina.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Bieden jullie ondersteuning bij het aanbrengen?</summary><!-- wp:paragraph -->
<p>Absoluut! Wij bieden telefonische ondersteuning en uitgebreide handleidingen voor elk product.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details --></div>
<!-- /wp:group -->',
	] );
}
add_action( 'init', 'oz_register_block_patterns' );
