<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * The admin console: an overview of what needs a decision (and whether the site is ready to go live),
 * listings (with the Verified check), shop products, opportunities, reviews, accounts, categories,
 * the inbox of messages and reports, and the log of everything an administrator has done.
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
            'health'       => SiteHealth::checks(),
        ]);
    }

    /** Send a test email to the signed-in administrator, to prove outgoing mail works on this server. */
    public function testEmail(): void
    {
        $admin = require_admin();
        verify_csrf();

        if (RateLimiter::tooMany('test_email', (string) (int) $admin['id'], 5, 3600)) {
            flash('error', 'You have sent several test emails in the last hour. Check your inbox and spam folder, or try again later.');
            admin_redirect_back('/admin');
        }
        RateLimiter::hit('test_email', (string) (int) $admin['id']);

        $to = (string) $admin['email'];
        if (Notifier::testEmail($to)) {
            $message = 'A test email was handed to the mail system for ' . $to . '. If it is not in your inbox in a few minutes, check spam: that means SPF/DKIM still need setting up.';
            if (app_is_local()) {
                $message = 'Local mode: the test email was written to storage/mail.log, not sent.';
            }
            flash('success', $message);
        } else {
            flash('error', 'The mail system refused the test email. Check the mail settings in app/config/env.php and the server\'s error log.');
        }
        admin_redirect_back('/admin');
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
            'portfolio' => $this->choice('portfolio', ['thin']),
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
            'planNow'      => PlanModel::effective($provider),
            'planChoices'  => PlanModel::assignable((int) ($provider['plan_id'] ?? 0)),
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
                // No end date: Featured until an administrator takes it off.
                if (!empty($provider['is_featured']) && trim((string) ($provider['featured_until'] ?? '')) === '') {
                    flash('success', $label . ' is already Featured, with no end date.');
                } else {
                    ProviderModel::adminSetFeatured($id, true, null);
                    AdminLogModel::record('provider.feature', 'provider', $id, $label, trim('No end date. ' . $note));
                    flash('success', $label . ' is now Featured, with no end date.');
                }
                break;

            case 'feature_until':
                $until = PlanModel::parseDate((string) ($_POST['featured_until'] ?? ''));
                if ($until === null) {
                    flash('error', 'Pick the last day of the Featured spot as a date.');
                } elseif ($until < date('Y-m-d') || $until > date('Y-m-d', strtotime('+3 years'))) {
                    flash('error', 'The end date must be today or later, and within the next 3 years.');
                } elseif (!empty($provider['is_featured']) && (string) ($provider['featured_until'] ?? '') === $until) {
                    flash('success', $label . ' is already Featured until ' . date('j M Y', strtotime($until)) . '.');
                } else {
                    ProviderModel::adminSetFeatured($id, true, $until);
                    AdminLogModel::record('provider.feature', 'provider', $id, $label, trim('Until ' . $until . '. ' . $note));
                    flash('success', $label . ' is Featured until ' . date('j M Y', strtotime($until)) . '.');
                }
                break;

            case 'unfeature':
                if (empty($provider['is_featured'])) {
                    flash('success', $label . ' is not Featured.');
                } else {
                    ProviderModel::adminSetFeatured($id, false);
                    AdminLogModel::record('provider.unfeature', 'provider', $id, $label, $note);
                    flash('success', $label . ' is no longer Featured.');
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

    /**
     * Put a listing on a plan until a date, or back on the default plan. Kopii does not take payment
     * itself yet: the administrator has already received it (for example by Mobile Money) and writes
     * the reference in the note, which goes into the audit log.
     */
    public function assignPlan(): void
    {
        require_admin();
        verify_csrf();

        $id       = (int) ($_POST['provider_id'] ?? 0);
        $provider = $id > 0 ? AdminModel::providerDetail($id) : null;
        if ($provider === null) {
            flash('error', 'That listing no longer exists.');
            admin_redirect_back('/admin/providers');
        }

        $back    = '/admin/providers/' . $id . '#plan-title';
        $note    = $this->note($back);
        $label   = (string) $provider['business_name'];
        $default = PlanModel::defaultPlan();
        $planId  = (int) ($_POST['plan_id'] ?? 0);
        $current = PlanModel::effective($provider);

        // Back to the free plan.
        if ($planId <= 0 || $planId === (int) $default['id']) {
            if ((int) ($provider['plan_id'] ?? 0) === 0) {
                flash('success', $label . ' is already on the ' . $default['name'] . ' plan.');
            } else {
                PlanModel::assign($id, null, null);
                AdminLogModel::record('provider.plan', 'provider', $id, $label, trim('Back to ' . $default['name'] . '. ' . $note));
                Notifier::listingPlanChanged($provider, (string) $default['name'], null);
                flash('success', $label . ' is back on the ' . $default['name'] . ' plan, and the owner has been emailed.');
            }
            admin_redirect_back($back);
        }

        $plan = PlanModel::find($planId);
        if ($plan === null) {
            flash('error', 'That plan no longer exists.');
            admin_redirect_back($back);
        }
        if (empty($plan['is_active']) && (int) ($provider['plan_id'] ?? 0) !== $planId) {
            flash('error', '"' . $plan['name'] . '" has been retired, so it cannot be given to more listings.');
            admin_redirect_back($back);
        }

        // An empty end date means "the length the plan covers, from today", never "for ever": a paid
        // plan with no end would quietly stay on after the payment ran out.
        $endsText = trim((string) ($_POST['plan_expires_on'] ?? ''));
        if ($endsText === '') {
            $ends = PlanModel::endDateFor($plan);
        } else {
            $ends = PlanModel::parseDate($endsText);
            if ($ends === null) {
                flash('error', 'Enter the end date as a calendar date, or leave it empty to use the length the plan covers.');
                admin_redirect_back($back);
            }
            if ($ends < date('Y-m-d') || $ends > date('Y-m-d', strtotime('+5 years'))) {
                flash('error', 'The end date must be today or later, and within the next 5 years.');
                admin_redirect_back($back);
            }
        }

        if ((int) ($provider['plan_id'] ?? 0) === $planId && (string) ($provider['plan_expires_on'] ?? '') === $ends && $current['lapsed_from'] === null) {
            flash('success', $label . ' is already on the ' . $plan['name'] . ' plan with that end date.');
            admin_redirect_back($back);
        }

        PlanModel::assign($id, $planId, $ends);
        AdminLogModel::record('provider.plan', 'provider', $id, $label, trim('Plan: ' . $plan['name'] . ' until ' . $ends . '. ' . $note));
        Notifier::listingPlanChanged($provider, (string) $plan['name'], $ends);
        flash('success', $label . ' is on the ' . $plan['name'] . ' plan until ' . date('j M Y', strtotime($ends)) . ', and the owner has been emailed.');
        admin_redirect_back($back);
    }

    // ---------------------------------------------------------------------------------------
    // Shop products
    // ---------------------------------------------------------------------------------------

    public function products(): void
    {
        require_admin();

        $filters = [
            'q'        => $this->text('q'),
            'status'   => $this->choice('status', ProductModel::STATUSES),
            'category' => $this->text('category'),
        ];

        [$page, $pages, $total] = $this->paginate(ProductModel::adminCount($filters));

        View::render('admin/products', [
            'pageTitle'    => 'Products — Admin — Kopii',
            'currentRoute' => '/admin/products',
            'adminTab'     => 'products',
            'products'     => ProductModel::adminSearch($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'categories'   => ProductModel::categories(),
            'filters'      => $filters,
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
        ]);
    }

    /** Hide a product from the shop (suspend) or show it again (reinstate). */
    public function moderateProduct(): void
    {
        require_admin();
        verify_csrf();

        $id      = (int) ($_POST['product_id'] ?? 0);
        $action  = (string) ($_POST['mod_action'] ?? '');
        $product = $id > 0 ? ProductModel::adminFind($id) : null;

        if ($product === null) {
            flash('error', 'That product no longer exists.');
            admin_redirect_back('/admin/products');
        }

        $note   = $this->note('/admin/products');
        $label  = (string) $product['name'] . ' (' . (string) $product['business_name'] . ')';
        $status = (string) $product['status'];

        if ($action === 'suspend') {
            if ($status === ProductModel::SUSPENDED) {
                flash('success', 'That product is already hidden.');
            } elseif (text_length($note) < 5) {
                // The seller is emailed this reason, so it has to say something.
                flash('error', 'Write a short reason (at least 5 characters). The seller receives it by email.');
            } else {
                ProductModel::adminSetStatus($id, ProductModel::SUSPENDED);
                AdminLogModel::record('product.suspend', 'product', $id, $label, $note);
                Notifier::contentModerated((string) $product['owner_email'], (string) $product['owner_name'], 'product', (string) $product['name'], true, $note);
                flash('success', '"' . $product['name'] . '" is hidden from the shop. The seller has been emailed.');
            }
        } elseif ($action === 'reinstate') {
            if ($status !== ProductModel::SUSPENDED) {
                flash('success', 'That product is not suspended.');
            } else {
                ProductModel::adminSetStatus($id, ProductModel::ACTIVE);
                AdminLogModel::record('product.reinstate', 'product', $id, $label, $note);
                Notifier::contentModerated((string) $product['owner_email'], (string) $product['owner_name'], 'product', (string) $product['name'], false);
                flash('success', '"' . $product['name'] . '" is back in the shop. The seller has been emailed.');
            }
        } else {
            flash('error', 'That action is not recognised.');
        }

        admin_redirect_back('/admin/products');
    }

    // ---------------------------------------------------------------------------------------
    // Opportunities
    // ---------------------------------------------------------------------------------------

    public function opportunities(): void
    {
        require_admin();

        $filters = [
            'q'      => $this->text('q'),
            'status' => $this->choice('status', OpportunityModel::STATUSES),
            'kind'   => $this->choice('kind', OpportunityModel::KINDS),
        ];

        [$page, $pages, $total] = $this->paginate(OpportunityModel::adminCount($filters));
        $uganda = require KOPII_ROOT . '/app/config/uganda.php';

        View::render('admin/opportunities', [
            'pageTitle'     => 'Opportunities — Admin — Kopii',
            'currentRoute'  => '/admin/opportunities',
            'adminTab'      => 'opportunities',
            'opportunities' => OpportunityModel::adminSearch($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'kinds'         => $uganda['opportunity_kinds'],
            'filters'       => $filters,
            'page'          => $page,
            'pages'         => $pages,
            'total'         => $total,
        ]);
    }

    public function moderateOpportunity(): void
    {
        require_admin();
        verify_csrf();

        $id  = (int) ($_POST['opportunity_id'] ?? 0);
        $opp = $id > 0 ? OpportunityModel::find($id) : null;

        if ($opp === null) {
            flash('error', 'That opportunity no longer exists.');
            admin_redirect_back('/admin/opportunities');
        }

        $action = (string) ($_POST['mod_action'] ?? '');
        $note   = $this->note('/admin/opportunities');
        $label  = (string) $opp['title'];

        if ($action === 'suspend') {
            if ($opp['status'] === OpportunityModel::SUSPENDED) {
                flash('success', 'That opportunity is already hidden.');
            } elseif (text_length($note) < 5) {
                flash('error', 'Write a short reason (at least 5 characters). The person who posted it receives it by email.');
            } else {
                OpportunityModel::adminSetStatus($id, OpportunityModel::SUSPENDED);
                AdminLogModel::record('opportunity.suspend', 'opportunity', $id, $label, $note);
                Notifier::contentModerated((string) $opp['poster_email'], (string) $opp['poster_name'], 'opportunity', $label, true, $note);
                flash('success', 'The opportunity is hidden. The person who posted it has been emailed.');
            }
        } elseif ($action === 'reinstate') {
            if ($opp['status'] !== OpportunityModel::SUSPENDED) {
                flash('success', 'That opportunity is not suspended.');
            } else {
                // Back as 'open'; if its closing date has passed it simply shows as closed until the poster extends it.
                OpportunityModel::adminSetStatus($id, OpportunityModel::OPEN);
                AdminLogModel::record('opportunity.reinstate', 'opportunity', $id, $label, $note);
                Notifier::contentModerated((string) $opp['poster_email'], (string) $opp['poster_name'], 'opportunity', $label, false);
                flash('success', 'The opportunity is visible again. The person who posted it has been emailed.');
            }
        } else {
            flash('error', 'That action is not recognised.');
        }

        admin_redirect_back('/admin/opportunities');
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
    // Categories
    // ---------------------------------------------------------------------------------------

    public function categories(): void
    {
        require_admin();

        $kind = $this->choice('kind', CategoryModel::KINDS);
        $kind = $kind !== '' ? $kind : 'service';

        View::render('admin/categories', [
            'pageTitle'    => 'Categories — Admin — Kopii',
            'currentRoute' => '/admin/categories',
            'adminTab'     => 'categories',
            'kind'         => $kind,
            'rows'         => CategoryModel::adminList($kind),
            'icons'        => CategoryModel::ICONS,
            'editing'      => ctype_digit((string) ($_GET['edit'] ?? '')) ? CategoryModel::adminFind($kind, (int) $_GET['edit']) : null,
        ]);
    }

    /** Add a category (no category_id) or change one. The slug is fixed when the category is made. */
    public function saveCategory(): void
    {
        require_admin();
        verify_csrf();

        $kind = in_array($_POST['kind'] ?? '', CategoryModel::KINDS, true) ? (string) $_POST['kind'] : 'service';
        $id   = (int) ($_POST['category_id'] ?? 0);
        $back = '/admin/categories?kind=' . $kind;

        $existing = $id > 0 ? CategoryModel::adminFind($kind, $id) : null;
        if ($id > 0 && $existing === null) {
            flash('error', 'That category no longer exists.');
            admin_redirect_back($back);
        }

        $name        = trim((string) ($_POST['name'] ?? ''));
        $icon        = (string) ($_POST['icon'] ?? '');
        $description = trim((string) ($_POST['description'] ?? ''));
        $sort        = max(0, min(999, (int) ($_POST['sort_order'] ?? 0)));

        $problems = [];
        if (text_length($name) < 2 || text_length($name) > 60) {
            $problems[] = 'Enter a name of 2 to 60 characters.';
        } elseif (CategoryModel::adminNameTaken($kind, $name, $id)) {
            $problems[] = 'Another category already has that name.';
        }
        if (!in_array($icon, CategoryModel::ICONS, true)) {
            $problems[] = 'Choose an icon from the list.';
        }
        if ($kind === 'service' && text_length($description) > 255) {
            $problems[] = 'Keep the description to 255 characters or fewer.';
        }
        if (!empty($problems)) {
            flash('error', 'Nothing was saved. ' . implode(' ', $problems));
            admin_redirect_back($id > 0 ? $back . '&edit=' . $id : $back);
        }

        $data = ['name' => $name, 'icon' => $icon, 'sort_order' => $sort, 'description' => $description];
        try {
            if ($existing === null) {
                $newId = CategoryModel::adminCreate($kind, $data);
                AdminLogModel::record('category.create', 'category', $newId, $name, $kind . ' category');
                flash('success', 'The category "' . $name . '" was added.');
            } else {
                CategoryModel::adminUpdate($kind, $id, $data);
                AdminLogModel::record('category.update', 'category', $id, $name, $kind . ' category');
                flash('success', 'The category "' . $name . '" was saved.');
            }
        } catch (Throwable $e) {
            flash('error', report_exception($e, 'We could not save that category. Please try again.'));
        }

        admin_redirect_back($back);
    }

    /** Delete a category nobody uses. One that is in use must be left alone (rename it instead). */
    public function deleteCategory(): void
    {
        require_admin();
        verify_csrf();

        $kind = in_array($_POST['kind'] ?? '', CategoryModel::KINDS, true) ? (string) $_POST['kind'] : 'service';
        $id   = (int) ($_POST['category_id'] ?? 0);
        $back = '/admin/categories?kind=' . $kind;

        $category = $id > 0 ? CategoryModel::adminFind($kind, $id) : null;
        if ($category === null) {
            flash('error', 'That category no longer exists.');
            admin_redirect_back($back);
        }

        $inUse = 0;
        foreach (CategoryModel::adminList($kind) as $row) {
            if ((int) $row['id'] === $id) {
                $inUse = (int) $row['uses'];
            }
        }
        if ($inUse > 0) {
            flash('error', '"' . $category['name'] . '" is used by ' . $inUse . ($kind === 'product' ? ' product' : ' listing') . ($inUse === 1 ? '' : 's') . ', so it cannot be deleted. Rename it instead, or wait until they have moved.');
            admin_redirect_back($back);
        }

        try {
            CategoryModel::adminDelete($kind, $id);
            AdminLogModel::record('category.delete', 'category', $id, (string) $category['name'], $kind . ' category');
            flash('success', 'The category "' . $category['name'] . '" was deleted.');
        } catch (Throwable $e) {
            flash('error', report_exception($e, 'We could not delete that category. Please try again.'));
        }
        admin_redirect_back($back);
    }

    // ---------------------------------------------------------------------------------------
    // Plans and fees
    // ---------------------------------------------------------------------------------------

    public function plans(): void
    {
        require_admin();

        $plans   = PlanModel::all();
        $editing = null;
        if (ctype_digit((string) ($_GET['edit'] ?? ''))) {
            $editing = PlanModel::find((int) $_GET['edit']);
        }

        View::render('admin/plans', [
            'pageTitle'    => 'Plans & fees — Admin — Kopii',
            'currentRoute' => '/admin/plans',
            'adminTab'     => 'plans',
            'plans'        => $plans,
            'editing'      => $editing,
            'rates'        => FeeModel::rates(),
            'renewals'     => PlanModel::listingsOnPaidPlans(),
            'siteLimit'    => ProductModel::MAX_PER_PROVIDER,
        ]);
    }

    /** Add a plan (no plan_id) or change one. The default plan keeps its price at 0 and cannot be retired. */
    public function savePlan(): void
    {
        require_admin();
        verify_csrf();

        $id       = (int) ($_POST['plan_id'] ?? 0);
        $existing = $id > 0 ? PlanModel::find($id) : null;
        if ($id > 0 && $existing === null) {
            flash('error', 'That plan no longer exists.');
            admin_redirect_back('/admin/plans');
        }
        $isDefault = $existing !== null && !empty($existing['is_default']);
        $back      = $id > 0 ? '/admin/plans?edit=' . $id . '#plan-form' : '/admin/plans#plan-form';

        [$data, $problems] = PlanModel::clean($_POST, $isDefault);
        if ($data['name'] !== '' && PlanModel::nameTaken($data['name'], $id)) {
            $problems[] = 'Another plan already has that name.';
        }
        if (!empty($problems)) {
            flash('error', 'Nothing was saved. ' . implode(' ', $problems));
            admin_redirect_back($back);
        }

        try {
            if ($existing === null) {
                $newId = PlanModel::create($data);
                AdminLogModel::record('plan.create', 'plan', $newId, $data['name'], $this->planSummary($data));
                flash('success', 'The plan "' . $data['name'] . '" was added.');
            } else {
                PlanModel::update($id, $data, $isDefault);
                AdminLogModel::record('plan.update', 'plan', $id, $data['name'], $this->planSummary($data, $isDefault));
                flash('success', 'The plan "' . $data['name'] . '" was saved. Listings already on it keep it until their end date.');
            }
        } catch (Throwable $e) {
            flash('error', report_exception($e, 'We could not save that plan. Please try again.'));
            admin_redirect_back($back);
        }
        admin_redirect_back('/admin/plans');
    }

    /** Set both commission rates. They are stored for the booking flow to use; nothing is charged yet. */
    public function saveFees(): void
    {
        require_admin();
        verify_csrf();

        $service = FeeModel::parseRate((string) ($_POST['service_rate'] ?? ''));
        $product = FeeModel::parseRate((string) ($_POST['product_rate'] ?? ''));
        if ($service === null || $product === null) {
            flash('error', 'Nothing was saved. Enter each rate as a percentage from 0 to ' . FeeModel::label(FeeModel::MAX_RATE) . ', for example 10 or 7.5.');
            admin_redirect_back('/admin/plans#fees-title');
        }

        $before = FeeModel::rates();
        if ($before[FeeModel::SERVICE] === $service && $before[FeeModel::PRODUCT] === $product) {
            flash('success', 'Those are the rates already set.');
            admin_redirect_back('/admin/plans#fees-title');
        }

        try {
            FeeModel::saveRates($service, $product);
            AdminLogModel::record(
                'fees.update', 'setting', null, 'Commission rates',
                'Services ' . FeeModel::label($before[FeeModel::SERVICE]) . ' to ' . FeeModel::label($service)
                . '; products ' . FeeModel::label($before[FeeModel::PRODUCT]) . ' to ' . FeeModel::label($product) . '.'
            );
            flash('success', 'The commission rates were saved. Nothing is charged until the booking flow exists.');
        } catch (Throwable $e) {
            flash('error', report_exception($e, 'We could not save the rates. Please try again.'));
        }
        admin_redirect_back('/admin/plans#fees-title');
    }

    /** What was actually saved, in a line for the audit log. The default plan is always free and on sale. */
    private function planSummary(array $d, bool $isDefault = false): string
    {
        if ($isDefault) {
            $d['price_ugx'] = 0;
            $d['is_active'] = 1;
        }
        $price = (int) $d['price_ugx'] > 0 ? 'UGX ' . number_format((int) $d['price_ugx']) . ' for ' . (int) $d['period_months'] . ' month(s)' : 'free';
        $limit = $d['product_limit'] !== null ? $d['product_limit'] . ' products' : 'site-wide product limit';
        return $price . '; ' . $limit . '; ' . ((int) $d['is_active'] === 1 ? 'on sale' : 'retired');
    }

    // ---------------------------------------------------------------------------------------
    // Inbox: contact messages and reported listings
    // ---------------------------------------------------------------------------------------

    public function inbox(): void
    {
        require_admin();

        $open   = SupportModel::openCount();
        $status = $this->choice('status', ['open', 'handled', 'all']);
        if ($status === '') {
            // Open on what is waiting when there is something: that is what the admin came to do.
            $status = $open > 0 ? 'open' : 'all';
        }
        $filters = [
            'kind'   => $this->choice('kind', SupportModel::KINDS),
            'status' => $status === 'all' ? '' : $status,
            'q'      => $this->text('q'),
        ];

        [$page, $pages, $total] = $this->paginate(SupportModel::count($filters));
        $messages = SupportModel::search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        // What each report is about, with the page to look at and the admin page to act on. Null when it
        // is gone: the listing may have been deleted since.
        foreach ($messages as &$m) {
            $m['target'] = !empty($m['target_type']) ? SupportModel::resolveTarget((string) $m['target_type'], (int) $m['target_id']) : null;
        }
        unset($m);

        View::render('admin/inbox', [
            'pageTitle'    => 'Inbox — Admin — Kopii',
            'currentRoute' => '/admin/inbox',
            'adminTab'     => 'inbox',
            'messages'     => $messages,
            'status'       => $status,
            'kind'         => $filters['kind'],
            'q'            => $filters['q'],
            'counts'       => [
                'open'    => $open,
                'handled' => SupportModel::count(['status' => 'handled']),
                'all'     => SupportModel::count([]),
            ],
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
        ]);
    }

    public function inboxAction(): void
    {
        $admin = require_admin();
        verify_csrf();

        $id      = (int) ($_POST['message_id'] ?? 0);
        $action  = (string) ($_POST['inbox_action'] ?? '');
        $message = $id > 0 ? SupportModel::find($id) : null;

        if ($message === null) {
            flash('error', 'That message no longer exists.');
            admin_redirect_back('/admin/inbox');
        }

        $note  = $this->note('/admin/inbox');
        $label = ((string) $message['kind'] === SupportModel::REPORT ? 'report on ' . $message['target_label'] : 'message from ' . ($message['name'] !== '' ? $message['name'] : 'a visitor'));

        if ($action === 'handle') {
            if ($message['status'] === SupportModel::HANDLED) {
                flash('success', 'That one was already marked as dealt with.');
            } else {
                SupportModel::setStatus($id, SupportModel::HANDLED, (int) $admin['id'], $note);
                AdminLogModel::record('message.handle', 'message', $id, $label, $note);
                flash('success', 'Marked as dealt with.');
            }
        } elseif ($action === 'reopen') {
            if ($message['status'] === SupportModel::OPEN) {
                flash('success', 'That one is already open.');
            } else {
                SupportModel::setStatus($id, SupportModel::OPEN, (int) $admin['id']);
                AdminLogModel::record('message.reopen', 'message', $id, $label);
                flash('success', 'Reopened.');
            }
        } else {
            flash('error', 'That action is not recognised.');
        }

        admin_redirect_back('/admin/inbox');
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
