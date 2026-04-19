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

return array(

	/* RAL Classic 1000-series — preserves the exact range CF7 form 1418 had. */
	'ral' => array_combine(
		array(
			'1000','1001','1002','1003','1004','1005','1006','1007','1008','1010',
			'1011','1014','1015','1016','1017','1018','1020','1021','1025','1026',
			'1027','1028','1029','1030','1031','1032','1033','1034','1035','1036',
			'1037','1038','1040','1041','1042','1043','1044','1045','1046','1048',
			'1049','1050','1051','1052','1053','1054','1060','1061','1062','1063',
		),
		array(
			'RAL 1000','RAL 1001','RAL 1002','RAL 1003','RAL 1004','RAL 1005','RAL 1006','RAL 1007','RAL 1008','RAL 1010',
			'RAL 1011','RAL 1014','RAL 1015','RAL 1016','RAL 1017','RAL 1018','RAL 1020','RAL 1021','RAL 1025','RAL 1026',
			'RAL 1027','RAL 1028','RAL 1029','RAL 1030','RAL 1031','RAL 1032','RAL 1033','RAL 1034','RAL 1035','RAL 1036',
			'RAL 1037','RAL 1038','RAL 1040','RAL 1041','RAL 1042','RAL 1043','RAL 1044','RAL 1045','RAL 1046','RAL 1048',
			'RAL 1049','RAL 1050','RAL 1051','RAL 1052','RAL 1053','RAL 1054','RAL 1060','RAL 1061','RAL 1062','RAL 1063',
		)
	),

	/* All-In-One palette — also used by Betonlook Verf (CF7 25532). */
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
