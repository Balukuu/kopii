<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * The signed-in person's own account: name and phone, sign-in email, password, and deleting the account.
 *
 * Changing the email or the password, and deleting the account, all ask for the CURRENT password, so a
 * stolen or borrowed session cannot lock the real owner out or wipe their data.
 */
class AccountController
{
    public function index(): void
    {
        $user    = require_auth();
        $account = UserModel::findById((int) $user['id']);
        if ($account === null) {
            redirect_to('/login');
        }

        $provider = ProviderModel::findByUserId((int) $user['id']);

        View::render('account/index', [
            'pageTitle'     => 'Account settings — Kopii',
            'currentRoute'  => '/account',
            'account'       => $account,
            'provider'      => $provider,
            'emailVerified' => !empty($account['email_verified_at']),
            'isAdmin'       => (string) $account['role'] === 'admin',
        ]);
    }

    public function updateDetails(): void
    {
        $user = require_auth();
        verify_csrf();

        $name  = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        $problems = [];
        if (text_length($name) < 2 || text_length($name) > 120) {
            $problems[] = 'Enter your full name (2–120 characters).';
        }
        if ($phone !== '' && (phone_digit_count($phone) < 9 || phone_digit_count($phone) > 15)) {
            $problems[] = 'Enter a valid phone number, or leave it blank.';
        }
        if (!empty($problems)) {
            flash('error', 'Nothing was saved. ' . implode(' ', $problems));
            redirect_to('/account');
        }

        try {
            UserModel::updateDetails((int) $user['id'], $name, $phone);
            $_SESSION['user']['full_name'] = $name;
            flash('success', 'Your details were saved.');
        } catch (Throwable $e) {
            flash('error', report_exception($e, 'We could not save your details. Please try again.'));
        }
        redirect_to('/account');
    }

    public function updateEmail(): void
    {
        $user = require_auth();
        verify_csrf();

        $account = $this->confirmedPassword($user);
        $current = strtolower((string) $account['email']);
        $new     = strtolower(trim((string) ($_POST['new_email'] ?? '')));

        if (!filter_var($new, FILTER_VALIDATE_EMAIL) || strlen($new) > 190) {
            flash('error', 'Enter a valid email address.');
            redirect_to('/account');
        }
        if ($new === $current) {
            flash('error', 'That is already your sign-in email.');
            redirect_to('/account');
        }
        $taken = UserModel::findByEmail($new);
        if ($taken !== null && (int) $taken['id'] !== (int) $account['id']) {
            flash('error', 'Another account already uses that email address.');
            redirect_to('/account');
        }

        try {
            UserModel::updateEmail((int) $account['id'], $new);
        } catch (Throwable $e) {
            // A second account took the address between the check above and the save.
            $duplicate = $e instanceof PDOException && (string) $e->getCode() === '23000';
            flash('error', $duplicate ? 'Another account already uses that email address.' : report_exception($e, 'We could not change your email. Please try again.'));
            redirect_to('/account');
        }
        $_SESSION['user']['email'] = $new;

        // Prove the new address works, and tell the old one that it happened.
        try {
            $token = EmailVerificationModel::issue((int) $account['id']);
            Notifier::verifyEmail(['full_name' => $account['full_name'], 'email' => $new], absolute_url('/verify-email', ['token' => $token]));
        } catch (Throwable $e) {
            report_exception($e);
        }
        Notifier::emailChanged($current, $account, $new);

        $message = 'Your sign-in email is now ' . $new . '. We sent a confirmation link to it: until you click it, quote requests sent before you signed up will not show in your account.';
        if (app_is_local()) {
            $message .= ' (Local mode: the email was written to storage/mail.log instead of being sent.)';
        }
        flash('success', $message);
        redirect_to('/account');
    }

    public function updatePassword(): void
    {
        $user = require_auth();
        verify_csrf();

        $account = $this->confirmedPassword($user);
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($new) < 8 || strlen($new) > 200) {
            flash('error', 'The new password must be between 8 and 200 characters long.');
            redirect_to('/account');
        }
        if (!hash_equals($new, $confirm)) {
            flash('error', 'The two new passwords do not match. Please type them again.');
            redirect_to('/account');
        }

        try {
            UserModel::updatePassword((int) $account['id'], $new);
            PasswordResetModel::invalidateForUser((int) $account['id']);
        } catch (Throwable $e) {
            flash('error', report_exception($e, 'We could not change your password. Please try again.'));
            redirect_to('/account');
        }

        // Keep THIS browser signed in under the new password. Every other session still carries the old
        // password's fingerprint, so refresh_session_user() signs those out on their next request.
        $fresh = UserModel::findById((int) $account['id']);
        session_regenerate_id(true);
        if ($fresh !== null) {
            $_SESSION['user']['pwf'] = password_fingerprint((string) $fresh['password_hash']);
            Notifier::passwordChanged($fresh);
        }

        flash('success', 'Your password was changed. Any other device that was signed in has been signed out.');
        redirect_to('/account');
    }

    public function delete(): void
    {
        $user = require_auth();
        verify_csrf();

        $account = $this->confirmedPassword($user);

        if ((string) $account['role'] === 'admin') {
            flash('error', 'Administrator accounts cannot be deleted here. Ask another administrator to change your role first.');
            redirect_to('/account');
        }
        if (trim((string) ($_POST['confirm_text'] ?? '')) !== 'DELETE') {
            flash('error', 'Type DELETE in capital letters to confirm. Nothing was deleted.');
            redirect_to('/account');
        }

        $email = (string) $account['email'];
        $name  = (string) $account['full_name'];

        try {
            $files = UserModel::deleteAccount((int) $account['id']);
        } catch (Throwable $e) {
            flash('error', report_exception($e, 'We could not delete your account. Nothing was changed. Please try again, or contact us.'));
            redirect_to('/account');
        }

        // The rows are gone; now remove their photos from the disk.
        foreach ($files as $path) {
            delete_upload($path);
        }
        Notifier::accountDeleted($email, $name);

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
        session_start();
        flash('success', 'Your account and everything on it has been deleted.');
        redirect_to('/');
    }

    /**
     * Stop here unless the submitted current password is right. Wrong guesses are rate limited per
     * account and connection, like signing in. Returns the account row.
     */
    private function confirmedPassword(array $sessionUser): array
    {
        $account = UserModel::findById((int) $sessionUser['id']);
        if ($account === null) {
            redirect_to('/login');
        }

        $subject = client_ip() . '|' . (int) $account['id'];
        if (RateLimiter::tooMany('account_pw', $subject, 8, 900)) {
            flash('error', 'Too many wrong passwords. Please wait about 15 minutes and try again.');
            redirect_to('/account');
        }

        $given = (string) ($_POST['current_password'] ?? '');
        if ($given === '' || !password_verify($given, (string) $account['password_hash'])) {
            RateLimiter::hit('account_pw', $subject);
            flash('error', 'That is not your current password. Nothing was changed.');
            redirect_to('/account');
        }

        RateLimiter::clear('account_pw', $subject);
        return $account;
    }
}
