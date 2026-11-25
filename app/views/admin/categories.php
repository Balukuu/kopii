<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var string $kind    service | product
 * @var array  $rows    from CategoryModel::adminList()
 * @var array  $icons   allowed icon names
 * @var ?array $editing the category being edited, if any
 */
View::partial('admin_header', ['adminTab' => $adminTab]);

$isProduct = $kind === 'product';
$listUrl   = route_url('/admin/categories', ['kind' => $kind]);
$e         = $editing;
?>
<section class="section-pad-sm">
    <div class="container">
        <p class="text-muted">
            Categories are the lists people choose from. <strong>Service categories</strong> are what providers are listed under;
            <strong>shop categories</strong> are what products are filed under. A category's web address is made from its first name and never changes,
            so renaming is always safe.
        </p>

        <nav class="filter-tabs" aria-label="Category list">
            <a class="filter-tab" href="<?= e(route_url('/admin/categories', ['kind' => 'service'])) ?>"<?= !$isProduct ? ' aria-current="page"' : '' ?>>Service categories</a>
            <a class="filter-tab" href="<?= e(route_url('/admin/categories', ['kind' => 'product'])) ?>"<?= $isProduct ? ' aria-current="page"' : '' ?>>Shop categories</a>
        </nav>

        <div class="admin-card" id="category-form">
            <h2><?= $e !== null ? 'Edit "' . e($e['name']) . '"' : 'Add a ' . ($isProduct ? 'shop' : 'service') . ' category' ?></h2>
            <form method="POST" action="<?= e(route_url('/admin/categories/save')) ?>" class="stack-form">
                <?= csrf_field() ?>
                <input type="hidden" name="kind" value="<?= e($kind) ?>">
                <?php if ($e !== null): ?>
                    <input type="hidden" name="category_id" value="<?= (int) $e['id'] ?>">
                <?php endif; ?>

                <div class="form-row-2">
                    <div class="field">
                        <label for="cat-name">Name *</label>
                        <input type="text" id="cat-name" name="name" value="<?= e($e['name'] ?? '') ?>" maxlength="60" required>
                    </div>
                    <div class="field">
                        <label for="cat-icon">Icon</label>
                        <select id="cat-icon" name="icon">
                            <?php foreach ($icons as $icon): ?>
                                <option value="<?= e($icon) ?>"<?= ($e['icon'] ?? 'briefcase') === $icon ? ' selected' : '' ?>><?= e(ucfirst($icon)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if (!$isProduct): ?>
                    <div class="field">
                        <label for="cat-desc">Short description</label>
                        <input type="text" id="cat-desc" name="description" value="<?= e($e['description'] ?? '') ?>" maxlength="255" placeholder="Shown on the category's page and in search">
                    </div>
                <?php endif; ?>
                <div class="field" style="max-inline-size:12rem;">
                    <label for="cat-sort">Order</label>
                    <input type="text" inputmode="numeric" id="cat-sort" name="sort_order" value="<?= (int) ($e['sort_order'] ?? (count($rows) + 1)) ?>" maxlength="3">
                    <span class="hint">Smaller numbers come first.</span>
                </div>
                <div class="cell-actions">
                    <button type="submit" class="btn btn-primary btn-sm"><?= $e !== null ? 'Save changes' : 'Add category' ?></button>
                    <?php if ($e !== null): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e($listUrl) ?>">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (empty($rows)): ?>
            <p class="empty-state">There are no <?= $isProduct ? 'shop' : 'service' ?> categories yet. Add the first one above.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Order</th>
                            <th scope="col">Category</th>
                            <th scope="col"><?= $isProduct ? 'Products' : 'Listings' ?></th>
                            <?php if (!$isProduct): ?><th scope="col">Opportunities</th><?php endif; ?>
                            <th scope="col">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $uses = (int) $row['uses']; ?>
                            <tr>
                                <td class="tnum"><?= (int) $row['sort_order'] ?></td>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:0.5rem;">
                                        <?= svg_icon((string) $row['icon'], 16) ?>
                                        <strong><?= e($row['name']) ?></strong>
                                    </span>
                                    <span class="cell-sub">/<?= e($row['slug']) ?></span>
                                </td>
                                <td class="tnum"><?= $uses ?></td>
                                <?php if (!$isProduct): ?><td class="tnum"><?= (int) $row['opportunity_uses'] ?></td><?php endif; ?>
                                <td>
                                    <div class="cell-actions">
                                        <a class="btn btn-outline btn-sm" href="<?= e(route_url('/admin/categories', ['kind' => $kind, 'edit' => (string) (int) $row['id']])) ?>#category-form">Edit</a>
                                        <?php if ($uses === 0): ?>
                                            <form method="POST" action="<?= e(route_url('/admin/categories/delete')) ?>" data-confirm="Yes, delete">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="kind" value="<?= e($kind) ?>">
                                                <input type="hidden" name="category_id" value="<?= (int) $row['id'] ?>">
                                                <button type="submit" class="btn btn-danger-outline btn-sm">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="cell-sub">In use: cannot delete</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
