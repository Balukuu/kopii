<?php
defined('KOPII_APP') or exit('Direct access denied.');

class AuthController
{
    /**
     * A real bcrypt hash that no account uses. Signing in with an unknown email address still runs
     * password_verify against it, so the response takes as long as for a real account and the
     * form cannot be used to discover which addresses are registered.
     */
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function showRegister(): void
    {
        if (is_logged_in()) {
            redirect_to('/dashboard');
        }
        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        $categories = CategoryModel::allWithCounts();

        View::render('auth/register', [
            'pageTitle'    => 'Join Kopii — List Your Business or Freelance Showcase Free',
            'currentRoute' => '/register',
            'cities'       => $uganda['cities'],
            'categories'   => $categories,
        ]);
    }

    public function register(): void
    {
        verify_csrf();
        if (!empty($_POST['company_fax_hp'])) {
            redirect_to('/');
        }

        // Generous on purpose: many mobile customers share one carrier IP address.
        $ip = client_ip();
        if (RateLimiter::tooMany('register', $ip, 20, 3600)) {
            flash('error', 'Too many sign-ups from this connection. Please try again in an hour.');
            redirect_to('/register');
        }
        RateLimiter::hit('register', $ip);

        $uganda = require KOPII_ROOT . '/app/config/uganda.php';

        $accountRole = ($_POST['account_role'] ?? 'provider') === 'client' ? 'client' : 'provider';
        $fullName    = trim((string) ($_POST['full_name'] ?? ''));
        $email       = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone       = trim((string) ($_POST['phone'] ?? ''));
        $password    = (string) ($_POST['new_password'] ?? '');

        $errors = [];
        if (text_length($fullName) < 2 || text_length($fullName) > 120) {
            $errors[] = 'Please enter your full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (UserModel::findByEmail($email)) {
            $errors[] = 'An account with this email already exists. Sign in, or use "Forgot your password?" on the sign-in page to get back in.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif (strlen($password) > 200) {
            $errors[] = 'Password is too long (200 characters at most).';
        }
        if ($phone !== '' && (phone_digit_count($phone) < 9 || phone_digit_count($phone) > 15)) {
            $errors[] = 'Please enter a valid phone number.';
        }

        $provider = [];
        if ($accountRole === 'provider') {
            $bizName      = trim((string) ($_POST['business_name'] ?? ''));
            $catId        = (int) ($_POST['category_id'] ?? 0);
            $headline     = trim((string) ($_POST['headline'] ?? ''));
            $bio          = trim((string) ($_POST['bio'] ?? ''));
            $city         = trim((string) ($_POST['city'] ?? '')) ?: 'Kampala';
            $neighborhood = trim((string) ($_POST['neighborhood'] ?? ''));
            $serviceAreas = normalize_area_list((string) ($_POST['service_areas'] ?? ''));
            $specialties  = trim((string) ($_POST['specialties'] ?? ''));
            $whatsapp     = trim((string) ($_POST['whatsapp'] ?? ''));
            $price        = (int) preg_replace('/\D+/', '', (string) ($_POST['starting_price_ugx'] ?? '0'));

            if (text_length($bizName) < 2 || text_length($bizName) > 120) {
                $errors[] = 'Please enter your business, studio, or freelance brand name (2–120 characters).';
            }
            if ($catId <= 0 || !CategoryModel::findById($catId)) {
                $errors[] = 'Please select your primary service category.';
            }
            if ($phone === '' || phone_digit_count($phone) < 9) {
                $errors[] = 'Please enter a Ugandan phone / WhatsApp number so clients can reach you.';
            }
            if ($whatsapp !== '' && (phone_digit_count($whatsapp) < 9 || phone_digit_count($whatsapp) > 15)) {
                $errors[] = 'Please enter a valid WhatsApp number, or leave it blank to use your phone number.';
            }
            if (!in_array($city, $uganda['cities'], true)) {
                $errors[] = 'Please choose your city from the list.';
            }
            if (text_length($headline) > 160) {
                $errors[] = 'Your headline can be at most 160 characters.';
            }
            if (text_length($bio) > 2000) {
                $errors[] = 'Your bio can be at most 2,000 characters.';
            }
            if (text_length($neighborhood) > 80 || text_length($specialties) > 300) {
                $errors[] = 'Neighborhood and specialties are too long.';
            }
            if ($serviceAreas === null) {
                $errors[] = 'List up to 12 other places you serve, each up to 40 characters, separated by commas.';
            }
            if ($headline === '') {
                $headline = $bizName . ' — Trusted Service Provider on Kopii';
            }
            if ($bio === '') {
                $bio = 'Welcome to ' . $bizName . '. Browse our portfolio work and packages or send a direct quote inquiry anytime.';
            }

            $provider = [
                'category_id'        => $catId,
                'business_name'      => $bizName,
                'provider_type'      => ($_POST['provider_type'] ?? 'freelancer') === 'business' ? 'business' : 'freelancer',
                'headline'           => $headline,
                'bio'                => $bio,
                'city'               => $city,
                'neighborhood'       => $neighborhood,
                'service_areas'      => (string) $serviceAreas,
                'phone'              => $phone,
                'whatsapp'           => $whatsapp !== '' ? $whatsapp : $phone,
                'public_email'       => $email,
                'starting_price_ugx' => min($price, 1000000000),
                'specialties'        => $specialties,
            ];
        }

        if (!empty($errors)) {
            set_old_input($_POST);
            flash('error', implode(' ', $errors));
            redirect_to('/register');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $userId = UserModel::create([
                'full_name' => $fullName,
                'email'     => $email,
                'phone'     => $phone,
                'password'  => $password,
                'role'      => $accountRole,
            ]);

            if ($accountRole === 'provider') {
                ProviderModel::create($provider + ['user_id' => $userId]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_old_input($_POST);

            // A second sign-up with the same address that slipped past the check above.
            $duplicate = $e instanceof PDOException && (string) $e->getCode() === '23000';
            flash('error', $duplicate
                ? 'An account with this email already exists. Sign in, or use "Forgot your password?" on the sign-in page to get back in.'
                : report_exception($e, 'We could not create your account right now. Please try again in a moment.'));
            redirect_to('/register');
        }

        // Ask the new user to confirm their address. Registration must succeed even if this fails.
        try {
            $token = EmailVerificationModel::issue($userId);
            Notifier::verifyEmail(['full_name' => $fullName, 'email' => $email], absolute_url('/verify-email', ['token' => $token]));
        } catch (Throwable $e) {
            report_exception($e);
        }

        $newUser = UserModel::findById($userId);
        if ($newUser === null) {
            flash('success', 'Your account is ready. Please sign in.');
            redirect_to('/login');
        }
        start_user_session($newUser);

        if ($accountRole === 'provider') {
            flash('success', 'Welcome to Kopii! Your showcase profile is live — upload your best portfolio photos and service packages below.');
            redirect_to('/dashboard', ['tab' => 'portfolio']);
        }

        flash('success', 'Welcome to Kopii! Explore creatives and businesses across Uganda.');
        redirect_to('/explore');
    }

    public function showLogin(): void
    {
        if (is_logged_in()) {
            redirect_to('/dashboard');
        }
        View::render('auth/login', [
            'pageTitle'    => 'Sign In — Kopii Marketplace',
            'currentRoute' => '/login',
        ]);
    }

    public function login(): void
    {
        verify_csrf();
        $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['current_password'] ?? '');

        // Throttle guessing: a few misses per account from one connection, and a larger cap per
        // connection overall. Only failed attempts count, and a successful sign-in clears them.
        $ip      = client_ip();
        $subject = $ip . '|' . $email;
        if (RateLimiter::tooMany('login', $subject, 8, 900) || RateLimiter::tooMany('login_ip', $ip, 60, 900)) {
            set_old_input(['email' => $email]);
            flash('error', 'Too many sign-in attempts. Please wait about 15 minutes and try again, or reset your password.');
            redirect_to('/login');
        }

        $user = UserModel::findByEmail($email);
        $hash = $user !== null ? (string) $user['password_hash'] : self::DUMMY_HASH;
        $ok   = password_verify($password, $hash) && $user !== null;

        if (!$ok) {
            RateLimiter::hit('login', $subject);
            RateLimiter::hit('login_ip', $ip);
            set_old_input(['email' => $email]);
            flash('error', 'Invalid email address or password. Please check your credentials and try again.');
            redirect_to('/login');
        }

        RateLimiter::clear('login', $subject);

        // Said only after the password was right, so it tells a stranger nothing about the account.
        if (!empty($user['suspended_at'])) {
            flash('error', 'This account has been suspended. Please contact the Kopii team if you think that is a mistake.');
            redirect_to('/login');
        }

        start_user_session($user);

        flash('success', 'Welcome back, ' . $user['full_name'] . '!');
        if ($user['role'] === 'admin') {
            redirect_to('/admin');
        }
        redirect_to('/dashboard');
    }

    public function logout(): void
    {
        verify_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
        session_start();
        flash('success', 'You have been signed out.');
        redirect_to('/');
    }

    // ---------------------------------------------------------------------------------------
    // Email confirmation
    // ---------------------------------------------------------------------------------------

    public function verifyEmail(): void
    {
        // The token is a secret: keep it out of Referer headers and browser caches.
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');

        $token = trim((string) ($_GET['token'] ?? ''));
        $row   = EmailVerificationModel::findValid($token);

        if ($row === null || !EmailVerificationModel::claim((int) $row['id'])) {
            flash('error', 'That confirmation link is invalid or has expired. Sign in and request a new one from your dashboard.');
            redirect_to(is_logged_in() ? '/dashboard' : '/login');
        }

        UserModel::markEmailVerified((int) $row['user_id']);
        flash('success', 'Thank you. Your email address is confirmed.');
        redirect_to(is_logged_in() ? '/dashboard' : '/login');
    }

    public function resendVerification(): void
    {
        $user = require_auth();
        verify_csrf();

        $row = UserModel::findById((int) $user['id']);
        if (!$row || !empty($row['email_verified_at'])) {
            flash('success', 'Your email address is already confirmed.');
            redirect_to('/dashboard');
        }

        try {
            if (!EmailVerificationModel::canIssue((int) $row['id'])) {
                flash('error', 'We have already sent several links. Please check your inbox and spam folder, then try again in an hour.');
                redirect_to('/dashboard');
            }

            $token = EmailVerificationModel::issue((int) $row['id']);
            Notifier::verifyEmail($row, absolute_url('/verify-email', ['token' => $token]));

            $message = 'We sent a confirmation link to ' . $row['email'] . '. Check your spam folder if it does not arrive.';
            if (app_is_local()) {
                $message .= ' (Local mode: the email was written to storage/mail.log instead of being sent.)';
            }
            flash('success', $message);
        } catch (Throwable $e) {
            flash('error', report_exception($e));
        }

        redirect_to('/dashboard');
    }

    // ---------------------------------------------------------------------------------------
    // Password reset
    // ---------------------------------------------------------------------------------------

    public function showForgot(): void
    {
        if (is_logged_in()) {
            redirect_to('/dashboard');
        }
        View::render('auth/forgot', [
            'pageTitle'    => 'Forgot Your Password? — Kopii',
            'currentRoute' => '/forgot-password',
        ]);
    }

    public function sendReset(): void
    {
        verify_csrf();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $user = UserModel::findByEmail($email);
                $ip   = client_ip();
                if ($user && PasswordResetModel::canIssue((int) $user['id'], $ip)) {
                    $token = PasswordResetModel::issue((int) $user['id'], $ip);
                    Notifier::passwordReset($user, absolute_url('/reset-password', ['token' => $token]));
                }
            } catch (Throwable $e) {
                report_exception($e);
            }
        }

        // Same answer whether or not the address has an account, so this form cannot be used
        // to find out who is registered.
        $message = 'If an account exists for that email, a reset link is on its way. It stays valid for '
            . PasswordResetModel::TTL_MINUTES . ' minutes. Check your spam folder if it does not arrive.';
        if (app_is_local()) {
            $message .= ' (Local mode: the email was written to storage/mail.log instead of being sent.)';
        }
        flash('success', $message);
        redirect_to('/login');
    }

    public function showReset(): void
    {
        // The token is a secret: keep it out of Referer headers and browser caches.
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');

        $token = trim((string) ($_GET['token'] ?? ''));
        if (PasswordResetModel::findValid($token) === null) {
            flash('error', 'That reset link is invalid or has expired. Please request a new one.');
            redirect_to('/forgot-password');
        }

        View::render('auth/reset', [
            'pageTitle'    => 'Choose a New Password — Kopii',
            'currentRoute' => '/reset-password',
            'token'        => $token,
        ]);
    }

    public function resetPassword(): void
    {
        verify_csrf();

        $token    = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['new_password'] ?? '');
        $confirm  = (string) ($_POST['confirm_password'] ?? '');

        $reset = PasswordResetModel::findValid($token);
        if ($reset === null) {
            flash('error', 'That reset link is invalid or has expired. Please request a new one.');
            redirect_to('/forgot-password');
        }

        if (strlen($password) < 8 || strlen($password) > 200) {
            flash('error', 'Password must be between 8 and 200 characters long.');
            redirect_to('/reset-password', ['token' => $token]);
        }
        if (!hash_equals($password, $confirm)) {
            flash('error', 'The two passwords do not match. Please type them again.');
            redirect_to('/reset-password', ['token' => $token]);
        }

        $userId = (int) $reset['user_id'];
        $pdo    = Database::connection();
        $pdo->beginTransaction();
        try {
            // claim() is atomic, so a link that was just used elsewhere cannot be used twice.
            if (!PasswordResetModel::claim((int) $reset['id'])) {
                throw new UserFacingException('Reset link already used.');
            }
            UserModel::updatePassword($userId, $password);
            // Opening the emailed link proves control of this mailbox.
            UserModel::markEmailVerified($userId);
            PasswordResetModel::invalidateForUser($userId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            report_exception($e);
            flash('error', 'That reset link has already been used or has expired. Please request a new one.');
            redirect_to('/forgot-password');
        }

        $user = UserModel::findById($userId);
        if ($user) {
            Notifier::passwordChanged($user);
        }

        // Anyone signed in on this browser must sign in again with the new password.
        session_regenerate_id(true);
        unset($_SESSION['user']);

        flash('success', 'Your password has been updated. Please sign in with your new password.');
        redirect_to('/login');
    }
}
