<?php
/**
 * Custom kleur aanvraag — separate form for customers who want a specific
 * RAL / NCS / Pantone / fabrikant-code op maat. Reply target: 3 working days.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'           => 'custom-kleur',
	'title'        => 'Custom kleur aanvragen',
	'submit_label' => 'Verstuur custom kleur-aanvraag',
	'notify_to'    => 'info@beton-cire-webshop.nl',
	'subject'      => function ( $data ) {
		$name = trim( ( $data['voornaam'] ?? '' ) . ' ' . ( $data['achternaam'] ?? '' ) );
		return sprintf( '[Custom kleur] Aanvraag van %s', $name ?: 'iemand' );
	},
	'reply_subject' => 'Bedankt voor je custom kleur-aanvraag - Beton Ciré Webshop',
	'reply_body'    => function ( $data ) {
		$name = $data['voornaam'] ?? '';
		return '<p>Hi ' . esc_html( $name ) . ',</p>'
			. '<p>Bedankt voor je custom kleur-aanvraag. We bekijken de mogelijkheden en de prijs en komen binnen 3 werkdagen persoonlijk bij je terug.</p>'
			. '<p>Heb je advies nodig? Mail ons of bel <a href="tel:0850270090">085-027 0090</a>.</p>'
			. '<p>Met vriendelijke groet,<br>Team Beton Ciré Webshop</p>';
	},

	'fields' => array(
		'voornaam'   => array( 'label' => 'Voornaam', 'type' => 'text', 'required' => true, 'maxlength' => 80, 'placeholder' => 'Uw voornaam' ),
		'achternaam' => array( 'label' => 'Achternaam', 'type' => 'text', 'required' => true, 'maxlength' => 80, 'placeholder' => 'Uw achternaam' ),
		'email'      => array( 'label' => 'E-mailadres', 'type' => 'email', 'required' => true, 'maxlength' => 150, 'placeholder' => 'Uw e-mailadres' ),
		'telefoon'   => array( 'label' => 'Telefoonnummer', 'type' => 'tel', 'required' => false, 'maxlength' => 30, 'placeholder' => 'Uw telefoonnummer (optioneel)' ),

		'product' => array(
			'label'    => 'Voor welk product wil je de custom kleur?',
			'type'     => 'radio',
			'required' => true,
			'options'  => array(
				'Original'                  => 'Beton Ciré Original',
				'Easyline & All-In-One'     => 'Beton Ciré Easyline & All-In-One',
				'Metallic Stuc Velvet'      => 'Metallic Stuc Velvet',
				'Microcement Performance'   => 'Microcement Performance',
				'Betonlook Verf'            => 'Betonlook Verf',
				'Anders'                    => 'Anders / weet ik nog niet',
			),
		),

		'kleur_type' => array(
			'label'    => 'Hoe wil je de kleur opgeven?',
			'type'     => 'radio',
			'required' => true,
			'options'  => array(
				'RAL'         => 'RAL-code',
				'NCS'         => 'NCS-code',
				'Pantone'     => 'Pantone-code',
				'Andere code' => 'Andere fabrikantcode',
				'Beschrijving'=> 'Beschrijving',
			),
		),

		'kleur_input' => array(
			'label'       => 'Kleurcode of beschrijving',
			'type'        => 'text',
			'required'    => true,
			'maxlength'   => 300,
			'placeholder' => 'Bijv. RAL 7016, NCS S 5500-N, of een korte beschrijving',
		),

		'm2' => array(
			'label'       => 'Hoeveel m² wil je doen?',
			'type'        => 'text',
			'required'    => false,
			'maxlength'   => 50,
			'placeholder' => 'Bijv. 12 m² (optioneel)',
		),

		'toelichting' => array(
			'label'       => 'Toelichting',
			'type'        => 'textarea',
			'required'    => false,
			'rows'        => 4,
			'maxlength'   => 2000,
			'placeholder' => 'Extra context of vragen (optioneel)',
		),
	),
);
