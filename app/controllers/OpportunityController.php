<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * The opportunities board. Anyone with a confirmed email can post something they need; providers
 * answer with a proposal; the poster shortlists, declines or chooses one. The poster's contact
 * details are never on the public page: proposals reach them in the dashboard and by email.
 */
class OpportunityController
{
    private const PER_PAGE         = 20;
    private const MAX_POSTS_PER_DAY = 5;
    public const ANYWHERE          = 'Anywhere in Uganda';

    // ---------------------------------------------------------------------------------------
    // Public board
    // ---------------------------------------------------------------------------------------

    public function index(): void
    {
        $uganda = require KOPII_ROOT . '/app/config/uganda.php';

        $filters = [
            'q'        => $this->text('q'),
            'kind'     => $this->choice('kind', OpportunityModel::KINDS),
            'category' => $this->text('category'),
            'city'     => $this->text('city'),
        ];

        $total = OpportunityModel::countSearch($filters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($pages, max(1, (int) ($_GET['page'] ?? 1)));

        View::render('pages/opportunities', [
            'pageTitle'       => 'Opportunities — jobs, projects and tenders in Uganda | Kopii',
            'metaDescription' => 'Open requests from clients and businesses across Uganda. Send a proposal with your price and timeline, or post what you need and let providers come to you.',
            'currentRoute'    => '/opportunities',
            'filters'         => $filters,
            'kinds'           => $uganda['opportunity_kinds'],
            'categories'      => CategoryModel::allWithCounts(),
            'cities'          => $uganda['cities'],
            'opportunities'   => OpportunityModel::search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page'            => $page,
            'pages'           => $pages,
            'total'           => $total,
        ]);
    }

    public function show(array $params): void
    {
        $opp  = $this->findOpportunity($params);
        $user = current_user();

        $isOwner = $opp !== null && $user !== null && (int) $user['id'] === (int) $opp['user_id'];
        $isAdmin = $user !== null && ($user['role'] ?? '') === 'admin';
        $hidden  = $opp !== null && ($opp['status'] === OpportunityModel::SUSPENDED || !empty($opp['poster_suspended_at']));

        if ($opp === null || ($hidden && !$isOwner && !$isAdmin)) {
            http_response_code(404);
            View::render('pages/404', ['pageTitle' => 'Opportunity not found — Kopii']);
            return;
        }

        if (!$hidden) {
            OpportunityModel::incrementViews((int) $opp['id']);
        }

        $provider   = $user !== null ? ProviderModel::findByUserId((int) $user['id']) : null;
        $myResponse = $provider !== null ? OpportunityModel::responseBy((int) $opp['id'], (int) $provider['id']) : null;
        $uganda     = require KOPII_ROOT . '/app/config/uganda.php';

        View::render('pages/opportunity', [
            'pageTitle'       => $opp['title'] . ' — Opportunities | Kopii',
            'metaDescription' => text_excerpt((string) $opp['description'], 155),
            'currentRoute'    => '/opportunities',
            'opp'             => $opp,
            'isOpen'          => OpportunityModel::isOpen($opp),
            'isOwner'         => $isOwner,
            'provider'        => $provider,
            'myResponse'      => $myResponse,
            'kinds'           => $uganda['opportunity_kinds'],
            'responseTimes'   => $uganda['response_times'],
        ]);
    }

    // ---------------------------------------------------------------------------------------
    // Posting
    // ---------------------------------------------------------------------------------------

    public function newForm(): void
    {
        $user    = require_auth();
        $account = UserModel::findById((int) $user['id']);
        $uganda  = require KOPII_ROOT . '/app/config/uganda.php';

        View::render('pages/opportunity_new', [
            'pageTitle'       => 'Post an opportunity — Kopii',
            'currentRoute'    => '/opportunities',
            'emailConfirmed'  => !empty($account['email_verified_at']),
            'userEmail'       => (string) $user['email'],
            'kinds'           => $uganda['opportunity_kinds'],
            'categories'      => CategoryModel::allWithCounts(),
            'cities'          => $uganda['cities'],
            'defaultCloses'   => date('Y-m-d', strtotime('+' . OpportunityModel::DEFAULT_DAYS . ' days')),
            'maxCloses'       => date('Y-m-d', strtotime('+' . OpportunityModel::MAX_DAYS . ' days')),
            'minCloses'       => date('Y-m-d'),
        ]);
    }

    public function store(): void
    {
        $user = require_auth();
        verify_csrf();

        if (!empty($_POST['opp_confirm_hp'])) {
            redirect_to('/opportunities');
        }

        // An address nobody has confirmed could be anyone's: confirmed email is what keeps the board honest.
        $account = UserModel::findById((int) $user['id']);
        if (empty($account['email_verified_at'])) {
            flash('warning', 'Please confirm your email address before posting an opportunity.');
            redirect_to('/opportunities/new');
        }

        $ip = client_ip();
        if (RateLimiter::tooMany('opportunity', $ip, 10, 3600)
            || OpportunityModel::countRecentByUser((int) $user['id'], date('Y-m-d H:i:s', strtotime('-1 day'))) >= self::MAX_POSTS_PER_DAY) {
            set_old_input($_POST);
            flash('error', 'You have posted several opportunities today already. Please try again tomorrow.');
            redirect_to('/opportunities/new');
        }

        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        [$clean, $problems] = self::cleanOpportunity($_POST, $uganda);
        if (!empty($problems)) {
            set_old_input($_POST);
            flash('error', 'Please check: ' . implode(' ', $problems));
            redirect_to('/opportunities/new');
        }

        RateLimiter::hit('opportunity', $ip);
        $id = OpportunityModel::create((int) $user['id'], $clean);

        clear_old_input();
        flash('success', 'Your opportunity is live. Providers can now send you proposals; you will get an email for each one.');
        redirect_to('/opportunities/' . $id);
    }

    // ---------------------------------------------------------------------------------------
    // Responding (a provider)
    // ---------------------------------------------------------------------------------------

    public function respond(array $params): void
    {
        $user = require_auth();
        verify_csrf();

        $opp = $this->findOpportunity($params);
        if ($opp === null || !OpportunityModel::isOpen($opp) || !empty($opp['poster_suspended_at'])) {
            flash('error', 'That opportunity is no longer taking proposals.');
            redirect_to('/opportunities');
        }
        $back = '/opportunities/' . (int) $opp['id'];

        if (!empty($_POST['proposal_hp'])) {
            redirect_to($back);
        }

        if ((int) $user['id'] === (int) $opp['user_id']) {
            flash('error', 'You cannot send a proposal to your own opportunity.');
            redirect_to($back);
        }

        $provider = ProviderModel::findByUserId((int) $user['id']);
        if ($provider === null) {
            flash('warning', 'Activate a showcase profile first, so the person who posted this can see who you are.');
            redirect_to('/dashboard');
        }
        if ($provider['status'] !== 'active') {
            flash('error', 'Your listing is hidden right now, so you cannot send proposals. Check your email for the reason.');
            redirect_to($back);
        }

        $ip = client_ip();
        if (RateLimiter::tooMany('proposal', $ip, 30, 3600) || RateLimiter::tooMany('proposal_user', (string) $user['id'], 20, 86400)) {
            set_old_input($_POST);
            flash('error', 'You have sent a lot of proposals in a short time. Please wait a little while before sending another.');
            redirect_to($back);
        }

        $message  = trim((string) ($_POST['message'] ?? ''));
        $quote    = (int) preg_replace('/\D+/', '', (string) ($_POST['quote_ugx'] ?? '0'));
        $timeline = trim((string) ($_POST['timeline'] ?? ''));

        $problems = [];
        if (text_length($message) < 20 || text_length($message) > 2000) {
            $problems[] = 'Write a message of 20 to 2,000 characters about how you would do this.';
        }
        if ($quote > 1000000000) {
            $problems[] = 'Enter a price up to UGX 1,000,000,000, or leave it empty.';
        }
        if (text_length($timeline) > 80) {
            $problems[] = 'Keep the timeline to 80 characters or fewer.';
        }
        if (!empty($problems)) {
            set_old_input($_POST);
            flash('error', 'Please check: ' . implode(' ', $problems));
            redirect_to($back);
        }

        $data       = ['message' => $message, 'quote_ugx' => $quote > 0 ? $quote : null, 'timeline' => $timeline];
        $responseId = OpportunityModel::addResponse((int) $opp['id'], (int) $provider['id'], $data);
        if ($responseId === null) {
            flash('error', 'You have already sent a proposal for this opportunity. You can see it in your dashboard.');
            redirect_to($back);
        }

        RateLimiter::hit('proposal', $ip);
        RateLimiter::hit('proposal_user', (string) $user['id']);
        Notifier::newProposal($opp, $provider, $data, (string) $user['email']);

        clear_old_input();
        flash('success', 'Your proposal was sent to the person who posted this. If they are interested they will contact you using your listing details.');
        redirect_to($back);
    }

    // ---------------------------------------------------------------------------------------
    // The dashboard side: what I posted, what I answered
    // ---------------------------------------------------------------------------------------

    public function mine(): void
    {
        $user     = require_auth();
        $provider = ProviderModel::findByUserId((int) $user['id']);

        $tab = (string) ($_GET['tab'] ?? 'posted');
        if (!in_array($tab, ['posted', 'proposals'], true) || ($tab === 'proposals' && $provider === null)) {
            $tab = 'posted';
        }

        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        View::render('dashboard/opportunities', [
            'pageTitle'    => 'My opportunities — Kopii',
            'currentRoute' => '/dashboard/opportunities',
            'tab'          => $tab,
            'provider'     => $provider,
            'posted'       => OpportunityModel::forUser((int) $user['id']),
            'proposals'    => $provider !== null ? OpportunityModel::responsesByProvider((int) $provider['id']) : [],
            'newProposals' => OpportunityModel::newResponseCount((int) $user['id']),
            'kinds'        => $uganda['opportunity_kinds'],
        ]);
    }

    public function manage(array $params): void
    {
        $user = require_auth();
        $opp  = $this->findOpportunity($params);

        if ($opp === null || (int) $opp['user_id'] !== (int) $user['id']) {
            http_response_code(404);
            View::render('pages/404', ['pageTitle' => 'Opportunity not found — Kopii']);
            return;
        }

        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        View::render('dashboard/opportunity', [
            'pageTitle'    => $opp['title'] . ' — Proposals | Kopii',
            'currentRoute' => '/dashboard/opportunities',
            'opp'          => $opp,
            'isOpen'       => OpportunityModel::isOpen($opp),
            'responses'    => OpportunityModel::responses((int) $opp['id']),
            'kinds'        => $uganda['opportunity_kinds'],
        ]);
    }

    /** Close, mark filled, or re-open one of your own posts. */
    public function setStatus(array $params): void
    {
        $user = require_auth();
        verify_csrf();

        $opp = $this->findOpportunity($params);
        if ($opp === null || (int) $opp['user_id'] !== (int) $user['id']) {
            flash('error', 'That opportunity no longer exists.');
            redirect_to('/dashboard/opportunities');
        }

        $id      = (int) $opp['id'];
        $action  = (string) ($_POST['action'] ?? '');
        $target  = match ($action) {
            'close'  => OpportunityModel::CLOSED,
            'filled' => OpportunityModel::FILLED,
            'reopen' => OpportunityModel::OPEN,
            default  => '',
        };

        if ($opp['status'] === OpportunityModel::SUSPENDED) {
            flash('error', 'The Kopii team has hidden this opportunity, so you cannot change it. Check your email for the reason.');
        } elseif ($target === '') {
            flash('error', 'That action is not recognised.');
        } elseif (OpportunityModel::setPosterStatus($id, (int) $user['id'], $target)) {
            flash('success', match ($target) {
                OpportunityModel::CLOSED => 'Closed. It is off the public board and no longer takes proposals.',
                OpportunityModel::FILLED => 'Marked as filled. It is off the public board.',
                default                  => 'Re-opened. It is back on the public board.',
            });
        }

        redirect_to('/dashboard/opportunities/' . $id);
    }

    /** Shortlist, decline or choose one proposal on one of your posts. */
    public function responseStatus(): void
    {
        $user = require_auth();
        verify_csrf();

        $responseId = (int) ($_POST['response_id'] ?? 0);
        $status     = (string) ($_POST['status'] ?? '');
        $response   = $responseId > 0 ? OpportunityModel::findResponseForPoster($responseId, (int) $user['id']) : null;

        if ($response === null || !in_array($status, OpportunityModel::RESPONSE_STATUSES, true)) {
            flash('error', 'That proposal no longer exists.');
            redirect_to('/dashboard/opportunities');
        }

        if ($response['status'] !== $status) {
            OpportunityModel::setResponseStatus($responseId, $status);
            Notifier::proposalUpdated($response, $status);
        }

        flash('success', match ($status) {
            OpportunityModel::SHORTLISTED => $response['business_name'] . ' is on your shortlist, and they have been told.',
            OpportunityModel::HIRED       => 'You chose ' . $response['business_name'] . ', and they have been told. Contact them from their details below, then mark the opportunity as filled.',
            OpportunityModel::DECLINED    => 'Proposal declined.',
            default                       => 'Proposal moved back to new.',
        });
        redirect_to('/dashboard/opportunities/' . (int) $response['opportunity_id']);
    }

    /** A provider takes back their own proposal (unless they were already chosen). */
    public function withdraw(): void
    {
        $user = require_auth();
        verify_csrf();

        $provider   = ProviderModel::findByUserId((int) $user['id']);
        $responseId = (int) ($_POST['response_id'] ?? 0);

        $mine = null;
        if ($provider !== null) {
            foreach (OpportunityModel::responsesByProvider((int) $provider['id']) as $row) {
                if ((int) $row['id'] === $responseId) {
                    $mine = $row;
                    break;
                }
            }
        }

        if ($mine === null) {
            flash('error', 'That proposal no longer exists.');
        } elseif ($mine['status'] === OpportunityModel::HIRED) {
            flash('error', 'You were chosen for this one, so it cannot be withdrawn here. Contact the person who posted it.');
        } else {
            OpportunityModel::withdrawResponse($responseId, (int) $provider['id']);
            flash('success', 'Proposal withdrawn.');
        }

        redirect_to('/dashboard/opportunities', ['tab' => 'proposals']);
    }

    // ---------------------------------------------------------------------------------------

    private function findOpportunity(array $params): ?array
    {
        $raw = (string) ($params['id'] ?? '');
        return ctype_digit($raw) && $raw !== '' && strlen($raw) < 10 ? OpportunityModel::find((int) $raw) : null;
    }

    /**
     * @return array{0: array, 1: list<string>} the cleaned values and a list of problems written for the visitor
     */
    private static function cleanOpportunity(array $post, array $uganda): array
    {
        $digits = static fn(string $key): int => (int) preg_replace('/\D+/', '', (string) ($post[$key] ?? ''));

        $c = [
            'kind'           => trim((string) ($post['kind'] ?? '')),
            'title'          => trim((string) ($post['title'] ?? '')),
            'description'    => trim((string) ($post['description'] ?? '')),
            'category_id'    => (int) ($post['category_id'] ?? 0),
            'city'           => trim((string) ($post['city'] ?? '')),
            'budget_min_ugx' => $digits('budget_min_ugx'),
            'budget_max_ugx' => $digits('budget_max_ugx'),
            'closes_on'      => trim((string) ($post['closes_on'] ?? '')),
        ];

        $problems = [];
        if (!in_array($c['kind'], OpportunityModel::KINDS, true)) {
            $problems[] = 'Choose what kind of opportunity this is.';
        }
        if (text_length($c['title']) < 8 || text_length($c['title']) > 160) {
            $problems[] = 'Give it a clear title (8–160 characters).';
        }
        if (text_length($c['description']) < 30 || text_length($c['description']) > 4000) {
            $problems[] = 'Describe what you need in 30 to 4,000 characters.';
        }
        if ($c['category_id'] > 0 && CategoryModel::findById($c['category_id']) === null) {
            $problems[] = 'Choose a category from the list, or leave it as "Any".';
        }
        if ($c['category_id'] <= 0) {
            $c['category_id'] = null;
        }
        if ($c['city'] !== self::ANYWHERE && !in_array($c['city'], $uganda['cities'], true)) {
            $problems[] = 'Choose where the work is from the list.';
        }
        if ($c['budget_min_ugx'] > 1000000000 || $c['budget_max_ugx'] > 1000000000) {
            $problems[] = 'Budgets can be up to UGX 1,000,000,000.';
        } elseif ($c['budget_min_ugx'] > 0 && $c['budget_max_ugx'] > 0 && $c['budget_min_ugx'] > $c['budget_max_ugx']) {
            $problems[] = 'The lowest budget cannot be higher than the highest.';
        }

        if ($c['closes_on'] === '') {
            $c['closes_on'] = date('Y-m-d', strtotime('+' . OpportunityModel::DEFAULT_DAYS . ' days'));
        } else {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $c['closes_on']);
            if ($date === false || $date->format('Y-m-d') !== $c['closes_on']) {
                $problems[] = 'Choose a valid closing date.';
            } elseif ($c['closes_on'] < date('Y-m-d') || $c['closes_on'] > date('Y-m-d', strtotime('+' . OpportunityModel::MAX_DAYS . ' days'))) {
                $problems[] = 'The closing date must be between today and ' . OpportunityModel::MAX_DAYS . ' days from now.';
            }
        }

        return [$c, $problems];
    }

    private function text(string $key, int $max = 80): string
    {
        $value = trim((string) ($_GET[$key] ?? ''));
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    private function choice(string $key, array $allowed): string
    {
        $value = (string) ($_GET[$key] ?? '');
        return in_array($value, $allowed, true) ? $value : '';
    }
}
