<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * The emails Kopii sends. Every method is safe to call from a request handler: a failure
 * to send is logged and swallowed so it can never break a form submission.
 */
final class Notifier
{
    /**
     * Tell a provider about a new quote request so they don't have to keep checking the dashboard.
     * The email's Reply-To is the client, so replying goes straight to them.
     *
     * @param array $lead client_name, client_email, client_phone, event_date, event_location, budget_ugx, project_details
     */
    public static function newLead(int $providerId, array $lead): void
    {
        try {
            $target = ProviderModel::notificationTarget($providerId);
            if (!$target) {
                return;
            }

            $clientName  = self::limit((string) ($lead['client_name'] ?? ''), 80);
            $clientPhone = trim((string) ($lead['client_phone'] ?? ''));

            $lines = [
                'Hi ' . self::firstName((string) $target['owner_name']) . ',',
                '',
                'You have a new quote request for ' . $target['business_name'] . ' on Kopii.',
                '',
                'From:   ' . $clientName,
                'Phone:  ' . $clientPhone,
                'Email:  ' . (string) ($lead['client_email'] ?? ''),
            ];
            if (!empty($lead['event_date'])) {
                $lines[] = 'Date:   ' . self::limit((string) $lead['event_date'], 60);
            }
            if (!empty($lead['event_location'])) {
                $lines[] = 'Where:  ' . self::limit((string) $lead['event_location'], 120);
            }
            if (!empty($lead['budget_ugx'])) {
                $lines[] = 'Budget: ' . format_ugx((int) $lead['budget_ugx']);
            }

            $lines[] = '';
            $lines[] = 'Their message:';
            $lines[] = self::limit((string) ($lead['project_details'] ?? ''), 1500);
            $lines[] = '';

            $whatsapp = whatsapp_url($clientPhone, 'Hi ' . $clientName . ', thanks for your request on Kopii.');
            if ($whatsapp !== '#') {
                $lines[] = 'Reply on WhatsApp: ' . $whatsapp;
            }
            $lines[] = 'Open your Kopii inbox: ' . absolute_url('/dashboard', ['tab' => 'inquiries']);
            $lines[] = '';
            $lines[] = 'You can also just reply to this email to reach the client directly.';

            Mailer::send(
                (string) $target['email'],
                'New quote request from ' . $clientName . ' — Kopii',
                implode("\n", $lines),
                (string) ($lead['client_email'] ?? '')
            );
        } catch (Throwable $e) {
            error_log('[Kopii] lead notification failed: ' . $e->getMessage());
        }
    }

    public static function verifyEmail(array $user, string $verifyUrl): void
    {
        try {
            $body = implode("\n", [
                'Hi ' . self::firstName((string) ($user['full_name'] ?? '')) . ',',
                '',
                'Welcome to Kopii. Please confirm that this is your email address:',
                $verifyUrl,
                '',
                'The link works for ' . EmailVerificationModel::TTL_HOURS . ' hours. Confirming lets you see the quote requests you sent before you created your account.',
                '',
                "If you didn't create a Kopii account, you can ignore this email.",
            ]);
            Mailer::send((string) $user['email'], 'Confirm your Kopii email address', $body);
        } catch (Throwable $e) {
            error_log('[Kopii] verification email failed: ' . $e->getMessage());
        }
    }

    public static function passwordReset(array $user, string $resetUrl): void
    {
        try {
            $body = implode("\n", [
                'Hi ' . self::firstName((string) ($user['full_name'] ?? '')) . ',',
                '',
                'We received a request to reset the password for your Kopii account.',
                '',
                'Choose a new password here (the link works for ' . PasswordResetModel::TTL_MINUTES . ' minutes and can be used once):',
                $resetUrl,
                '',
                "If you didn't ask for this, you can safely ignore this email. Your password will not change.",
            ]);
            Mailer::send((string) $user['email'], 'Reset your Kopii password', $body);
        } catch (Throwable $e) {
            error_log('[Kopii] password reset email failed: ' . $e->getMessage());
        }
    }

    public static function passwordChanged(array $user): void
    {
        try {
            $body = implode("\n", [
                'Hi ' . self::firstName((string) ($user['full_name'] ?? '')) . ',',
                '',
                'The password for your Kopii account was just changed.',
                '',
                "If that was you, there's nothing more to do. If it wasn't, reset your password right away:",
                absolute_url('/forgot-password'),
            ]);
            Mailer::send((string) $user['email'], 'Your Kopii password was changed', $body);
        } catch (Throwable $e) {
            error_log('[Kopii] password-changed email failed: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------------------------
    // Admin-initiated emails. $provider is a row from AdminModel::providerDetail().
    // ---------------------------------------------------------------------------------------

    public static function listingVerified(array $provider): void
    {
        try {
            $body = implode("\n", [
                'Hi ' . self::firstName((string) ($provider['owner_name'] ?? '')) . ',',
                '',
                'Good news: the Kopii team has checked ' . $provider['business_name'] . ' and added the Verified badge to your listing.',
                '',
                'It now shows on your profile and next to your name in search results.',
                absolute_url('/provider/' . $provider['slug']),
            ]);
            Mailer::send((string) $provider['owner_email'], 'You are now Verified on Kopii', $body);
        } catch (Throwable $e) {
            error_log('[Kopii] verified email failed: ' . $e->getMessage());
        }
    }

    public static function listingPaused(array $provider, string $reason): void
    {
        try {
            $lines = [
                'Hi ' . self::firstName((string) ($provider['owner_name'] ?? '')) . ',',
                '',
                'The Kopii team has paused ' . $provider['business_name'] . '. It is hidden from search and your public profile is not visible until it is reactivated.',
            ];
            if (trim($reason) !== '') {
                $lines[] = '';
                $lines[] = 'Reason: ' . trim($reason);
            }
            $lines[] = '';
            $lines[] = self::contactLine();
            Mailer::send((string) $provider['owner_email'], 'Your Kopii listing has been paused', implode("\n", $lines));
        } catch (Throwable $e) {
            error_log('[Kopii] paused email failed: ' . $e->getMessage());
        }
    }

    public static function listingReactivated(array $provider): void
    {
        try {
            $body = implode("\n", [
                'Hi ' . self::firstName((string) ($provider['owner_name'] ?? '')) . ',',
                '',
                $provider['business_name'] . ' is live on Kopii again. Clients can find it in search and send you quote requests.',
                absolute_url('/provider/' . $provider['slug']),
            ]);
            Mailer::send((string) $provider['owner_email'], 'Your Kopii listing is live again', $body);
        } catch (Throwable $e) {
            error_log('[Kopii] reactivated email failed: ' . $e->getMessage());
        }
    }

    public static function accountSuspended(array $user, string $reason): void
    {
        try {
            $lines = [
                'Hi ' . self::firstName((string) ($user['full_name'] ?? '')) . ',',
                '',
                'Your Kopii account has been suspended. You cannot sign in, and any listing on your account is hidden.',
            ];
            if (trim($reason) !== '') {
                $lines[] = '';
                $lines[] = 'Reason: ' . trim($reason);
            }
            $lines[] = '';
            $lines[] = self::contactLine();
            Mailer::send((string) $user['email'], 'Your Kopii account has been suspended', implode("\n", $lines));
        } catch (Throwable $e) {
            error_log('[Kopii] suspension email failed: ' . $e->getMessage());
        }
    }

    public static function accountReinstated(array $user): void
    {
        try {
            $body = implode("\n", [
                'Hi ' . self::firstName((string) ($user['full_name'] ?? '')) . ',',
                '',
                'Your Kopii account is active again and you can sign in:',
                absolute_url('/login'),
                '',
                'If you have a listing, it stays hidden until the Kopii team makes it public again.',
            ]);
            Mailer::send((string) $user['email'], 'Your Kopii account is active again', $body);
        } catch (Throwable $e) {
            error_log('[Kopii] reinstated email failed: ' . $e->getMessage());
        }
    }

    /** Tell every administrator that a review is waiting, so it does not sit unseen. */
    public static function reviewWaiting(string $businessName, array $review): void
    {
        try {
            $recipients = AdminModel::adminEmails();
            if (empty($recipients)) {
                return;
            }

            $body = implode("\n", [
                'A review is waiting for approval on Kopii.',
                '',
                'Provider: ' . $businessName,
                'From:     ' . self::limit((string) ($review['reviewer_name'] ?? ''), 80),
                'Rating:   ' . (int) ($review['rating'] ?? 0) . '/5',
                '',
                self::limit((string) ($review['comment'] ?? ''), 600),
                '',
                'It is not public and does not count toward the rating until you approve it:',
                absolute_url('/admin/reviews', ['status' => 'pending']),
            ]);
            foreach ($recipients as $to) {
                Mailer::send($to, 'A review is waiting for approval — Kopii', $body);
            }
        } catch (Throwable $e) {
            error_log('[Kopii] pending review alert failed: ' . $e->getMessage());
        }
    }

    /** How to reach the team. Set 'support_email' in app/config/env.php to give a real address. */
    private static function contactLine(): string
    {
        $support = trim((string) app_config('support_email', ''));
        return $support !== ''
            ? 'If you think this is a mistake, write to us at ' . $support . '.'
            : 'If you think this is a mistake, please get in touch with the Kopii team.';
    }

    private static function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        return ($parts[0] ?? '') !== '' ? $parts[0] : 'there';
    }

    private static function limit(string $text, int $max): string
    {
        $text = trim($text);
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $max, '…', 'UTF-8');
        }
        return strlen($text) > $max ? substr($text, 0, $max - 1) . '…' : $text;
    }
}
