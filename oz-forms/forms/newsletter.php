<?php
/**
 * Newsletter inschrijving — single email, no Turnstile noise.
 * Subscribers are stored as oz_submission CPT with form_id=newsletter.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'           => 'newsletter',
	'title'        => 'Nieuwsbrief inschrijving',
	'submit_label' => 'Inschrijven',
	'notify_to'    => 'info@beton-cire-webshop.nl',
	'subject'      => function ( $data ) {
		return sprintf( '[Nieuwsbrief] %s heeft zich ingeschreven', $data['email'] ?? '' );
	},
	'reply_subject' => 'Welkom bij de Beton Ciré nieuwsbrief',
	'reply_body'    => function () {
		return '<p>Bedankt voor je inschrijving. We sturen je geen spam — alleen de leukste tips, nieuwe kleuren en aanbiedingen.</p>'
			. '<p>Met vriendelijke groet,<br>Team Beton Ciré Webshop</p>';
	},

	'fields' => array(
		'email' => array(
			'label'       => 'E-mailadres',
			'type'        => 'email',
			'required'    => true,
			'maxlength'   => 150,
			'placeholder' => 'jij@voorbeeld.nl',
		),
	),
);
