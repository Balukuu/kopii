<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array  $entries admin_log rows
 * @var string $type    '' | provider | product | opportunity | review | user
 * @var int    $page
 * @var int    $pages
 * @var int    $total
 */
View::partial('admin_header', ['adminTab' => $adminTab]);

$tabs = [
    ''         => 'Everything',
    'provider' => 'Listings',
    'product'  => 'Products',
    'opportunity' => 'Opportunities',
    'review'   => 'Reviews',
    'user'     => 'Accounts',
];
?>
<section class="section-pad-sm">
    <div class="container">
        <p class="text-muted">Everything an administrator has done, newest first. Entries are kept even when the thing they were about is deleted.</p>

        <nav class="filter-tabs" aria-label="Activity type">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="filter-tab" href="<?= e(route_url('/admin/activity', ['type' => $key])) ?>"<?= $type === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if (empty($entries)): ?>
            <p class="empty-state">Nothing has been recorded<?= $type !== '' ? ' for this type' : ' yet' ?>.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">When</th>
                            <th scope="col">Who</th>
                            <th scope="col">What</th>
                            <th scope="col">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $row): ?>
                            <tr>
                                <td><?= e(time_ago((string) $row['created_at'])) ?><span class="cell-sub"><?= e(date('j M Y, H:i', (int) strtotime((string) $row['created_at']))) ?></span></td>
                                <td><?= e($row['admin_name'] ?? 'An administrator') ?></td>
                                <td>
                                    <?= e(AdminLogModel::describe($row)) ?>
                                    <?php if ($row['target_type'] === 'provider' && !empty($row['target_id'])): ?>
                                        <span class="cell-sub"><a href="<?= e(route_url('/admin/providers/' . (int) $row['target_id'])) ?>">Open listing</a></span>
                                    <?php endif; ?>
                                </td>
                                <td class="review-text"><?= (string) $row['note'] !== '' ? e($row['note']) : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php View::partial('admin_pager', ['page' => $page, 'pages' => $pages, 'total' => $total, 'path' => '/admin/activity', 'query' => ['type' => $type]]); ?>
    </div>
</section>
