<?php
/**
 * Product Line Configuration for Beton Ciré Webshop
 *
 * Central config for all 9 BCW product lines. This is the exact runtime
 * representation of reference/WAPO-PARITY-CONFIG.md.
 *
 * All prices are fixed per-unit amounts (not formulas). Every option
 * carries a 'default' flag matching the live WAPO state. The public API
 * returns payload-ready arrays that can be passed directly to JS.
 *
 * Source: YITH WAPO blocks extracted 2026-03-06. Frozen in WAPO-PARITY-CONFIG.md.
 *
 * @package OZ_Variations_BCW
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class OZ_Product_Line_Config {

    /**
     * PU price tables — fixed per-unit prices, keyed by layer count.
     * Matches WAPO-PARITY-CONFIG.md §3 exactly.
     */
    private static $pu_prices = [
        'original'    => [0 => 0, 1 => 40,    2 => 80,    3 => 120],
        'all-in-one'  => [0 => 0, 1 => 8,     2 => 16,    3 => 24],
        'easyline'    => [0 => -40, 1 => 0,   2 => 40,   3 => 80],
        'microcement' => [0 => 0, 1 => 8,     2 => 16,    3 => 24],
        'metallic'    => [0 => 0, 1 => 39.99, 2 => 79.99, 3 => 119.99],
        'lavasteen'   => [0 => 0, 1 => 40,    2 => 80,    3 => 120],
    ];

    /**
     * PU option labels + defaults per line.
     * Each entry: [layers, label, default]
     * Order matches WAPO display order.
     */
    private static $pu_options = [
        'original' => [
            [0, 'Geen toplaag',  false],
            [1, '1 toplaag',     false],
            [2, '2 Toplagen',    false],
            [3, '3 Toplagen',    false],
        ],
        'all-in-one' => [
            [1, '1 toplaag',          false],
            [2, '2 toplagen',         false],
            [3, '3 toplagen',         false],
            [0, 'Geen Beschermlaag',  false],
        ],
        'easyline' => [
            [1, '1 toplaag', true],
            [2, '2 toplagen', false],
            [3, '3 toplagen', false],
            [0, 'Geen PU',    false],
        ],
        'microcement' => [
            [1, '1 toplaag',          false],
            [2, '2 toplagen',         false],
            [3, '3 toplagen',         false],
            [0, 'Geen Beschermlaag',  false],
        ],
        'metallic' => [
            [0, 'Geen PU',     true],  // default = yes in WAPO
            [1, '1 Laag PU',   false],
            [2, '2 Lagen PU',  false],
            [3, '3 Lagen PU',  false],
        ],
        'lavasteen' => [
            [1, '1 toplaag',    true],  // default = yes in WAPO
            [2, '2 toplagen',   false],
            [3, '3 toplagen',   false],
            [0, 'Geen toplaag', false],
        ],
    ];

    /**
     * Primer price tables per line.
     * Each entry: [label, price, default, recommended]
     * 4th element (recommended) controls "Advies" badge display.
     * Matches WAPO-PARITY-CONFIG.md §4.
     */
    private static $primer_options = [
        'original' => [
            ['Geen',                     0,     false, false],
            ['Zuigende ondergrond',      12.50, false, true],   // Advies
            ['Niet zuigende ondergrond', 12.50, false, true],   // Advies
        ],
        'metallic' => [
            ['Geen',   0,    true,  false],
            ['Primer', 5.99, false, true],   // Advies
        ],
        'betonlook-verf' => [
            ['Geen Primer', 0,    true,  false],
            ['Primer',      6.00, false, true],   // Advies
        ],
        'stuco-paste' => [
            ['Nee', 0,     false, false],
            ['Ja',  16.00, false, true],   // Advies
        ],
        // Beton Ciré lines — primer included in base price, no price change either way
        'all-in-one' => [
            ['Primer', 0, true,  true],   // Advies (already default + included)
            ['Geen',   0, false, false],
        ],
        'easyline' => [
            ['Primer', 0, true,  true],   // Advies
            ['Geen',   0, false, false],
        ],
        'microcement' => [
            ['Primer', 0, true,  true],   // Advies
            ['Geen',   0, false, false],
        ],
        'lavasteen' => [
            ['Primer', 0, true,  true],   // Advies
            ['Geen',   0, false, false],
        ],
    ];

    /**
     * Product line definitions.
     *
     * Structure per line:
     *   cats          => category IDs for detection
     *   product_ids   => extra product IDs outside line categories (loose emmers, single-product lines)
     *   base_id       => base product ID for redirect (null = no variants)
     *   unit          => display label for package size
     *   unitM2        => m² per unit (0 = not m²-based)
     *   has_pu        => bool — PU toplagen available
     *   has_primer    => bool — primer addon available
     *   has_colorfresh => bool — colorfresh addon available (Original only)
     *   has_toepassing => bool — toepassing selector available (Original only)
     *   has_pakket    => bool — pakket selector available
     *   ral_ncs       => bool — RAL/NCS color mode toggle available
     *   ral_ncs_only  => bool — standard colors disabled, only RAL/NCS (PU Color)
     *   option_order  => display order of option sections on product page
     */
    private static $lines = [

        // ─── ORIGINAL ────────────────────────────────────────────────
        // 48 colors (1000-series), PU 0/40/80/120, primer, colorfresh, toepassing
        'original' => [
            'cats'           => [290],
            'usps'           => [
                'Naadloze betonlook voor wanden, vloeren en meubels',
                'Zelf mengen voor maximale controle over kleur en textuur',
                'Waterdicht bij gebruik van PU toplaag',
            ],
            'specs'          => [
                'Type'            => 'Zelf te mengen mortel + pigment',
                'Lagen'           => '2 lagen (RAW + FINE)',
                'Droogtijd'       => '24 uur per laag',
                'Geschikt voor'   => 'Wand, vloer, meubel, keuken, badkamer, trap',
                'Waterdicht'      => 'Ja, met PU toplaag',
                'Verbruik'        => '~1 kg per m² (2 lagen)',
            ],
            'base_id'        => 11161,
            'unit'           => '5m² pakket',
            'unitM2'         => 5,
            'has_pu'         => true,
            'has_primer'     => true,
            'has_colorfresh' => true,
            'has_toepassing' => true,
            'has_pakket'     => true,
            'ral_ncs'        => false,  // Original has NO RAL/NCS
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'kleurstalen_url' => '/original-kleurstalen/',
            // Cross-link: suggest an alternative product line to visitors
            'cross_link'     => [
                'text' => 'Liever kant & klaar?',
                'label' => 'Bekijk Microcement',
                'url'   => '/microcement/',
            ],
            'option_order'   => ['pakket', 'color', 'toepassing', 'primer', 'colorfresh', 'pu', 'tools'],
            'faq' => [
                ['q' => 'Hoeveel m² heb ik nodig?', 'a' => 'Reken het oppervlak uit (lengte × breedte) en bestel minimaal die hoeveelheid. Wij adviseren 10% extra aan te houden voor snijverlies en onregelmatigheden.'],
                ['q' => 'Kan ik Beton Ciré Original zelf aanbrengen?', 'a' => 'Ja, met de juiste voorbereiding en ons gereedschapset is het goed zelf te doen. Bekijk onze handleiding of volg een workshop voor het beste resultaat.'],
                ['q' => 'Is een PU toplaag nodig?', 'a' => 'Voor vloeren en natte ruimtes raden wij minimaal 2 lagen PU aan. Dit beschermt tegen slijtage en maakt het oppervlak waterdicht. Voor wanden is PU optioneel.'],
                ['q' => 'Wat is het verschil tussen wand- en vloertoepassing?', 'a' => 'De vloertoepassing bevat een harder bindmiddel dat bestand is tegen loopverkeer. De wandtoepassing is lichter en makkelijker verticaal aan te brengen.'],
                ['q' => 'Hoe lang is de droogtijd?', 'a' => 'Reken op 24 uur per laag bij kamertemperatuur (18-22°C). Na de laatste PU-laag is het oppervlak na 7 dagen volledig uitgehard.'],
            ],
        ],

        // ─── ALL-IN-ONE ──────────────────────────────────────────────
        // 38 colors (K&K palette), PU 8/16/24/0, RAL/NCS
        'all-in-one' => [
            'cats'           => [289],
            'usps'           => [
                'Op kleur gemengde pasta, direct klaar voor gebruik',
                'Inclusief primer, per 1 m² bestellen',
                'Geschikt voor robuust én rustig betoneffect',
            ],
            'specs'          => [
                'Type'            => 'Voorgemengde pasta op kleur',
                'Lagen'           => '2 lagen',
                'Droogtijd'       => '24 uur per laag',
                'Geschikt voor'   => 'Wand, vloer, meubel, badkamer',
                'Waterdicht'      => 'Ja, met PU toplaag',
                'Verbruik'        => '1 kg per m² (500 gr per laag)',
                'Inclusief'       => 'Primer + Pre-seal',
            ],
            'product_ids'    => [11191],  // loose emmer (cat 17 "Losse Materialen")
            'base_id'        => 11165,
            'unit'           => '1m² emmer',
            'unitM2'         => 1,
            'has_pu'         => true,
            'has_primer'     => true,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => true,
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'kleurstalen_url' => '/kleurstalen/',
            'option_order'   => ['color', 'primer', 'pu', 'tools'],
            'faq' => [
                ['q' => 'Hoeveel m² heb ik nodig?', 'a' => 'Reken het oppervlak uit (lengte × breedte) en bestel minimaal die hoeveelheid. Wij adviseren 10% extra aan te houden voor snijverlies en onregelmatigheden.'],
                ['q' => 'Wat is het verschil met Beton Ciré Original?', 'a' => 'All-in-One is een voorgemengde pasta op kleur — geen zelf mengen nodig. Je bestelt per 1 m², inclusief primer en pre-seal. Ideaal als je minder ervaring hebt.'],
                ['q' => 'Is een PU toplaag nodig?', 'a' => 'Voor vloeren en natte ruimtes raden wij minimaal 2 lagen PU aan. Voor wanden kun je het zonder PU laten, maar het maakt schoonmaken wel makkelijker.'],
                ['q' => 'Kan ik een RAL of NCS kleur bestellen?', 'a' => 'Ja, kies de optie "RAL/NCS kleur" bij de kleurselectie en vul je kleurcode in. Wij mengen de pasta op maat. Levertijd kan 1-2 werkdagen langer zijn.'],
                ['q' => 'Hoe lang is de droogtijd?', 'a' => 'Reken op 24 uur per laag bij kamertemperatuur (18-22°C). Na de laatste PU-laag is het oppervlak na 7 dagen volledig uitgehard.'],
            ],
        ],

        // ─── EASYLINE ────────────────────────────────────────────────
        // 38 colors (K&K palette), 1 PU included, "Geen PU" = -40, RAL/NCS, pakket
        'easyline' => [
            'cats'           => [314],
            'usps'           => [
                'Kant-en-klare pasta, direct klaar voor gebruik',
                'Standaard inclusief primer en 1 laag PU',
                'Over bestaande tegels aan te brengen',
            ],
            'specs'          => [
                'Type'            => 'Voorgemengde kant-en-klare pasta',
                'Lagen'           => '2 lagen (RAW + FINE)',
                'Droogtijd'       => '24 uur per laag',
                'Geschikt voor'   => 'Wand, vloer, meubel, keuken, badkamer, trap',
                'Waterdicht'      => 'Ja, met PU toplaag',
                'Verbruik'        => '~1 kg per m² (2 lagen)',
                'Inclusief'       => 'Primer + standaard 1 laag PU',
            ],
            'product_ids'    => [11001, 11002],  // loose emmers (cat 17 "Losse Materialen")
            'base_id'        => 11160,
            'unit'           => '5m² pakket',  // corrected from 4m²
            'unitM2'         => 5,
            'has_pu'         => true,
            'has_primer'     => true,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => true,
            'ral_ncs'        => true,
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'kleurstalen_url' => '/kleurstalen/',
            'option_order'   => ['pakket', 'color', 'primer', 'pu', 'tools'],
            'faq' => [
                ['q' => 'Hoeveel m² heb ik nodig?', 'a' => 'Reken het oppervlak uit (lengte × breedte) en bestel minimaal die hoeveelheid. Wij adviseren 10% extra aan te houden voor snijverlies en onregelmatigheden.'],
                ['q' => 'Wat is het verschil tussen Easyline en All-in-One?', 'a' => 'Easyline is speciaal ontwikkeld voor eenvoudige toepassing op wanden. Het is lichter, makkelijker te verwerken en beschikbaar in handige pakketten. All-in-One is veelzijdiger voor zowel wand als vloer.'],
                ['q' => 'Is een PU toplaag nodig?', 'a' => 'Voor natte ruimtes (badkamer, keuken) raden wij minimaal 2 lagen PU aan. Voor droge wanden is 1 laag voldoende voor extra bescherming.'],
                ['q' => 'Kan ik een RAL of NCS kleur bestellen?', 'a' => 'Ja, kies de optie "RAL/NCS kleur" bij de kleurselectie en vul je kleurcode in. Wij mengen de pasta op maat.'],
                ['q' => 'Hoe lang is de droogtijd?', 'a' => 'Reken op 24 uur per laag bij kamertemperatuur (18-22°C). Na de laatste PU-laag is het oppervlak na 7 dagen volledig uitgehard.'],
            ],
        ],

        // ─── MICROCEMENT ─────────────────────────────────────────────
        // 36 colors (own palette), PU 8/16/24/0, RAL/NCS
        'microcement' => [
            'cats'           => [455, 463],
            'usps'           => [
                'Het beste van Beton Ciré Original en EasyLine',
                'Ultradunne laag (1-2mm), over bestaand oppervlak',
                'Beschikbaar per 1 m² voor exacte hoeveelheid',
            ],
            'specs'          => [
                'Type'            => 'Microcement pasta op kleur',
                'Laagdikte'       => '1-2 mm',
                'Lagen'           => '2 lagen',
                'Droogtijd'       => '24 uur per laag',
                'Geschikt voor'   => 'Wand, vloer, meubel, badkamer',
                'Waterdicht'      => 'Ja, met PU toplaag',
                'Verbruik'        => '~1 kg per m²',
            ],
            'base_id'        => 22760,
            'unit'           => 'stuk (1m²)',
            'unitM2'         => 1,
            'has_pu'         => true,
            'has_primer'     => true,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => true,
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'kleurstalen_url' => '/kleurstalen-microcement-aanvragen/',
            'option_order'   => ['color', 'primer', 'pu', 'tools'],
            'faq' => [
                ['q' => 'Hoeveel m² heb ik nodig?', 'a' => 'Reken het oppervlak uit (lengte × breedte) en bestel minimaal die hoeveelheid. Wij adviseren 10% extra aan te houden voor snijverlies en onregelmatigheden.'],
                ['q' => 'Wat is het verschil tussen Microcement en Beton Ciré?', 'a' => 'Microcement is dunner (1-2mm) en flexibeler dan traditioneel Beton Ciré. Het is ideaal voor renovatie omdat het direct over bestaande tegels of ondergronden kan.'],
                ['q' => 'Kan ik Microcement over tegels aanbrengen?', 'a' => 'Ja, mits de tegels goed vastzitten en de ondergrond schoon en vetvrij is. Gebruik altijd onze primer voor de beste hechting.'],
                ['q' => 'Is een PU toplaag nodig?', 'a' => 'Voor vloeren en natte ruimtes raden wij minimaal 2 lagen PU aan. Dit beschermt tegen slijtage en maakt het oppervlak waterdicht.'],
                ['q' => 'Hoe lang is de droogtijd?', 'a' => 'Reken op 24 uur per laag bij kamertemperatuur (18-22°C). Na de laatste PU-laag is het oppervlak na 7 dagen volledig uitgehard.'],
            ],
        ],

        // ─── METALLIC VELVET ─────────────────────────────────────────
        // 12 colors (own palette), PU 0/39.99/79.99/119.99, primer 5.99
        'metallic' => [
            'cats'           => [18],
            'usps'           => [
                'Luxe parelmoer/velvet effect op wanden',
                'Fluweelzacht gevoel, unieke lichtreflecties',
                'Geschikt voor wanden, meubels en objecten',
            ],
            'specs'          => [
                'Verbruik'        => '4 m² per pakket',
                'Type'            => 'Metallic stuc velvet',
                'Effect'          => 'Parelmoer / velvet',
                'Lagen'           => '2 lagen',
                'Droogtijd'       => '24 uur per laag',
                'Geschikt voor'   => 'Wand, meubel, objecten',
                'Waterdicht'      => 'Ja, met PU toplaag',
            ],
            'base_id'        => 11162,
            'unit'           => '4m² pakket',
            'unitM2'         => 4,
            'has_pu'         => true,
            'has_primer'     => true,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => false,
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'kleurstalen_url' => '/velvet-kleurstalen/',
            'option_order'   => ['color', 'primer', 'pu', 'tools'],  // corrected from WAPO block order
            'faq' => [
                ['q' => 'Hoeveel m² heb ik nodig?', 'a' => 'Reken het oppervlak uit (lengte × breedte) en bestel minimaal die hoeveelheid. Wij adviseren 10% extra aan te houden voor snijverlies en onregelmatigheden.'],
                ['q' => 'Hoe krijg ik het metallic effect?', 'a' => 'Het metallic effect ontstaat door de speciale Velvet pasta in twee lagen aan te brengen. Door de richting van je spatel te variëren creëer je een uniek lichtspel.'],
                ['q' => 'Is Metallic Velvet geschikt voor vloeren?', 'a' => 'Metallic Velvet is primair ontwikkeld voor wanden en meubels. Voor vloeren raden wij Beton Ciré Original of Lavasteen aan.'],
                ['q' => 'Is een PU toplaag nodig?', 'a' => 'Voor wanden is PU optioneel maar het maakt schoonmaken makkelijker. Voor meubels en aanrechtbladen raden wij minimaal 2 lagen PU aan.'],
                ['q' => 'Hoe lang is de droogtijd?', 'a' => 'Reken op 24 uur per laag bij kamertemperatuur (18-22°C). Na de laatste PU-laag is het oppervlak na 7 dagen volledig uitgehard.'],
            ],
        ],

        // ─── LAVASTEEN ───────────────────────────────────────────────
        // 20 colors (own palette), PU 40/80/120/0, default = 1 layer
        'lavasteen' => [
            'cats'           => [464],
            'usps'           => [
                'Minerale gietvloer met vulkanisch gesteente',
                'Extreem hard, slijtvast en UV-bestendig',
                'Waterdicht tot in de kern',
            ],
            'specs'          => [
                'Verbruik'        => '5 m² per pakket',
                'Type'            => 'Mineraal gebonden lavasteen',
                'Bindmiddel'      => '2-componenten epoxy',
                'Lagen'           => '2 lagen',
                'Droogtijd'       => '24 uur per laag',
                'Geschikt voor'   => 'Vloer, wand',
                'Waterdicht'      => 'Ja, tot in de kern',
                'UV-bestendig'    => 'Ja',
            ],
            'base_id'        => 27736,
            'unit'           => '5m² pakket',
            'unitM2'         => 5,
            'has_pu'         => true,
            'has_primer'     => true,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => false,
            'ral_ncs_only'   => false,
            'has_tools'      => true,
            'kleurstalen_url'  => '/product/kleurenpakket/',
            'kleurstalen_text' => 'Kleurstalen aanvragen',
            'option_order'   => ['color', 'primer', 'pu', 'tools'],
            'faq' => [
                ['q' => 'Hoeveel m² heb ik nodig?', 'a' => 'Reken het oppervlak uit (lengte × breedte) en bestel minimaal die hoeveelheid. Wij adviseren 10% extra aan te houden voor snijverlies en onregelmatigheden.'],
                ['q' => 'Wat is Lavasteen gietvloer?', 'a' => 'Lavasteen is een minerale gietvloer op basis van vulkanisch gesteente. Het geeft een robuuste, natuurlijke uitstraling met de sterkte van een industriële vloer.'],
                ['q' => 'Is Lavasteen geschikt voor vloerverwarming?', 'a' => 'Ja, Lavasteen geleidt warmte uitstekend en is volledig geschikt voor vloerverwarming. Het oppervlak warmt gelijkmatig op.'],
                ['q' => 'Is een PU toplaag nodig?', 'a' => 'Wij raden minimaal 1 laag PU aan voor bescherming. Voor intensief belopen ruimtes adviseren wij 2 lagen voor extra slijtvastheid.'],
                ['q' => 'Hoe lang is de droogtijd?', 'a' => 'Reken op 24 uur per laag bij kamertemperatuur (18-22°C). Na de laatste PU-laag is het oppervlak na 7 dagen volledig uitgehard.'],
            ],
        ],

        // ─── BETONLOOK VERF ─────────────────────────────────────────
        // Single product (no color variants of its own).
        // Borrows the All-in-One color palette via share_colors_from.
        // Primer 6.00, RAL/NCS
        'betonlook-verf' => [
            'cats'           => [],
            'share_colors_from' => 'all-in-one',  // borrow color swatches from All-in-One K&K
            'usps'           => [
                'Eenvoudig betonlook effect met verf',
                'Snel aan te brengen, geen speciale kennis nodig',
                'Beschikbaar in RAL/NCS kleuren',
            ],
            'specs'          => [
                'Type'            => 'Betonlook verf',
                'Aanbrengen'      => 'Met roller of kwast',
                'Geschikt voor'   => 'Wand',
            ],
            'product_ids'    => [11135],  // single product, detect by ID
            'base_id'        => null,
            'unit'           => 'stuk',
            'unitM2'         => 0,
            'has_pu'         => false,
            'has_primer'     => true,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => true,
            'ral_ncs_only'   => false,
            'kleurstalen_url' => '/kleurstalen-betonlook/',
            'option_order'   => ['color', 'primer'],
            'faq' => [
                ['q' => 'Hoeveel m² doe ik met één pot?', 'a' => 'Eén pot is voldoende voor het aangegeven oppervlak in m². Breng twee lagen aan voor een dekkend resultaat. Houd 10% extra aan voor onregelmatigheden.'],
                ['q' => 'Kan ik Betonlook Verf over tegels aanbrengen?', 'a' => 'Ja, mits de tegels schoon, vetvrij en licht opgeschuurd zijn. Gebruik altijd onze primer voor optimale hechting.'],
                ['q' => 'Is Betonlook Verf waterdicht?', 'a' => 'Betonlook Verf is vochtbestendig maar niet volledig waterdicht. Voor natte ruimtes zoals douches raden wij Microcement of Beton Ciré Original aan.'],
                ['q' => 'Hoe lang is de droogtijd?', 'a' => 'Reken op 24 uur per laag bij kamertemperatuur (18-22°C). Na 48 uur is het oppervlak volledig droog en belastbaar.'],
            ],
        ],

        // ─── STUCO PASTE ─────────────────────────────────────────────
        // No colors, primer 16.00
        'stuco-paste' => [
            'cats'           => [457],
            'usps'           => [
                'Decoratieve stucpasta voor wanden',
                'Creëer diverse texturen en effecten',
                'Eenvoudig aan te brengen',
            ],
            'specs'          => [
                'Type'            => 'Decoratieve stucpasta',
                'Geschikt voor'   => 'Wand',
            ],
            'base_id'        => null,
            'unit'           => 'stuk',
            'unitM2'         => 0,
            'has_pu'         => false,
            'has_primer'     => true,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => false,
            'ral_ncs_only'   => false,
            'option_order'   => ['primer'],
            'faq' => [
                ['q' => 'Waarvoor gebruik ik Stuco Paste?', 'a' => 'Stuco Paste is een decoratieve stucpasta waarmee je diverse texturen en effecten op wanden kunt creëren. Denk aan een betonlook, marmereffect of rustieke stijl.'],
                ['q' => 'Heb ik primer nodig?', 'a' => 'Wij raden altijd primer aan voor de beste hechting, vooral op gladde of geschilderde ondergronden.'],
            ],
        ],

        // ─── PU COLOR ────────────────────────────────────────────────
        // RAL/NCS ONLY — standard colors disabled in WAPO
        'pu-color' => [
            'cats'           => [456],
            'usps'           => [
                'Gekleurde PU toplaag op maat',
                'Beschermt en kleurt in één laag',
                'Elke RAL/NCS kleur mogelijk',
            ],
            'specs'          => [
                'Type'            => 'Gekleurde polyurethaan toplaag',
                'Kleursysteem'    => 'RAL / NCS',
                'Waterdicht'      => 'Ja',
            ],
            'base_id'        => null,
            'unit'           => 'stuk',
            'unitM2'         => 0,
            'has_pu'         => false,
            'has_primer'     => false,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => true,
            'ral_ncs_only'   => true,   // ONLY RAL/NCS, standard swatches disabled
            'option_order'   => ['color'],
            'faq' => [
                ['q' => 'Wat is PU Color?', 'a' => 'PU Color is een gekleurde polyurethaan toplaag. Het beschermt je Beton Ciré oppervlak én geeft het een kleur naar keuze in elke RAL of NCS tint.'],
                ['q' => 'Hoe breng ik PU Color aan?', 'a' => 'Breng PU Color aan met een roller of kwast over een volledig uitgeharde Beton Ciré ondergrond. Minimaal 2 lagen voor een dekkend resultaat.'],
            ],
        ],
    ];

    /**
     * Toepassing options (Original only). All free, no pricing.
     */
    private static $toepassing_options = [
        'Vloer', 'Wand', 'Meubel', 'Keuken', 'Badkamer', 'Trap',
    ];

    /**
     * Colorfresh options (Original only).
     * [label, price, default]
     */
    private static $colorfresh_options = [
        ['Zonder Colorfresh', 0,     true],   // default = yes in WAPO
        ['Met Colorfresh',    15.00, false],
    ];

    /**
     * Pakket options per line. Only Original and Easyline have this.
     * [label, price, default]
     */
    private static $pakket_options = [
        'original' => [
            ['5m2', 0, true],
        ],
        'easyline' => [
            ['5m2', 0, true],
        ],
    ];


    /* ══════════════════════════════════════════════════════════════════
     * GENERIC ADDON GROUPS — per-product option groups for generic_addons mode
     *
     * Replaces YITH WAPO for non-line products. Each product ID maps to
     * an array of addon groups. Each group has:
     *   key      => unique slug (used in cart data as oz_addon_{key})
     *   label    => display label
     *   type     => 'select' (single choice — only type for now)
     *   required => bool (must pick a non-zero option)
     *   options  => array of [label, price, default]
     *
     * Source: YITH WAPO Block #14 extracted 2026-03-07.
     * ══════════════════════════════════════════════════════════════════ */

    private static $generic_addon_configs = [

        // Gereedschapset Kant & Klaar (product 11177)
        // YITH Block #14, Addon #40 + #41
        // Options format: [label, price, default] — same as PU/primer/colorfresh
        11177 => [
            [
                'key'      => 'formaat_roller',
                'label'    => 'Formaat Roller',
                'type'     => 'select',
                'required' => false,
                'options'  => [
                    ['Roller 10cm', 0,     true],
                    ['Roller 18cm', 10,    false],
                    ['Roller 25cm', 14.99, false],
                ],
            ],
            [
                'key'      => 'troffels',
                'label'    => 'Troffels?',
                'type'     => 'select',
                'required' => false,
                'options'  => [
                    ['Nee, Ik heb voldoende gereedschap', 0,  true],
                    ['Ja, Ik wil alle troffels erbij',    45, false],
                ],
            ],
        ],

        // Kleurenstalen pakket (product 13718)
        // 4 product line choices — no price difference, just selection
        13718 => [
            [
                'key'      => 'type',
                'label'    => 'Type',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    ['Original',               0, false],
                    ['Beton Ciré Kant & Klaar', 0, false],
                    ['Microcement',             0, false],
                    ['Lavasteen',               0, true],
                ],
            ],
        ],

        // Gereedschapset Zelf Mengen (product 11163)
        // Same YITH Block #14 — identical addons
        11163 => [
            [
                'key'      => 'formaat_roller',
                'label'    => 'Formaat Roller',
                'type'     => 'select',
                'required' => false,
                'options'  => [
                    ['Roller 10cm', 0,     true],
                    ['Roller 18cm', 10,    false],
                    ['Roller 25cm', 14.99, false],
                ],
            ],
            [
                'key'      => 'troffels',
                'label'    => 'Troffels?',
                'type'     => 'select',
                'required' => false,
                'options'  => [
                    ['Nee, Ik heb voldoende gereedschap', 0,  true],
                    ['Ja, Ik wil alle troffels erbij',    45, false],
                ],
            ],
        ],

        // Gereedschapset Lavasteen / 2K Epoxystone (product 25550)
        // Same roller + troffels options as Kant & Klaar and Zelf Mengen
        25550 => [
            [
                'key'      => 'formaat_roller',
                'label'    => 'Formaat Roller',
                'type'     => 'select',
                'required' => false,
                'options'  => [
                    ['Roller 10cm', 0,     true],
                    ['Roller 18cm', 10,    false],
                    ['Roller 25cm', 14.99, false],
                ],
            ],
            [
                'key'      => 'troffels',
                'label'    => 'Troffels?',
                'type'     => 'select',
                'required' => false,
                'options'  => [
                    ['Nee, Ik heb voldoende gereedschap', 0,  true],
                    ['Ja, Ik wil alle troffels erbij',    45, false],
                ],
            ],
        ],
    ];

    /* ══════════════════════════════════════════════════════════════════
     * GENERIC PRODUCT CONTENT — USPs & specs for non-line products
     *
     * Products without a BCW product line have no config-level USPs or
     * specs. This static map provides per-product content so the
     * template can render complete pages without manual _oz_usps/_oz_specs
     * meta setup.
     *
     * Fallback chain (template): _oz_usps meta → this config → empty (hidden)
     * ══════════════════════════════════════════════════════════════════ */

    private static $generic_product_content = [

        // Gereedschapset Kant & Klaar (11177) — €89.99
        11177 => [
            'usps' => [
                'Compleet gereedschapspakket voor Beton Ciré',
                'Geschikt voor alle Kant & Klaar toepassingen',
                'Spaan, PU rollers, kwast, verfbakken en meer',
                'Optioneel uitbreidbaar met troffels',
            ],
            'specs' => [
                'Geschikt voor'     => 'Kant & Klaar Beton Ciré',
                'Inhoud'            => 'Spaan, 3x PU roller, kwast, PU garde, tape, 2x verfbak, vachtroller',
                'Rollerformaat'     => '10cm (standaard), 18cm of 25cm',
                'Optioneel'         => 'Troffelset (+€45)',
            ],
        ],

        // Gereedschapset Zelf Mengen (11163) — €119.99
        11163 => [
            'usps' => [
                'Compleet gereedschapspakket voor Zelf Mengen',
                'Geschikt voor alle Zelf Mengen & Mixen toepassingen',
                'Spaan, PU rollers, kwast, verfbakken en meer',
                'Optioneel uitbreidbaar met troffels',
            ],
            'specs' => [
                'Geschikt voor'     => 'Zelf Mengen & Mixen Beton Ciré',
                'Inhoud'            => 'Spaan, 3x PU roller, kwast, PU garde, tape, 2x verfbak, vachtroller, mengstaaf',
                'Rollerformaat'     => '10cm (standaard), 18cm of 25cm',
                'Optioneel'         => 'Troffelset (+€45)',
            ],
        ],

        // Gereedschapset Lavasteen (25550) — €115.95
        25550 => [
            'usps' => [
                'Compleet gereedschapspakket voor Lavasteen',
                'Geschikt voor alle 2K Epoxystone toepassingen',
                'Spaan, PU rollers, kwast, verfbakken en meer',
                'Optioneel uitbreidbaar met troffels',
            ],
            'specs' => [
                'Geschikt voor'     => '2K Epoxystone / Lavasteen',
                'Inhoud'            => 'Spaan, 3x PU roller, kwast, PU garde, tape, 2x verfbak, vachtroller',
                'Rollerformaat'     => '10cm (standaard), 18cm of 25cm',
                'Optioneel'         => 'Troffelset (+€45)',
            ],
        ],

        // RAL Kleurenwaaier (10998) — €15.00
        10998 => [
            'usps' => [
                'Bekijk RAL kleuren in het echt',
                '€15 cashback bij bestelling Beton Ciré pakket',
                'Handig ter indicatie voor kleurkeuze',
            ],
            'specs' => [
                'Type'          => 'RAL Kleurenwaaier',
                'Cashback'      => '€15 bij pakketbestelling',
            ],
        ],

        // Verfbak (11164) — €2.95
        11164 => [
            'usps' => [
                'Geschikt voor 10cm, 18cm en 25cm rollers',
                'Stevig kunststof',
            ],
            'specs' => [
                'Formaten' => '10cm, 18cm, 25cm',
                'Materiaal' => 'Kunststof',
            ],
        ],
    ];

    /**
     * Get content (USPs + specs) for a generic product.
     * Returns array with 'usps' and 'specs' keys, or false.
     *
     * @param int $product_id
     * @return array|false
     */
    public static function get_product_content($product_id) {
        return isset(self::$generic_product_content[$product_id])
            ? self::$generic_product_content[$product_id]
            : false;
    }

        /**
     * Get addon groups for a product.
     * Checks product meta _oz_addon_groups first, falls back to static config.
     *
     * @param int $product_id
     * @return array|false  Array of addon groups or false
     */
    public static function get_addon_groups($product_id) {
        // Future: check product meta first
        // $meta = get_post_meta($product_id, '_oz_addon_groups', true);
        // if (!empty($meta) && is_array($meta)) return $meta;

        if (isset(self::$generic_addon_configs[$product_id])) {
            return self::$generic_addon_configs[$product_id];
        }

        return false;
    }

    /**
     * Get addon groups formatted for wp_localize_script (JS payload).
     * Transforms [label, price, default] tuples into keyed objects.
     *
     * @param int $product_id
     * @return array|false
     */
    public static function get_addon_groups_for_js($product_id) {
        $groups = self::get_addon_groups($product_id);
        if (!$groups) {
            return false;
        }

        $js_groups = [];
        foreach ($groups as $group) {
            $options = [];
            foreach ($group['options'] as list($label, $price, $default)) {
                $options[] = [
                    'label'   => $label,
                    'price'   => $price,
                    'default' => $default,
                ];
            }
            $js_groups[] = [
                'key'      => $group['key'],
                'label'    => $group['label'],
                'type'     => $group['type'],
                'required' => $group['required'],
                'options'  => $options,
            ];
        }
        return $js_groups;
    }

    /**
     * Check if a product has generic addon groups configured.
     *
     * @param int $product_id
     * @return bool
     */
    public static function has_addon_groups($product_id) {
        return isset(self::$generic_addon_configs[$product_id]);
    }

    /**
     * Resolve total addon price surcharge for a generic_addons product.
     * Reads oz_addon_{key} values from cart data and sums matching prices.
     *
     * @param int   $product_id
     * @param array $cart_data  Cart item data (oz_addon_* keys)
     * @return float  Per-unit surcharge
     */
    public static function resolve_generic_addon_price($product_id, $cart_data) {
        $groups = self::get_addon_groups($product_id);
        if (!$groups) {
            return 0;
        }

        $total = 0;
        foreach ($groups as $group) {
            $cart_key = 'oz_addon_' . $group['key'];
            if (empty($cart_data[$cart_key])) {
                continue;
            }
            $selected_label = $cart_data[$cart_key];
            foreach ($group['options'] as list($label, $price, $default)) {
                if ($label === $selected_label) {
                    $total += floatval($price);
                    break;
                }
            }
        }
        return $total;
    }


    /* ══════════════════════════════════════════════════════════════════
     * PAGE MODE — determines which template mode a product uses
     *
     * Three modes:
     *   'configured_line'  — auto-detected BCW product line (full options)
     *   'generic_simple'   — manually assigned, no addons (just shell)
     *   'generic_addons'   — manually assigned, with custom addon groups
     *   false              — not our product, use theme default
     * ══════════════════════════════════════════════════════════════════ */

    /** Valid page modes that can be set via product meta */
    private static $valid_modes = ['generic_simple', 'generic_addons'];

    /**
     * Determine the page mode for a product.
     * BCW product lines are auto-detected. Other products can be
     * manually assigned a mode via _oz_page_mode product meta.
     *
     * @param WC_Product $product
     * @return string|false  Page mode or false (use theme default)
     */
    public static function get_page_mode($product) {
        // BCW product lines always use configured_line
        if (self::detect($product)) {
            return 'configured_line';
        }

        // Check for explicit page mode meta (set via admin metabox)
        $mode = get_post_meta($product->get_id(), '_oz_page_mode', true);
        if (in_array($mode, self::$valid_modes, true)) {
            return $mode;
        }

        // Auto-detect products with generic addon configs
        if (self::has_addon_groups($product->get_id())) {
            return 'generic_addons';
        }

        // All products use our template — default to generic_simple
        return 'generic_simple';
    }

    /**
     * Get a default config array for generic products (no product line).
     * Provides sensible fallbacks so the template doesn't need null checks.
     *
     * @return array
     */
    public static function get_generic_config() {
        return [
            'cats'           => [],
            'usps'           => [],
            'specs'          => [],
            'base_id'        => null,

            'unit'           => 'stuk',
            'unitM2'         => 0,
            'has_pu'         => false,
            'has_primer'     => false,
            'has_colorfresh' => false,
            'has_toepassing' => false,
            'has_pakket'     => false,
            'ral_ncs'        => false,
            'ral_ncs_only'   => false,
            'has_tools'      => false,
            'option_order'   => [],
        ];
    }


    /* ══════════════════════════════════════════════════════════════════
     * DETECTION
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Detect product line from a WooCommerce product.
     * Shell wrapper — fetches category IDs then delegates to pure detect_from_data().
     *
     * @param WC_Product $product
     * @return string|false  Line key or false
     */
    public static function detect($product) {
        $product_id   = $product->get_id();
        $category_ids = self::get_category_ids($product_id);
        return self::detect_from_data($product_id, $category_ids);
    }

    /**
     * Pure detection: match product to a line using pre-fetched data.
     * No I/O — testable with plain arrays.
     *
     * @param int   $product_id    WooCommerce product ID
     * @param array $category_ids  Product's category term IDs
     * @return string|false  Line key or false
     */
    public static function detect_from_data($product_id, array $category_ids) {
        foreach (self::$lines as $key => $line) {
            // Category match (main detection path)
            if (!empty($line['cats']) && array_intersect($category_ids, $line['cats'])) {
                return $key;
            }
            // product_ids array — loose emmers, single-product lines
            if (!empty($line['product_ids']) && in_array($product_id, $line['product_ids'], false)) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Get full config for a line key.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_config($line_key) {
        return isset(self::$lines[$line_key]) ? self::$lines[$line_key] : false;
    }

    /**
     * Detect + get config in one call.
     *
     * @param WC_Product $product
     * @return array  ['line' => key, 'config' => array] or ['line' => false, 'config' => false]
     */
    public static function for_product($product) {
        $line = self::detect($product);
        return [
            'line'   => $line,
            'config' => $line ? self::get_config($line) : false,
        ];
    }


    /* ══════════════════════════════════════════════════════════════════
     * PU — PAYLOAD-READY OPTIONS + PRICE LOOKUP
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get PU options array for frontend / wp_localize_script.
     * Returns array of {layers, label, price, default} or false.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_pu_options($line_key) {
        if (!isset(self::$pu_options[$line_key])) {
            return false;
        }

        $prices  = self::$pu_prices[$line_key];
        $options = [];

        foreach (self::$pu_options[$line_key] as list($layers, $label, $default)) {
            $options[] = [
                'layers'  => $layers,
                'label'   => $label,
                'price'   => isset($prices[$layers]) ? $prices[$layers] : 0,
                'default' => $default,
            ];
        }
        return $options;
    }

    /**
     * Look up the PU price for a given layer count.
     * Direct table lookup — no formula.
     *
     * @param string $line_key
     * @param int    $layers  0–3
     * @return float
     */
    public static function get_pu_price($line_key, $layers) {
        $layers = max(0, min(3, intval($layers)));
        if (!isset(self::$pu_prices[$line_key])) {
            return 0;
        }
        return isset(self::$pu_prices[$line_key][$layers])
            ? floatval(self::$pu_prices[$line_key][$layers])
            : 0;
    }


    /* ══════════════════════════════════════════════════════════════════
     * PRIMER — PAYLOAD-READY OPTIONS + PRICE LOOKUP
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get primer options for frontend.
     * Returns array of {label, price, default} or false.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_primer_options($line_key) {
        if (!isset(self::$primer_options[$line_key])) {
            return false;
        }

        $options = [];
        foreach (self::$primer_options[$line_key] as $opt) {
            $options[] = [
                'label'       => $opt[0],
                'price'       => $opt[1],
                'default'     => $opt[2],
                'recommended' => isset($opt[3]) ? $opt[3] : false,
            ];
        }
        return $options;
    }

    /**
     * Look up primer price for a given selection.
     *
     * @param string $line_key
     * @param string $primer_choice  The selected option label
     * @return float
     */
    public static function get_primer_price($line_key, $primer_choice) {
        if (!isset(self::$primer_options[$line_key])) {
            return 0;
        }
        foreach (self::$primer_options[$line_key] as list($label, $price, $default)) {
            if ($label === $primer_choice) {
                return floatval($price);
            }
        }
        return 0;
    }


    /* ══════════════════════════════════════════════════════════════════
     * COLORFRESH — ORIGINAL ONLY
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get colorfresh options for frontend.
     * Returns array of {label, price, default} or false.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_colorfresh_options($line_key) {
        $config = self::get_config($line_key);
        if (!$config || !$config['has_colorfresh']) {
            return false;
        }

        $options = [];
        foreach (self::$colorfresh_options as list($label, $price, $default)) {
            $options[] = [
                'label'   => $label,
                'price'   => $price,
                'default' => $default,
            ];
        }
        return $options;
    }

    /**
     * Get colorfresh price for a given selection.
     *
     * @param string $line_key
     * @param string $choice
     * @return float
     */
    public static function get_colorfresh_price($line_key, $choice) {
        $config = self::get_config($line_key);
        if (!$config || !$config['has_colorfresh']) {
            return 0;
        }
        foreach (self::$colorfresh_options as list($label, $price, $default)) {
            if ($label === $choice) {
                return floatval($price);
            }
        }
        return 0;
    }


    /* ══════════════════════════════════════════════════════════════════
     * TOEPASSING — ORIGINAL ONLY
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get toepassing options for frontend.
     * Returns array of strings or false.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_toepassing_options($line_key) {
        $config = self::get_config($line_key);
        if (!$config || !$config['has_toepassing']) {
            return false;
        }
        return self::$toepassing_options;
    }


    /* ══════════════════════════════════════════════════════════════════
     * PAKKET
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get pakket options for frontend.
     * Returns array of {label, price, default} or false.
     *
     * @param string $line_key
     * @return array|false
     */
    public static function get_pakket_options($line_key) {
        if (!isset(self::$pakket_options[$line_key])) {
            return false;
        }

        $options = [];
        foreach (self::$pakket_options[$line_key] as list($label, $price, $default)) {
            $options[] = [
                'label'   => $label,
                'price'   => $price,
                'default' => $default,
            ];
        }
        return $options;
    }


    /* ══════════════════════════════════════════════════════════════════
     * CONVENIENCE
     * ══════════════════════════════════════════════════════════════════ */

    /** @return int|null */
    public static function get_base_product_id($line_key) {
        $config = self::get_config($line_key);
        return $config ? $config['base_id'] : null;
    }

    /** @return array */
    public static function get_option_order($line_key) {
        $config = self::get_config($line_key);
        return $config ? $config['option_order'] : [];
    }

    /** @return array  All line keys */
    public static function get_all_lines() {
        return array_keys(self::$lines);
    }


    /* ══════════════════════════════════════════════════════════════════
     * DEFAULTS — server-side fallback for omitted POST fields
     *
     * Returns the default addon values for a product line, derived from
     * the 'default' flags in PU/primer/colorfresh option tables.
     * Used by OZ_Cart_Manager::capture_addon_data() when POST omits keys.
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get default addon values for a product line.
     * Reads the 'default' flag from each option table.
     *
     * @param string $line_key
     * @return array  Keys: oz_pu_layers, oz_primer, oz_colorfresh (only set if line has them)
     */
    public static function get_defaults($line_key) {
        $defaults = [];

        // PU default layers
        if (isset(self::$pu_options[$line_key])) {
            foreach (self::$pu_options[$line_key] as list($layers, $label, $is_default)) {
                if ($is_default) {
                    $defaults['oz_pu_layers'] = $layers;
                    break;
                }
            }
        }

        // Primer default
        if (isset(self::$primer_options[$line_key])) {
            foreach (self::$primer_options[$line_key] as list($label, $price, $is_default)) {
                if ($is_default) {
                    $defaults['oz_primer'] = $label;
                    break;
                }
            }
        }

        // Colorfresh default (Original only)
        $config = self::get_config($line_key);
        if ($config && $config['has_colorfresh']) {
            foreach (self::$colorfresh_options as list($label, $price, $is_default)) {
                if ($is_default) {
                    $defaults['oz_colorfresh'] = $label;
                    break;
                }
            }
        }

        // ral_ncs_only lines force ral_ncs color mode
        if ($config && $config['ral_ncs_only']) {
            $defaults['oz_color_mode'] = 'ral_ncs';
        }

        return $defaults;
    }


    /* ══════════════════════════════════════════════════════════════════
     * FULL ADDON PRICE RESOLVER
     *
     * Single function that takes a line key + cart item data array
     * and returns the total addon surcharge (per unit).
     * Used by cart manager for woocommerce_before_calculate_totals.
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Resolve total addon price surcharge for a cart item.
     *
     * @param string $line_key
     * @param array  $item_data  Cart item data keys (oz_pu_layers, oz_primer, etc.)
     * @return float  Per-unit surcharge
     */
    public static function resolve_addon_price($line_key, $item_data) {
        $total = 0;

        // PU layers
        if (isset($item_data['oz_pu_layers']) && $item_data['oz_pu_layers'] !== '') {
            $total += self::get_pu_price($line_key, $item_data['oz_pu_layers']);
        }

        // Primer
        if (!empty($item_data['oz_primer'])) {
            $total += self::get_primer_price($line_key, $item_data['oz_primer']);
        }

        // Colorfresh (Original only)
        if (!empty($item_data['oz_colorfresh'])) {
            $total += self::get_colorfresh_price($line_key, $item_data['oz_colorfresh']);
        }

        // Toepassing and pakket are free — no price impact

        return $total;
    }


    /* ══════════════════════════════════════════════════════════════════
     * INTERNAL HELPERS
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Get WooCommerce category IDs for a product.
     *
     * @param int $product_id
     * @return array
     */
    private static function get_category_ids($product_id) {
        $terms = get_the_terms($product_id, 'product_cat');
        if (empty($terms) || is_wp_error($terms)) {
            return [];
        }
        return wp_list_pluck($terms, 'term_id');
    }


    /* ══════════════════════════════════════════════════════════════════
     * TOOL / GEREEDSCHAP CATALOG
     *
     * Each tool is defined once in $tool_catalog. The extras and
     * individual lists reference items by ID — no duplication.
     * All prices and WooCommerce product IDs from real BCW catalog.
     * ══════════════════════════════════════════════════════════════════ */

    /**
     * Master tool catalog — each tool defined exactly once.
     * Keyed by tool slug. Contains name, base price, WC product ID,
     * optional note, and optional size variants.
     */
    private static $tool_catalog = [
        'flexibele-spaan' => [
            'name' => 'Flexibele spaan', 'price' => 39.95, 'wcId' => 11025,
        ],
        'pu-roller' => [
            'name' => 'PU Roller', 'price' => 2.50, 'wcId' => 11175,
            'note' => 'Verhardt na ~2 uur',
            'sizes' => [
                ['label' => '10cm', 'price' => 2.50,  'wcId' => 11175],
                ['label' => '18cm', 'price' => 9.95,  'wcId' => 17360],
                ['label' => '25cm', 'price' => 12.95, 'wcId' => 17361],
                ['label' => '50cm', 'price' => 17.50, 'wcId' => 19705],
            ],
        ],
        'kwast' => [
            'name' => 'Kwast', 'price' => 1.99, 'wcId' => 11022,
        ],
        'pu-garde' => [
            'name' => 'PU garde', 'price' => 8.99, 'wcId' => 11020,
        ],
        'tape' => [
            'name' => 'Tape', 'price' => 5.99, 'wcId' => 11018,
        ],
        'verfbak' => [
            'name' => 'Verfbak', 'price' => 2.95, 'wcId' => 11164,
            'sizes' => [
                ['label' => '10cm', 'price' => 2.95, 'wcId' => 11164],
                ['label' => '18cm', 'price' => 4.95, 'wcId' => 28234],  // Separate product: Verfbak 18cm
                ['label' => '32cm', 'price' => 5.95, 'wcId' => 28235],  // Separate product: Verfbak 32cm
            ],
        ],
        'vachtroller' => [
            'name' => 'Vachtroller', 'price' => 8.95, 'wcId' => 11015,
        ],
        'blokkwast' => [
            'name' => 'Blokkwast', 'price' => 6.99, 'wcId' => 22997,
        ],
        'troffel' => [
            'name' => 'Troffel 180mm', 'price' => 16.95, 'wcId' => 11017,
        ],
        'hoek-inwendig' => [
            'name' => 'Inwendige hoektroffel', 'price' => 15.95, 'wcId' => 11023,
        ],
        'hoek-uitwendig' => [
            'name' => 'Uitwendige hoektroffel', 'price' => 15.95, 'wcId' => 11016,
        ],
    ];

    /**
     * Gereedschapset Kant & Klaar — the complete set product.
     */
    private static $tool_set = [
        'id'       => 11177,
        'name'     => 'Gereedschapset Kant & Klaar',
        'price'    => 89.99,
        'contents' => [
            '1x Flexibele spaan',
            '1x Kwast primer',
            '1x Kwast PU',
            '1x PU garde',
            '3x PU roller',
            '1x Tape',
            '2x Verfbak',
            '1x Vachtroller',
        ],
    ];

    /** Tool IDs available as extras on top of the set */
    private static $set_extra_ids = [
        'pu-roller', 'verfbak', 'tape', 'vachtroller',
        'troffel', 'hoek-inwendig', 'hoek-uitwendig',
    ];

    /** Tool IDs available in "Zelf samenstellen" (individual) mode */
    private static $individual_tool_ids = [
        'flexibele-spaan', 'pu-roller', 'kwast', 'pu-garde', 'tape',
        'verfbak', 'vachtroller', 'blokkwast', 'troffel',
        'hoek-inwendig', 'hoek-uitwendig',
    ];

    /**
     * Get tool/gereedschap configuration for JS.
     * Composes from single-source catalog — no data duplication.
     *
     * @return array  Tool config array for wp_localize_script
     */
    /**
     * Check WooCommerce stock status for a tool item wcId.
     * Returns true if the product exists and is in stock, false otherwise.
     */
    private static function check_tool_stock($wc_id) {
        if (!function_exists('wc_get_product') || !$wc_id) return true;
        $product = wc_get_product($wc_id);
        return $product ? $product->is_in_stock() : false;
    }

    /**
     * Add stock status to a tool/extra item and its sizes.
     * Adds 'inStock' boolean to the item and each size entry.
     */
    private static function enrich_with_stock($item) {
        $item['inStock'] = self::check_tool_stock($item['wcId'] ?? null);
        if (!empty($item['sizes'])) {
            foreach ($item['sizes'] as &$size) {
                $size['inStock'] = self::check_tool_stock($size['wcId'] ?? null);
            }
            unset($size);
        }
        return $item;
    }

    public static function get_tool_config() {
        // Build extras and tools arrays from catalog references
        // Each item is enriched with stock status from WooCommerce
        $extras = [];
        foreach (self::$set_extra_ids as $id) {
            $extras[] = self::enrich_with_stock(
                array_merge(['id' => $id], self::$tool_catalog[$id])
            );
        }

        $tools = [];
        foreach (self::$individual_tool_ids as $id) {
            $tools[] = self::enrich_with_stock(
                array_merge(['id' => $id], self::$tool_catalog[$id])
            );
        }

        return [
            'toolSet'            => self::$tool_set,
            'extras'             => $extras,
            'tools'              => $tools,
            'nudgeQtyThreshold'  => 3,
        ];
    }
}
