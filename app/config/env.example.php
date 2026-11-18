<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Kopii Production Configuration Template (cPanel / LiteSpeed MySQL)
 *
 * Copy this file to `app/config/env.php` OR simply run `/install.php` in your browser
 * on your LiteSpeed / cPanel host to generate `app/config/env.php` automatically.
 */
return [
    'app_name'  => 'Kopii',
    'app_url'   => 'https://kopii.online', // No trailing slash; leave '' for auto-detection
    'app_env'   => 'production',           // 'production' or 'local'
    'timezone'  => 'Africa/Kampala',

    // Behind Cloudflare or another proxy, every visitor looks like the proxy's address, which breaks
    // per-visitor rate limiting. Name the header your proxy sets (for Cloudflare: 'HTTP_CF_CONNECTING_IP').
    // Leave empty if visitors connect directly. Only set it if a proxy really overwrites that header.
    'trusted_ip_header' => '',

    // Address shown to providers in emails about a paused listing or suspended account, so they can
    // reply to the team. Leave '' to say "get in touch with the Kopii team" without an address.
    'support_email' => '',

    // Outbound email (lead alerts to providers, password resets).
    // 'mail' uses PHP's mail() — the normal choice on cPanel. Use a From address on your own
    // domain and enable SPF + DKIM in cPanel → Email Deliverability so messages reach inboxes.
    // 'log' writes emails to storage/mail.log instead of sending (this is the default for app_env 'local').
    'mail' => [
        'driver'          => 'mail',
        'from_email'      => 'no-reply@kopii.online',
        'from_name'       => 'Kopii',
        'envelope_sender' => true,
    ],

    // Database connection: 'mysql' for cPanel/LiteSpeed production, 'sqlite' for local zero-config dev
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'cpaneluser_kopii',
        'username' => 'cpaneluser_kopii',
        'password' => 'CHANGE_ME_STRONG_PASSWORD',
        'charset'  => 'utf8mb4',
    ],
];
