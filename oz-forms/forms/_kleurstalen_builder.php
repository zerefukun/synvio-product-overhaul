<?php
/**
 * Builder for the 5 kleurstalen schemas. They share the exact same shape,
 * only the title, product name, and color palette differ.
 *
 * Each kleurstalen-*.php file does:
 *   $build = include __DIR__ . '/_kleurstalen_builder.php';
 *   return $build( ['id'=>'…', 'title'=>'…', 'product'=>'…', 'palette'=>'…'] );
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$palettes = include __DIR__ . '/_palettes.php';

return function ( array $cfg ) use ( $palettes ) {
	$id        = $cfg['id'];
	$title     = $cfg['title'];
	$product   = $cfg['product'];
	$palette   = $cfg['palette'];
	$swatches  = $palettes[ $palette ] ?? array();

	if ( ! $swatches ) {
		// Fail loudly during dev.
		return array(
			'id'    => $id,
			'title' => $title . ' (palette missing: ' . esc_html( $palette ) . ')',
			'fields'=> array(),
		);
	}

	$kleur_field = function ( $n ) use ( $swatches ) {
		return array(
			'label'    => 'Kleur ' . $n,
			'type'     => 'select',
			'required' => true,
			'placeholder' => 'Selecteer een kleur',
			'options'  => $swatches,
		);
	};

	return array(
		'id'           => $id,
		'title'        => $title,
		'submit_label' => 'Verstuur kleurstalen-aanvraag',
		'notify_to'    => 'info@beton-cire-webshop.nl',
		'subject'      => function ( $data ) use ( $product ) {
			$name = trim( ( $data['voornaam'] ?? '' ) . ' ' . ( $data['achternaam'] ?? '' ) );
			return sprintf( '[Kleurstalen %s] Aanvraag van %s', $product, $name ?: 'iemand' );
		},
		'reply_subject' => 'Bedankt voor je kleurstalen-aanvraag — Beton Ciré Webshop',
		'reply_body'    => function ( $data ) use ( $product ) {
			$name = $data['voornaam'] ?? '';
			return '<p>Hi ' . esc_html( $name ) . ',</p>'
				. '<p>Hartelijk bedankt voor het aanvragen van ' . esc_html( $product ) . ' kleurstalen.</p>'
				. '<p>We hopen dat je aan de hand van de kleurstalen een keuze kunt maken. Als dank krijg je 10% korting op je eerste bestelling met de code: <strong>KLEURSTAAL10</strong></p>'
				. '<p>Heb je advies nodig? Mail ons of bel <a href="tel:0850270090">085-027 0090</a>.</p>'
				. '<p>Met vriendelijke groet,<br>Team Beton Ciré Webshop</p>';
		},

		'steps' => array(
			array(
				'title'  => 'Kies je kleuren',
				'intro'  => 'Selecteer 4 kleuren waar je benieuwd naar bent.',
				'fields' => array( 'kleur1', 'kleur2', 'kleur3', 'kleur4' ),
			),
			array(
				'title'  => 'Jouw gegevens',
				'fields' => array(
					'voornaam', 'achternaam', 'bedrijfsnaam', 'email',
					'aanbrengen', 'verwachting', 'gevonden',
				),
			),
		),

		'fields' => array(
			'kleur1' => $kleur_field( 1 ),
			'kleur2' => $kleur_field( 2 ),
			'kleur3' => $kleur_field( 3 ),
			'kleur4' => $kleur_field( 4 ),

			'voornaam'     => array( 'label' => 'Voornaam', 'type' => 'text', 'required' => true, 'maxlength' => 80, 'placeholder' => 'Uw voornaam' ),
			'achternaam'   => array( 'label' => 'Achternaam', 'type' => 'text', 'required' => true, 'maxlength' => 80, 'placeholder' => 'Uw achternaam' ),
			'bedrijfsnaam' => array( 'label' => 'Bedrijfsnaam', 'type' => 'text', 'required' => false, 'maxlength' => 120, 'placeholder' => 'Uw bedrijfsnaam (optioneel)' ),
			'email'        => array( 'label' => 'E-mailadres', 'type' => 'email', 'required' => true, 'maxlength' => 150, 'placeholder' => 'Uw e-mailadres' ),

			'aanbrengen' => array(
				'label'    => 'Waar wil je het aanbrengen?',
				'type'     => 'select',
				'required' => false,
				'placeholder' => 'Maak een keuze',
				'options'  => $palettes['aanbrengen'],
			),
			'verwachting' => array(
				'label'    => 'Welke verwachting heb je van de kleuren?',
				'type'     => 'textarea',
				'required' => false,
				'rows'     => 3,
				'maxlength' => 1000,
				'placeholder' => 'Geef je verwachtingen aan…',
			),
			'gevonden' => array(
				'label'    => 'Hoe heb je ons gevonden?',
				'type'     => 'text',
				'required' => true,
				'maxlength' => 200,
				'placeholder' => 'Google, Instagram, via via…',
			),
		),
	);
};
