<?php
declare(strict_types=1);

// Allow PHP built-in server (`php -S`) to serve static assets/uploads directly during local dev
if (PHP_SAPI === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $fullFile = __DIR__ . $reqPath;
    if ($reqPath !== '/' && is_file($fullFile) && !preg_match('#^/(app|database|storage)(/|$)#', $reqPath)) {
        return false;
    }
}

define('KOPII_APP', true);
define('KOPII_ROOT', __DIR__);

// Load configuration: app/config/env.php (written by install.php) on a live site.
$envFile = KOPII_ROOT . '/app/config/env.php';
if (is_file($envFile)) {
    $config = require $envFile;
} else {
    // No configuration file. Only a developer's own machine may fall back to the throw-away SQLite
    // database, which is seeded with documented demo passwords. A live site must never do that
    // silently (it would run with a publicly known admin password), so anywhere else we stop here
    // and point at the installer instead.
    $isDevMachine = PHP_SAPI === 'cli-server'
        || in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);

    if (!$isDevMachine) {
        $installed  = is_file(KOPII_ROOT . '/storage/installed.lock');
        $scriptDir  = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        $installUrl = htmlspecialchars($scriptDir . '/install.php', ENT_QUOTES, 'UTF-8');
        http_response_code(503);
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Kopii is not set up yet</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:34rem;margin:4rem auto;padding:0 1.25rem;line-height:1.6;color:#262d3d">'
            . '<h1 style="font-size:1.5rem">Kopii is not set up yet</h1>';
        echo $installed
            ? '<p>The configuration file <code>app/config/env.php</code> is missing. Restore it from your backup, or ask your developer to recreate it.</p>'
            : '<p>If you own this site, <a href="' . $installUrl . '">run the installer</a> to finish setup.</p>';
        echo '</body></html>';
        exit;
    }

    $config = [
        'app_name' => 'Kopii',
        'app_url'  => '',
        'app_env'  => 'local',
        'timezone' => 'Africa/Kampala',
        'db'       => [
            'driver'   => 'sqlite',
            'database' => KOPII_ROOT . '/storage/kopii.sqlite',
        ],
    ];
}

date_default_timezone_set($config['timezone'] ?? 'Africa/Kampala');

// Errors are logged, never shown to visitors, on a live site: messages can contain file paths,
// SQL and table names. Developers (app_env = local) see them on screen.
ini_set('display_errors', ($config['app_env'] ?? 'production') === 'local' ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Helpers load before the session starts so the cookie's `secure` flag can use request_is_https(),
// which also recognises HTTPS terminated by a proxy / CDN (X-Forwarded-Proto, Cloudflare).
require_once KOPII_ROOT . '/app/core/Helpers.php';
app_config_store($config);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('kopii_session');
    session_start();
}

require_once KOPII_ROOT . '/app/core/UserFacingException.php';
require_once KOPII_ROOT . '/app/core/Database.php';
require_once KOPII_ROOT . '/app/core/Migrator.php';
require_once KOPII_ROOT . '/app/core/RateLimiter.php';
require_once KOPII_ROOT . '/app/core/Router.php';
require_once KOPII_ROOT . '/app/core/View.php';
require_once KOPII_ROOT . '/app/core/ImageProcessor.php';
require_once KOPII_ROOT . '/app/core/Mailer.php';
require_once KOPII_ROOT . '/app/core/Notifier.php';

require_once KOPII_ROOT . '/app/models/CategoryModel.php';
require_once KOPII_ROOT . '/app/models/UserModel.php';
require_once KOPII_ROOT . '/app/models/ReviewModel.php';
require_once KOPII_ROOT . '/app/models/ProviderModel.php';
require_once KOPII_ROOT . '/app/models/InquiryModel.php';
require_once KOPII_ROOT . '/app/models/ProductModel.php';
require_once KOPII_ROOT . '/app/models/OpportunityModel.php';
require_once KOPII_ROOT . '/app/models/PasswordResetModel.php';
require_once KOPII_ROOT . '/app/models/EmailVerificationModel.php';
require_once KOPII_ROOT . '/app/models/AdminLogModel.php';
require_once KOPII_ROOT . '/app/models/AdminModel.php';

require_once KOPII_ROOT . '/app/controllers/MarketplaceController.php';
require_once KOPII_ROOT . '/app/controllers/AuthController.php';
require_once KOPII_ROOT . '/app/controllers/DashboardController.php';
require_once KOPII_ROOT . '/app/controllers/ShopController.php';
require_once KOPII_ROOT . '/app/controllers/SellerController.php';
require_once KOPII_ROOT . '/app/controllers/OpportunityController.php';
require_once KOPII_ROOT . '/app/controllers/AdminController.php';

// Anything uncaught ends here: log the details, show visitors a plain apology. Only a developer's
// local copy shows what actually went wrong.
set_exception_handler(static function (Throwable $e): void {
    error_log('[Kopii] Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    try {
        View::render('pages/500', [
            'pageTitle' => 'Something went wrong — Kopii',
            'detail'    => app_is_local() ? $e : null,
        ]);
    } catch (Throwable) {
        echo 'Something went wrong on our side. Please try again in a moment.';
    }
});

Database::init($config);

// Signed-in visitors are checked against the database each request, so suspending an account
// takes effect at once instead of when its session happens to end.
refresh_session_user();

$router = new Router();

// Public Marketplace Routes
$router->get('/', [MarketplaceController::class, 'home']);
$router->get('/hire', [MarketplaceController::class, 'hireWizard']);
$router->post('/hire/brief', [MarketplaceController::class, 'submitHireBrief']);
$router->get('/explore', [MarketplaceController::class, 'explore']);
$router->get('/about', [MarketplaceController::class, 'about']);
$router->get('/provider/{slug}', [MarketplaceController::class, 'showProvider']);
$router->post('/provider/{slug}/inquire', [MarketplaceController::class, 'submitInquiry']);
$router->post('/provider/{slug}/review', [MarketplaceController::class, 'submitReview']);

// Shop
$router->get('/shop', [ShopController::class, 'index']);
$router->get('/product/{slug}', [ShopController::class, 'show']);
$router->post('/product/{slug}/inquire', [ShopController::class, 'inquire']);

// Opportunities board ("/opportunities/new" is listed before "/opportunities/{id}" so it is not read as an id)
$router->get('/opportunities', [OpportunityController::class, 'index']);
$router->get('/opportunities/new', [OpportunityController::class, 'newForm']);
$router->post('/opportunities', [OpportunityController::class, 'store']);
$router->get('/opportunities/{id}', [OpportunityController::class, 'show']);
$router->post('/opportunities/{id}/respond', [OpportunityController::class, 'respond']);

// Self-Registration & Authentication Routes
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/forgot-password', [AuthController::class, 'showForgot']);
$router->post('/forgot-password', [AuthController::class, 'sendReset']);
$router->get('/reset-password', [AuthController::class, 'showReset']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);
$router->get('/verify-email', [AuthController::class, 'verifyEmail']);
$router->post('/verify-email/resend', [AuthController::class, 'resendVerification']);

// Provider & Client Studio Dashboard Routes
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->post('/dashboard/become-provider', [DashboardController::class, 'becomeProvider']);
$router->post('/dashboard/profile', [DashboardController::class, 'updateProfile']);
$router->post('/dashboard/portfolio/add', [DashboardController::class, 'addPortfolio']);
$router->post('/dashboard/portfolio/delete', [DashboardController::class, 'deletePortfolio']);
$router->post('/dashboard/packages/add', [DashboardController::class, 'addPackage']);
$router->post('/dashboard/packages/delete', [DashboardController::class, 'deletePackage']);
$router->post('/dashboard/inquiries/status', [DashboardController::class, 'updateInquiryStatus']);
$router->post('/dashboard/products/save', [SellerController::class, 'save']);
$router->post('/dashboard/products/status', [SellerController::class, 'setStatus']);
$router->post('/dashboard/products/delete', [SellerController::class, 'delete']);
$router->post('/dashboard/products/image/delete', [SellerController::class, 'deleteImage']);
$router->get('/dashboard/opportunities', [OpportunityController::class, 'mine']);
$router->post('/dashboard/opportunities/response', [OpportunityController::class, 'responseStatus']);
$router->post('/dashboard/opportunities/withdraw', [OpportunityController::class, 'withdraw']);
$router->get('/dashboard/opportunities/{id}', [OpportunityController::class, 'manage']);
$router->post('/dashboard/opportunities/{id}/status', [OpportunityController::class, 'setStatus']);

// Platform Admin Routes
$router->get('/admin', [AdminController::class, 'index']);
$router->get('/admin/providers', [AdminController::class, 'providers']);
$router->get('/admin/providers/{id}', [AdminController::class, 'provider']);
$router->post('/admin/moderate', [AdminController::class, 'moderate']);
$router->get('/admin/products', [AdminController::class, 'products']);
$router->post('/admin/products/moderate', [AdminController::class, 'moderateProduct']);
$router->get('/admin/opportunities', [AdminController::class, 'opportunities']);
$router->post('/admin/opportunities/moderate', [AdminController::class, 'moderateOpportunity']);
$router->get('/admin/reviews', [AdminController::class, 'reviews']);
$router->post('/admin/reviews/moderate', [AdminController::class, 'moderateReview']);
$router->get('/admin/users', [AdminController::class, 'users']);
$router->post('/admin/users/action', [AdminController::class, 'userAction']);
$router->get('/admin/activity', [AdminController::class, 'activity']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
