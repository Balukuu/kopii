<?php
defined('KOPII_APP') or exit('Direct access denied.');

class MarketplaceController
{
    public function home(): void
    {
        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        $categories = CategoryModel::allWithCounts();
        $featuredProviders = ProviderModel::search(['sort' => 'featured'], 6);
        $showcaseWork = ProviderModel::portfolioShowcaseFeed([], 6);
        $stats = ProviderModel::marketplaceStats();

        // Build the category tabs for the home page (All + the busiest categories)
        $verticalTabs = [
            [
                'id'        => 'all-talent',
                'label'     => 'All Talent',
                'slug'      => '',
                'icon'      => 'sparkles',
                'providers' => $featuredProviders,
            ],
        ];
        foreach ($categories as $cat) {
            if ((int) ($cat['provider_count'] ?? 0) > 0 && count($verticalTabs) < 6) {
                $verticalTabs[] = [
                    'id'        => 'cat-' . $cat['slug'],
                    'label'     => $cat['name'],
                    'slug'      => $cat['slug'],
                    'icon'      => $cat['icon'],
                    'providers' => ProviderModel::search(['category' => $cat['slug'], 'sort' => 'featured'], 6),
                ];
            }
        }

        View::render('pages/home', [
            'pageTitle'         => 'Kopii — Hire Photographers, Creatives & Businesses in Uganda',
            'metaDescription'   => 'Find a real, verified provider in Uganda for the work you need: repairs, solar, cleaning, events, design, photography and more. See their past work and UGX prices, then talk on WhatsApp.',
            'currentRoute'      => '/',
            'cities'            => $uganda['cities'],
            'categories'        => $categories,
            'featuredProviders' => $featuredProviders,
            'heroSpotlight'     => array_slice($featuredProviders, 0, 5),
            'verticalTabs'      => $verticalTabs,
            'showcaseWork'      => $showcaseWork,
            'stats'             => $stats,
            'latestProducts'      => ProductModel::latest(4),
            'latestOpportunities' => OpportunityModel::latest(3),
            'opportunityKinds'    => $uganda['opportunity_kinds'],
        ]);
    }

    public function hireWizard(): void
    {
        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        $categories = CategoryModel::allWithCounts();

        $criteria = [
            'category'  => trim((string) ($_GET['category'] ?? '')),
            'city'      => trim((string) ($_GET['city'] ?? '')),
            'type'      => trim((string) ($_GET['type'] ?? '')),
            'timeline'  => trim((string) ($_GET['timeline'] ?? 'Within 2 weeks')),
            'max_price' => (int) ($_GET['max_price'] ?? 0),
            'q'         => trim((string) ($_GET['q'] ?? '')),
        ];

        $isMatched = !empty($_GET['matched']) || $criteria['category'] !== '' || $criteria['q'] !== '';
        $matches   = ProviderModel::matchTalent($criteria, 6);
        $selectedCat = $criteria['category'] !== '' ? CategoryModel::findBySlug($criteria['category']) : null;

        View::render('pages/hire', [
            'pageTitle'    => 'Get matched with providers in Uganda | Kopii',
            'metaDescription' => 'Tell Kopii what you are building or planning in Uganda and get matched with photographers, creatives, and local businesses.',
            'currentRoute' => '/hire',
            'categories'   => $categories,
            'cities'       => $uganda['cities'],
            'criteria'     => $criteria,
            'selectedCat'  => $selectedCat,
            'isMatched'    => $isMatched,
            'matches'      => $matches,
        ]);
    }

    /**
     * Validate and tidy the contact fields shared by the quote form, the hire brief and the product enquiry.
     *
     * @return array{0: array, 1: list<string>} the cleaned values and a list of problems
     */
    public static function cleanLead(array $post): array
    {
        $clean = [
            'name'       => trim((string) ($post['client_name'] ?? '')),
            'email'      => strtolower(trim((string) ($post['client_email'] ?? ''))),
            'phone'      => trim((string) ($post['client_phone'] ?? '')),
            'details'    => trim((string) ($post['project_details'] ?? '')),
            'event_date' => trim((string) ($post['event_date'] ?? '')),
            'location'   => trim((string) ($post['event_location'] ?? '')),
            'budget'     => !empty($post['budget_ugx'])
                ? min((int) preg_replace('/\D+/', '', (string) $post['budget_ugx']), 1000000000)
                : null,
        ];

        $problems = [];
        if (text_length($clean['name']) < 2 || text_length($clean['name']) > 100) {
            $problems[] = 'your name';
        }
        // Optional: most people here reach each other by phone or WhatsApp. If one is given it must be real.
        if ($clean['email'] !== '' && (!filter_var($clean['email'], FILTER_VALIDATE_EMAIL) || strlen($clean['email']) > 190)) {
            $problems[] = 'a valid email address, or leave it blank';
        }
        $digits = phone_digit_count($clean['phone']);
        if ($digits < 9 || $digits > 15) {
            $problems[] = 'a valid phone / WhatsApp number';
        }
        if (text_length($clean['details']) < 8) {
            $problems[] = 'a short description of what you need';
        } elseif (text_length($clean['details']) > 3000) {
            $problems[] = 'a shorter description (3,000 characters at most)';
        }
        if (text_length($clean['event_date']) > 40 || text_length($clean['location']) > 150) {
            $problems[] = 'a shorter date and location';
        }

        return [$clean, $problems];
    }

    public function submitHireBrief(): void
    {
        verify_csrf();

        if (!empty($_POST['brief_confirm_hp'])) {
            redirect_to('/hire');
        }

        $backTo = [
            'matched'   => '1',
            'category'  => trim((string) ($_POST['category'] ?? '')),
            'city'      => trim((string) ($_POST['city'] ?? '')),
            'max_price' => (int) ($_POST['max_price'] ?? 0),
        ];

        // One brief goes to up to five providers, so it is limited more tightly than a single request.
        $ip = client_ip();
        if (RateLimiter::tooMany('brief', $ip, 10, 3600)) {
            set_old_input($_POST);
            flash('error', 'You have sent a lot of briefs in a short time. Please wait a little while before sending another.');
            redirect_to('/hire', $backTo);
        }

        $rawIds      = $_POST['provider_ids'] ?? [];
        $providerIds = is_array($rawIds)
            ? ProviderModel::activeIdsFrom(array_slice(array_values($rawIds), 0, 20))
            : [];
        $providerIds = array_slice($providerIds, 0, 5);
        $timeline    = trim((string) ($_POST['timeline'] ?? ''));

        [$clean, $problems] = self::cleanLead($_POST);
        if (empty($providerIds)) {
            array_unshift($problems, 'at least one available provider');
        }
        if (text_length($timeline) > 60) {
            $problems[] = 'a shorter timeline';
        }

        if (!empty($problems)) {
            set_old_input($_POST);
            flash('error', 'Please check: ' . implode('; ', $problems) . '.');
            redirect_to('/hire', $backTo);
        }

        RateLimiter::hit('brief', $ip);

        $fullDetails = ($timeline !== '' ? "[Timeline: {$timeline}] " : '') . $clean['details'];
        $sentCount = 0;

        foreach ($providerIds as $pid) {
            $lead = [
                'provider_id'     => $pid,
                'client_user_id'  => current_user()['id'] ?? null,
                'package_id'      => null,
                'client_name'     => $clean['name'],
                'client_email'    => $clean['email'],
                'client_phone'    => $clean['phone'],
                'event_date'      => $clean['event_date'],
                'event_location'  => $clean['location'],
                'budget_ugx'      => $clean['budget'],
                'project_details' => $fullDetails,
            ];
            InquiryModel::create($lead);
            Notifier::newLead($pid, $lead);
            $sentCount++;
        }

        clear_old_input();
        flash(
            'success',
            'Your project brief has been dispatched directly to ' . $sentCount . ' matched Kopii ' . ($sentCount === 1 ? 'specialist' : 'specialists') . '! Expect a direct response via phone, WhatsApp, or email.'
        );
        redirect_to('/explore', [
            'category' => trim((string) ($_POST['category'] ?? '')),
            'city'     => trim((string) ($_POST['city'] ?? '')),
        ]);
    }

    public function explore(): void
    {
        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        $categories = CategoryModel::allWithCounts();

        $tab = ($_GET['tab'] ?? 'providers') === 'work' ? 'work' : 'providers';
        $shortlistRaw = trim((string) ($_GET['shortlist'] ?? ''));
        $shortlistIds = [];
        if ($shortlistRaw !== '') {
            $shortlistIds = array_values(array_filter(array_map('intval', explode(',', $shortlistRaw)), static fn($v) => $v > 0));
        }

        $filters = [
            'q'         => trim((string) ($_GET['q'] ?? '')),
            'category'  => trim((string) ($_GET['category'] ?? '')),
            'city'      => trim((string) ($_GET['city'] ?? '')),
            'area'      => function_exists('mb_substr') ? mb_substr(trim((string) ($_GET['area'] ?? '')), 0, 60, 'UTF-8') : substr(trim((string) ($_GET['area'] ?? '')), 0, 60),
            'type'      => trim((string) ($_GET['type'] ?? '')),
            'max_price' => (int) ($_GET['max_price'] ?? 0),
            'verified'  => !empty($_GET['verified']) ? 1 : 0,
            'sort'      => trim((string) ($_GET['sort'] ?? 'featured')),
        ];
        if (!empty($shortlistIds)) {
            $filters['ids'] = $shortlistIds;
        }

        $activeCategory = $filters['category'] !== '' ? CategoryModel::findBySlug($filters['category']) : null;
        $providers = ProviderModel::search($filters, 36);
        $totalProviders = count($shortlistIds) > 0 ? count($providers) : ProviderModel::countSearch($filters);
        $workItems = ProviderModel::portfolioShowcaseFeed($filters, 30);

        $heading = 'Explore Talent & Businesses Across Uganda';
        if (!empty($shortlistIds)) {
            $heading = 'Comparing Your Shortlisted Providers (' . count($providers) . ')';
        } elseif ($activeCategory) {
            $heading = $activeCategory['name'] . ($filters['city'] !== '' ? ' in ' . $filters['city'] : ' in Uganda');
        } elseif ($filters['area'] !== '') {
            $heading = 'Providers near ' . $filters['area'] . ($filters['city'] !== '' ? ', ' . $filters['city'] : '');
        } elseif ($filters['city'] !== '') {
            $heading = 'Businesses & Freelancers in ' . $filters['city'];
        }

        View::render('pages/explore', [
            'pageTitle'      => $heading . ' — Kopii Marketplace',
            'currentRoute'   => '/explore',
            'heading'        => $heading,
            'activeCategory' => $activeCategory,
            'tab'            => $tab,
            'filters'        => $filters,
            'shortlistMode'  => !empty($shortlistIds),
            'cities'         => $uganda['cities'],
            'areaSuggestions' => $uganda['kampala_areas'],
            'categories'     => $categories,
            'providers'      => $providers,
            'totalProviders' => $totalProviders,
            'workItems'      => $workItems,
        ]);
    }

    public function showProvider(array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $provider = ProviderModel::findBySlug($slug);
        if (!$provider || ($provider['status'] !== 'active' && (current_user()['id'] ?? 0) !== (int) $provider['user_id'] && (current_user()['role'] ?? '') !== 'admin')) {
            http_response_code(404);
            View::render('pages/404', ['pageTitle' => 'Provider Not Found — Kopii']);
            return;
        }

        ProviderModel::incrementViews((int) $provider['id']);

        $related = array_values(array_filter(
            ProviderModel::search(['category' => $provider['category_slug']], 4),
            static fn($p) => (int) $p['id'] !== (int) $provider['id']
        ));

        View::render('pages/provider', [
            'pageTitle'       => $provider['business_name'] . ' (' . $provider['city'] . ') — ' . $provider['category_name'] . ' | Kopii',
            'metaDescription' => $provider['headline'],
            'ogImage'         => share_image_path($provider['cover_path'] ?: ($provider['avatar_path'] ?: ($provider['portfolio'][0]['image_path'] ?? null))),
            'noindex'         => $provider['status'] !== 'active',
            'currentRoute'    => '/provider',
            'provider'        => $provider,
            'related'         => array_slice($related, 0, 3),
            'products'        => $provider['status'] === 'active' ? ProductModel::forProvider((int) $provider['id'], true) : [],
        ]);
    }

    public function submitInquiry(array $params): void
    {
        verify_csrf();
        $slug = (string) ($params['slug'] ?? '');
        $provider = ProviderModel::findBySlug($slug);
        if (!$provider || $provider['status'] !== 'active') {
            flash('error', 'That provider is not accepting requests right now.');
            redirect_to('/explore');
        }

        // Honeypot spam protection
        if (!empty($_POST['website_confirm_hp'])) {
            redirect_to('/provider/' . $slug);
        }

        $providerId = (int) $provider['id'];
        $ip         = client_ip();
        if (RateLimiter::tooMany('inquiry', $ip, 20, 3600) || RateLimiter::tooMany('inquiry_provider', $ip . '|' . $providerId, 5, 3600)) {
            set_old_input($_POST);
            flash('error', 'You have sent a lot of requests in a short time. Please wait a little while before sending another.');
            redirect_to('/provider/' . $slug);
        }

        [$clean, $problems] = self::cleanLead($_POST);
        if (!empty($problems)) {
            set_old_input($_POST);
            flash('error', 'Please check: ' . implode('; ', $problems) . '.');
            redirect_to('/provider/' . $slug);
        }

        // A package can only be attached if it really belongs to this provider.
        $packageId = null;
        $wanted    = (int) ($_POST['package_id'] ?? 0);
        foreach ($provider['packages'] ?? [] as $package) {
            if ($wanted > 0 && (int) $package['id'] === $wanted) {
                $packageId = $wanted;
                break;
            }
        }

        RateLimiter::hit('inquiry', $ip);
        RateLimiter::hit('inquiry_provider', $ip . '|' . $providerId);

        $lead = [
            'provider_id'     => $providerId,
            'client_user_id'  => current_user()['id'] ?? null,
            'package_id'      => $packageId,
            'client_name'     => $clean['name'],
            'client_email'    => $clean['email'],
            'client_phone'    => $clean['phone'],
            'event_date'      => $clean['event_date'],
            'event_location'  => $clean['location'],
            'budget_ugx'      => $clean['budget'],
            'project_details' => $clean['details'],
        ];
        InquiryModel::create($lead);
        Notifier::newLead($providerId, $lead);

        flash('success', 'Your quote inquiry has been delivered directly to ' . $provider['business_name'] . '! You can also tap "Chat on WhatsApp" for an instant conversation.');
        redirect_to('/provider/' . $slug, ['inquired' => '1']);
    }

    public function submitReview(array $params): void
    {
        verify_csrf();
        $slug = (string) ($params['slug'] ?? '');
        $provider = ProviderModel::findBySlug($slug);
        if (!$provider || $provider['status'] !== 'active') {
            flash('error', 'That provider is not accepting reviews right now.');
            redirect_to('/explore');
        }

        if (!empty($_POST['review_hp_field'])) {
            redirect_to('/provider/' . $slug);
        }

        $providerId = (int) $provider['id'];
        $ip         = client_ip();
        if (RateLimiter::tooMany('review', $ip, 10, 3600) || RateLimiter::tooMany('review_provider', $ip . '|' . $providerId, 2, 86400)) {
            flash('error', 'You have already sent a review for this provider recently. Please try again later.');
            redirect_to('/provider/' . $slug);
        }

        $user = current_user();
        if ($user && (int) $user['id'] === (int) $provider['user_id']) {
            flash('error', 'You cannot review your own listing.');
            redirect_to('/provider/' . $slug);
        }

        $name    = trim((string) ($_POST['reviewer_name'] ?? ''));
        $rating  = (int) ($_POST['rating'] ?? 0);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $city    = trim((string) ($_POST['reviewer_city'] ?? ''));
        $service = trim((string) ($_POST['service_hired'] ?? ''));

        if (text_length($name) < 2 || text_length($name) > 80 || $rating < 1 || $rating > 5
            || text_length($comment) < 10 || text_length($comment) > 1500
            || text_length($city) > 80 || text_length($service) > 120) {
            flash('error', 'Please enter your name, choose a star rating (1–5), and write between 10 and 1,500 characters about your experience.');
            redirect_to('/provider/' . $slug);
        }

        if ($user && ReviewModel::existsFromUser($providerId, (int) $user['id'])) {
            flash('error', 'You have already reviewed this provider.');
            redirect_to('/provider/' . $slug);
        }

        // A review from someone who really asked this provider for a quote through Kopii goes live
        // straight away. Everything else waits for an administrator, so ratings (which drive the
        // ranking) cannot be bought, faked, or pumped by the provider's friends.
        $verifiedRequest = false;
        if ($user) {
            $account         = UserModel::findById((int) $user['id']);
            $emailConfirmed  = !empty($account['email_verified_at']);
            $verifiedRequest = InquiryModel::userHasContacted($providerId, (int) $user['id'], (string) $user['email'], $emailConfirmed);
        }

        ReviewModel::create([
            'provider_id'      => $providerId,
            'reviewer_user_id' => $user['id'] ?? null,
            'reviewer_name'    => $name,
            'reviewer_city'    => $city,
            'service_hired'    => $service,
            'rating'           => $rating,
            'comment'          => $comment,
            'status'           => $verifiedRequest ? ReviewModel::PUBLISHED : ReviewModel::PENDING,
            'verified_request' => $verifiedRequest,
        ]);

        RateLimiter::hit('review', $ip);
        RateLimiter::hit('review_provider', $ip . '|' . $providerId);

        // A review that is not public yet needs an administrator: say so by email, so it does not wait unseen.
        if (!$verifiedRequest) {
            Notifier::reviewWaiting((string) $provider['business_name'], [
                'reviewer_name' => $name,
                'rating'        => $rating,
                'comment'       => $comment,
            ]);
        }

        flash('success', $verifiedRequest
            ? 'Thank you! Your review is now live on ' . $provider['business_name'] . '\'s showcase.'
            : 'Thank you! Your review will appear on ' . $provider['business_name'] . '\'s showcase once the Kopii team has checked it.');
        redirect_to('/provider/' . $slug);
    }

    public function about(): void
    {
        $stats = ProviderModel::marketplaceStats();
        View::render('pages/about', [
            'pageTitle'    => 'About Kopii — Where Customers Meet Businesses in Uganda',
            'currentRoute' => '/about',
            'stats'        => $stats,
        ]);
    }
}
