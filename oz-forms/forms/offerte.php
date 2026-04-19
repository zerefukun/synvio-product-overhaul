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
			'fields' => array( 'reden', 'object', 'ondergrond' ),
		),
		array(
			'title'  => 'Wanneer & waar',
			'fields' => array( 'datum', 'adres', 'informatie' ),
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
		'adres' => array(
			'label'    => 'Adres en woonplaats van het project',
			'type'     => 'textarea', 'required' => true, 'rows' => 3, 'maxlength' => 500,
			'placeholder' => 'Straat + huisnummer, postcode, plaats',
		),
		'informatie' => array(
			'label'    => 'Extra informatie',
			'type'     => 'textarea', 'required' => false, 'rows' => 4, 'maxlength' => 2000,
			'placeholder' => 'Noteer eventuele extra informatie…',
		),
	),
);
