<?php
/**
 * Shop review submission form — generic "how was your experience" review,
 * not tied to a specific product. Stored as oz_shop_review CPT with
 * source='shop_native'.
 *
 * Renders on /reviews/ page (or any page via [oz_form id="shop-review"]).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'              => 'shop-review',
	'title'           => 'Review schrijven',
	'submit_label'    => 'Review plaatsen',
	'success_message' => 'Bedankt voor je review! Hij staat in de moderatiewachtrij en we plaatsen hem zo snel mogelijk.',
	'skip_email'      => true,
	'fields' => array(
		'token' => array(
			'type'       => 'hidden',
			'from_query' => true,
		),
		'rating' => array(
			'label'    => 'Beoordeling',
			'type'     => 'rating',
			'required' => true,
		),
		'title' => array(
			'label'     => 'Kop (bv. "Uitstekend advies en snelle levering")',
			'type'      => 'text',
			'required'  => true,
			'maxlength' => 100,
		),
		'body' => array(
			'label'     => 'Je ervaring met Beton Ciré Webshop',
			'type'      => 'textarea',
			'required'  => true,
			'maxlength' => 2000,
			'rows'      => 6,
			'help'      => 'Minimaal 20 tekens. Beschrijf het bestelproces, levering, advies, of eindresultaat.',
		),
		'naam' => array(
			'label'     => 'Je naam (verschijnt bij je review)',
			'type'      => 'text',
			'required'  => true,
			'maxlength' => 60,
		),
		'email' => array(
			'label'     => 'E-mailadres (niet publiek)',
			'type'      => 'email',
			'required'  => true,
			'maxlength' => 150,
		),
		'stad' => array(
			'label'     => 'Plaats (optioneel)',
			'type'      => 'text',
			'required'  => false,
			'maxlength' => 60,
		),
		'project_type' => array(
			'label'    => 'Type project (optioneel)',
			'type'     => 'select',
			'required' => false,
			'placeholder' => 'Maak een keuze',
			'options'  => array(
				'vloer'    => 'Vloer',
				'badkamer' => 'Badkamer',
				'keuken'   => 'Keuken',
				'toilet'   => 'Toilet',
				'trap'     => 'Trap',
				'meubel'   => 'Meubel',
				'wand'     => 'Wand',
				'overig'   => 'Anders',
			),
		),
		'photos' => array(
			'label'     => 'Foto\'s (max 4, optioneel)',
			'type'      => 'file',
			'required'  => false,
			'multiple'  => true,
			'accept'    => 'image/*',
			'max_size'  => 8 * 1024 * 1024,
			'max_files' => 4,
		),
	),
);
