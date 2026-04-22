<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$build = include __DIR__ . '/_kleurstalen_builder.php';
return $build( array(
	'id'      => 'kleurstalen-betonlook-verf',
	'title'   => 'Kleurstalen Betonlook Verf aanvragen',
	'product' => 'Betonlook Verf',
	'palette' => 'allinone',
) );
