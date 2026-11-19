<?php
declare(strict_types=1);

/**
 * Kopii 1-Click LiteSpeed / cPanel MySQL Installer
 *
 * Upload Kopii to your cPanel document root (e.g., public_html on kopii.online),
 * create a MySQL database + user in cPanel -> MySQL Databases, then open:
 * https://kopii.online/install.php
 *
 * Automatically locks itself with `storage/installed.lock` once completed.
 *
 * Run it as soon as you upload the site, and delete install.php from the server afterwards:
 * until the installer has run, whoever opens this page first controls the installation.
 */

define('KOPII_APP', true);
define('KOPII_ROOT', __DIR__);

$envPath  = KOPII_ROOT . '/app/config/env.php';
$lockFile = KOPII_ROOT . '/storage/installed.lock';
if (is_file($lockFile) || is_file($envPath)) {
    http_response_code(403);
    exit('Kopii is already installed. To re-run setup, remove app/config/env.php and storage/installed.lock in cPanel File Manager first.');
}

// The installer must be able to save its settings. Check that up front instead of discovering it
// after the database has been set up (and then reporting success for a site that cannot start).
$storageDir = KOPII_ROOT . '/storage';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0775, true);
}
$setupProblems = [];
if (!is_writable(dirname($envPath))) {
    $setupProblems[] = 'The folder app/config is not writable. In cPanel File Manager, set its permissions to 755 (or 775), then reload this page.';
}
if (!is_dir($storageDir) || !is_writable($storageDir)) {
    $setupProblems[] = 'The folder storage is missing or not writable. In cPanel File Manager, set its permissions to 755 (or 775), then reload this page.';
}

session_start();
if (empty($_SESSION['_install_csrf'])) {
    $_SESSION['_install_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['_install_csrf'], $token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $dbHost    = trim((string) ($_POST['db_host'] ?? 'localhost'));
        $dbPort    = (int) ($_POST['db_port'] ?? 3306);
        $dbName    = trim((string) ($_POST['db_name'] ?? ''));
        $dbUser    = trim((string) ($_POST['db_user'] ?? ''));
        $dbPass    = (string) ($_POST['db_pass'] ?? '');
        $appUrl    = rtrim(trim((string) ($_POST['app_url'] ?? 'https://kopii.online')), '/');
        $adminName = trim((string) ($_POST['admin_name'] ?? 'Kopii Admin'));
        $adminMail = strtolower(trim((string) ($_POST['admin_email'] ?? 'admin@kopii.online')));
        $adminPass = (string) ($_POST['admin_pass'] ?? '');
        $seedDemo  = !empty($_POST['seed_demo']);

        $appUrlParts = parse_url($appUrl);

        if (!empty($setupProblems)) {
            $error = implode(' ', $setupProblems);
        } elseif ($dbName === '' || $dbUser === '' || strlen($adminPass) < 8 || !filter_var($adminMail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please fill in your MySQL database name, username, valid admin email, and an admin password (8+ characters).';
        } elseif (!is_array($appUrlParts) || !in_array(strtolower((string) ($appUrlParts['scheme'] ?? '')), ['http', 'https'], true) || empty($appUrlParts['host'])) {
            $error = 'The site URL must be a full address such as https://kopii.online.';
        } else {
            try {
                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                $schemaSql = file_get_contents(KOPII_ROOT . '/database/schema.mysql.sql');
                if ($schemaSql === false) {
                    throw new RuntimeException('Could not read database/schema.mysql.sql.');
                }
                // One statement per call: PDO's MySQL driver only reports a failure in the first statement of a
                // multi-statement string, so a table that failed to create would otherwise be reported as success.
                require_once KOPII_ROOT . '/app/core/Migrator.php';
                foreach (Migrator::splitStatements($schemaSql) as $statement) {
                    $pdo->exec($statement);
                }

                // Sample providers get a random password nobody knows, so a live site never has
                // demo accounts with a published password.
                require_once KOPII_ROOT . '/database/Seeder.php';
                Seeder::run($pdo, $seedDemo, [
                    'full_name' => $adminName,
                    'email'     => $adminMail,
                    'password'  => $adminPass,
                ], bin2hex(random_bytes(12)));

                $mailHost = preg_replace('/^www\./i', '', strtolower((string) (parse_url($appUrl, PHP_URL_HOST) ?: 'kopii.online')));

                $envConfig = [
                    'app_name' => 'Kopii',
                    'app_url'  => $appUrl,
                    'app_env'  => 'production',
                    'timezone' => 'Africa/Kampala',
                    'trusted_ip_header' => '',
                    'mail'     => [
                        'driver'          => 'mail',
                        'from_email'      => 'no-reply@' . $mailHost,
                        'from_name'       => 'Kopii',
                        'envelope_sender' => true,
                    ],
                    'db'       => [
                        'driver'   => 'mysql',
                        'host'     => $dbHost,
                        'port'     => $dbPort,
                        'database' => $dbName,
                        'username' => $dbUser,
                        'password' => $dbPass,
                        'charset'  => 'utf8mb4',
                    ],
                ];

                $envPhp = "<?php\ndefined('KOPII_APP') or exit('Direct access denied.');\n\nreturn " . var_export($envConfig, true) . ";\n";

                // Never report success unless the settings file really exists: without it the site
                // cannot connect to its database.
                if (@file_put_contents($envPath, $envPhp, LOCK_EX) === false || !is_file($envPath)) {
                    throw new RuntimeException('The database was set up, but app/config/env.php could not be written. Make the app/config folder writable and run the installer again (your administrator account was already created).');
                }
                @chmod($envPath, 0640);

                if (@file_put_contents($lockFile, 'Installed on ' . date('c') . "\n", LOCK_EX) === false) {
                    throw new RuntimeException('Settings were saved, but storage/installed.lock could not be written. Make the storage folder writable and reload this page.');
                }
                $success = true;
            } catch (Throwable $e) {
                $error = 'Setup could not be completed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Kopii Marketplace — cPanel / LiteSpeed Setup</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
    <main class="container section-pad">
        <div class="installer-card">
            <img src="assets/images/logo.png" alt="Kopii" width="160" height="107" class="installer-logo">
            <h1>Kopii Marketplace Setup</h1>
            <p class="text-muted">Connect your cPanel / LiteSpeed MySQL database to launch Kopii in seconds.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif (!empty($setupProblems)): ?>
                <?php foreach ($setupProblems as $problem): ?>
                    <div class="alert alert-warning" role="alert"><?= htmlspecialchars($problem, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="status">
                    <strong>Kopii is live!</strong> Your MySQL tables, Ugandan categories, and admin account are ready, and this installer is now locked. For safety, delete <code>install.php</code> from your server now.
                </div>
                <div class="form-actions">
                    <a href="./" class="btn btn-primary">Launch Kopii Marketplace</a>
                    <a href="./login" class="btn btn-outline">Sign In as Admin</a>
                </div>
            <?php else: ?>
                <form method="POST" action="install.php" class="stack-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['_install_csrf'], ENT_QUOTES, 'UTF-8') ?>">

                    <fieldset class="form-fieldset">
                        <legend>1. cPanel MySQL Database</legend>
                        <div class="form-row-2">
                            <div class="field">
                                <label for="db_host">Database Host *</label>
                                <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="field">
                                <label for="db_port">Port *</label>
                                <input type="text" inputmode="numeric" id="db_port" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>
                        <div class="field">
                            <label for="db_name">Database Name (e.g. kopii_marketplace) *</label>
                            <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" spellcheck="false" autocorrect="off" autocapitalize="none" required>
                        </div>
                        <div class="form-row-2">
                            <div class="field">
                                <label for="db_user">Database Username *</label>
                                <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES, 'UTF-8') ?>" spellcheck="false" autocorrect="off" autocapitalize="none" required>
                            </div>
                            <div class="field">
                                <label for="db_pass">Database Password</label>
                                <input type="password" id="db_pass" name="db_pass" autocomplete="off">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="form-fieldset">
                        <legend>2. Site &amp; Administrator Account</legend>
                        <div class="field">
                            <label for="app_url">Site URL *</label>
                            <input type="url" id="app_url" name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? 'https://kopii.online', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="form-row-2">
                            <div class="field">
                                <label for="admin_name">Admin Full Name *</label>
                                <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Kopii Admin', ENT_QUOTES, 'UTF-8') ?>" autocomplete="name" required>
                            </div>
                            <div class="field">
                                <label for="admin_email">Admin Email *</label>
                                <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? 'admin@kopii.online', ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" spellcheck="false" required>
                            </div>
                        </div>
                        <div class="field">
                            <label for="admin_pass">Admin Password (8+ characters) *</label>
                            <input type="password" id="admin_pass" name="admin_pass" minlength="8" autocomplete="new-password" required>
                        </div>
                        <div class="checkbox-row">
                            <input type="checkbox" id="seed_demo" name="seed_demo" value="1">
                            <label for="seed_demo">Add sample providers for testing. They are made up and will show on your public site until you pause them in Admin.</label>
                        </div>
                    </fieldset>

                    <button type="submit" class="btn btn-primary btn-lg btn-block">Install &amp; Launch Kopii</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
