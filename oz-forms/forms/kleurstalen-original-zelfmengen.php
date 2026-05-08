<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$build = include __DIR__ . '/_kleurstalen_builder.php';
return $build( array(
	'id'      => 'kleurstalen-original-zelfmengen',
	'title'   => 'Kleurstalen Original Zelf Mengen aanvragen',
	'product' => 'Original Zelf Mengen',
	'palette' => 'original-zelfmengen',
) );
