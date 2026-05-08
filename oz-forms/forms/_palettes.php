<?php
/**
 * Color palettes — single source of truth used by every kleurstalen schema.
 *
 * Returns an associative array keyed by palette id; each value is a value=>label map
 * suitable for dropping straight into a select field's "options".
 *
 * Filename starts with "_" so Schema_Registry skips it as not-a-schema.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beton Ciré Original codes 1000-1063 with their human-readable color names.
 * Same underlying set is used twice: once as Kant & Klaar (label = "Stone White 1000")
 * and once as Zelf Mengen (label = bare code "1000", name surfaces only on the swatch grid).
 */
$original_codes = array(
	'1000' => 'Stone White',
	'1001' => 'France Grey',
	'1002' => 'France Grey Mid',
	'1003' => 'France Grey Dark',
	'1004' => 'Elephant Skin',
	'1005' => 'Earth Stone',
	'1006' => 'Tin Grey',
	'1007' => 'Smoke Black',
	'1008' => 'Jack Black',
	'1010' => 'Azul Blue',
	'1011' => 'Sky Blue',
	'1014' => 'Lavendel',
	'1015' => 'Powder Skin',
	'1016' => 'Peach Blossom Light',
	'1017' => 'Peach Blossom Dark',
	'1018' => 'Nude',
	'1020' => 'Ashes Of Rose',
	'1021' => 'Taupe Dark',
	'1025' => 'Almond',
	'1026' => 'Camel',
	'1027' => 'Army Grey',
	'1028' => 'Antique White',
	'1029' => 'Havanna Yellow',
	'1030' => 'Sunday',
	'1031' => 'Sahara Dust',
	'1032' => 'Goldzand',
	'1033' => 'Old Romance',
	'1034' => 'Deep Earth',
	'1035' => 'China Clay',
	'1036' => 'Nutmeg',
	'1037' => 'Dusty Rose',
	'1038' => 'Soft Taupe',
	'1040' => 'Island Stone Light',
	'1041' => 'Island Stone Deep',
	'1042' => 'Island Stone Dark',
	'1043' => 'Old Linnen',
	'1044' => 'Dried Clay',
	'1045' => 'Pebble Stone',
	'1046' => 'Taupe Light',
	'1048' => 'Pepper',
	'1049' => 'Silky Grey',
	'1050' => 'Silver Clay',
	'1051' => 'Shabby Clay',
	'1052' => 'Urban Grey',
	'1053' => 'Storm Grey',
	'1054' => 'Telegrey',
	'1060' => 'Greyish',
	'1061' => 'Pistache Soft Green',
	'1062' => 'Shadow Green',
	'1063' => 'Mud Green',
);

$original_kk = array();
foreach ( $original_codes as $code => $name ) {
	// Kant & Klaar dropdown shows "Stone White 1000" (matches the product drawer).
	$label = $name . ' ' . $code;
	$original_kk[ $label ] = $label;
}

$original_zm = array();
foreach ( $original_codes as $code => $name ) {
	// Zelf Mengen dropdown shows the code only — the swatch grid carries the name
	// underneath each chip, so the customer still has the matching context visually.
	$original_zm[ $code ] = $code;
}

return array(

	/* Beton Ciré Original — Kant & Klaar (named first, code suffix). */
	'original-kk' => $original_kk,

	/* Beton Ciré Original — Zelf Mengen / Mixen pigment line (code only in dropdown). */
	'original-zelfmengen' => $original_zm,

	/* All-In-One palette — also used by Betonlook Verf. */
	'allinone' => array_combine(
		array(
			'Atmos','Base grey','Basil','Bellbird','Bit of green','Bricks','Camouflage','Canyon','Cloudy','Coconut grove',
			'Dark night','Dark shades','Dusty rose','Egypt','Emerald bay','Gloria','Grey','Ground cover','Hippo','Hunter',
			'Island stone','Mermaid','New york','Octo','Olive','Olive vierge','Pale','Pale stone','Pearl white','Pure',
			'Pure white','Ribbon','Sage','Sandy beach','Shades','Silk','Simply grey','Smooth grey','Stone grey','Stonehenge',
		),
		array(
			'Atmos','Base grey','Basil','Bellbird','Bit of green','Bricks','Camouflage','Canyon','Cloudy','Coconut grove',
			'Dark night','Dark shades','Dusty rose','Egypt','Emerald bay','Gloria','Grey','Ground cover','Hippo','Hunter',
			'Island stone','Mermaid','New york','Octo','Olive','Olive vierge','Pale','Pale stone','Pearl white','Pure',
			'Pure white','Ribbon','Sage','Sandy beach','Shades','Silk','Simply grey','Smooth grey','Stone grey','Stonehenge',
		)
	),

	/* Metallic Stuc Velvet palette. */
	'velvet' => array_combine(
		array( 'Black pearl','Brilliant','Champagne','Copper','Duna','Griseo','Oro','Pandora','Platinum','Rosé','Royal flush','Silver lining' ),
		array( 'Black pearl','Brilliant','Champagne','Copper','Duna','Griseo','Oro','Pandora','Platinum','Rosé','Royal flush','Silver lining' )
	),

	/* Microcement Performance palette. */
	'microcement' => array_combine(
		array(
			'Cement 1','Cement 2','Cement 3','Cement 4','Cement 5','Blue 2',
			'Sand 1','Sand 2','Sand 3','Sand 4','Sand 5','Sand 6',
			'Green 1','Green 2','Green 3','Green 4','Green 5','Green 6',
			'Nude 1','Nude 2','Nude 3','Nude 4','Nude 5','Nude 6',
			'Warm Grey 1','Warm Grey 2','Warm Grey 3','Warm Grey 4','Warm Grey 5','Warm Grey 6',
			'Lavender Grey 1','Lavender Grey 2','Lavender Grey 3','Lavender Grey 4','Lavender Grey 5','Lavender Grey 6',
		),
		array(
			'Cement 1','Cement 2','Cement 3','Cement 4','Cement 5','Blue 2',
			'Sand 1','Sand 2','Sand 3','Sand 4','Sand 5','Sand 6',
			'Green 1','Green 2','Green 3','Green 4','Green 5','Green 6',
			'Nude 1','Nude 2','Nude 3','Nude 4','Nude 5','Nude 6',
			'Warm Grey 1','Warm Grey 2','Warm Grey 3','Warm Grey 4','Warm Grey 5','Warm Grey 6',
			'Lavender Grey 1','Lavender Grey 2','Lavender Grey 3','Lavender Grey 4','Lavender Grey 5','Lavender Grey 6',
		)
	),

	/* "Waar wilt u Beton Ciré gaan aanbrengen?" — shared application-area dropdown. */
	'aanbrengen' => array(
		'Badkamer' => 'Badkamer',
		'Vloer'    => 'Vloer',
		'Muur'     => 'Muur',
		'Keuken'   => 'Keuken',
		'Trap'     => 'Trap',
		'Meubel'   => 'Meubel',
	),
);
