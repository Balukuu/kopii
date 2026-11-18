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
