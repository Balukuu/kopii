<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * Brings an existing database up to the current schema without a manual migration.
 *
 * Fresh installs get everything from database/schema.*.sql; this exists for databases created
 * before a change (the local SQLite file and any already-installed MySQL database). Every step is
 * idempotent. The version number is remembered in storage/schema.version so the checks only run
 * once per upgrade, not on every request.
 *
 * Bump VERSION and add steps to apply() when the schema changes.
 */
final class Migrator
{
    private const VERSION = 2;

    public static function run(PDO $pdo): void
    {
        $file    = KOPII_ROOT . '/storage/schema.version';
        $current = is_file($file) ? (int) trim((string) @file_get_contents($file)) : 0;
        if ($current >= self::VERSION) {
            return;
        }

        self::apply($pdo);
        @file_put_contents($file, (string) self::VERSION, LOCK_EX);
    }

    private static function apply(PDO $pdo): void
    {
        $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        // v1: email verification, review moderation, rate limiting
        if (!self::hasColumn($pdo, 'users', 'email_verified_at')) {
            $pdo->exec($sqlite
                ? 'ALTER TABLE users ADD COLUMN email_verified_at TEXT DEFAULT NULL'
                : 'ALTER TABLE `users` ADD COLUMN `email_verified_at` DATETIME DEFAULT NULL');
            // Administrators were created by the installer / seeder, not by a self-service sign-up.
            $pdo->exec("UPDATE users SET email_verified_at = created_at WHERE role = 'admin' AND email_verified_at IS NULL");
        }

        if (!self::hasColumn($pdo, 'reviews', 'status')) {
            // Reviews that already exist stay published.
            $pdo->exec($sqlite
                ? "ALTER TABLE reviews ADD COLUMN status TEXT NOT NULL DEFAULT 'published'"
                : "ALTER TABLE `reviews` ADD COLUMN `status` VARCHAR(12) NOT NULL DEFAULT 'published'");
        }
        if (!self::hasColumn($pdo, 'reviews', 'verified_request')) {
            $pdo->exec($sqlite
                ? 'ALTER TABLE reviews ADD COLUMN verified_request INTEGER NOT NULL DEFAULT 0'
                : 'ALTER TABLE `reviews` ADD COLUMN `verified_request` TINYINT(1) NOT NULL DEFAULT 0');
        }

        if ($sqlite) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS rate_limits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    bucket TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    created_at TEXT NOT NULL
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_limits_lookup ON rate_limits (bucket, subject, created_at)');

            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS email_verifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    token_hash TEXT NOT NULL UNIQUE,
                    created_at TEXT NOT NULL,
                    expires_at TEXT NOT NULL,
                    used_at TEXT DEFAULT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_email_verifications_user ON email_verifications (user_id, created_at)');
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `rate_limits` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `bucket` VARCHAR(40) NOT NULL,
                    `subject` CHAR(64) NOT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_rate_limits_lookup` (`bucket`, `subject`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `email_verifications` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` INT UNSIGNED NOT NULL,
                    `token_hash` CHAR(64) NOT NULL,
                    `created_at` DATETIME NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `used_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_email_verifications_token` (`token_hash`),
                    KEY `idx_email_verifications_user` (`user_id`, `created_at`),
                    CONSTRAINT `fk_email_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        // v2: admin console. Account suspension, a record of who verified a listing and when, and
        // an audit log of every administrator action.
        if (!self::hasColumn($pdo, 'users', 'suspended_at')) {
            $pdo->exec($sqlite
                ? 'ALTER TABLE users ADD COLUMN suspended_at TEXT DEFAULT NULL'
                : 'ALTER TABLE `users` ADD COLUMN `suspended_at` DATETIME DEFAULT NULL');
        }
        if (!self::hasColumn($pdo, 'providers', 'verified_at')) {
            $pdo->exec($sqlite
                ? 'ALTER TABLE providers ADD COLUMN verified_at TEXT DEFAULT NULL'
                : 'ALTER TABLE `providers` ADD COLUMN `verified_at` DATETIME DEFAULT NULL');
        }
        if (!self::hasColumn($pdo, 'providers', 'verified_by')) {
            $pdo->exec($sqlite
                ? 'ALTER TABLE providers ADD COLUMN verified_by INTEGER DEFAULT NULL'
                : 'ALTER TABLE `providers` ADD COLUMN `verified_by` INT UNSIGNED DEFAULT NULL');
        }

        if ($sqlite) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS admin_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    admin_user_id INTEGER DEFAULT NULL,
                    action TEXT NOT NULL,
                    target_type TEXT NOT NULL,
                    target_id INTEGER DEFAULT NULL,
                    target_label TEXT NOT NULL DEFAULT \'\',
                    note TEXT NOT NULL DEFAULT \'\',
                    created_at TEXT NOT NULL,
                    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_admin_log_created ON admin_log (created_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_admin_log_target ON admin_log (target_type, target_id)');
        } else {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `admin_log` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `admin_user_id` INT UNSIGNED DEFAULT NULL,
                    `action` VARCHAR(40) NOT NULL,
                    `target_type` VARCHAR(20) NOT NULL,
                    `target_id` INT UNSIGNED DEFAULT NULL,
                    `target_label` VARCHAR(190) NOT NULL DEFAULT \'\',
                    `note` VARCHAR(500) NOT NULL DEFAULT \'\',
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_admin_log_created` (`created_at`),
                    KEY `idx_admin_log_target` (`target_type`, `target_id`),
                    CONSTRAINT `fk_admin_log_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }

    private static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info(' . $table . ')') as $row) {
                if (strcasecmp((string) $row['name'], $column) === 0) {
                    return true;
                }
            }
            return false;
        }

        // A plain SELECT works with real prepared statements on every MySQL / MariaDB version.
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
