<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Client testimonials shown on the home page. Empty means the section is not shown.
 *
 * Only add quotes that are genuine and that the person agreed to publish. Showing invented
 * testimonials as if real customers said them misleads visitors and breaks advertising rules
 * in many countries. Format:
 *
 *   ['quote' => 'What they said.', 'name' => 'Full Name', 'detail' => 'Role · Place'],
 *
 * The home page previously had three hard-coded sample quotes (a summit producer in Kololo, a
 * Munyonyo wedding couple, and the founder of Rwenzori Roast). They were removed because there
 * was nothing in the project showing they were real; restore them here only if they are.
 */
return [];
