<?php
defined('KOPII_APP') or exit('Direct access denied.');

class DashboardController
{
    /** Upper bounds that keep one account from filling the disk or the page. */
    private const MAX_PORTFOLIO_ITEMS = 60;
    private const MAX_PACKAGES        = 12;

    public function index(): void
    {
        $user = require_auth();
        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        $categories = CategoryModel::allWithCounts();

        $provider = ProviderModel::findByUserId((int) $user['id']);

        if (!$provider) {
            // Requests are matched to this account by email address only once that address is confirmed.
            $account       = UserModel::findById((int) $user['id']);
            $emailVerified = !empty($account['email_verified_at']);
            $sentInquiries = InquiryModel::forClient((int) $user['id'], (string) $user['email'], $emailVerified);
            View::render('dashboard/client', [
                'pageTitle'     => 'My Account — Kopii',
                'currentRoute'  => '/dashboard',
                'user'          => $user,
                'emailVerified' => $emailVerified,
                'sentInquiries' => $sentInquiries,
                'categories'    => $categories,
                'cities'        => $uganda['cities'],
                'myOpportunities' => count(OpportunityModel::forUser((int) $user['id'])),
                'newProposals'    => OpportunityModel::newResponseCount((int) $user['id']),
            ]);
            return;
        }

        $tab = (string) ($_GET['tab'] ?? 'overview');
        if (!in_array($tab, ['overview', 'inquiries', 'portfolio', 'packages', 'products', 'profile'], true)) {
            $tab = 'overview';
        }

        $statusFilter  = isset($_GET['status']) ? (string) $_GET['status'] : null;
        $portfolio     = ProviderModel::getPortfolio((int) $provider['id']);
        $packages      = ProviderModel::getPackages((int) $provider['id']);
        $inquiries     = InquiryModel::forProvider((int) $provider['id'], $statusFilter);
        $inquiryCounts = InquiryModel::countsForProvider((int) $provider['id']);
        $products      = ProductModel::forProvider((int) $provider['id']);

        // "Edit" opens the form on the Products tab filled in with that product.
        $editing = null;
        if ($tab === 'products' && ctype_digit((string) ($_GET['edit'] ?? ''))) {
            $editing = ProductModel::findOwned((int) $_GET['edit'], (int) $provider['id']);
        }

        // "Edit" on a package or a portfolio item opens the side form filled in with it.
        $editingPackage = null;
        if ($tab === 'packages' && ctype_digit((string) ($_GET['edit'] ?? ''))) {
            $editingPackage = ProviderModel::findPackage((int) $_GET['edit'], (int) $provider['id']);
        }
        $editingItem = null;
        if ($tab === 'portfolio' && ctype_digit((string) ($_GET['edit'] ?? ''))) {
            $editingItem = ProviderModel::findPortfolioItem((int) $_GET['edit'], (int) $provider['id']);
        }

        // Compute profile completeness score
        $checks = [
            'Bio & Headline'    => strlen((string) $provider['bio']) >= 40,
            'Portfolio Work'    => count($portfolio) >= 1,
            '3+ Portfolio Shots'=> count($portfolio) >= 3,
            'Service Packages'  => count($packages) >= 1,
            'Cover Banner'      => !empty($provider['cover_path']),
        ];
        $completedCount = count(array_filter($checks));
        $completeness   = (int) round(($completedCount / count($checks)) * 100);

        View::render('dashboard/index', [
            'pageTitle'     => 'Studio Dashboard — ' . $provider['business_name'] . ' | Kopii',
            'currentRoute'  => '/dashboard',
            'tab'           => $tab,
            'statusFilter'  => $statusFilter,
            'provider'      => $provider,
            'portfolio'     => $portfolio,
            'packages'      => $packages,
            'inquiries'     => $inquiries,
            'inquiryCounts' => $inquiryCounts,
            'completeness'  => $completeness,
            'checks'        => $checks,
            'categories'    => $categories,
            'cities'        => $uganda['cities'],
            'responseTimes' => $uganda['response_times'],
            'priceUnits'    => $uganda['price_units'],
            'products'      => $products,
            'editing'       => $editing,
            'editingPackage' => $editingPackage,
            'editingItem'   => $editingItem,
            'productCategories' => ProductModel::categories(),
            'productUnits'  => $uganda['product_units'],
            'productConditions' => $uganda['product_conditions'],
            'stockStatuses' => $uganda['stock_statuses'],
            'maxImages'     => ProductModel::MAX_IMAGES,
            'plan'          => PlanModel::effective($provider),
            'productLimit'  => PlanModel::productLimit($provider),
            'featuredNow'   => ProviderModel::isFeaturedNow($provider),
            'newProposals'  => OpportunityModel::newResponseCount((int) $user['id']),
        ]);
    }

    /**
     * Validate and tidy the provider profile fields shared by "activate showcase" and "edit profile".
     * Every value is length-limited and checked against the real option lists.
     *
     * @param array $post     the submitted form
     * @param array $current  the provider's existing values (used when a field is not on the form)
     * @return array{0: array, 1: list<string>} cleaned values and a list of problems written for the visitor
     */
    private static function cleanProfile(array $post, array $current, array $uganda): array
    {
        $c = [
            'category_id'        => (int) ($post['category_id'] ?? ($current['category_id'] ?? 0)),
            'business_name'      => trim((string) ($post['business_name'] ?? ($current['business_name'] ?? ''))),
            'provider_type'      => ($post['provider_type'] ?? 'freelancer') === 'business' ? 'business' : 'freelancer',
            'headline'           => trim((string) ($post['headline'] ?? ($current['headline'] ?? ''))),
            'bio'                => trim((string) ($post['bio'] ?? ($current['bio'] ?? ''))),
            'city'               => trim((string) ($post['city'] ?? ($current['city'] ?? 'Kampala'))),
            'neighborhood'       => trim((string) ($post['neighborhood'] ?? ($current['neighborhood'] ?? ''))),
            'service_areas'      => trim((string) ($post['service_areas'] ?? ($current['service_areas'] ?? ''))),
            'phone'              => trim((string) ($post['phone'] ?? ($current['phone'] ?? ''))),
            'whatsapp'           => trim((string) ($post['whatsapp'] ?? ($current['whatsapp'] ?? ''))),
            'public_email'       => trim((string) ($post['public_email'] ?? ($current['public_email'] ?? ''))),
            'instagram'          => trim((string) ($post['instagram'] ?? ($current['instagram'] ?? ''))),
            'starting_price_ugx' => (int) preg_replace('/\D+/', '', (string) ($post['starting_price_ugx'] ?? ($current['starting_price_ugx'] ?? '0'))),
            'years_experience'   => (int) ($post['years_experience'] ?? ($current['years_experience'] ?? 1)),
            'response_time'      => trim((string) ($post['response_time'] ?? ($current['response_time'] ?? 'Within 2 hours'))),
            'specialties'        => trim((string) ($post['specialties'] ?? ($current['specialties'] ?? ''))),
            'is_available'       => !empty($post['is_available']) ? 1 : 0,
        ];

        $problems = [];

        if (text_length($c['business_name']) < 2 || text_length($c['business_name']) > 120) {
            $problems[] = 'Enter your business or freelance name (2–120 characters).';
        }
        if ($c['category_id'] <= 0 || !CategoryModel::findById($c['category_id'])) {
            $problems[] = 'Choose a category from the list.';
        }
        if (!in_array($c['city'], $uganda['cities'], true)) {
            $problems[] = 'Choose your city from the list.';
        }
        if (!in_array($c['response_time'], $uganda['response_times'], true)) {
            $problems[] = 'Choose a typical response time from the list.';
        }

        if (phone_digit_count($c['phone']) < 9 || phone_digit_count($c['phone']) > 15) {
            $problems[] = 'Enter a valid phone number.';
        }
        if ($c['whatsapp'] === '') {
            $c['whatsapp'] = $c['phone'];
        } elseif (phone_digit_count($c['whatsapp']) < 9 || phone_digit_count($c['whatsapp']) > 15) {
            $problems[] = 'Enter a valid WhatsApp number, or leave it blank to use your phone number.';
        }

        if ($c['public_email'] !== '' && (!filter_var($c['public_email'], FILTER_VALIDATE_EMAIL) || strlen($c['public_email']) > 190)) {
            $problems[] = 'Enter a valid public email address, or leave it blank.';
        }

        // Instagram: accept "@name", "name" or a profile link; store "@name".
        if ($c['instagram'] !== '') {
            $handle = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $c['instagram']) ?? '';
            $handle = ltrim(trim(explode('?', rtrim($handle, '/'))[0]), '@');
            if (!preg_match('/^[A-Za-z0-9._]{1,30}$/', $handle)) {
                $problems[] = 'Enter your Instagram handle like @yourstudio, or leave it blank.';
            } else {
                $c['instagram'] = '@' . $handle;
            }
        }

        if (text_length($c['headline']) > 160) {
            $problems[] = 'Keep your headline to 160 characters or fewer.';
        }
        if (text_length($c['bio']) > 2000) {
            $problems[] = 'Keep your bio to 2,000 characters or fewer.';
        }
        if (text_length($c['neighborhood']) > 80 || text_length($c['specialties']) > 300) {
            $problems[] = 'Neighborhood (80) and specialties (300) are too long.';
        }

        $areas = normalize_area_list($c['service_areas']);
        if ($areas === null) {
            $problems[] = 'List up to 12 other places you serve, each up to 40 characters, separated by commas.';
        } else {
            $c['service_areas'] = $areas;
        }

        $c['starting_price_ugx'] = min(max($c['starting_price_ugx'], 0), 1000000000);
        $c['years_experience']   = min(max($c['years_experience'], 0), 80);

        return [$c, $problems];
    }

    public function becomeProvider(): void
    {
        $user = require_auth();
        verify_csrf();

        if (ProviderModel::findByUserId((int) $user['id'])) {
            redirect_to('/dashboard');
        }

        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        [$clean, $problems] = self::cleanProfile($_POST, [], $uganda);

        if (!empty($problems)) {
            flash('error', 'Please check: ' . implode(' ', $problems));
            redirect_to('/dashboard');
        }

        $bizName = $clean['business_name'];
        ProviderModel::create([
            'user_id'            => (int) $user['id'],
            'category_id'        => $clean['category_id'],
            'business_name'      => $bizName,
            'provider_type'      => $clean['provider_type'],
            'headline'           => $clean['headline'] !== '' ? $clean['headline'] : ($bizName . ' on Kopii'),
            'bio'                => $clean['bio'] !== '' ? $clean['bio'] : ('Explore work and request quotes from ' . $bizName . '.'),
            'city'               => $clean['city'],
            'phone'              => $clean['phone'],
            'whatsapp'           => $clean['whatsapp'],
            'public_email'       => (string) $user['email'],
            'starting_price_ugx' => $clean['starting_price_ugx'],
        ]);

        $pdo = Database::connection();
        $pdo->prepare("UPDATE users SET role = 'provider' WHERE id = :id AND role = 'client'")
            ->execute([':id' => (int) $user['id']]);
        if ($_SESSION['user']['role'] === 'client') {
            $_SESSION['user']['role'] = 'provider';
        }

        flash('success', 'Your showcase profile has been activated! Add your portfolio work below.');
        redirect_to('/dashboard', ['tab' => 'portfolio']);
    }

    public function updateProfile(): void
    {
        $user = require_auth();
        verify_csrf();
        $provider = ProviderModel::findByUserId((int) $user['id']);
        if (!$provider) {
            redirect_to('/dashboard');
        }

        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        [$clean, $problems] = self::cleanProfile($_POST, $provider, $uganda);

        // Validate everything (including the website) before touching any uploads, so a typo
        // does not leave stray files behind.
        $website = normalize_website((string) ($_POST['website'] ?? ''));
        if ($website === null) {
            $problems[] = 'Enter a full website address such as https://yourstudio.ug, or leave it blank.';
        }

        if (!empty($problems)) {
            flash('error', 'Nothing was saved. Please check: ' . implode(' ', $problems));
            redirect_to('/dashboard', ['tab' => 'profile']);
        }

        $newAvatar = null;
        $newCover  = null;
        try {
            $newAvatar = upload_image($_FILES['avatar_image'] ?? [], 'avatars');
            $newCover  = upload_image($_FILES['cover_image'] ?? [], 'covers');

            ProviderModel::update((int) $provider['id'], $clean + [
                'website'     => $website,
                'avatar_path' => $newAvatar ?? $provider['avatar_path'],
                'cover_path'  => $newCover ?? $provider['cover_path'],
            ]);

            // The profile now points at the new photos; remove the ones they replaced.
            if ($newAvatar !== null) {
                delete_upload($provider['avatar_path'] ?? null);
            }
            if ($newCover !== null) {
                delete_upload($provider['cover_path'] ?? null);
            }

            flash('success', 'Your public showcase profile has been updated.');
        } catch (Throwable $e) {
            // Nothing was saved, so don't keep photos uploaded during this failed attempt.
            delete_upload($newAvatar);
            delete_upload($newCover);
            flash('error', report_exception($e, 'We could not save your changes. Please try again.'));
        }

        redirect_to('/dashboard', ['tab' => 'profile']);
    }

    public function addPortfolio(): void
    {
        $user = require_auth();
        verify_csrf();
        $provider = ProviderModel::findByUserId((int) $user['id']);
        if (!$provider) {
            redirect_to('/dashboard');
        }

        [$clean, $problem] = self::cleanPortfolio($_POST);
        if ($problem !== null) {
            flash('error', $problem);
            redirect_to('/dashboard', ['tab' => 'portfolio']);
        }
        if (count(ProviderModel::getPortfolio((int) $provider['id'])) >= self::MAX_PORTFOLIO_ITEMS) {
            flash('error', 'You have reached the limit of ' . self::MAX_PORTFOLIO_ITEMS . ' portfolio items. Remove one to add another.');
            redirect_to('/dashboard', ['tab' => 'portfolio']);
        }

        try {
            $imgPath = upload_image($_FILES['portfolio_image'] ?? [], 'portfolios');
            if (!$imgPath) {
                throw new UserFacingException('Please choose a photo (JPG, PNG, or WebP) to showcase your work.');
            }

            ProviderModel::addPortfolioItem((int) $provider['id'], $clean + ['image_path' => $imgPath]);

            flash('success', 'Work sample added to your public portfolio!');
        } catch (Throwable $e) {
            // The row was not saved, so don't keep an orphaned file.
            delete_upload($imgPath ?? null);
            flash('error', report_exception($e, 'We could not add that work sample. Please try again.'));
        }

        redirect_to('/dashboard', ['tab' => 'portfolio']);
    }

    public function deletePortfolio(): void
    {
        $user = require_auth();
        verify_csrf();
        $provider = ProviderModel::findByUserId((int) $user['id']);
        if ($provider && !empty($_POST['item_id'])) {
            $item = ProviderModel::findPortfolioItem((int) $_POST['item_id'], (int) $provider['id']);
            if ($item && ProviderModel::deletePortfolioItem((int) $item['id'], (int) $provider['id'])) {
                delete_upload((string) $item['image_path']);
                flash('success', 'Portfolio item removed.');
            }
        }
        redirect_to('/dashboard', ['tab' => 'portfolio']);
    }

    public function addPackage(): void
    {
        $user = require_auth();
        verify_csrf();
        $provider = ProviderModel::findByUserId((int) $user['id']);
        if (!$provider) {
            redirect_to('/dashboard');
        }

        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        [$clean, $problem] = self::cleanPackage($_POST, $uganda);
        if ($problem !== null) {
            flash('error', $problem);
            redirect_to('/dashboard', ['tab' => 'packages']);
        }
        if (count(ProviderModel::getPackages((int) $provider['id'])) >= self::MAX_PACKAGES) {
            flash('error', 'You have reached the limit of ' . self::MAX_PACKAGES . ' packages. Remove one to add another.');
            redirect_to('/dashboard', ['tab' => 'packages']);
        }

        ProviderModel::addPackage((int) $provider['id'], $clean);

        flash('success', 'Service package added to your profile!');
        redirect_to('/dashboard', ['tab' => 'packages']);
    }

    public function deletePackage(): void
    {
        $user = require_auth();
        verify_csrf();
        $provider = ProviderModel::findByUserId((int) $user['id']);
        if ($provider && !empty($_POST['package_id'])) {
            ProviderModel::deletePackage((int) $_POST['package_id'], (int) $provider['id']);
            flash('success', 'Service package deleted.');
        }
        redirect_to('/dashboard', ['tab' => 'packages']);
    }

    public function updatePortfolio(): void
    {
        $user = require_auth();
        verify_csrf();
        $provider = ProviderModel::findByUserId((int) $user['id']);
        if (!$provider) {
            redirect_to('/dashboard');
        }

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $item   = $itemId > 0 ? ProviderModel::findPortfolioItem($itemId, (int) $provider['id']) : null;
        if ($item === null) {
            flash('error', 'That work sample no longer exists.');
            redirect_to('/dashboard', ['tab' => 'portfolio']);
        }

        [$clean, $problem] = self::cleanPortfolio($_POST);
        if ($problem !== null) {
            flash('error', 'Nothing was saved. ' . $problem);
            redirect_to('/dashboard', ['tab' => 'portfolio', 'edit' => (string) $itemId]);
        }

        $newImage = null;
        try {
            // A new photo is optional: without one the current photo stays.
            $newImage = upload_image($_FILES['portfolio_image'] ?? [], 'portfolios');
            $data     = $newImage !== null ? $clean + ['image_path' => $newImage] : $clean;

            if (!ProviderModel::updatePortfolioItem($itemId, (int) $provider['id'], $data)) {
                throw new UserFacingException('That work sample no longer exists.');
            }
            if ($newImage !== null) {
                delete_upload((string) $item['image_path']);
            }
            flash('success', 'Work sample updated.');
        } catch (Throwable $e) {
            // Nothing was saved, so don't keep a photo uploaded during this failed attempt.
            delete_upload($newImage);
            flash('error', report_exception($e, 'We could not save your changes. Please try again.'));
            redirect_to('/dashboard', ['tab' => 'portfolio', 'edit' => (string) $itemId]);
        }

        redirect_to('/dashboard', ['tab' => 'portfolio']);
    }

    public function updatePackage(): void
    {
        $user = require_auth();
        verify_csrf();
        $provider = ProviderModel::findByUserId((int) $user['id']);
        if (!$provider) {
            redirect_to('/dashboard');
        }

        $packageId = (int) ($_POST['package_id'] ?? 0);
        if ($packageId <= 0 || ProviderModel::findPackage($packageId, (int) $provider['id']) === null) {
            flash('error', 'That package no longer exists.');
            redirect_to('/dashboard', ['tab' => 'packages']);
        }

        $uganda = require KOPII_ROOT . '/app/config/uganda.php';
        [$clean, $problem] = self::cleanPackage($_POST, $uganda);
        if ($problem !== null) {
            flash('error', 'Nothing was saved. ' . $problem);
            redirect_to('/dashboard', ['tab' => 'packages', 'edit' => (string) $packageId]);
        }

        try {
            ProviderModel::updatePackage($packageId, (int) $provider['id'], $clean);
            flash('success', 'Package updated.');
        } catch (Throwable $e) {
            flash('error', report_exception($e, 'We could not save your changes. Please try again.'));
            redirect_to('/dashboard', ['tab' => 'packages', 'edit' => (string) $packageId]);
        }

        redirect_to('/dashboard', ['tab' => 'packages']);
    }

    /**
     * Validate and tidy a portfolio item's text fields (shared by "add" and "edit").
     *
     * @return array{0: array, 1: ?string} cleaned values and the first problem, written for the visitor
     */
    private static function cleanPortfolio(array $post): array
    {
        $c = [
            'title'           => trim((string) ($post['title'] ?? '')),
            'project_tag'     => trim((string) ($post['project_tag'] ?? '')),
            'description'     => trim((string) ($post['description'] ?? '')),
            'client_location' => trim((string) ($post['client_location'] ?? '')),
            'is_featured'     => !empty($post['is_featured']) ? 1 : 0,
        ];

        if ($c['title'] === '') {
            return [$c, 'Please enter a title for your portfolio work item.'];
        }
        if (text_length($c['title']) > 120 || text_length($c['project_tag']) > 60 || text_length($c['description']) > 1000 || text_length($c['client_location']) > 120) {
            return [$c, 'One of those fields is too long. Title 120, tag 60, description 1,000 and location 120 characters at most.'];
        }
        return [$c, null];
    }

    /**
     * Validate and tidy a service package (shared by "add" and "edit").
     *
     * @return array{0: array, 1: ?string} cleaned values and the first problem, written for the visitor
     */
    private static function cleanPackage(array $post, array $uganda): array
    {
        $c = [
            'name'         => trim((string) ($post['name'] ?? '')),
            'price_ugx'    => (int) preg_replace('/\D+/', '', (string) ($post['price_ugx'] ?? '0')),
            'price_unit'   => trim((string) ($post['price_unit'] ?? 'per session')),
            'turnaround'   => trim((string) ($post['turnaround'] ?? '')),
            'description'  => trim((string) ($post['description'] ?? '')),
            'deliverables' => trim((string) ($post['deliverables'] ?? '')),
            'is_popular'   => !empty($post['is_popular']) ? 1 : 0,
        ];

        if ($c['name'] === '' || $c['price_ugx'] <= 0) {
            return [$c, 'Please enter both a package name and a price in UGX.'];
        }
        if ($c['price_ugx'] > 1000000000 || !in_array($c['price_unit'], $uganda['price_units'], true)) {
            return [$c, 'Please enter a price up to UGX 1,000,000,000 and choose a price unit from the list.'];
        }
        if (text_length($c['name']) > 100 || text_length($c['turnaround']) > 80 || text_length($c['description']) > 500 || text_length($c['deliverables']) > 1000) {
            return [$c, 'One of those fields is too long. Name 100, turnaround 80, description 500 and deliverables 1,000 characters at most.'];
        }
        return [$c, null];
    }

    public function updateInquiryStatus(): void
    {
        $user = require_auth();
        verify_csrf();
        $provider = ProviderModel::findByUserId((int) $user['id']);
        if ($provider && !empty($_POST['inquiry_id']) && !empty($_POST['status'])) {
            InquiryModel::updateStatus((int) $_POST['inquiry_id'], (int) $provider['id'], (string) $_POST['status']);
            flash('success', 'Lead status updated to "' . ucfirst((string) $_POST['status']) . '".');
        }
        redirect_to('/dashboard', ['tab' => 'inquiries']);
    }
}
