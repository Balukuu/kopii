<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * HTML-escape output safely.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Runtime configuration (the array returned by app/config/env.php, set once from index.php).
 * Dot notation reads nested keys, e.g. app_config('mail.from_email', 'no-reply@example.com').
 */
function app_config_store(?array $set = null): array
{
    static $config = [];
    if ($set !== null) {
        $config = $set;
    }
    return $config;
}

function app_config(string $key, mixed $default = null): mixed
{
    $value = app_config_store();
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/**
 * True when running as a local development copy (app_env = local). Used to gate anything that
 * must never reach a live site: demo sign-in shortcuts, detailed error pages, mail written to a log.
 */
function app_is_local(): bool
{
    return app_config('app_env', 'production') === 'local';
}

/**
 * The visitor's IP address, for rate limiting.
 *
 * Only REMOTE_ADDR is trusted by default. Behind Cloudflare or another proxy every visitor
 * appears to come from the proxy, so name the header the proxy sets in app/config/env.php, e.g.
 * 'trusted_ip_header' => 'HTTP_CF_CONNECTING_IP'. Do not set it unless a proxy really overwrites
 * that header, because otherwise visitors could choose their own IP.
 */
function client_ip(): string
{
    $header = (string) app_config('trusted_ip_header', '');
    if ($header !== '' && preg_match('/^HTTP_[A-Z0-9_]+$/', $header) && !empty($_SERVER[$header])) {
        $candidate = trim(explode(',', (string) $_SERVER[$header])[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

/**
 * The message to show a visitor for a caught exception. Messages written for visitors
 * (UserFacingException) pass through; anything else (database errors, file paths, bugs) is
 * logged for the site owner and replaced by a generic sentence so internals are never exposed.
 */
function report_exception(Throwable $e, string $fallback = 'Something went wrong on our side. Please try again in a moment.'): string
{
    if ($e instanceof UserFacingException) {
        return $e->getMessage();
    }
    error_log('[Kopii] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    return $fallback;
}

/** Number of digits in a phone number as typed ("+256 772 123 456" => 12). */
function phone_digit_count(string $raw): int
{
    return strlen((string) preg_replace('/\D+/', '', $raw));
}

/** Character count that is correct for non-ASCII text. */
function text_length(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

/**
 * Cut text to at most $max characters (correct for non-ASCII text), adding "…" when it was cut.
 * Whitespace runs collapse to single spaces, so it is also fit for a one-line summary.
 */
function text_excerpt(string $text, int $max): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    if (text_length($text) <= $max) {
        return $text;
    }
    return function_exists('mb_substr')
        ? rtrim(mb_substr($text, 0, $max - 1, 'UTF-8')) . '…'
        : rtrim(substr($text, 0, $max - 1)) . '…';
}

/**
 * True when the visitor reached us over HTTPS, including behind a TLS-terminating
 * proxy or CDN (Cloudflare, cPanel proxy, load balancers) that forwards plain HTTP to PHP.
 */
function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    $forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    if ($forwarded === 'https') {
        return true;
    }
    return str_contains((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"scheme":"https"');
}

/**
 * Compute base path automatically (supports both domain root and subdirectory installs).
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = rtrim(dirname($scriptName), '/');
    $base = ($dir === '' || $dir === '.') ? '' : $dir;
    return $base;
}

/**
 * Build an application route URL.
 */
function route_url(string $path = '/', array $query = []): string
{
    $clean = '/' . ltrim($path, '/');
    if ($clean !== '/') {
        $clean = rtrim($clean, '/');
    }
    $url = base_path() . ($clean === '/' ? '/' : $clean);
    if (!empty($query)) {
        $filtered = array_filter($query, static fn($v) => $v !== null && $v !== '');
        if (!empty($filtered)) {
            $url .= '?' . http_build_query($filtered);
        }
    }
    return $url;
}

/**
 * Scheme + host (+ port) used when a link must work outside the browser, e.g. inside an email.
 *
 * Prefers the configured `app_url` so a forged Host header can never redirect a
 * password-reset link to another domain; only falls back to the request host when
 * no app_url is configured (local development).
 */
function site_origin(): string
{
    $configured = parse_url((string) app_config('app_url', ''));
    if (is_array($configured) && !empty($configured['scheme']) && !empty($configured['host'])) {
        return strtolower((string) $configured['scheme']) . '://' . $configured['host']
            . (isset($configured['port']) ? ':' . (int) $configured['port'] : '');
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }
    return (request_is_https() ? 'https' : 'http') . '://' . $host;
}

/**
 * Fully-qualified application URL (for emails and sharing).
 */
function absolute_url(string $path = '/', array $query = []): string
{
    return site_origin() . route_url($path, $query);
}

/**
 * Build a static asset or uploaded media URL.
 */
function asset_url(string $path): string
{
    if ( str_starts_with($path, 'http://') || str_starts_with($path, 'https://') ) {
        return $path;
    }
    return base_path() . '/' . ltrim($path, '/');
}

/**
 * Redirect and terminate.
 */
function redirect_to(string $path, array $query = []): never
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        $target = $path;
    } else {
        // "/admin/reviews#waiting" keeps the part after # as a page anchor.
        $fragment = '';
        if (str_contains($path, '#')) {
            [$path, $anchor] = explode('#', $path, 2);
            $anchor   = (string) preg_replace('/[^A-Za-z0-9_-]/', '', $anchor);
            $fragment = $anchor !== '' ? '#' . $anchor : '';
        }
        $target = route_url($path, $query) . $fragment;
    }
    header('Location: ' . $target, true, 302);
    exit;
}

/**
 * Escape a visitor's search text so % and _ match themselves in a `LIKE ... ESCAPE '!'` pattern.
 * `!` is the escape character because the syntax for escaping a backslash differs between MySQL and SQLite.
 */
function like_escape(string $text): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text);
}

/**
 * Format Ugandan Shillings (UGX).
 */
function format_ugx(int|float|string|null $amount): string
{
    $num = (int) ($amount ?? 0);
    if ($num <= 0) {
        return 'Custom Quote';
    }
    return 'UGX ' . number_format($num, 0, '.', ',');
}

/** A product's price. 0 means the seller left it open: "Ask for price" (format_ugx() says "Custom Quote", which suits services). */
function product_price(int|float|string|null $amount): string
{
    return (int) ($amount ?? 0) > 0 ? format_ugx($amount) : 'Ask for price';
}

/** An opportunity's budget as one line: "UGX 500,000 – 900,000", "Up to UGX 2,000,000", or "Budget not stated". */
function budget_range(int|string|null $min, int|string|null $max): string
{
    $min = (int) ($min ?? 0);
    $max = (int) ($max ?? 0);
    if ($min > 0 && $max > 0) {
        return $min === $max
            ? format_ugx($min)
            : 'UGX ' . number_format($min, 0, '.', ',') . ' – ' . number_format($max, 0, '.', ',');
    }
    if ($max > 0) {
        return 'Up to ' . format_ugx($max);
    }
    if ($min > 0) {
        return 'From ' . format_ugx($min);
    }
    return 'Budget not stated';
}

/**
 * Availability of a product as [label, badge class] for the shop cards and pages.
 *
 * @return array{0: string, 1: string}
 */
function stock_badge(string $status): array
{
    return match ($status) {
        'low_stock'     => ['Low stock', 'badge-warn'],
        'made_to_order' => ['Made to order', 'badge-type'],
        'out_of_stock'  => ['Out of stock', 'badge-out'],
        default         => ['In stock', 'badge-avail'],
    };
}

/**
 * Normalize Ugandan phone numbers (+256) for WhatsApp & tel links.
 */
function normalize_ug_phone(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '256' . substr($digits, 1);
    }
    if (strlen($digits) === 9 && (str_starts_with($digits, '7') || str_starts_with($digits, '3'))) {
        return '256' . $digits;
    }
    return $digits;
}

function display_ug_phone(string $raw): string
{
    $norm = normalize_ug_phone($raw);
    if (strlen($norm) === 12 && str_starts_with($norm, '256')) {
        return '+256 ' . substr($norm, 3, 3) . ' ' . substr($norm, 6, 3) . ' ' . substr($norm, 9, 3);
    }
    return $raw;
}

function whatsapp_url(string $phone, string $message = ''): string
{
    $norm = normalize_ug_phone($phone);
    if ($norm === '') {
        return '#';
    }
    $url = 'https://wa.me/' . $norm;
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}

function tel_url(string $phone): string
{
    $norm = normalize_ug_phone($phone);
    return $norm !== '' ? 'tel:+' . $norm : '#';
}

/**
 * Create URL-safe slug.
 */
function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'provider-' . bin2hex(random_bytes(3));
}

/**
 * Human-readable relative date.
 */
function time_ago(string $datetime): string
{
    $ts = strtotime($datetime);
    if (!$ts) {
        return $datetime;
    }
    $diff = max(1, time() - $ts);
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' min' . ($m === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' hr' . ($h === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 2592000) {
        $d = (int) floor($diff / 86400);
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }
    return date('M j, Y', $ts);
}

/**
 * Initials avatar fallback text.
 */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = isset($parts[0][0]) ? strtoupper($parts[0][0]) : 'K';
    $second = isset($parts[1][0]) ? strtoupper($parts[1][0]) : '';
    return $first . $second;
}

/**
 * CSRF Protection.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = (string) ($_POST['csrf_token'] ?? '');
    $expected  = (string) ($_SESSION['_csrf_token'] ?? '');
    if ($expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(419);
        exit('Session expired or invalid security token. Please go back, refresh the page, and try again.');
    }
}

/**
 * Flash Messages & Old Input Persistence.
 */
function flash(string $type, string $message): void
{
    $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['_flashes'] ?? [];
    unset($_SESSION['_flashes']);
    return $flashes;
}

function set_old_input(array $data): void
{
    unset($data['password'], $data['new_password'], $data['current_password'], $data['csrf_token']);
    $_SESSION['_old_input'] = $data;
}

function old(string $key, mixed $default = ''): string
{
    return (string) ($_SESSION['_old_input'][$key] ?? $default);
}

function clear_old_input(): void
{
    unset($_SESSION['_old_input']);
}

/**
 * Auth Session Helpers.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']['id']);
}

function require_auth(): array
{
    if (!is_logged_in()) {
        flash('warning', 'Please sign in to access your dashboard.');
        redirect_to('/login');
    }
    return $_SESSION['user'];
}

function require_admin(): array
{
    $user = require_auth();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Access restricted to Kopii administrators.');
    }
    return $user;
}

/**
 * Re-check the signed-in account against the database on every request. The session holds a copy of
 * the account (name, role), so without this a suspended user would stay signed in until their
 * session expired, and a role change would not apply until the next login.
 */
function refresh_session_user(): void
{
    if (!is_logged_in()) {
        return;
    }

    try {
        $row = UserModel::findById((int) $_SESSION['user']['id']);
    } catch (Throwable $e) {
        // Do not lock everyone out because of a database hiccup; the page itself will report it.
        report_exception($e);
        return;
    }

    if ($row === null || !empty($row['suspended_at'])) {
        $suspended = $row !== null;
        unset($_SESSION['user']);
        session_regenerate_id(true);
        if ($suspended) {
            flash('error', 'This account has been suspended. Please contact the Kopii team if you think that is a mistake.');
        }
        return;
    }

    $_SESSION['user']['role']      = (string) $row['role'];
    $_SESSION['user']['full_name'] = (string) $row['full_name'];
    $_SESSION['user']['email']     = (string) $row['email'];
}

/** The current admin page (path + query, without the site's base path), for "come back here" form fields. */
function admin_current_path(): string
{
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin'), PHP_URL_PATH) ?? '/admin');
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    if (!str_starts_with($path, '/admin')) {
        return '/admin';
    }
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    return $path . ($query !== '' ? '?' . $query : '');
}

function admin_return_field(): string
{
    return '<input type="hidden" name="return" value="' . e(admin_current_path()) . '">';
}

/**
 * Send the admin back to the page a form came from. Only pages inside /admin are accepted, so the
 * hidden field cannot be used to bounce someone to another site.
 */
function admin_redirect_back(string $fallback = '/admin'): never
{
    $raw = (string) ($_POST['return'] ?? '');
    if (!preg_match('#^/admin(/[A-Za-z0-9_-]+)*(\?[A-Za-z0-9_=&%.+-]*)?(\#[A-Za-z0-9_-]+)?$#', $raw)) {
        $raw = $fallback;
    }

    $fragment = '';
    if (str_contains($raw, '#')) {
        [$raw, $anchor] = explode('#', $raw, 2);
        $fragment = '#' . $anchor;
    }
    $query = [];
    if (str_contains($raw, '?')) {
        [$raw, $queryString] = explode('?', $raw, 2);
        parse_str($queryString, $query);
    }

    redirect_to($raw . $fragment, $query);
}

/** What is waiting for an administrator's decision. Looked up once per request, however many places show it. */
function admin_attention_counts(): array
{
    static $counts = null;
    if ($counts === null) {
        try {
            $counts = AdminModel::attentionCounts();
        } catch (Throwable $e) {
            report_exception($e);
            $counts = ['pending_reviews' => 0, 'unverified_providers' => 0, 'unconfirmed_emails' => 0];
        }
    }
    return $counts;
}

/**
 * The number on the nav badge: reviews waiting for approval. Listings without the Verified badge are
 * deliberately not counted: anyone can list for free, so most listings start unverified and the
 * badge would never clear. They are shown on the admin overview instead.
 */
function admin_attention_total(): int
{
    return (int) admin_attention_counts()['pending_reviews'];
}

/**
 * Secure Image Upload Handler (validates MIME & dimensions, prevents script uploads).
 */
function upload_image(array $file, string $subdir, int $maxBytes = 6291456): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new UserFacingException('Image upload failed. Please choose a JPG, PNG, or WebP file under 6 MB.');
    }
    if (($file['size'] ?? 0) > $maxBytes || ($file['size'] ?? 0) <= 0) {
        throw new UserFacingException('Image must be smaller than 6 MB.');
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_file($tmpPath)) {
        throw new UserFacingException('Invalid temporary upload file.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($tmpPath);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new UserFacingException('Unsupported image format. Please upload a JPG, PNG, or WebP photo.');
    }

    $imgInfo = @getimagesize($tmpPath);
    if ($imgInfo === false || $imgInfo[0] < 40 || $imgInfo[1] < 40) {
        throw new UserFacingException('Uploaded file is not a valid image.');
    }

    $subdir    = trim($subdir, '/');
    $targetDir = KOPII_ROOT . '/uploads/' . $subdir;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    // [largest edge of the stored photo, largest edge of the card thumbnail] in pixels
    $sizes = [
        'avatars'    => [640, 160],
        'covers'     => [1920, 800],
        'portfolios' => [1600, 640],
    ];
    [$maxEdge, $thumbEdge] = $sizes[$subdir] ?? [1600, 640];

    $filename  = date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
    $destPath  = $targetDir . '/' . $filename;
    $thumbPath = $targetDir . '/thumbs/' . $filename;

    // Re-encode with GD: shrinks 6 MB phone photos to web size, bakes in the EXIF rotation,
    // and drops all metadata (including GPS coordinates). Returns false only when GD cannot
    // handle this format, in which case the original file is stored unchanged.
    $processed = ImageProcessor::process($tmpPath, $mime, $destPath, $thumbPath, $maxEdge, $thumbEdge);

    if (!$processed) {
        if (!move_uploaded_file($tmpPath, $destPath)) {
            if (!copy($tmpPath, $destPath)) {
                throw new UserFacingException('Could not save uploaded image.');
            }
        }
    }

    return 'uploads/' . $subdir . '/' . $filename;
}

/**
 * Relative path of the small thumbnail that upload_image() writes next to each photo.
 * uploads/portfolios/abc.jpg  ->  uploads/portfolios/thumbs/abc.jpg
 */
function thumb_path(string $path): string
{
    $dir = dirname($path);
    return ($dir === '.' ? '' : $dir . '/') . 'thumbs/' . basename($path);
}

/**
 * URL of the thumbnail for grids and cards. Falls back to the full image when no thumbnail
 * exists (seed images, photos uploaded before thumbnails, or servers without GD).
 */
function thumb_url(?string $path): string
{
    $path = (string) $path;
    if ($path === '' || !str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
        return asset_url($path);
    }

    static $resolved = [];
    if (!isset($resolved[$path])) {
        $thumb = thumb_path($path);
        $resolved[$path] = is_file(KOPII_ROOT . '/' . $thumb) ? $thumb : $path;
    }
    return asset_url($resolved[$path]);
}

/**
 * Delete an uploaded photo and its thumbnail. Only ever touches files inside /uploads,
 * and never the bundled uploads/seed demo images.
 */
function delete_upload(?string $path): void
{
    $path = (string) $path;
    if ($path === '' || !str_starts_with($path, 'uploads/') || str_starts_with($path, 'uploads/seed/') || str_contains($path, '..')) {
        return;
    }

    $uploadsRoot = realpath(KOPII_ROOT . '/uploads');
    if ($uploadsRoot === false) {
        return;
    }

    foreach ([$path, thumb_path($path)] as $relative) {
        $real = realpath(KOPII_ROOT . '/' . $relative);
        if ($real !== false && str_starts_with($real, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($real)) {
            @unlink($real);
        }
    }
}

/**
 * Normalise a website address typed by a provider.
 * Returns '' for empty input, the cleaned https?:// URL when valid, or null when invalid.
 */
function normalize_website(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $raw)) {
        $raw = 'https://' . ltrim($raw, '/');
    }
    if (strlen($raw) > 255 || filter_var($raw, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    $parts = parse_url($raw);
    if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host   = (string) ($parts['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '' || !str_contains($host, '.')) {
        return null;
    }
    return $raw;
}

/**
 * Output-side guard for stored URLs: only plain http(s) links with a host are ever rendered
 * as clickable, so a bad value in the database can never become a javascript: link.
 */
function safe_url(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return '';
    }
    return in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) ? $url : '';
}

/**
 * Display text for a link: the host without "www.".
 */
function url_host(string $url): string
{
    $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
    return preg_replace('/^www\./i', '', $host) ?? $host;
}

/**
 * Inline Decorative SVG Icons (aria-hidden="true").
 */
function svg_icon(string $name, int $size = 20, string $class = ''): string
{
    // Icons come in three sizes only (16 inline, 20 default, 24 feature), whatever size is asked for,
    // and every size draws the same 1.5px line: the stroke-width is scaled against the 24px viewBox.
    $size   = $size <= 17 ? 16 : ($size <= 21 ? 20 : 24);
    $stroke = round(36 / $size, 2);

    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';
    $attrs = 'width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"' . $cls;

    $paths = match ($name) {
        'camera'      => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3.5"/>',
        'video'       => '<rect x="2" y="6" width="14" height="12" rx="2"/><path d="m22 8-6 4 6 4V8z"/>',
        'palette'     => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.65-.75 1.65-1.69 0-.43-.18-.83-.44-1.12-.29-.29-.44-.65-.44-1.12a1.64 1.64 0 0 1 1.67-1.67h2c3.05 0 5.56-2.5 5.56-5.55C22 6.01 17.5 2 12 2z"/>',
        'scissors'    => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/>',
        'utensils'    => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
        'code'        => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
        'sparkles'    => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/><path d="M5 3v4"/><path d="M3 5h4"/>',
        'home'        => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'music'       => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'truck'       => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'search'      => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'map-pin'     => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'verified'    => '<path d="M12 2l2.4 2.1 3.1-.4.7 3.1 2.8 1.5-1.3 2.9 1.3 2.9-2.8 1.5-.7 3.1-3.1-.4L12 22l-2.4-2.1-3.1.4-.7-3.1-2.8-1.5 1.3-2.9-1.3-2.9 2.8-1.5.7-3.1 3.1.4Z"/><path d="m9 12 2 2 4-4"/>',
        'star'        => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="currentColor"/>',
        'star-outline'=> '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'whatsapp'    => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
        'phone'       => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'mail'        => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'globe'       => '<circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'check'       => '<polyline points="20 6 9 17 4 12"/>',
        'plus'        => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'menu'        => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>',
        'close'       => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'clock'       => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'image'       => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'inbox'       => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'layers'      => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'user'        => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'eye'         => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'     => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/>',
        'trash'       => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
        'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'chevron-left'=> '<polyline points="15 18 9 12 15 6"/>',
        'chevron-right'=> '<polyline points="9 18 15 12 9 6"/>',
        default       => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
    };

    return '<svg ' . $attrs . '>' . $paths . '</svg>';
}
