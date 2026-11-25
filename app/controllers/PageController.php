<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * The pages around the marketplace rather than in it: Terms, Privacy, the contact form, "report this
 * listing", and the two files search engines ask for (robots.txt, sitemap.xml).
 */
class PageController
{
    /** Shown at the top of the Terms and the Privacy Policy. Change it whenever either text changes. */
    private const LEGAL_UPDATED = '25 November 2026';

    // ---------------------------------------------------------------------------------------
    // Terms, privacy, contact
    // ---------------------------------------------------------------------------------------

    public function terms(): void
    {
        View::render('pages/terms', [
            'pageTitle'       => 'Terms of Use — Kopii',
            'metaDescription' => 'The rules for using Kopii: what the marketplace is, what the Verified badge means, and what we expect from providers, sellers and customers.',
            'currentRoute'    => '/terms',
            'updated'         => self::LEGAL_UPDATED,
            'supportEmail'    => trim((string) app_config('support_email', '')),
        ]);
    }

    public function privacy(): void
    {
        View::render('pages/privacy', [
            'pageTitle'       => 'Privacy Policy — Kopii',
            'metaDescription' => 'What personal information Kopii collects, why, who sees it, how long we keep it, and how to see, correct or delete it.',
            'currentRoute'    => '/privacy',
            'updated'         => self::LEGAL_UPDATED,
            'supportEmail'    => trim((string) app_config('support_email', '')),
        ]);
    }

    public function safety(): void
    {
        View::render('pages/safety', [
            'pageTitle'       => 'Safety Tips for Hiring and Buying in Uganda — Kopii',
            'metaDescription' => 'How to hire a provider or buy from a seller safely: check real past work, agree the price in writing, pay in steps, and what to do about Mobile Money scams.',
            'currentRoute'    => '/safety',
            'supportEmail'    => trim((string) app_config('support_email', '')),
        ]);
    }

    public function contact(): void
    {
        View::render('pages/contact', [
            'pageTitle'       => 'Contact Kopii',
            'metaDescription' => 'Write to the Kopii team: questions, help with your listing or account, a problem with a provider, or a request about your personal data.',
            'currentRoute'    => '/contact',
            'topics'          => SupportModel::CONTACT_TOPICS,
            'supportEmail'    => trim((string) app_config('support_email', '')),
            'chosenTopic'     => in_array($_GET['topic'] ?? '', array_keys(SupportModel::CONTACT_TOPICS), true) ? (string) $_GET['topic'] : '',
        ]);
    }

    public function contactSend(): void
    {
        verify_csrf();

        // Hidden field that only a bot fills in.
        if (!empty($_POST['contact_url_hp'])) {
            redirect_to('/contact');
        }

        $ip = client_ip();
        if (RateLimiter::tooMany('contact', $ip, 5, 3600)) {
            set_old_input($_POST);
            flash('error', 'You have sent several messages in a short time. Please wait a little while before sending another.');
            redirect_to('/contact');
        }

        $user    = current_user();
        $name    = trim((string) ($_POST['name'] ?? ''));
        $email   = strtolower(trim((string) ($_POST['email'] ?? '')));
        $topic   = (string) ($_POST['topic'] ?? '');
        $message = trim((string) ($_POST['message'] ?? ''));

        $problems = [];
        if (text_length($name) < 2 || text_length($name) > 100) {
            $problems[] = 'your name';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $problems[] = 'a valid email address so we can reply';
        }
        if (!isset(SupportModel::CONTACT_TOPICS[$topic])) {
            $problems[] = 'what your message is about';
        }
        if (text_length($message) < 10) {
            $problems[] = 'a message of at least 10 characters';
        } elseif (text_length($message) > 3000) {
            $problems[] = 'a shorter message (3,000 characters at most)';
        }

        if (!empty($problems)) {
            set_old_input($_POST);
            flash('error', 'Please check: ' . implode('; ', $problems) . '.');
            redirect_to('/contact');
        }

        try {
            $id = SupportModel::create([
                'kind'    => SupportModel::CONTACT,
                'user_id' => $user['id'] ?? null,
                'name'    => $name,
                'email'   => $email,
                'subject' => SupportModel::CONTACT_TOPICS[$topic],
                'message' => $message,
            ]);
        } catch (Throwable $e) {
            set_old_input($_POST);
            flash('error', report_exception($e, 'We could not save your message right now. Please try again in a moment.'));
            redirect_to('/contact');
        }

        RateLimiter::hit('contact', $ip);

        $saved = SupportModel::find($id);
        if ($saved !== null) {
            Notifier::teamMessage($saved);
        }

        clear_old_input();
        flash('success', 'Thank you, your message is with the Kopii team. We reply to ' . $email . ', usually within two working days.');
        redirect_to('/contact');
    }

    // ---------------------------------------------------------------------------------------
    // Report a listing
    // ---------------------------------------------------------------------------------------

    /** The "Report" form on a provider, product or opportunity page. */
    public function report(): void
    {
        verify_csrf();

        $type = (string) ($_POST['target_type'] ?? '');
        $id   = (int) ($_POST['target_id'] ?? 0);

        // Hidden field that only a bot fills in.
        if (!empty($_POST['report_url_hp'])) {
            redirect_to('/');
        }

        $target = in_array($type, SupportModel::TARGETS, true) ? SupportModel::resolveTarget($type, $id) : null;
        if ($target === null) {
            flash('error', 'That listing no longer exists, so there is nothing to report.');
            redirect_to('/');
        }
        $back = $target['public_path'];

        $ip = client_ip();
        if (RateLimiter::tooMany('report', $ip, 10, 3600) || RateLimiter::tooMany('report_target', $ip . '|' . $type . '|' . $id, 2, 86400)) {
            flash('error', 'You have already sent reports recently. The Kopii team will look at them; please try again later.');
            redirect_to($back);
        }

        $reason  = (string) ($_POST['reason'] ?? '');
        $details = trim((string) ($_POST['details'] ?? ''));
        $user    = current_user();
        $name    = $user ? (string) $user['full_name'] : trim((string) ($_POST['reporter_name'] ?? ''));
        $email   = $user ? (string) $user['email'] : strtolower(trim((string) ($_POST['reporter_email'] ?? '')));

        $problems = [];
        if (!isset(SupportModel::REPORT_REASONS[$reason])) {
            $problems[] = 'a reason';
        }
        if ($reason === 'other' && text_length($details) < 10) {
            $problems[] = 'a few words about what is wrong';
        }
        if (text_length($details) > 1500) {
            $problems[] = 'shorter details (1,500 characters at most)';
        }
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
            $problems[] = 'a valid email address, or leave it blank';
        }
        if (text_length($name) > 100) {
            $problems[] = 'a shorter name';
        }
        if (!empty($problems)) {
            flash('error', 'Please check: ' . implode('; ', $problems) . '.');
            redirect_to($back);
        }

        try {
            $messageId = SupportModel::create([
                'kind'         => SupportModel::REPORT,
                'user_id'      => $user['id'] ?? null,
                'name'         => $name,
                'email'        => $email,
                'subject'      => SupportModel::REPORT_REASONS[$reason],
                'message'      => $details,
                'target_type'  => $type,
                'target_id'    => $id,
                'target_label' => $target['label'],
            ]);
        } catch (Throwable $e) {
            flash('error', report_exception($e, 'We could not send your report right now. Please try again in a moment.'));
            redirect_to($back);
        }

        RateLimiter::hit('report', $ip);
        RateLimiter::hit('report_target', $ip . '|' . $type . '|' . $id);

        $saved = SupportModel::find($messageId);
        if ($saved !== null) {
            Notifier::teamMessage($saved);
        }

        flash('success', 'Thank you. The Kopii team will look at this. The owner is not told who reported it.');
        redirect_to($back);
    }

    // ---------------------------------------------------------------------------------------
    // For search engines
    // ---------------------------------------------------------------------------------------

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: max-age=3600');

        // A developer's copy must never be indexed.
        if (app_is_local()) {
            echo "User-agent: *\nDisallow: /\n";
            return;
        }

        echo "User-agent: *\n";
        foreach (['/admin', '/dashboard', '/account', '/login', '/forgot-password', '/reset-password', '/verify-email', '/hire/brief'] as $path) {
            echo 'Disallow: ' . route_url($path) . "\n";
        }
        echo "\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: max-age=3600');

        $limit = 2000;
        $today = date('Y-m-d');

        // [path, last change, how often it changes, importance]
        $urls = [
            ['/',               $today, 'daily',   '1.0'],
            ['/explore',        $today, 'daily',   '0.9'],
            ['/shop',           $today, 'daily',   '0.8'],
            ['/opportunities',  $today, 'daily',   '0.8'],
            ['/hire',           null,   'monthly', '0.6'],
            ['/about',          null,   'monthly', '0.5'],
            ['/register',       null,   'monthly', '0.5'],
            ['/safety',         null,   'monthly', '0.4'],
            ['/contact',        null,   'yearly',  '0.3'],
            ['/terms',          null,   'yearly',  '0.2'],
            ['/privacy',        null,   'yearly',  '0.2'],
        ];

        try {
            foreach (ProviderModel::sitemapRows($limit) as $row) {
                $urls[] = ['/provider/' . $row['slug'], self::day($row['updated_at'] ?? null), 'weekly', '0.7'];
            }
            foreach (ProductModel::search(['sort' => 'newest'], $limit) as $row) {
                $urls[] = ['/product/' . $row['slug'], self::day($row['updated_at'] ?? null), 'weekly', '0.6'];
            }
            foreach (OpportunityModel::search([], $limit) as $row) {
                $urls[] = ['/opportunities/' . (int) $row['id'], self::day($row['updated_at'] ?? null), 'daily', '0.5'];
            }
        } catch (Throwable $e) {
            // Still answer with the fixed pages: a half-empty sitemap beats an error for a crawler.
            report_exception($e);
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$path, $lastmod, $freq, $priority]) {
            echo "  <url>\n";
            echo '    <loc>' . htmlspecialchars(absolute_url($path), ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if ($lastmod !== null) {
                echo '    <lastmod>' . $lastmod . "</lastmod>\n";
            }
            echo '    <changefreq>' . $freq . "</changefreq>\n";
            echo '    <priority>' . $priority . "</priority>\n";
            echo "  </url>\n";
        }
        echo "</urlset>\n";
    }

    /** 'Y-m-d' for a stored timestamp, or null when it cannot be read. */
    private static function day(mixed $timestamp): ?string
    {
        $ts = strtotime((string) $timestamp);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
