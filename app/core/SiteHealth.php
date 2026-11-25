<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * A short pre-launch checklist for the administrator: the things that go wrong when a PHP site moves
 * from a developer's computer to cPanel, each stated as one plain sentence. It only reads settings,
 * the file system and PHP's loaded extensions; nothing here changes anything.
 *
 * Some checks are stricter on a live site than on a developer's copy (app_env = local), because
 * a leftover installer or a demo password is only dangerous where strangers can reach it.
 */
final class SiteHealth
{
    public const OK   = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /** The demo administrator password written in the README. It must never survive on a live site. */
    private const DEMO_ADMIN_PASSWORD = 'KopiiAdmin2026!';

    /** @return list<array{level: string, title: string, detail: string}> */
    public static function checks(): array
    {
        $local  = app_is_local();
        $checks = [];
        $add = static function (string $level, string $title, string $detail) use (&$checks): void {
            $checks[] = ['level' => $level, 'title' => $title, 'detail' => $detail];
        };

        // Mode
        if ($local) {
            $add(self::WARN, 'Local mode', 'This copy runs with app_env = local: demo sign-in, on-screen errors and mail written to a file. Right on your computer, never on the live site.');
        } else {
            $add(self::OK, 'Live mode', 'app_env is production: errors are logged, not shown.');
        }

        // HTTPS and the site address
        if (!$local) {
            $add(
                request_is_https() ? self::OK : self::FAIL,
                'HTTPS',
                request_is_https()
                    ? 'This page was opened over HTTPS.'
                    : 'This page was opened over plain HTTP. Install an SSL certificate in cPanel (AutoSSL) and open the site with https://.'
            );
        }
        $appUrl = trim((string) app_config('app_url', ''));
        if ($appUrl === '' && !$local) {
            $add(self::FAIL, 'Site address (app_url)', 'app_url is empty in app/config/env.php, so links in emails are built from whatever address the visitor typed. Set it to https://kopii.online.');
        } elseif ($appUrl !== '' && !$local && !str_starts_with(strtolower($appUrl), 'https://')) {
            $add(self::WARN, 'Site address (app_url)', 'app_url is ' . $appUrl . '. Use the https:// address so the links in emails and the sitemap are secure.');
        } else {
            $add(self::OK, 'Site address (app_url)', $appUrl !== '' ? 'Emails, the sitemap and link previews use ' . $appUrl . '.' : 'Not set: fine on your computer.');
        }

        // Installer and demo credentials
        if (is_file(KOPII_ROOT . '/install.php')) {
            $add(
                $local ? self::WARN : self::FAIL,
                'Installer file',
                $local
                    ? 'install.php exists. Delete it from the live server once the installer has run.'
                    : 'install.php is still on the server. Delete it in cPanel File Manager now: until it is gone, the setup page is reachable by anyone.'
            );
        } else {
            $add(self::OK, 'Installer file', 'install.php has been removed.');
        }

        if (self::demoAdminPasswordInUse()) {
            $add(
                $local ? self::WARN : self::FAIL,
                'Administrator password',
                'An administrator account still uses the demo password from the README. ' . ($local ? 'Fine locally. ' : '') . 'Change it with Account → Password before going live.'
            );
        } else {
            $add(self::OK, 'Administrator password', 'No administrator uses the documented demo password.');
        }

        // Database
        $driver = strtolower((string) app_config('db.driver', 'sqlite'));
        if ($driver === 'sqlite' && !$local) {
            $add(self::FAIL, 'Database', 'The live site is running on SQLite. Kopii is built for MySQL on cPanel: run the installer so it writes the MySQL settings.');
        } else {
            $add(self::OK, 'Database', $driver === 'mysql' ? 'Connected to MySQL.' : 'SQLite, the throw-away local database.');
        }

        // Email
        $mailDriver = strtolower((string) app_config('mail.driver', $local ? 'log' : 'mail'));
        if ($mailDriver === 'log' && !$local) {
            $add(self::FAIL, 'Outgoing email', 'Email is set to "log", so nothing is sent: lead alerts, password resets and confirmation links will never arrive. Set mail.driver to "mail".');
        } elseif ($mailDriver === 'log') {
            $add(self::OK, 'Outgoing email', 'Written to storage/mail.log instead of being sent (local mode).');
        } else {
            $from     = strtolower(trim((string) app_config('mail.from_email', '')));
            $siteHost = strtolower((string) (parse_url($appUrl, PHP_URL_HOST) ?: ''));
            $siteHost = (string) preg_replace('/^www\./', '', $siteHost);
            $fromHost = $from !== '' && str_contains($from, '@') ? substr($from, (int) strrpos($from, '@') + 1) : '';
            if ($siteHost !== '' && $fromHost !== '' && $fromHost !== $siteHost) {
                $add(self::WARN, 'Outgoing email', 'Emails are sent from ' . $from . ', which is not on ' . $siteHost . '. Mail from another domain is often filed as spam; use an address on your own domain, with SPF and DKIM on.');
            } else {
                $add(self::OK, 'Outgoing email', 'Sent with PHP mail() from ' . ($from !== '' ? $from : 'the default address') . '. Use "Send a test email" below to confirm it reaches an inbox.');
            }
        }

        // Support address
        if (trim((string) app_config('support_email', '')) === '') {
            $add(self::WARN, 'Support address', 'support_email is empty in env.php. The contact page and reports then email every administrator, and emails to providers say "get in touch with the Kopii team" without an address.');
        } else {
            $add(self::OK, 'Support address', 'Contact messages and reports are emailed to ' . trim((string) app_config('support_email', '')) . '.');
        }

        // Behind a proxy
        $proxied = !empty($_SERVER['HTTP_CF_CONNECTING_IP']) || !empty($_SERVER['HTTP_CF_RAY']);
        if ($proxied && trim((string) app_config('trusted_ip_header', '')) === '') {
            $add(self::WARN, 'Visitor IP addresses', 'This site is behind Cloudflare but trusted_ip_header is empty, so every visitor looks like the same address and rate limits are shared. Set it to HTTP_CF_CONNECTING_IP in env.php.');
        } else {
            $add(self::OK, 'Visitor IP addresses', $proxied ? 'The proxy header is configured.' : 'Visitors connect directly.');
        }

        // Browser protections
        $csp = app_config('csp', true);
        if ($csp === false && !$local) {
            $add(self::WARN, 'Browser protections', 'The Content-Security-Policy is switched off in env.php.');
        } elseif ($csp === 'report-only') {
            $add(self::WARN, 'Browser protections', 'The Content-Security-Policy is in report-only mode: it logs problems but blocks nothing. Set "csp" to true once the console is quiet.');
        } else {
            $add(self::OK, 'Browser protections', 'The Content-Security-Policy is enforced.');
        }

        // Folders the app writes to
        foreach (['uploads' => 'Photo uploads', 'storage' => 'Storage'] as $dir => $label) {
            $path = KOPII_ROOT . '/' . $dir;
            if (is_dir($path) && is_writable($path)) {
                $add(self::OK, $label . ' folder', '/' . $dir . ' is writable.');
            } else {
                $add(self::FAIL, $label . ' folder', '/' . $dir . ' is missing or not writable by PHP. Set its permissions to 755 or 775 in cPanel File Manager.');
            }
        }

        // PHP extensions
        $missing = [];
        foreach (['fileinfo' => 'photo type checks', 'pdo' => 'the database'] as $ext => $why) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext . ' (' . $why . ')';
            }
        }
        if (!empty($missing)) {
            $add(self::FAIL, 'PHP extensions', 'Missing: ' . implode(', ', $missing) . '. Enable them in cPanel → Select PHP Version.');
        } elseif (!extension_loaded('gd')) {
            $add(self::WARN, 'PHP extensions', 'The gd extension is off, so uploaded photos are stored at full size with no thumbnails. Enable gd in cPanel → Select PHP Version.');
        } elseif (!extension_loaded('mbstring')) {
            $add(self::WARN, 'PHP extensions', 'mbstring is off. Names with accents and emoji may be cut in the wrong place. Enable it in cPanel → Select PHP Version.');
        } else {
            $add(self::OK, 'PHP extensions', 'PDO, fileinfo, gd and mbstring are loaded (PHP ' . PHP_VERSION . ').');
        }

        return $checks;
    }

    /**
     * @param list<array{level: string, title: string, detail: string}> $checks
     * @return array{ok: int, warn: int, fail: int}
     */
    public static function summary(array $checks): array
    {
        $count = [self::OK => 0, self::WARN => 0, self::FAIL => 0];
        foreach ($checks as $c) {
            $count[$c['level']] = ($count[$c['level']] ?? 0) + 1;
        }
        return $count;
    }

    /** Does any active administrator still sign in with the README's demo password? */
    private static function demoAdminPasswordInUse(): bool
    {
        try {
            $rows = Database::connection()->query("SELECT password_hash FROM users WHERE role = 'admin'")->fetchAll();
            foreach ($rows as $row) {
                if (password_verify(self::DEMO_ADMIN_PASSWORD, (string) $row['password_hash'])) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            report_exception($e);
        }
        return false;
    }
}
