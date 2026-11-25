<?php
defined('KOPII_APP') or exit('Direct access denied.');

/**
 * The public shop: browse products, see one, and send the seller an enquiry. There is no checkout
 * yet: a buyer contacts the seller, who answers from the same inbox as quote requests.
 */
class ShopController
{
    private const PER_PAGE = 24;

    public function index(): void
    {
        $uganda = require KOPII_ROOT . '/app/config/uganda.php';

        $filters = [
            'q'         => $this->text('q'),
            'category'  => $this->text('category'),
            'city'      => $this->text('city'),
            'max_price' => max(0, (int) ($_GET['max_price'] ?? 0)),
            'in_stock'  => !empty($_GET['in_stock']) ? 1 : 0,
            'condition' => $this->choice('condition', array_keys($uganda['product_conditions'])),
            'sort'      => $this->choice('sort', ['newest', 'price_asc', 'price_desc', 'popular']) ?: 'newest',
        ];

        $total = ProductModel::countSearch($filters);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($pages, max(1, (int) ($_GET['page'] ?? 1)));

        $activeCategory = $filters['category'] !== '' ? ProductModel::categoryBySlug($filters['category']) : null;
        $heading = $activeCategory ? $activeCategory['name'] : 'Shop products from Ugandan businesses';
        if ($filters['city'] !== '') {
            $heading .= ' in ' . $filters['city'];
        }

        View::render('pages/shop', [
            'pageTitle'       => $heading . ' — Kopii Shop',
            'metaDescription' => 'Browse products from businesses and makers across Uganda, see prices in UGX, and message the seller directly.',
            'currentRoute'    => '/shop',
            'heading'         => $heading,
            'filters'         => $filters,
            'activeCategory'  => $activeCategory,
            'categories'      => ProductModel::categories(),
            'cities'          => $uganda['cities'],
            'conditions'      => $uganda['product_conditions'],
            'products'        => ProductModel::search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'page'            => $page,
            'pages'           => $pages,
            'total'           => $total,
        ]);
    }

    public function show(array $params): void
    {
        $product = ProductModel::findBySlug((string) ($params['slug'] ?? ''));
        $user    = current_user();

        $isPublic = $product !== null && $product['status'] === ProductModel::ACTIVE && $product['provider_status'] === 'active';
        $isOwner  = $product !== null && $user !== null && (int) $user['id'] === (int) $product['provider_user_id'];
        $isAdmin  = $user !== null && ($user['role'] ?? '') === 'admin';

        if ($product === null || !($isPublic || $isOwner || $isAdmin)) {
            http_response_code(404);
            View::render('pages/404', ['pageTitle' => 'Product not found — Kopii']);
            return;
        }

        if ($isPublic) {
            ProductModel::incrementViews((int) $product['id']);
        }

        $uganda  = require KOPII_ROOT . '/app/config/uganda.php';
        $more    = ProductModel::search(['provider_id' => (int) $product['provider_id'], 'exclude_id' => (int) $product['id']], 4);
        $similar = ProductModel::search(['category' => $product['category_slug'], 'exclude_id' => (int) $product['id']], 12);
        // Other things from the same category, but not the ones already shown from this seller.
        $moreIds = array_map(static fn(array $p): int => (int) $p['id'], $more);
        $similar = array_slice(array_values(array_filter($similar, static fn(array $p): bool => !in_array((int) $p['id'], $moreIds, true))), 0, 4);

        View::render('pages/product', [
            'pageTitle'       => $product['name'] . ' — ' . $product['business_name'] . ' | Kopii Shop',
            'metaDescription' => text_excerpt((string) ($product['description'] ?? ''), 155) ?: $product['name'] . ' from ' . $product['business_name'],
            'currentRoute'    => '/shop',
            'ogImage'         => share_image_path($product['images'][0]['image_path'] ?? null),
            'noindex'         => !$isPublic,
            'product'         => $product,
            'isPublic'        => $isPublic,
            'isOwner'         => $isOwner,
            'stockLabels'     => $uganda['stock_statuses'],
            'conditions'      => $uganda['product_conditions'],
            'more'            => $more,
            'similar'         => $similar,
        ]);
    }

    public function inquire(array $params): void
    {
        verify_csrf();

        $slug    = (string) ($params['slug'] ?? '');
        $product = ProductModel::findBySlug($slug);
        if ($product === null || $product['status'] !== ProductModel::ACTIVE || $product['provider_status'] !== 'active') {
            flash('error', 'That product is not available right now.');
            redirect_to('/shop');
        }

        // Honeypot spam protection
        if (!empty($_POST['website_confirm_hp'])) {
            redirect_to('/product/' . $slug);
        }

        $user = current_user();
        if ($user !== null && (int) $user['id'] === (int) $product['provider_user_id']) {
            flash('error', 'This is your own product.');
            redirect_to('/product/' . $slug);
        }

        $productId = (int) $product['id'];
        $ip        = client_ip();
        if (RateLimiter::tooMany('product_inquiry', $ip, 20, 3600) || RateLimiter::tooMany('product_inquiry_item', $ip . '|' . $productId, 3, 3600)) {
            set_old_input($_POST);
            flash('error', 'You have sent a lot of messages in a short time. Please wait a little while before sending another.');
            redirect_to('/product/' . $slug);
        }

        [$clean, $problems] = MarketplaceController::cleanLead($_POST);
        if (!empty($problems)) {
            set_old_input($_POST);
            flash('error', 'Please check: ' . implode('; ', $problems) . '.');
            redirect_to('/product/' . $slug);
        }

        RateLimiter::hit('product_inquiry', $ip);
        RateLimiter::hit('product_inquiry_item', $ip . '|' . $productId);

        $lead = [
            'provider_id'     => (int) $product['provider_id'],
            'client_user_id'  => $user['id'] ?? null,
            'package_id'      => null,
            'product_id'      => $productId,
            'product_name'    => (string) $product['name'],
            'client_name'     => $clean['name'],
            'client_email'    => $clean['email'],
            'client_phone'    => $clean['phone'],
            'event_date'      => '',
            'event_location'  => $clean['location'],
            'budget_ugx'      => null,
            'project_details' => $clean['details'],
        ];
        InquiryModel::create($lead);
        Notifier::newLead((int) $product['provider_id'], $lead);

        clear_old_input();
        flash('success', 'Your message about "' . $product['name'] . '" was sent to ' . $product['business_name'] . '. They will reply by phone, WhatsApp or email.');
        redirect_to('/product/' . $slug, ['sent' => '1']);
    }

    /** A free-text query value, trimmed and capped so a huge value cannot reach a query. */
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
