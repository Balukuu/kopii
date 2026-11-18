<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Small database-backed rate limiter for form endpoints (sign-in, registration, quote requests,
 * reviews). Each attempt is one row; a request is refused when the subject already has `$max`
 * attempts inside the window.
 *
 *   if (RateLimiter::tooMany('login', $ip . '|' . $email, 8, 900)) { ...refuse... }
 *   ...on failure:  RateLimiter::hit('login', $ip . '|' . $email);
 *
 * It fails open: if the table is unavailable the site keeps working (and the error is logged)
 * rather than locking everybody out.
 */
final class RateLimiter
{
    public static function tooMany(string $bucket, string $subject, int $max, int $windowSeconds): bool
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) FROM rate_limits WHERE bucket = :bucket AND subject = :subject AND created_at > :since'
            );
            $stmt->execute([
                ':bucket'  => $bucket,
                ':subject' => self::key($subject),
                ':since'   => date('Y-m-d H:i:s', time() - $windowSeconds),
            ]);
            return (int) $stmt->fetchColumn() >= $max;
        } catch (Throwable $e) {
            error_log('[Kopii] rate limiter unavailable: ' . $e->getMessage());
            return false;
        }
    }

    public static function hit(string $bucket, string $subject): void
    {
        try {
            $pdo = Database::connection();
            $pdo->prepare('INSERT INTO rate_limits (bucket, subject, created_at) VALUES (:bucket, :subject, :created)')
                ->execute([
                    ':bucket'  => $bucket,
                    ':subject' => self::key($subject),
                    ':created' => date('Y-m-d H:i:s'),
                ]);

            // Housekeeping: now and then drop attempts older than a day.
            if (random_int(1, 50) === 1) {
                $pdo->prepare('DELETE FROM rate_limits WHERE created_at < :cutoff')
                    ->execute([':cutoff' => date('Y-m-d H:i:s', time() - 86400)]);
            }
        } catch (Throwable $e) {
            error_log('[Kopii] rate limiter unavailable: ' . $e->getMessage());
        }
    }

    /** Forget a subject's attempts (e.g. after a successful sign-in). */
    public static function clear(string $bucket, string $subject): void
    {
        try {
            Database::connection()
                ->prepare('DELETE FROM rate_limits WHERE bucket = :bucket AND subject = :subject')
                ->execute([':bucket' => $bucket, ':subject' => self::key($subject)]);
        } catch (Throwable $e) {
            error_log('[Kopii] rate limiter unavailable: ' . $e->getMessage());
        }
    }

    /** Subjects contain emails and IP addresses, so only a hash is stored. */
    private static function key(string $subject): string
    {
        return hash('sha256', strtolower($subject));
    }
}
