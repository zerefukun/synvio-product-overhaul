<?php
/**
 * Product review submission form.
 * Action id "product-review" must match the Cloudflare Turnstile widget data-action.
 *
 * Hidden fields (product_id, order_id, token) are injected by the landing page
 * from a signed email link (TKT-1.3). The oz-reviews plugin listens on
 * `oz_forms_submission_stored` with form_id === 'product-review' and writes the
 * review into wp_comments with comment_type=review.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'              => 'product-review',
	'title'           => 'Review schrijven',
	'submit_label'    => 'Review plaatsen',
	'success_message' => 'Bedankt voor je review! Hij staat in de moderatiewachtrij en we plaatsen hem zo snel mogelijk.',
	'skip_email'      => true, // oz-reviews owns the acknowledgement + admin-alert flow.
	'fields' => array(
		'product_id' => array(
			'type'       => 'hidden',
			'from_query' => true,
		),
		'order_id' => array(
			'type'       => 'hidden',
			'from_query' => true,
		),
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
			'label'     => 'Kop (bv. “Zeer tevreden over het resultaat”)',
			'type'      => 'text',
			'required'  => true,
			'maxlength' => 100,
		),
		'body' => array(
			'label'     => 'Je ervaring',
			'type'      => 'textarea',
			'required'  => true,
			'maxlength' => 2000,
			'rows'      => 6,
			'help'      => 'Minimaal 20 tekens. Beschrijf bv. de aanschaf, het aanbrengen en het eindresultaat.',
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
			'label'    => 'Type project',
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
		'color_used' => array(
			'label'     => 'Welke kleur heb je gebruikt? (optioneel)',
			'type'      => 'text',
			'required'  => false,
			'maxlength' => 60,
		),
		'm2' => array(
			'label'    => 'Oppervlakte in m² (optioneel)',
			'type'     => 'number',
			'required' => false,
		),
		'photos' => array(
			'label'     => 'Foto’s (max 4, optioneel)',
			'type'      => 'file',
			'required'  => false,
			'multiple'  => true,
			'accept'    => 'image/*',
			'max_size'  => 8 * 1024 * 1024,
			'max_files' => 4,
			'help'      => 'Foto’s maken je review voor anderen veel waardevoller.',
		),
	),
);
