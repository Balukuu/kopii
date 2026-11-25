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
    private const VERSION = 6;

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

        // v3: the shop and the opportunities board. The new tables are all `CREATE TABLE IF NOT EXISTS`
        // in the schema file, so that file is the one place they are defined; the columns added to
        // tables that already existed are the only steps spelled out here.
        if (!self::hasColumn($pdo, 'inquiries', 'product_id')) {
            $pdo->exec($sqlite
                ? 'ALTER TABLE inquiries ADD COLUMN product_id INTEGER DEFAULT NULL'
                : 'ALTER TABLE `inquiries` ADD COLUMN `product_id` INT UNSIGNED DEFAULT NULL');
        }
        if (!self::hasColumn($pdo, 'inquiries', 'product_name')) {
            $pdo->exec($sqlite
                ? 'ALTER TABLE inquiries ADD COLUMN product_name TEXT DEFAULT NULL'
                : 'ALTER TABLE `inquiries` ADD COLUMN `product_name` VARCHAR(160) DEFAULT NULL');
        }

        // v5: the areas a provider serves (beyond their base city and neighbourhood), and everyday
        // service categories (home repairs, solar, cleaning, agriculture). Both are no-ops the second time.
        if (!self::hasColumn($pdo, 'providers', 'service_areas')) {
            $pdo->exec($sqlite
                ? 'ALTER TABLE providers ADD COLUMN service_areas TEXT DEFAULT NULL'
                : 'ALTER TABLE `providers` ADD COLUMN `service_areas` VARCHAR(255) DEFAULT NULL');
        }

        // v6: plans and fees. A listing can be on a plan (with an end date) and can be Featured until a
        // date. `plans` and `platform_settings` are `CREATE TABLE IF NOT EXISTS` in the schema file
        // (executed below); only the columns added to the existing providers table are spelled out here.
        foreach (['featured_until' => ['TEXT', 'DATE'], 'plan_id' => ['INTEGER', 'INT UNSIGNED'], 'plan_expires_on' => ['TEXT', 'DATE']] as $column => [$sqliteType, $mysqlType]) {
            if (!self::hasColumn($pdo, 'providers', $column)) {
                $pdo->exec($sqlite
                    ? 'ALTER TABLE providers ADD COLUMN ' . $column . ' ' . $sqliteType . ' DEFAULT NULL'
                    : 'ALTER TABLE `providers` ADD COLUMN `' . $column . '` ' . $mysqlType . ' DEFAULT NULL');
            }
        }

        // v4: support_messages (the contact form and "report this listing"). Like v3's tables it is
        // `CREATE TABLE IF NOT EXISTS` in the schema file, which is executed below, so there is no
        // separate step for it here.

        $schemaSql = (string) file_get_contents(
            KOPII_ROOT . '/database/' . ($sqlite ? 'schema.sqlite.sql' : 'schema.mysql.sql')
        );
        if ($sqlite) {
            $pdo->exec($schemaSql);
        } else {
            // PDO's MySQL driver runs a multi-statement string but only reports a failure in the FIRST
            // statement: a table that failed to create would pass unnoticed and the version number
            // would still move on. One statement per call makes every failure throw, so the upgrade
            // is retried on the next request instead of being recorded as done.
            foreach (self::splitStatements($schemaSql) as $statement) {
                $pdo->exec($statement);
            }
        }

        require_once KOPII_ROOT . '/database/Seeder.php';
        Seeder::seedProductCategories($pdo);
        Seeder::seedExtraServiceCategories($pdo);
        Seeder::seedPlansAndFees($pdo);

        // A developer's own demo database gets sample products and opportunities so the new pages are
        // not empty. Never on a live site: it needs app_env = local, SQLite, and the seeded demo providers.
        if ($sqlite && app_is_local()) {
            Seeder::seedCommerceDemo($pdo);
        }
    }

    /**
     * Split a schema file into single statements: on `;` outside quotes and outside `-- ` comments
     * (the schema's comments contain semicolons of their own).
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $quote      = '';
        $length     = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $ch = $sql[$i];

            if ($quote !== '') {
                $current .= $ch;
                if ($ch === '\\' && $quote !== '`' && $i + 1 < $length) {
                    $current .= $sql[++$i];
                } elseif ($ch === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($ch === '-' && ($sql[$i + 1] ?? '') === '-' && ctype_space($sql[$i + 2] ?? ' ')) {
                $end = strpos($sql, "\n", $i);
                if ($end === false) {
                    break;
                }
                $i = $end - 1; // the newline itself is kept by the next pass
                continue;
            }

            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote    = $ch;
                $current .= $ch;
                continue;
            }

            if ($ch === ';') {
                $statement = trim($current);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $current = '';
                continue;
            }

            $current .= $ch;
        }

        $statement = trim($current);
        if ($statement !== '') {
            $statements[] = $statement;
        }
        return $statements;
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
