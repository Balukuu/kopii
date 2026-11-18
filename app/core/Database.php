<?php
defined('KOPII_APP') or exit('Direct access denied.');

class Database
{
    private static ?PDO $pdo = null;
    private static array $config = [];

    public static function init(array $config): void
    {
        self::$config = $config;
    }

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $db = self::$config['db'] ?? ['driver' => 'sqlite'];
        $driver = strtolower((string) ($db['driver'] ?? 'sqlite'));

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if ($driver === 'mysql') {
            $host    = $db['host'] ?? 'localhost';
            $port    = (int) ($db['port'] ?? 3306);
            $name    = $db['database'] ?? '';
            $charset = $db['charset'] ?? 'utf8mb4';
            $dsn     = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            self::$pdo = new PDO($dsn, (string) ($db['username'] ?? ''), (string) ($db['password'] ?? ''), $options);
        } else {
            $sqlitePath = $db['database'] ?? (KOPII_ROOT . '/storage/kopii.sqlite');
            $dir = dirname($sqlitePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $isNew = !file_exists($sqlitePath) || filesize($sqlitePath) === 0;
            self::$pdo = new PDO('sqlite:' . $sqlitePath, null, null, $options);
            self::$pdo->exec('PRAGMA foreign_keys = ON;');
            self::$pdo->exec('PRAGMA busy_timeout = 5000;');

            if ($isNew || !self::hasTable(self::$pdo, 'categories', 'sqlite')) {
                $schema = file_get_contents(KOPII_ROOT . '/database/schema.sqlite.sql');
                if ($schema !== false) {
                    self::$pdo->exec($schema);
                }
                require_once KOPII_ROOT . '/database/Seeder.php';
                Seeder::run(self::$pdo, true);
            }
        }

        // Bring older databases up to the current schema (adds new columns / tables once).
        try {
            Migrator::run(self::$pdo);
        } catch (Throwable $e) {
            error_log('[Kopii] schema upgrade failed: ' . $e->getMessage());
        }

        return self::$pdo;
    }

    public static function hasTable(PDO $pdo, string $table, string $driver = 'mysql'): bool
    {
        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :t LIMIT 1");
                $stmt->execute([':t' => $table]);
                return (bool) $stmt->fetchColumn();
            }
            $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
            $stmt->execute([':t' => $table]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
