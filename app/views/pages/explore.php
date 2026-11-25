<?php
defined('KOPII_APP') or exit('Direct access denied.');
$providersQuery = array_merge($filters, ['tab' => 'providers']);
$workQuery      = array_merge($filters, ['tab' => 'work']);
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container" style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:0.75rem;">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <?php if (!empty($activeCategory)): ?>
                <li><a href="<?= e(route_url('/explore')) ?>">Providers</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page"><?= e($activeCategory['name']) ?></li>
            <?php else: ?>
                <li aria-current="page"><?= $tab === 'work' ? 'Portfolio work' : 'Providers' ?></li>
            <?php endif; ?>
        </ol>
    </div>
</nav>

<section class="explore-bar">
    <div class="container">
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:1rem; margin-block-end: 1.35rem;">
            <div>
                <h1 style="margin:0 0 0.35rem; font-size:clamp(1.65rem, 3vw, 2.3rem); color:var(--slate-950); letter-spacing:-0.025em;">
                    <?= e($heading) ?>
                </h1>
                <?php if (!empty($activeCategory['description'])): ?>
                    <p class="text-muted m-0"><?= e($activeCategory['description']) ?></p>
                <?php else: ?>
                    <p class="text-muted m-0">Inspect real portfolios, compare transparent UGX packages, and engage specialists directly.</p>
                <?php endif; ?>
            </div>
            <a href="<?= e(route_url('/hire', array_filter(['category' => $filters['category'], 'city' => $filters['city']]))) ?>" class="btn btn-primary btn-sm">
                Get matched
            </a>
        </div>

        <search aria-label="Filter marketplace listings">
            <form action="<?= e(route_url('/explore')) ?>" method="GET">
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                <div class="filter-grid">
                    <div class="field">
                        <label for="f-q">Keyword or Skill</label>
                        <input type="search" id="f-q" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. Wedding, drone, portraits..." spellcheck="false" enterkeyhint="search">
                    </div>
                    <div class="field">
                        <label for="f-cat">Category</label>
                        <select id="f-cat" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat['slug']) ?>"<?= $filters['category'] === $cat['slug'] ? ' selected' : '' ?>>
                                    <?= e($cat['name']) ?> (<?= (int) $cat['provider_count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="f-city">Ugandan City</label>
                        <select id="f-city" name="city">
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= e($c) ?>"<?= strcasecmp((string) $filters['city'], $c) === 0 ? ' selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="f-area">Area or Neighbourhood</label>
                        <input type="search" id="f-area" name="area" list="area-suggestions" value="<?= e($filters['area']) ?>" placeholder="e.g. Nakawa, Ntinda, Mukono" maxlength="60" autocomplete="off" spellcheck="false" enterkeyhint="search">
                        <datalist id="area-suggestions">
                            <?php foreach ($areaSuggestions as $areaName): ?>
                                <option value="<?= e($areaName) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="field">
                        <label for="f-type">Provider Type</label>
                        <select id="f-type" name="type">
                            <option value="">Freelancers &amp; Businesses</option>
                            <option value="freelancer"<?= $filters['type'] === 'freelancer' ? ' selected' : '' ?>>Freelancers Only</option>
                            <option value="business"<?= $filters['type'] === 'business' ? ' selected' : '' ?>>Businesses / Studios Only</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="f-price">Max Starting Rate (UGX)</label>
                        <select id="f-price" name="max_price">
                            <option value="0">Any Budget</option>
                            <?php foreach ([300000 => 'Up to UGX 300,000', 500000 => 'Up to UGX 500,000', 1000000 => 'Up to UGX 1,000,000', 2500000 => 'Up to UGX 2,500,000'] as $val => $lbl): ?>
                                <option value="<?= $val ?>"<?= (int) $filters['max_price'] === $val ? ' selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="f-sort">Sort Results</label>
                        <select id="f-sort" name="sort">
                            <option value="featured"<?= $filters['sort'] === 'featured' ? ' selected' : '' ?>>Recommended</option>
                            <option value="rating"<?= $filters['sort'] === 'rating' ? ' selected' : '' ?>>Highest Rated</option>
                            <option value="price_asc"<?= $filters['sort'] === 'price_asc' ? ' selected' : '' ?>>Lowest Starting Price</option>
                            <option value="work"<?= $filters['sort'] === 'work' ? ' selected' : '' ?>>Most Portfolio Work</option>
                            <option value="newest"<?= $filters['sort'] === 'newest' ? ' selected' : '' ?>>Newest Joined</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; margin-block-start:1rem;">
                    <div class="checkbox-row">
                        <input type="checkbox" id="f-verified" name="verified" value="1"<?= !empty($filters['verified']) ? ' checked' : '' ?>>
                        <label for="f-verified" style="font-weight:600; font-size:0.9rem;">Verified Kopii providers only</label>
                    </div>
                    <div style="display:flex; gap:0.6rem;">
                        <a href="<?= e(route_url('/explore', ['tab' => $tab])) ?>" class="btn btn-ghost btn-sm">Reset Filters</a>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <?= svg_icon('search', 16) ?> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </search>
    </div>
</section>

<section class="section-pad-sm">
    <div class="container">
        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; margin-block-end:1.25rem;">
            <nav class="mode-tabs" aria-label="Marketplace view mode">
                <a class="mode-tab" href="<?= e(route_url('/explore', $providersQuery)) ?>"<?= $tab === 'providers' ? ' aria-current="page"' : '' ?>>
                    <?= svg_icon('user', 16) ?> Talent &amp; Businesses (<?= (int) $totalProviders ?>)
                </a>
                <a class="mode-tab" href="<?= e(route_url('/explore', $workQuery)) ?>"<?= $tab === 'work' ? ' aria-current="page"' : '' ?>>
                    <?= svg_icon('image', 16) ?> Portfolio Work Gallery (<?= count($workItems) ?>)
                </a>
            </nav>

            <?php if (!empty($shortlistMode)): ?>
                <a href="<?= e(route_url('/explore')) ?>" class="btn btn-outline btn-sm">
                    Exit Comparison Mode &middot; View All
                </a>
            <?php endif; ?>
        </div>

        <?php if ($tab === 'work'): ?>
            <?php if (empty($workItems)): ?>
                <div class="content-panel" style="text-align:center; padding:3rem 1.5rem;">
                    <h2>No portfolio samples match those filters yet</h2>
                    <p class="text-muted">Try clearing a filter or register to be the first creator showcasing work in this category!</p>
                    <a href="<?= e(route_url('/explore', ['tab' => 'work'])) ?>" class="btn btn-primary">Show All Work</a>
                </div>
            <?php else: ?>
                <div class="work-grid">
                    <?php foreach ($workItems as $w): ?>
                        <?php View::partial('work_card', ['w' => $w]); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php if (empty($providers)): ?>
                <div class="content-panel" style="text-align:center; padding:3rem 1.5rem;">
                    <?php
                    // A category nobody has joined yet is an invitation, not a dead end. Only when it is the sole filter.
                    $onlyCategory = $activeCategory !== null && $filters['q'] === '' && $filters['city'] === '' && $filters['area'] === '' && $filters['type'] === '' && (int) $filters['max_price'] === 0 && empty($filters['verified']);
                    ?>
                    <?php if ($onlyCategory): ?>
                        <h2>Nobody is listed in <?= e($activeCategory['name']) ?> yet</h2>
                        <p class="text-muted">Do this kind of work? List your business free and be the first customers find. Need it done now? <a href="<?= e(route_url('/opportunities/new')) ?>">Post what you need</a> and providers will send you proposals.</p>
                    <?php else: ?>
                        <h2>No freelancers or businesses found for that search</h2>
                        <p class="text-muted">Try a nearby area or a wider budget, or list your own business in this category.</p>
                    <?php endif; ?>
                    <div style="display:flex; justify-content:center; gap:0.75rem; flex-wrap:wrap; margin-block-start:1rem;">
                        <a href="<?= e(route_url('/explore')) ?>" class="btn btn-outline">Clear All Filters</a>
                        <a href="<?= e(route_url('/register')) ?>" class="btn btn-primary">List Your Business Here</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="provider-grid">
                    <?php foreach ($providers as $p): ?>
                        <?php View::partial('provider_card', ['p' => $p]); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
