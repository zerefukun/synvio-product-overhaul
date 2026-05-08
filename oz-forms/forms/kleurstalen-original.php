<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$build = include __DIR__ . '/_kleurstalen_builder.php';
return $build( array(
	'id'      => 'kleurstalen-original',
	'title'   => 'Kleurstalen Original Kant & Klaar aanvragen',
	'product' => 'Original Kant & Klaar',
	'palette' => 'original-kk',
) );
