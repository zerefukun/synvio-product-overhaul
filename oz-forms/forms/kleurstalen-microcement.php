<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$build = include __DIR__ . '/_kleurstalen_builder.php';
return $build( array(
	'id'      => 'kleurstalen-microcement',
	'title'   => 'Kleurstalen Microcement Performance aanvragen',
	'product' => 'Microcement Performance',
	'palette' => 'microcement',
) );
