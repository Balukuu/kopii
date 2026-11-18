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
            ]);
            return;
        }

        $tab = (string) ($_GET['tab'] ?? 'overview');
        if (!in_array($tab, ['overview', 'inquiries', 'portfolio', 'packages', 'profile'], true)) {
            $tab = 'overview';
        }

        $statusFilter  = isset($_GET['status']) ? (string) $_GET['status'] : null;
        $portfolio     = ProviderModel::getPortfolio((int) $provider['id']);
        $packages      = ProviderModel::getPackages((int) $provider['id']);
        $inquiries     = InquiryModel::forProvider((int) $provider['id'], $statusFilter);
        $inquiryCounts = InquiryModel::countsForProvider((int) $provider['id']);

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

        $title       = trim((string) ($_POST['title'] ?? ''));
        $tag         = trim((string) ($_POST['project_tag'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $location    = trim((string) ($_POST['client_location'] ?? ''));

        if ($title === '') {
            flash('error', 'Please enter a title for your portfolio work item.');
            redirect_to('/dashboard', ['tab' => 'portfolio']);
        }
        if (text_length($title) > 120 || text_length($tag) > 60 || text_length($description) > 1000 || text_length($location) > 120) {
            flash('error', 'One of those fields is too long. Title 120, tag 60, description 1,000 and location 120 characters at most.');
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

            ProviderModel::addPortfolioItem((int) $provider['id'], [
                'title'           => $title,
                'project_tag'     => $tag,
                'description'     => $description,
                'image_path'      => $imgPath,
                'client_location' => $location,
                'is_featured'     => !empty($_POST['is_featured']) ? 1 : 0,
            ]);

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

        $uganda       = require KOPII_ROOT . '/app/config/uganda.php';
        $name         = trim((string) ($_POST['name'] ?? ''));
        $price        = (int) preg_replace('/\D+/', '', (string) ($_POST['price_ugx'] ?? '0'));
        $unit         = trim((string) ($_POST['price_unit'] ?? 'per session'));
        $turnaround   = trim((string) ($_POST['turnaround'] ?? ''));
        $description  = trim((string) ($_POST['description'] ?? ''));
        $deliverables = trim((string) ($_POST['deliverables'] ?? ''));

        if ($name === '' || $price <= 0) {
            flash('error', 'Please enter both a package name and a price in UGX.');
            redirect_to('/dashboard', ['tab' => 'packages']);
        }
        if ($price > 1000000000 || !in_array($unit, $uganda['price_units'], true)) {
            flash('error', 'Please enter a price up to UGX 1,000,000,000 and choose a price unit from the list.');
            redirect_to('/dashboard', ['tab' => 'packages']);
        }
        if (text_length($name) > 100 || text_length($turnaround) > 80 || text_length($description) > 500 || text_length($deliverables) > 1000) {
            flash('error', 'One of those fields is too long. Name 100, turnaround 80, description 500 and deliverables 1,000 characters at most.');
            redirect_to('/dashboard', ['tab' => 'packages']);
        }
        if (count(ProviderModel::getPackages((int) $provider['id'])) >= self::MAX_PACKAGES) {
            flash('error', 'You have reached the limit of ' . self::MAX_PACKAGES . ' packages. Remove one to add another.');
            redirect_to('/dashboard', ['tab' => 'packages']);
        }

        ProviderModel::addPackage((int) $provider['id'], [
            'name'         => $name,
            'price_ugx'    => $price,
            'price_unit'   => $unit,
            'turnaround'   => $turnaround,
            'description'  => $description,
            'deliverables' => $deliverables,
            'is_popular'   => !empty($_POST['is_popular']) ? 1 : 0,
        ]);

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
