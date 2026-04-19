<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_id = isset( $attributes['formId'] ) ? sanitize_key( $attributes['formId'] ) : '';
if ( $form_id === '' ) {
	return;
}

echo \OZ_Forms\Form::render( $form_id ); // phpcs:ignore WordPress.Security.EscapeOutput
