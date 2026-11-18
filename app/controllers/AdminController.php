<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * The admin console: an overview of what needs a decision, listings (with the Verified check),
 * reviews, accounts, and the log of everything an administrator has done.
 *
 * Every action sets an explicit state (never "flip whatever it is now"), is recorded in the
 * audit log, and returns to the page it was started from.
 */
class AdminController
{
    private const PER_PAGE = 20;
    private const MAX_NOTE = 500;

    // ---------------------------------------------------------------------------------------
    // Overview
    // ---------------------------------------------------------------------------------------

    public function index(): void
    {
        require_admin();

        View::render('admin/index', [
            'pageTitle'    => 'Admin — Kopii',
            'currentRoute' => '/admin',
            'adminTab'     => 'overview',
            'attention'    => admin_attention_counts(),
            'week'         => AdminModel::activitySince(date('Y-m-d H:i:s', strtotime('-7 days'))),
            'stats'        => ProviderModel::marketplaceStats(),
            'hidden'       => AdminModel::hiddenListings(),
            'recent'       => AdminLogModel::recent(6),
        ]);
    }

    // ---------------------------------------------------------------------------------------
    // Listings
    // ---------------------------------------------------------------------------------------

    public function providers(): void
    {
        require_admin();

        $filters = [
            'q'        => $this->text('q'),
            'status'   => $this->choice('status', AdminModel::PROVIDER_STATUSES),
            'verified' => $this->choice('verified', ['1', '0']),
            'featured' => $this->choice('featured', ['1']),
        ];

        [$page, $pages, $total] = $this->paginate(AdminModel::providerCount($filters));

        View::render('admin/providers', [
            'pageTitle'    => 'Listings — Admin — Kopii',
            'currentRoute' => '/admin/providers',
            'adminTab'     => 'providers',
            'providers'    => AdminModel::providerList($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'filters'      => $filters,
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
        ]);
    }

    public function provider(array $params): void
    {
        require_admin();

        $id       = (int) ($params['id'] ?? 0);
        $provider = $id > 0 ? AdminModel::providerDetail($id) : null;
        if ($provider === null) {
            http_response_code(404);
            View::render('pages/404', ['pageTitle' => 'Listing not found — Kopii']);
            return;
        }

        View::render('admin/provider', [
            'pageTitle'    => $provider['business_name'] . ' — Admin — Kopii',
            'currentRoute' => '/admin/providers',
            'adminTab'     => 'providers',
            'provider'     => $provider,
            'portfolio'    => ProviderModel::getPortfolio($id),
            'packages'     => ProviderModel::getPackages($id),
            'history'      => AdminLogModel::recent(15, 0, 'provider', $id),
        ]);
    }

    public function moderate(): void
    {
        $admin = require_admin();
        verify_csrf();

        $id       = (int) ($_POST['provider_id'] ?? 0);
        $action   = (string) ($_POST['mod_action'] ?? '');
        $provider = $id > 0 ? AdminModel::providerDetail($id) : null;

        if ($provider === null) {
            flash('error', 'That listing no longer exists.');
            admin_redirect_back('/admin/providers');
        }

        $note    = $this->note('/admin/providers/' . $id);
        $label   = (string) $provider['business_name'];
        $status  = (string) $provider['status'];
        $adminId = (int) $admin['id'];

        switch ($action) {
            case 'verify':
                if (!$this->allChecked(['check_phone', 'check_work', 'check_pricing'])) {
                    flash('error', 'Tick all three checks before verifying: the phone number works, the work is their own, and the pricing is clear.');
                } elseif (!empty($provider['is_verified'])) {
                    flash('success', $label . ' is already Verified.');
                } else {
                    ProviderModel::adminSetVerified($id, true, $adminId);
                    AdminLogModel::record('provider.verify', 'provider', $id, $label, $note);
                    Notifier::listingVerified($provider);
                    flash('success', $label . ' is now Verified, and the owner has been emailed.');
                }
                break;

            case 'unverify':
                if (empty($provider['is_verified'])) {
                    flash('success', $label . ' is not Verified.');
                } else {
                    ProviderModel::adminSetVerified($id, false, $adminId);
                    AdminLogModel::record('provider.unverify', 'provider', $id, $label, $note);
                    flash('success', 'The Verified badge was removed from ' . $label . '.');
                }
                break;

            case 'feature':
            case 'unfeature':
                $want = $action === 'feature';
                if ((bool) $provider['is_featured'] === $want) {
                    flash('success', $label . ($want ? ' is already Featured.' : ' is not Featured.'));
                } else {
                    ProviderModel::adminSetFeatured($id, $want);
                    AdminLogModel::record($want ? 'provider.feature' : 'provider.unfeature', 'provider', $id, $label, $note);
                    flash('success', $want ? $label . ' is now Featured.' : $label . ' is no longer Featured.');
                }
                break;

            case 'pause':
                if ($status !== 'active') {
                    flash('error', $label . ' is already hidden from the public.');
                } else {
                    ProviderModel::adminSetStatus($id, 'paused');
                    AdminLogModel::record('provider.pause', 'provider', $id, $label, $note);
                    Notifier::listingPaused($provider, $note);
                    flash('success', $label . ' is paused and hidden from search. The owner has been emailed.');
                }
                break;

            case 'activate':
                if ($status === 'suspended') {
                    flash('error', 'This listing is suspended along with its owner\'s account. Reinstate the account on the Users page first.');
                } elseif ($status === 'active') {
                    flash('success', $label . ' is already live.');
                } else {
                    ProviderModel::adminSetStatus($id, 'active');
                    AdminLogModel::record('provider.activate', 'provider', $id, $label, $note);
                    Notifier::listingReactivated($provider);
                    flash('success', $label . ' is live again, and the owner has been emailed.');
                }
                break;

            default:
                flash('error', 'That action is not recognised.');
        }

        admin_redirect_back('/admin/providers/' . $id);
    }

    // ---------------------------------------------------------------------------------------
    // Reviews
    // ---------------------------------------------------------------------------------------

    public function reviews(): void
    {
        require_admin();

        $pending = ReviewModel::pendingCount();
        $status  = $this->choice('status', ['pending', 'published', 'all']);
        if ($status === '') {
            // Open on the waiting reviews when there are any: that is what the admin came to do.
            $status = $pending > 0 ? 'pending' : 'all';
        }
        $filter = $status === 'all' ? '' : $status;

        [$page, $pages, $total] = $this->paginate(ReviewModel::adminCount($filter));

        View::render('admin/reviews', [
            'pageTitle'    => 'Reviews — Admin — Kopii',
            'currentRoute' => '/admin/reviews',
            'adminTab'     => 'reviews',
            'reviews'      => ReviewModel::adminSearch($filter, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'status'       => $status,
            'counts'       => [
                'pending'   => $pending,
                'published' => ReviewModel::adminCount(ReviewModel::PUBLISHED),
                'all'       => ReviewModel::adminCount(''),
            ],
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
        ]);
    }

    /** Approve a waiting review (it then appears and counts toward the rating) or delete any review. */
    public function moderateReview(): void
    {
        require_admin();
        verify_csrf();

        $id     = (int) ($_POST['review_id'] ?? 0);
        $action = (string) ($_POST['review_action'] ?? '');
        $review = $id > 0 ? ReviewModel::findForAdmin($id) : null;

        if ($review === null) {
            flash('error', 'That review no longer exists.');
            admin_redirect_back('/admin/reviews');
        }

        $label = 'Review by ' . $review['reviewer_name'] . ' on ' . $review['business_name'];

        if ($action === 'approve') {
            if ($review['status'] === ReviewModel::PENDING) {
                ReviewModel::approve($id);
                AdminLogModel::record('review.approve', 'review', $id, $label);
                flash('success', 'Review approved. It is now public and counts toward the rating.');
            } else {
                flash('success', 'That review is already public.');
            }
        } elseif ($action === 'delete') {
            ReviewModel::delete($id);
            // The review is gone, so keep what it said in the log: that is the only record left.
            AdminLogModel::record('review.delete', 'review', $id, $label, (int) $review['rating'] . '/5: ' . $this->excerpt((string) $review['comment']));
            flash('success', 'Review deleted.');
        } else {
            flash('error', 'That action is not recognised.');
        }

        admin_redirect_back('/admin/reviews');
    }

    // ---------------------------------------------------------------------------------------
    // Accounts
    // ---------------------------------------------------------------------------------------

    public function users(): void
    {
        $admin = require_admin();

        $filters = [
            'q'      => $this->text('q'),
            'role'   => $this->choice('role', AdminModel::USER_ROLES),
            'email'  => $this->choice('email', ['confirmed', 'unconfirmed']),
            'status' => $this->choice('status', ['active', 'suspended']),
        ];

        [$page, $pages, $total] = $this->paginate(AdminModel::userCount($filters));

        View::render('admin/users', [
            'pageTitle'    => 'Users — Admin — Kopii',
            'currentRoute' => '/admin/users',
            'adminTab'     => 'users',
            'users'        => AdminModel::userList($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'filters'      => $filters,
            'myId'         => (int) $admin['id'],
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
        ]);
    }

    public function userAction(): void
    {
        $admin = require_admin();
        verify_csrf();

        $id     = (int) ($_POST['user_id'] ?? 0);
        $action = (string) ($_POST['user_action'] ?? '');
        $target = $id > 0 ? UserModel::findById($id) : null;

        if ($target === null) {
            flash('error', 'That account no longer exists.');
            admin_redirect_back('/admin/users');
        }

        $note      = $this->note('/admin/users');
        $label     = (string) $target['full_name'] . ' (' . (string) $target['email'] . ')';
        $suspended = !empty($target['suspended_at']);

        switch ($action) {
            case 'resend_verification':
                if (!empty($target['email_verified_at'])) {
                    flash('success', 'That email address is already confirmed.');
                } elseif (!EmailVerificationModel::canIssue($id)) {
                    flash('error', 'Several links were already sent to ' . $target['email'] . ' in the last hour. Ask them to check spam, or try again later.');
                } else {
                    try {
                        $token = EmailVerificationModel::issue($id);
                        Notifier::verifyEmail($target, absolute_url('/verify-email', ['token' => $token]));
                        AdminLogModel::record('user.resend_verification', 'user', $id, $label);
                        $message = 'A new confirmation link was sent to ' . $target['email'] . '.';
                        if (app_is_local()) {
                            $message .= ' (Local mode: written to storage/mail.log instead of being sent.)';
                        }
                        flash('success', $message);
                    } catch (Throwable $e) {
                        flash('error', report_exception($e));
                    }
                }
                break;

            case 'suspend':
                if ($target['role'] === 'admin' || $id === (int) $admin['id']) {
                    flash('error', 'Administrator accounts cannot be suspended here.');
                } elseif ($suspended) {
                    flash('success', 'That account is already suspended.');
                } elseif (text_length($note) < 5) {
                    // The person is emailed this reason, so it has to say something.
                    flash('error', 'Write a short reason (at least 5 characters). The person receives it by email.');
                } else {
                    try {
                        AdminModel::suspendUser($id);
                    } catch (Throwable $e) {
                        flash('error', report_exception($e));
                        break;
                    }
                    AdminLogModel::record('user.suspend', 'user', $id, $label, $note);
                    Notifier::accountSuspended($target, $note);
                    flash('success', $target['full_name'] . ' is suspended: they cannot sign in and their listing is hidden.');
                }
                break;

            case 'reinstate':
                if (!$suspended) {
                    flash('success', 'That account is not suspended.');
                } else {
                    try {
                        AdminModel::reinstateUser($id);
                    } catch (Throwable $e) {
                        flash('error', report_exception($e));
                        break;
                    }
                    AdminLogModel::record('user.reinstate', 'user', $id, $label, $note);
                    Notifier::accountReinstated($target);
                    flash('success', $target['full_name'] . ' can sign in again. Any listing stays paused until you reactivate it.');
                }
                break;

            default:
                flash('error', 'That action is not recognised.');
        }

        admin_redirect_back('/admin/users');
    }

    // ---------------------------------------------------------------------------------------
    // Activity log
    // ---------------------------------------------------------------------------------------

    public function activity(): void
    {
        require_admin();

        $type = $this->choice('type', AdminLogModel::TYPES);
        [$page, $pages, $total] = $this->paginate(AdminLogModel::count($type));

        View::render('admin/activity', [
            'pageTitle'    => 'Activity — Admin — Kopii',
            'currentRoute' => '/admin/activity',
            'adminTab'     => 'activity',
            'entries'      => AdminLogModel::recent(self::PER_PAGE, ($page - 1) * self::PER_PAGE, $type),
            'type'         => $type,
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
        ]);
    }

    // ---------------------------------------------------------------------------------------
    // Request helpers
    // ---------------------------------------------------------------------------------------

    /** A free-text query parameter, trimmed and capped so a huge value cannot reach a query. */
    private function text(string $key, int $max = 80): string
    {
        $value = trim((string) ($_GET[$key] ?? ''));
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    /** A query parameter that must be one of a fixed list; anything else means "no filter". */
    private function choice(string $key, array $allowed): string
    {
        $value = (string) ($_GET[$key] ?? '');
        return in_array($value, $allowed, true) ? $value : '';
    }

    /** @return array{0:int,1:int,2:int} current page, page count, total rows */
    private function paginate(int $total): array
    {
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
        return [$page, $pages, $total];
    }

    /** The optional note that goes with an action. Too long a note sends the admin back with a message. */
    private function note(string $backTo): string
    {
        $note = trim((string) ($_POST['note'] ?? ''));
        if (text_length($note) > self::MAX_NOTE) {
            flash('error', 'Please keep the note under ' . self::MAX_NOTE . ' characters.');
            admin_redirect_back($backTo);
        }
        return $note;
    }

    private function allChecked(array $fields): bool
    {
        foreach ($fields as $field) {
            if ((string) ($_POST[$field] ?? '') !== '1') {
                return false;
            }
        }
        return true;
    }

    private function excerpt(string $text, int $max = 150): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        return function_exists('mb_strimwidth') ? mb_strimwidth($text, 0, $max, '…', 'UTF-8') : substr($text, 0, $max);
    }
}
