<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Organisations shown in the "Trusted By Leading Brands and Startups" bar on the home page.
 *
 * PLACEHOLDER CONTENT: the names and the logo files in assets/images/clients/ are samples. Before
 * launch, replace them with clients who have agreed to be shown, using their real logo files.
 *
 *   'name'       Shown to screen readers and if the logo fails to load.
 *   'logo'       Path under the site root. SVG or PNG; a wide logo about 240x48 on a transparent
 *                background works best. The bar shows logos in grey so mixed colours look consistent.
 *   'case_study' true to show a "Watch the Case Study" link under the logo.
 */
return [
    ['name' => 'Serena Hotels',         'logo' => 'assets/images/clients/serena-hotels.svg',          'width' => 206, 'height' => 56,  'case_study' => true,  'query' => 'Serena'],
    ['name' => 'Stanbic Bank',          'logo' => 'assets/images/clients/stanbic-bank.png',           'width' => 352, 'height' => 115, 'case_study' => false, 'query' => 'Stanbic'],
    ['name' => 'Speke Resort Munyonyo', 'logo' => 'assets/images/clients/speke-resort-munyonyo.png',  'width' => 370, 'height' => 104, 'case_study' => false, 'query' => 'Munyonyo'],
    ['name' => 'Rwenzori',              'logo' => 'assets/images/clients/rwenzori.svg',               'width' => 232, 'height' => 64,  'case_study' => false, 'query' => 'Rwenzori'],
    ['name' => 'Centenary Bank',        'logo' => 'assets/images/clients/centenary-bank.png',         'width' => 330, 'height' => 108, 'case_study' => false, 'query' => 'Centenary'],
    ['name' => 'Lake Victoria Hotel',   'logo' => 'assets/images/clients/lake-victoria-hotel.png',    'width' => 564, 'height' => 177, 'case_study' => true,  'query' => 'Lake Victoria'],
];
