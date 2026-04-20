<?php
/**
 * Offerte aanvragen — multi-step form (replaces CF7 146 / wpforms 2554).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'           => 'offerte',
	'title'        => 'Offerte aanvragen',
	'submit_label' => 'Verstuur offerte-aanvraag',
	'notify_to'    => 'info@beton-cire-webshop.nl',
	'subject'      => function ( $data ) {
		$name = trim( ( $data['voornaam'] ?? '' ) . ' ' . ( $data['achternaam'] ?? '' ) );
		return sprintf( '[Offerte] Aanvraag van %s', $name ?: 'iemand' );
	},
	'reply_subject' => 'Bedankt voor je offerte-aanvraag — Beton Ciré Webshop',
	'reply_body'    => function ( $data ) {
		$name = $data['voornaam'] ?? '';
		return '<p>Hi ' . esc_html( $name ) . ',</p>'
			. '<p>Bedankt voor je offerte-aanvraag. We bekijken je project en nemen zo snel mogelijk (meestal binnen één werkdag) contact met je op met een passend voorstel.</p>'
			. '<p>Met vriendelijke groet,<br>Team Beton Ciré Webshop</p>';
	},

	'steps' => array(
		array(
			'title'  => 'Jouw gegevens',
			'fields' => array( 'voornaam', 'achternaam', 'email', 'telefoon' ),
		),
		array(
			'title'  => 'Het project',
			'fields' => array( 'reden', 'producten', 'object', 'ondergrond' ),
		),
		array(
			'title'  => 'Wanneer & extra',
			'fields' => array( 'datum', 'foto', 'informatie' ),
		),
	),

	'fields' => array(
		'voornaam'   => array( 'label' => 'Voornaam', 'type' => 'text', 'required' => true, 'maxlength' => 80, 'placeholder' => 'Uw voornaam' ),
		'achternaam' => array( 'label' => 'Achternaam', 'type' => 'text', 'required' => true, 'maxlength' => 80, 'placeholder' => 'Uw achternaam' ),
		'email'      => array( 'label' => 'E-mailadres', 'type' => 'email', 'required' => true, 'maxlength' => 150, 'placeholder' => 'Uw e-mailadres' ),
		'telefoon'   => array( 'label' => 'Telefoonnummer', 'type' => 'tel', 'required' => false, 'maxlength' => 30, 'placeholder' => 'Uw telefoonnummer' ),
		'reden'      => array(
			'label'    => 'Wat heb je nodig?',
			'type'     => 'radio',
			'required' => true,
			'options'  => array(
				'Alleen materiaal'              => 'Alleen materiaal',
				'Materiaal & laten uitvoeren'   => 'Materiaal & laten uitvoeren',
			),
		),
		'producten' => array(
			'label'       => 'Welke producten heb je op het oog?',
			'type'        => 'multiselect',
			'required'    => true,
			'placeholder' => 'Typ om te zoeken, bijv. "beton ciré"…',
			'help'        => 'Kies één of meer productlijnen. Geen zorgen over kleuren — die bepalen we samen.',
			'options'     => array(
				'betonverf'                => 'Betonverf',
				'microcement'              => 'Microcement',
				'beton-cire-easyline'      => 'Beton ciré — Easyline',
				'beton-cire-all-in-one'    => 'Beton ciré — All-in-One',
				'beton-cire-original'      => 'Beton ciré — Original',
				'metallic-velvet'          => 'Metallic Velvet',
				'weet-ik-nog-niet'         => 'Weet ik nog niet — adviseer mij',
			),
		),
		'object' => array(
			'label'    => 'Wat is het object / hoeveel m²?',
			'type'     => 'textarea', 'required' => true, 'rows' => 4, 'maxlength' => 1000,
			'placeholder' => 'Beschrijf het object en geef het aantal m²…',
		),
		'ondergrond' => array(
			'label'    => 'Wat is de ondergrond?',
			'type'     => 'textarea', 'required' => true, 'rows' => 4, 'maxlength' => 1000,
			'placeholder' => 'Beschrijf de ondergrond van het object…',
		),
		'datum' => array(
			'label'    => 'Wanneer moet het project uitgevoerd worden?',
			'type'     => 'text', 'required' => true, 'maxlength' => 200,
			'placeholder' => 'Bijv. binnen 4 weken, in juli, geen haast…',
		),
		'foto' => array(
			'label'    => 'Foto van de ruimte (optioneel)',
			'type'     => 'file',
			'required' => false,
			'accept'   => 'image/*',
			'max_size' => 8 * 1024 * 1024,
			'help'     => 'JPG, PNG of HEIC — helpt ons een betere inschatting te maken.',
		),
		'informatie' => array(
			'label'    => 'Extra informatie',
			'type'     => 'textarea', 'required' => false, 'rows' => 4, 'maxlength' => 2000,
			'placeholder' => 'Noteer eventuele extra informatie…',
		),
	),
);
