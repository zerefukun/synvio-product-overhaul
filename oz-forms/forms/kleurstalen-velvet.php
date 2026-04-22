<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$build = include __DIR__ . '/_kleurstalen_builder.php';
return $build( array(
	'id'      => 'kleurstalen-velvet',
	'title'   => 'Kleurstalen Metallic Stuc Velvet aanvragen',
	'product' => 'Metallic Stuc Velvet',
	'palette' => 'velvet',
) );
