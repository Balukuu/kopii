<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Categories for products in the shop. These are separate from the service categories that
 * providers are listed under, because a seller of fabric and a tailor are different things.
 *
 * Seeded once into `product_categories` (see Seeder::seedProductCategories). After that the
 * database is the source of truth, so the list can be edited there without touching this file.
 *
 * [name, slug, icon (see svg_icon()), sort order]
 */
return [
    ['Fashion & Textiles',        'fashion-textiles',   'scissors', 1],
    ['Food & Groceries',          'food-groceries',     'utensils', 2],
    ['Electronics & Phones',      'electronics-phones', 'code',     3],
    ['Home & Furniture',          'home-furniture',     'home',     4],
    ['Beauty & Personal Care',    'beauty-personal',    'sparkles', 5],
    ['Farm Produce & Agri Inputs', 'farm-agri',         'layers',   6],
    ['Building & Hardware',       'building-hardware',  'truck',    7],
    ['Art, Crafts & Gifts',       'art-crafts-gifts',   'palette',  8],
    ['Books, Office & School',    'books-office',       'image',    9],
    ['Other Products',            'other-products',     'search',   10],
];
