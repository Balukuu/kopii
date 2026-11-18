<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array $providers rows from AdminModel::providerList()
 * @var array $filters   q, status, verified, featured
 * @var int   $page
 * @var int   $pages
 * @var int   $total
 */
View::partial('admin_header', ['adminTab' => $adminTab]);
$activeFilters = array_filter($filters, static fn($v) => $v !== '');
?>
<section class="section-pad-sm">
    <div class="container">
        <form class="admin-filters" method="GET" action="<?= e(route_url('/admin/providers')) ?>" role="search">
            <div class="field field-grow">
                <label for="f-q">Search</label>
                <input id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Business, owner, email or city" maxlength="80">
            </div>
            <div class="field">
                <label for="f-status">Status</label>
                <select id="f-status" name="status">
                    <option value="">Any</option>
                    <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Live</option>
                    <option value="paused"<?= $filters['status'] === 'paused' ? ' selected' : '' ?>>Paused</option>
                    <option value="suspended"<?= $filters['status'] === 'suspended' ? ' selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <div class="field">
                <label for="f-verified">Verified badge</label>
                <select id="f-verified" name="verified">
                    <option value="">Any</option>
                    <option value="1"<?= $filters['verified'] === '1' ? ' selected' : '' ?>>Verified</option>
                    <option value="0"<?= $filters['verified'] === '0' ? ' selected' : '' ?>>Not verified</option>
                </select>
            </div>
            <div class="field">
                <label for="f-featured">Featured</label>
                <select id="f-featured" name="featured">
                    <option value="">Any</option>
                    <option value="1"<?= $filters['featured'] === '1' ? ' selected' : '' ?>>Featured only</option>
                </select>
            </div>
            <div class="cell-actions">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <?php if (!empty($activeFilters)): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(route_url('/admin/providers')) ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($providers)): ?>
            <p class="empty-state">No listings match.<?= !empty($activeFilters) ? ' Try clearing a filter.' : '' ?></p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Listing</th>
                            <th scope="col">Category &amp; city</th>
                            <th scope="col">Work / leads</th>
                            <th scope="col">Status</th>
                            <th scope="col">Joined</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($providers as $p): ?>
                            <?php $pid = (int) $p['id']; ?>
                            <tr>
                                <td>
                                    <a href="<?= e(route_url('/admin/providers/' . $pid)) ?>"><strong><?= e($p['business_name']) ?></strong></a>
                                    <span class="cell-sub">
                                        <?= e($p['owner_name']) ?> &middot; <?= e($p['owner_email']) ?>
                                        <?php if (empty($p['owner_email_verified_at'])): ?>(email not confirmed)<?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?= e($p['category_name']) ?>
                                    <span class="cell-sub"><?= e($p['city']) ?> &middot; <?= e(format_ugx($p['starting_price_ugx'])) ?></span>
                                </td>
                                <td>
                                    <strong class="tnum"><?= (int) $p['portfolio_count'] ?></strong> work &middot;
                                    <strong class="tnum"><?= (int) $p['inquiry_count'] ?></strong> leads
                                </td>
                                <td>
                                    <div class="status-line">
                                        <span class="badge <?= $p['status'] === 'active' ? 'badge-avail' : 'badge-type' ?>"><?= e($p['status'] === 'active' ? 'Live' : ucfirst((string) $p['status'])) ?></span>
                                        <?php if (!empty($p['is_verified'])): ?>
                                            <span class="badge badge-verified">Verified</span>
                                        <?php else: ?>
                                            <span class="badge badge-type">Not verified</span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['is_featured'])): ?>
                                            <span class="badge badge-featured">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= e(time_ago((string) $p['created_at'])) ?></td>
                                <td>
                                    <div class="cell-actions">
                                        <a class="btn btn-outline btn-sm" href="<?= e(route_url('/admin/providers/' . $pid)) ?>">Review</a>
                                        <form method="POST" action="<?= e(route_url('/admin/moderate')) ?>">
                                            <?= csrf_field() ?>
                                            <?= admin_return_field() ?>
                                            <input type="hidden" name="provider_id" value="<?= $pid ?>">
                                            <input type="hidden" name="mod_action" value="<?= !empty($p['is_featured']) ? 'unfeature' : 'feature' ?>">
                                            <button type="submit" class="btn btn-outline btn-sm"><?= !empty($p['is_featured']) ? 'Unfeature' : 'Feature' ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php View::partial('admin_pager', ['page' => $page, 'pages' => $pages, 'total' => $total, 'path' => '/admin/providers', 'query' => $activeFilters]); ?>
    </div>
</section>
