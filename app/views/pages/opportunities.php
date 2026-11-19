<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array $filters q, kind, category, city
 * @var array $kinds   key => label
 * @var array $categories
 * @var array $cities
 * @var array $opportunities
 * @var int   $page
 * @var int   $pages
 * @var int   $total
 */
$activeFilters = array_filter($filters, static fn($v) => $v !== '');
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page">Opportunities</li>
        </ol>
    </div>
</nav>

<section class="explore-bar">
    <div class="container">
        <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:1rem; margin-block-end:1.35rem;">
            <div>
                <h1 style="margin:0 0 0.35rem; font-size:clamp(1.65rem, 3vw, 2.3rem); color:var(--slate-950); letter-spacing:-0.025em;">Opportunities in Uganda</h1>
                <p class="text-muted m-0">Projects, jobs, tenders and supply requests from clients and businesses. Send a proposal with your price and timeline.</p>
            </div>
            <a href="<?= e(route_url('/opportunities/new')) ?>" class="btn btn-primary btn-sm"><?= svg_icon('plus', 16) ?> Post an opportunity</a>
        </div>

        <search aria-label="Filter opportunities">
            <form action="<?= e(route_url('/opportunities')) ?>" method="GET">
                <div class="filter-grid">
                    <div class="field">
                        <label for="o-q">Search</label>
                        <input type="search" id="o-q" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. photographer, logo, fabric" spellcheck="false" enterkeyhint="search">
                    </div>
                    <div class="field">
                        <label for="o-kind">Kind</label>
                        <select id="o-kind" name="kind">
                            <option value="">All kinds</option>
                            <?php foreach ($kinds as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $filters['kind'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="o-cat">Category</label>
                        <select id="o-cat" name="category">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat['slug']) ?>"<?= $filters['category'] === $cat['slug'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="o-city">Where</label>
                        <select id="o-city" name="city">
                            <option value="">Anywhere</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= e($c) ?>"<?= strcasecmp($filters['city'], $c) === 0 ? ' selected' : '' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.6rem; margin-block-start:1rem;">
                    <a href="<?= e(route_url('/opportunities')) ?>" class="btn btn-ghost btn-sm">Reset</a>
                    <button type="submit" class="btn btn-primary btn-sm"><?= svg_icon('search', 16) ?> Apply</button>
                </div>
            </form>
        </search>
    </div>
</section>

<section class="section-pad-sm">
    <div class="container">
        <?php if (empty($opportunities)): ?>
            <div class="content-panel" style="text-align:center; padding:3rem 1.5rem;">
                <h2><?= empty($activeFilters) ? 'Nothing is open right now' : 'No open opportunities match that search' ?></h2>
                <p class="text-muted">Need something done? Post it and let providers come to you.</p>
                <div style="display:flex; justify-content:center; gap:0.75rem; flex-wrap:wrap; margin-block-start:1rem;">
                    <?php if (!empty($activeFilters)): ?>
                        <a href="<?= e(route_url('/opportunities')) ?>" class="btn btn-outline">Clear filters</a>
                    <?php endif; ?>
                    <a href="<?= e(route_url('/opportunities/new')) ?>" class="btn btn-primary">Post an opportunity</a>
                </div>
            </div>
        <?php else: ?>
            <div class="opp-list">
                <?php foreach ($opportunities as $o): ?>
                    <?php View::partial('opportunity_card', ['o' => $o, 'kinds' => $kinds]); ?>
                <?php endforeach; ?>
            </div>
            <?php View::partial('admin_pager', ['page' => $page, 'pages' => $pages, 'total' => $total, 'path' => '/opportunities', 'query' => $activeFilters]); ?>
        <?php endif; ?>
    </div>
</section>
