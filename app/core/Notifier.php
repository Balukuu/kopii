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

            $productName = trim((string) ($lead['product_name'] ?? ''));
            $lines = [
                'Hi ' . self::firstName((string) $target['owner_name']) . ',',
                '',
                $productName !== ''
                    ? 'Someone is interested in "' . self::limit($productName, 120) . '" from the ' . $target['business_name'] . ' shop on Kopii.'
                    : 'You have a new quote request for ' . $target['business_name'] . ' on Kopii.',
                '',
                'From:   ' . $clientName,
                'Phone:  ' . $clientPhone,
                'Email:  ' . (trim((string) ($lead['client_email'] ?? '')) !== '' ? (string) $lead['client_email'] : 'not given: reply by phone or WhatsApp'),
            ];
            if (!empty($lead['event_date'])) {
                $lines[] = 'Date:   ' . self::limit((string) $lead['event_date'], 60);
            }
            if (!empty($lead['event_location'])) {
                $lines[] = ($productName !== '' ? 'Deliver to: ' : 'Where:  ') . self::limit((string) $lead['event_location'], 120);
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
                ($productName !== '' ? 'Enquiry about ' . self::limit($productName, 60) : 'New quote request from ' . $clientName) . ' — Kopii',
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

    // ---------------------------------------------------------------------------------------
    // Opportunities
    // ---------------------------------------------------------------------------------------

    /**
     * Tell the person who posted an opportunity that a provider has sent a proposal. Reply-To is the
     * provider's account email, so replying goes straight to them.
     *
     * @param array $opportunity row from OpportunityModel::find(): id, title, poster_name, poster_email
     * @param array $provider    business_name, slug, phone, public_email
     * @param array $response    message, quote_ugx, timeline
     */
    public static function newProposal(array $opportunity, array $provider, array $response, string $replyTo = ''): void
    {
        try {
            $lines = [
                'Hi ' . self::firstName((string) ($opportunity['poster_name'] ?? '')) . ',',
                '',
                $provider['business_name'] . ' sent a proposal for your opportunity "' . self::limit((string) $opportunity['title'], 120) . '" on Kopii.',
                '',
            ];
            if (!empty($response['quote_ugx'])) {
                $lines[] = 'Their price: ' . format_ugx((int) $response['quote_ugx']);
            }
            if (!empty($response['timeline'])) {
                $lines[] = 'Timeline:    ' . self::limit((string) $response['timeline'], 80);
            }
            $lines[] = '';
            $lines[] = 'Their message:';
            $lines[] = self::limit((string) ($response['message'] ?? ''), 1500);
            $lines[] = '';
            $lines[] = 'See their profile: ' . absolute_url('/provider/' . $provider['slug']);
            $lines[] = 'Review every proposal: ' . absolute_url('/dashboard/opportunities/' . (int) $opportunity['id']);

            Mailer::send(
                (string) $opportunity['poster_email'],
                'New proposal from ' . $provider['business_name'] . ' — Kopii',
                implode("\n", $lines),
                $replyTo
            );
        } catch (Throwable $e) {
            error_log('[Kopii] proposal notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Tell a provider what happened to their proposal. Only shortlisted and hired are sent: a decline
     * is better delivered by the poster than by an automatic email.
     *
     * @param array $response row from OpportunityModel::findResponseForPoster()
     */
    public static function proposalUpdated(array $response, string $status): void
    {
        try {
            if (!in_array($status, [OpportunityModel::SHORTLISTED, OpportunityModel::HIRED], true)) {
                return;
            }
            $headline = $status === OpportunityModel::HIRED
                ? 'You were chosen for "' . self::limit((string) $response['opportunity_title'], 120) . '".'
                : 'You are on the shortlist for "' . self::limit((string) $response['opportunity_title'], 120) . '".';

            $body = implode("\n", [
                'Hi ' . self::firstName((string) ($response['provider_owner_name'] ?? '')) . ',',
                '',
                $headline,
                'The person who posted it can now contact you using the details on your ' . $response['business_name'] . ' listing.',
                '',
                'Your proposals: ' . absolute_url('/dashboard/opportunities', ['tab' => 'proposals']),
            ]);
            Mailer::send(
                (string) $response['provider_email'],
                ($status === OpportunityModel::HIRED ? 'You were chosen' : 'You were shortlisted') . ' — Kopii',
                $body
            );
        } catch (Throwable $e) {
            error_log('[Kopii] proposal update email failed: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------------------------
    // Admin moderation of products and opportunities
    // ---------------------------------------------------------------------------------------

    /**
     * Tell someone an administrator took their product or opportunity down, or put it back.
     *
     * @param string $kind   'product' or 'opportunity'
     * @param string $reason shown to the person when taking it down
     */
    public static function contentModerated(string $toEmail, string $toName, string $kind, string $title, bool $removed, string $reason = ''): void
    {
        try {
            $lines = ['Hi ' . self::firstName($toName) . ',', ''];
            if ($removed) {
                $lines[] = 'The Kopii team has hidden your ' . $kind . ' "' . self::limit($title, 120) . '". It is no longer visible to the public.';
                if (trim($reason) !== '') {
                    $lines[] = '';
                    $lines[] = 'Reason: ' . trim($reason);
                }
                $lines[] = '';
                $lines[] = self::contactLine();
            } else {
                $lines[] = 'Your ' . $kind . ' "' . self::limit($title, 120) . '" is visible on Kopii again.';
            }
            Mailer::send(
                $toEmail,
                $removed ? 'Your ' . $kind . ' has been hidden — Kopii' : 'Your ' . $kind . ' is visible again — Kopii',
                implode("\n", $lines)
            );
        } catch (Throwable $e) {
            error_log('[Kopii] moderation email failed: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------------------------
    // Messages to the team, and account changes
    // ---------------------------------------------------------------------------------------

    /**
     * Who hears about a contact message or a report: the support address from env.php when there is
     * one, otherwise every administrator. The message is already stored in the admin inbox by now, so
     * this email is only the nudge.
     *
     * @return list<string>
     */
    public static function teamRecipients(): array
    {
        $support = trim((string) app_config('support_email', ''));
        if ($support !== '' && filter_var($support, FILTER_VALIDATE_EMAIL)) {
            return [$support];
        }
        return AdminModel::adminEmails();
    }

    /**
     * Tell the team that someone wrote to them or reported a listing. Reply-To is the sender when they
     * gave an address, so replying goes straight to them.
     *
     * @param array $m row from SupportModel::find()
     */
    public static function teamMessage(array $m): void
    {
        try {
            $recipients = self::teamRecipients();
            if (empty($recipients)) {
                return;
            }

            $isReport = (string) $m['kind'] === SupportModel::REPORT;
            $lines    = [$isReport ? 'Someone reported a listing on Kopii.' : 'Someone wrote to Kopii through the contact page.', ''];

            if ($isReport) {
                $lines[] = 'About:   ' . self::limit((string) $m['target_label'], 160) . ' (' . (string) $m['target_type'] . ')';
                $lines[] = 'Reason:  ' . self::limit((string) $m['subject'], 120);
            } else {
                $lines[] = 'Topic:   ' . self::limit((string) $m['subject'], 120);
            }
            $lines[] = 'From:    ' . (trim((string) $m['name']) !== '' ? self::limit((string) $m['name'], 80) : 'Anonymous')
                . (trim((string) $m['email']) !== '' ? ' <' . $m['email'] . '>' : ' (no email given)');
            $lines[] = '';
            $lines[] = self::limit((string) $m['message'], 1500);
            $lines[] = '';
            $lines[] = 'Open it in the admin inbox: ' . absolute_url('/admin/inbox', ['status' => 'open']);

            $subject = ($isReport ? 'Report: ' . self::limit((string) $m['target_label'], 60) : 'Contact: ' . self::limit((string) $m['subject'], 60)) . ' — Kopii';
            foreach ($recipients as $to) {
                Mailer::send($to, $subject, implode("\n", $lines), (string) ($m['email'] ?? ''));
            }
        } catch (Throwable $e) {
            error_log('[Kopii] team message email failed: ' . $e->getMessage());
        }
    }

    /** Sent to the OLD address when the sign-in email changes, so a hijacked account cannot change it silently. */
    public static function emailChanged(string $oldEmail, array $user, string $newEmail): void
    {
        try {
            $body = implode("\n", [
                'Hi ' . self::firstName((string) ($user['full_name'] ?? '')) . ',',
                '',
                'The sign-in email for your Kopii account was just changed from this address to ' . $newEmail . '.',
                '',
                "If that was you, there's nothing more to do. If it wasn't, please " . self::reachUs() . ' right away.',
            ]);
            Mailer::send($oldEmail, 'Your Kopii email address was changed', $body);
        } catch (Throwable $e) {
            error_log('[Kopii] email-changed notice failed: ' . $e->getMessage());
        }
    }

    public static function accountDeleted(string $email, string $fullName): void
    {
        try {
            $body = implode("\n", [
                'Hi ' . self::firstName($fullName) . ',',
                '',
                'Your Kopii account has been deleted, along with your listing, photos, products, opportunities and reviews.',
                '',
                "If you didn't ask for this, please " . self::reachUs() . ' right away.',
            ]);
            Mailer::send($email, 'Your Kopii account was deleted', $body);
        } catch (Throwable $e) {
            error_log('[Kopii] account-deleted email failed: ' . $e->getMessage());
        }
    }

    /** Proof that outgoing email works, sent by an administrator from the health check. Returns whether the mail system accepted it. */
    public static function testEmail(string $to): bool
    {
        try {
            return Mailer::send($to, 'Kopii test email', implode("\n", [
                'This is a test email from your Kopii site (' . absolute_url('/') . ').',
                '',
                'If you can read this in your inbox, lead alerts, password resets and confirmation links can reach people.',
                'If it landed in spam, set up SPF and DKIM for your domain in cPanel (Email Deliverability).',
            ]));
        } catch (Throwable $e) {
            error_log('[Kopii] test email failed: ' . $e->getMessage());
            return false;
        }
    }

    /** How to reach the team. Set 'support_email' in app/config/env.php to give a real address. */
    private static function contactLine(): string
    {
        return 'If you think this is a mistake, please ' . self::reachUs() . '.';
    }

    /** "write to us at support@..." when env.php names a support address, otherwise "get in touch with the Kopii team". */
    private static function reachUs(): string
    {
        $support = trim((string) app_config('support_email', ''));
        return $support !== '' ? 'write to us at ' . $support : 'get in touch with the Kopii team';
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
