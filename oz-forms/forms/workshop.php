<?php
/**
 * Workshop op locatie — multi-step (replaces CF7 8969 / wpforms 2569).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'           => 'workshop',
	'title'        => 'Workshop op locatie aanvragen',
	'submit_label' => 'Verstuur workshop-aanvraag',
	'notify_to'    => 'info@beton-cire-webshop.nl',
	'subject'      => function ( $data ) {
		$name = trim( ( $data['voornaam'] ?? '' ) . ' ' . ( $data['achternaam'] ?? '' ) );
		return sprintf( '[Workshop] Aanvraag van %s', $name ?: 'iemand' );
	},
	'reply_subject' => 'Bedankt voor je workshop-aanvraag — Beton Ciré Webshop',
	'reply_body'    => function ( $data ) {
		$name = $data['voornaam'] ?? '';
		return '<p>Hi ' . esc_html( $name ) . ',</p>'
			. '<p>Bedankt voor je workshop-aanvraag. We bekijken je verzoek en nemen zo snel mogelijk contact met je op om een datum en details af te stemmen.</p>'
			. '<p>Met vriendelijke groet,<br>Team Beton Ciré Webshop</p>';
	},

	'steps' => array(
		array(
			'title'  => 'Jouw gegevens',
			'fields' => array( 'voornaam', 'achternaam', 'email', 'telefoon' ),
		),
		array(
			'title'  => 'Project & locatie',
			'fields' => array( 'datum', 'adres', 'object', 'ondergrond', 'informatie' ),
		),
	),

	'fields' => array(
		'voornaam'   => array( 'label' => 'Voornaam', 'type' => 'text', 'required' => true, 'maxlength' => 80, 'placeholder' => 'Uw voornaam' ),
		'achternaam' => array( 'label' => 'Achternaam', 'type' => 'text', 'required' => true, 'maxlength' => 80, 'placeholder' => 'Uw achternaam' ),
		'email'      => array( 'label' => 'E-mailadres', 'type' => 'email', 'required' => true, 'maxlength' => 150, 'placeholder' => 'Uw e-mailadres' ),
		'telefoon'   => array( 'label' => 'Telefoonnummer', 'type' => 'tel', 'required' => false, 'maxlength' => 30, 'placeholder' => 'Uw telefoonnummer' ),
		'datum' => array(
			'label'    => 'Wanneer moet de workshop plaatsvinden?',
			'type'     => 'text', 'required' => true, 'maxlength' => 200,
			'placeholder' => 'Bijv. binnen 4 weken, in juli…',
		),
		'adres' => array(
			'label'    => 'Adres van de locatie',
			'type'     => 'textarea', 'required' => true, 'rows' => 3, 'maxlength' => 500,
			'placeholder' => 'Straat + huisnummer, postcode, plaats',
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
		'informatie' => array(
			'label'    => 'Extra informatie',
			'type'     => 'textarea', 'required' => false, 'rows' => 4, 'maxlength' => 2000,
			'placeholder' => 'Noteer eventuele extra informatie…',
		),
	),
);
