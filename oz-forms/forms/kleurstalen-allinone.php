<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$build = include __DIR__ . '/_kleurstalen_builder.php';
return $build( array(
	'id'      => 'kleurstalen-allinone',
	'title'   => 'Kleurstalen Easyline & All-In-One aanvragen',
	'product' => 'Easyline & All-In-One',
	'palette' => 'allinone',
) );
