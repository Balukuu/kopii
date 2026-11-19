<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array $products   rows from ProductModel::adminSearch()
 * @var array $categories product categories
 * @var array $filters    q, status, category
 * @var int   $page
 * @var int   $pages
 * @var int   $total
 */
View::partial('admin_header', ['adminTab' => $adminTab]);
$activeFilters = array_filter($filters, static fn($v) => $v !== '');
?>
<section class="section-pad-sm">
    <div class="container">
        <form class="admin-filters" method="GET" action="<?= e(route_url('/admin/products')) ?>" role="search">
            <div class="field field-grow">
                <label for="f-q">Search</label>
                <input id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Product, seller or seller email" maxlength="80">
            </div>
            <div class="field">
                <label for="f-status">Status</label>
                <select id="f-status" name="status">
                    <option value="">Any</option>
                    <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Live</option>
                    <option value="paused"<?= $filters['status'] === 'paused' ? ' selected' : '' ?>>Paused by seller</option>
                    <option value="suspended"<?= $filters['status'] === 'suspended' ? ' selected' : '' ?>>Hidden by Kopii</option>
                </select>
            </div>
            <div class="field">
                <label for="f-cat">Category</label>
                <select id="f-cat" name="category">
                    <option value="">Any</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat['slug']) ?>"<?= $filters['category'] === $cat['slug'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cell-actions">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <?php if (!empty($activeFilters)): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e(route_url('/admin/products')) ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (empty($products)): ?>
            <p class="empty-state">No products match.<?= !empty($activeFilters) ? ' Try clearing a filter.' : '' ?></p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Product</th>
                            <th scope="col">Seller</th>
                            <th scope="col">Price</th>
                            <th scope="col">Status</th>
                            <th scope="col">Activity</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <?php $pid = (int) $p['id']; ?>
                            <tr>
                                <td>
                                    <a href="<?= e(route_url('/product/' . $p['slug'])) ?>"><strong><?= e($p['name']) ?></strong></a>
                                    <span class="cell-sub"><?= e($p['category_name']) ?> &middot; added <?= e(time_ago((string) $p['created_at'])) ?></span>
                                </td>
                                <td>
                                    <a href="<?= e(route_url('/admin/providers/' . (int) $p['provider_id'])) ?>"><?= e($p['business_name']) ?></a>
                                    <span class="cell-sub"><?= e($p['owner_email']) ?></span>
                                </td>
                                <td class="tnum"><?= e(product_price($p['price_ugx'])) ?></td>
                                <td>
                                    <div class="status-line">
                                        <span class="badge <?= $p['status'] === 'active' ? 'badge-avail' : ($p['status'] === 'suspended' ? 'badge-out' : 'badge-type') ?>"><?= e(['active' => 'Live', 'paused' => 'Paused', 'suspended' => 'Hidden'][$p['status']] ?? ucfirst((string) $p['status'])) ?></span>
                                        <?php if ($p['provider_status'] !== 'active'): ?>
                                            <span class="badge badge-type">Seller hidden</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="tnum"><?= (int) $p['views_count'] ?></span> views &middot; <span class="tnum"><?= (int) $p['enquiry_count'] ?></span> enquiries</td>
                                <td>
                                    <form method="POST" action="<?= e(route_url('/admin/products/moderate')) ?>" class="cell-actions">
                                        <?= csrf_field() ?>
                                        <?= admin_return_field() ?>
                                        <input type="hidden" name="product_id" value="<?= $pid ?>">
                                        <?php if ($p['status'] === 'suspended'): ?>
                                            <input type="hidden" name="mod_action" value="reinstate">
                                            <button type="submit" class="btn btn-outline btn-sm">Reinstate</button>
                                        <?php else: ?>
                                            <input type="hidden" name="mod_action" value="suspend">
                                            <label class="visually-hidden" for="note-<?= $pid ?>">Reason (emailed to the seller)</label>
                                            <input id="note-<?= $pid ?>" type="text" name="note" placeholder="Reason (emailed to seller)" maxlength="500" required style="min-inline-size:11rem;">
                                            <button type="submit" class="btn btn-danger-outline btn-sm">Hide</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php View::partial('admin_pager', ['page' => $page, 'pages' => $pages, 'total' => $total, 'path' => '/admin/products', 'query' => $activeFilters]); ?>
    </div>
</section>
