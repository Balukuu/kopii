<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Single-use password reset tokens.
 *
 * Only a SHA-256 hash of each token is stored, so a database leak cannot be used to reset
 * accounts. The emailed token is 256 bits of randomness; it expires after TTL_MINUTES and
 * stops working the moment it is used (or another reset is completed for the account).
 */
final class PasswordResetModel
{
    public const TTL_MINUTES = 60;

    /** Reset emails allowed per account / per IP address within one hour (stops inbox flooding). */
    private const MAX_PER_USER_PER_HOUR = 3;
    private const MAX_PER_IP_PER_HOUR   = 10;

    private static bool $tableReady = false;

    /**
     * Create the table on first use so existing databases (the local SQLite file and any
     * already-installed MySQL database) pick it up without a manual migration.
     * Fresh installs get the same table from database/schema.*.sql.
     */
    public static function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }

        $pdo = Database::connection();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS password_resets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    token_hash TEXT NOT NULL UNIQUE,
                    ip TEXT DEFAULT NULL,
                    created_at TEXT NOT NULL,
                    expires_at TEXT NOT NULL,
                    used_at TEXT DEFAULT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets (user_id, created_at)');
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `password_resets` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT UNSIGNED NOT NULL,
                    `token_hash` CHAR(64) NOT NULL,
                    `ip` VARCHAR(45) DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `used_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_password_resets_token` (`token_hash`),
                    KEY `idx_password_resets_user` (`user_id`, `created_at`),
                    CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        self::$tableReady = true;
    }

    /**
     * Whether another reset email may be sent for this account / from this IP right now.
     */
    public static function canIssue(int $userId, string $ip): bool
    {
        self::ensureTable();
        $pdo   = Database::connection();
        $since = date('Y-m-d H:i:s', time() - 3600);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = :uid AND created_at > :since');
        $stmt->execute([':uid' => $userId, ':since' => $since]);
        if ((int) $stmt->fetchColumn() >= self::MAX_PER_USER_PER_HOUR) {
            return false;
        }

        if ($ip !== '') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE ip = :ip AND created_at > :since');
            $stmt->execute([':ip' => $ip, ':since' => $since]);
            if ((int) $stmt->fetchColumn() >= self::MAX_PER_IP_PER_HOUR) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a token for the account and return the plain value to put in the email link.
     */
    public static function issue(int $userId, string $ip): string
    {
        self::ensureTable();
        $pdo = Database::connection();

        // Housekeeping: drop rows that expired more than a day ago.
        $pdo->prepare('DELETE FROM password_resets WHERE expires_at < :cutoff')
            ->execute([':cutoff' => date('Y-m-d H:i:s', time() - 86400)]);

        $token = bin2hex(random_bytes(32));
        $now   = time();

        $pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, ip, created_at, expires_at)
             VALUES (:uid, :hash, :ip, :created, :expires)'
        )->execute([
            ':uid'     => $userId,
            ':hash'    => hash('sha256', $token),
            ':ip'      => $ip !== '' ? substr($ip, 0, 45) : null,
            ':created' => date('Y-m-d H:i:s', $now),
            ':expires' => date('Y-m-d H:i:s', $now + self::TTL_MINUTES * 60),
        ]);

        return $token;
    }

    /**
     * The unused, unexpired reset row for a token from an email link, or null.
     */
    public static function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'SELECT * FROM password_resets WHERE token_hash = :hash AND used_at IS NULL AND expires_at > :now LIMIT 1'
        );
        $stmt->execute([':hash' => hash('sha256', $token), ':now' => date('Y-m-d H:i:s')]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Atomically mark a token as used. False means someone else used it first.
     */
    public static function claim(int $resetId): bool
    {
        self::ensureTable();
        $stmt = Database::connection()->prepare(
            'UPDATE password_resets SET used_at = :now WHERE id = :id AND used_at IS NULL'
        );
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $resetId]);
        return $stmt->rowCount() === 1;
    }

    /**
     * Retire every outstanding token for the account (called after a successful reset).
     */
    public static function invalidateForUser(int $userId): void
    {
        self::ensureTable();
        Database::connection()
            ->prepare('UPDATE password_resets SET used_at = :now WHERE user_id = :uid AND used_at IS NULL')
            ->execute([':now' => date('Y-m-d H:i:s'), ':uid' => $userId]);
    }
}
