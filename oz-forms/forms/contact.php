<?php
/**
 * Contact form schema.
 * Action id "contact" must match the Cloudflare Turnstile widget data-action.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'           => 'contact',
	'title'        => 'Contactformulier',
	'submit_label' => 'Verstuur bericht',
	'notify_to'    => 'info@beton-cire-webshop.nl',
	'subject'      => function ( $data ) {
		$name = $data['naam'] ?? 'iemand';
		return sprintf( '[Contact] Nieuw bericht van %s', $name );
	},
	'reply_subject' => 'Bedankt voor je bericht — Beton Ciré Webshop',
	'reply_body'    => function ( $data ) {
		$name = $data['naam'] ?? '';
		return '<p>Hi ' . esc_html( $name ) . ',</p>'
			. '<p>Bedankt voor je bericht. We nemen zo snel mogelijk (meestal binnen één werkdag) contact met je op.</p>'
			. '<p>Met vriendelijke groet,<br>Team Beton Ciré Webshop</p>';
	},
	'fields' => array(
		'naam' => array(
			'label'    => 'Naam',
			'type'     => 'text',
			'required' => true,
			'maxlength' => 100,
		),
		'email' => array(
			'label'    => 'E-mailadres',
			'type'     => 'email',
			'required' => true,
			'maxlength' => 150,
		),
		'telefoon' => array(
			'label'    => 'Telefoonnummer',
			'type'     => 'tel',
			'required' => false,
			'maxlength' => 30,
		),
		'bericht' => array(
			'label'    => 'Bericht',
			'type'     => 'textarea',
			'required' => true,
			'maxlength' => 4000,
			'rows'     => 6,
		),
	),
);
