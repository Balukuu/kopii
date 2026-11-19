<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var string $heading
 * @var array  $filters   q, category, city, max_price, in_stock, condition, sort
 * @var ?array $activeCategory
 * @var array  $categories rows from ProductModel::categories()
 * @var array  $cities
 * @var array  $conditions key => label
 * @var array  $products
 * @var int    $page
 * @var int    $pages
 * @var int    $total
 */
$activeFilters = array_filter($filters, static fn($v) => $v !== '' && $v !== 0 && $v !== 'newest');
$user = current_user();
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <?php if ($activeCategory): ?>
                <li><a href="<?= e(route_url('/shop')) ?>">Shop</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page"><?= e($activeCategory['name']) ?></li>
            <?php else: ?>
                <li aria-current="page">Shop</li>
            <?php endif; ?>
        </ol>
    </div>
</nav>

<section class="explore-bar">
    <div class="container">
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:1rem; margin-block-end:1.35rem;">
            <div>
                <h1 style="margin:0 0 0.35rem; font-size:clamp(1.65rem, 3vw, 2.3rem); color:var(--slate-950); letter-spacing:-0.025em;"><?= e($heading) ?></h1>
                <p class="text-muted m-0">Prices in UGX. Message the seller to ask questions, arrange delivery and agree how to pay.</p>
            </div>
            <a href="<?= e(route_url($user ? '/dashboard' : '/register', $user ? ['tab' => 'products'] : [])) ?>" class="btn btn-primary btn-sm">Sell on Kopii</a>
        </div>

        <search aria-label="Filter products">
            <form action="<?= e(route_url('/shop')) ?>" method="GET">
                <div class="filter-grid">
                    <div class="field">
                        <label for="s-q">Search</label>
                        <input type="search" id="s-q" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. coffee, kitenge, notebook" spellcheck="false" enterkeyhint="search">
                    </div>
                    <div class="field">
                        <label for="s-cat">Category</label>
                        <select id="s-cat" name="category">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat['slug']) ?>"<?= $filters['category'] === $cat['slug'] ? ' selected' : '' ?>><?= e($cat['name']) ?> (<?= (int) $cat['product_count'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="s-city">Seller's city</label>
                        <select id="s-city" name="city">
                            <option value="">All cities</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= e($c) ?>"<?= strcasecmp($filters['city'], $c) === 0 ? ' selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="s-price">Price</label>
                        <select id="s-price" name="max_price">
                            <option value="0">Any price</option>
                            <?php foreach ([25000 => 'Up to UGX 25,000', 100000 => 'Up to UGX 100,000', 250000 => 'Up to UGX 250,000', 1000000 => 'Up to UGX 1,000,000'] as $val => $lbl): ?>
                                <option value="<?= $val ?>"<?= (int) $filters['max_price'] === $val ? ' selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="s-cond">Condition</label>
                        <select id="s-cond" name="condition">
                            <option value="">New or used</option>
                            <?php foreach ($conditions as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $filters['condition'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="s-sort">Sort</label>
                        <select id="s-sort" name="sort">
                            <option value="newest"<?= $filters['sort'] === 'newest' ? ' selected' : '' ?>>Newest first</option>
                            <option value="price_asc"<?= $filters['sort'] === 'price_asc' ? ' selected' : '' ?>>Price: low to high</option>
                            <option value="price_desc"<?= $filters['sort'] === 'price_desc' ? ' selected' : '' ?>>Price: high to low</option>
                            <option value="popular"<?= $filters['sort'] === 'popular' ? ' selected' : '' ?>>Most viewed</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; margin-block-start:1rem;">
                    <div class="checkbox-row">
                        <input type="checkbox" id="s-stock" name="in_stock" value="1"<?= !empty($filters['in_stock']) ? ' checked' : '' ?>>
                        <label for="s-stock" style="font-weight:600; font-size:0.9rem;">Hide items that are out of stock</label>
                    </div>
                    <div style="display:flex; gap:0.6rem;">
                        <a href="<?= e(route_url('/shop')) ?>" class="btn btn-ghost btn-sm">Reset</a>
                        <button type="submit" class="btn btn-primary btn-sm"><?= svg_icon('search', 16) ?> Apply</button>
                    </div>
                </div>
            </form>
        </search>
    </div>
</section>

<section class="section-pad-sm">
    <div class="container">
        <div class="shop-chips" aria-label="Categories">
            <a class="btn btn-sm <?= $filters['category'] === '' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(route_url('/shop')) ?>">All</a>
            <?php foreach ($categories as $cat): ?>
                <?php if ((int) $cat['product_count'] > 0 || $filters['category'] === $cat['slug']): ?>
                    <a class="btn btn-sm <?= $filters['category'] === $cat['slug'] ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(route_url('/shop', ['category' => $cat['slug']])) ?>"><?= e($cat['name']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="content-panel" style="text-align:center; padding:3rem 1.5rem;">
                <h2>No products match that search</h2>
                <p class="text-muted">Try fewer filters, or list your own products so buyers can find you.</p>
                <div style="display:flex; justify-content:center; gap:0.75rem; flex-wrap:wrap; margin-block-start:1rem;">
                    <a href="<?= e(route_url('/shop')) ?>" class="btn btn-outline">Clear filters</a>
                    <a href="<?= e(route_url($user ? '/dashboard' : '/register', $user ? ['tab' => 'products'] : [])) ?>" class="btn btn-primary">Sell on Kopii</a>
                </div>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $p): ?>
                    <?php View::partial('product_card', ['p' => $p]); ?>
                <?php endforeach; ?>
            </div>
            <?php View::partial('admin_pager', ['page' => $page, 'pages' => $pages, 'total' => $total, 'path' => '/shop', 'query' => $activeFilters]); ?>
        <?php endif; ?>
    </div>
</section>
