<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Ugandan Cities, Regions & Marketplace Taxonomies
 */
return [
    'cities' => [
        'Kampala',
        'Entebbe',
        'Wakiso',
        'Mukono',
        'Jinja',
        'Mbarara',
        'Gulu',
        'Fort Portal',
        'Mbale',
        'Lira',
        'Masaka',
        'Hoima',
        'Arua',
        'Kabale',
        'Soroti',
        'Kasese',
        'Mityana',
        'Iganga',
    ],

    'kampala_areas' => [
        'Kololo',
        'Nakasero',
        'Ntinda',
        'Bugolobi',
        'Muyenga',
        'Kamwokya',
        'Naguru',
        'Najjera',
        'Kira',
        'Lubowa',
        'Kansanga',
        'industrial Area',
        'Makindye',
        'Mengon / Rubaga',
    ],

    'response_times' => [
        'Within 30 minutes',
        'Within 1 hour',
        'Within 2 hours',
        'Same business day',
        'Within 24 hours',
    ],

    'price_units' => [
        'per session',
        'per event',
        'per day',
        'per project',
        'per hour',
        'starting from',
    ],

    // What a product is sold by. Shown next to the price: "UGX 45,000 per kg".
    'product_units' => [
        'each',
        'per pair',
        'per set',
        'per dozen',
        'per kg',
        'per litre',
        'per bag',
        'per metre',
        'per carton',
    ],

    'stock_statuses' => [
        'in_stock'      => 'In stock',
        'low_stock'     => 'Low stock',
        'made_to_order' => 'Made to order',
        'out_of_stock'  => 'Out of stock',
    ],

    'product_conditions' => [
        'new'         => 'New',
        'used'        => 'Used',
        'refurbished' => 'Refurbished',
    ],

    // Kinds of public opportunity a client or business can post for providers to answer.
    'opportunity_kinds' => [
        'project' => 'Project / gig',
        'job'     => 'Job',
        'tender'  => 'Tender / quotation',
        'supply'  => 'Supply request',
    ],
];
