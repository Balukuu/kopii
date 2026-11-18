<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Minimal outbound email.
 *
 * Production (cPanel / LiteSpeed) uses PHP's mail(), which hands the message to the server's
 * mail system. For reliable inbox delivery, set up SPF and DKIM for your domain in cPanel →
 * "Email Deliverability" and use a From address on that same domain.
 *
 * Configuration (optional) in app/config/env.php:
 *   'mail' => [
 *       'driver'            => 'mail',               // 'mail' (send) or 'log' (write to storage/mail.log)
 *       'from_email'        => 'no-reply@kopii.online',
 *       'from_name'         => 'Kopii',
 *       'envelope_sender'   => true,                 // pass -f<from_email> to the mail system (helps SPF)
 *   ]
 *
 * When `driver` is not set it defaults to 'log' for app_env=local and 'mail' otherwise, so
 * local development never sends real email and password-reset links are readable in the log.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $text, ?string $replyTo = null): bool
    {
        $to = self::singleLine($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = self::singleLine($subject);
        $text    = str_replace(["\r\n", "\r"], "\n", $text);

        if (self::driver() === 'log') {
            return self::writeLog($to, $subject, $text);
        }

        $fromEmail = self::fromEmail();
        $fromName  = self::singleLine((string) app_config('mail.from_name', app_config('app_name', 'Kopii')));

        $headers = [
            'From: ' . self::address($fromName, $fromEmail),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            'X-Mailer: Kopii',
        ];
        if ($replyTo !== null) {
            $replyTo = self::singleLine($replyTo);
            if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $headers[] = 'Reply-To: ' . $replyTo;
            }
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $envelope       = app_config('mail.envelope_sender', true) ? '-f' . $fromEmail : '';

        $sent = @mail($to, $encodedSubject, quoted_printable_encode($text), implode("\r\n", $headers), $envelope);
        if (!$sent) {
            // Never log the body: reset emails contain a secret link.
            error_log('[Kopii] mail() could not queue a message to ' . $to . ' (subject: ' . $subject . ')');
        }
        return $sent;
    }

    private static function driver(): string
    {
        $configured = app_config('mail.driver');
        if (is_string($configured) && $configured !== '') {
            return strtolower($configured);
        }
        return app_config('app_env', 'production') === 'local' ? 'log' : 'mail';
    }

    private static function fromEmail(): string
    {
        $configured = trim((string) app_config('mail.from_email', ''));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $host = (string) (parse_url((string) app_config('app_url', ''), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? ''));
        $host = (string) preg_replace(['/:\d+$/', '/^www\./i'], '', strtolower($host));

        $candidate = 'no-reply@' . $host;
        return ($host !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) ? $candidate : 'no-reply@kopii.online';
    }

    /** "Name" <email>, MIME-encoding the name when it contains non-ASCII characters. */
    private static function address(string $name, string $email): string
    {
        if ($name === '') {
            return $email;
        }
        if (preg_match('/[^\x20-\x7E]/', $name)) {
            $display = '=?UTF-8?B?' . base64_encode($name) . '?=';
        } else {
            $display = '"' . str_replace(['"', '\\'], '', $name) . '"';
        }
        return $display . ' <' . $email . '>';
    }

    /** Strip anything that could start a new header line (header-injection defence). */
    private static function singleLine(string $value): string
    {
        return trim((string) preg_replace('/[\r\n\x00]+/', ' ', $value));
    }

    private static function writeLog(string $to, string $subject, string $text): bool
    {
        $dir = KOPII_ROOT . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = '[' . date('Y-m-d H:i:s') . "] To: {$to}\nSubject: {$subject}\n\n{$text}\n" . str_repeat('-', 60) . "\n";
        return @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}
