<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array  $reviews rows from ReviewModel::adminSearch()
 * @var string $status  pending | published | all
 * @var array  $counts  pending, published, all
 * @var int    $page
 * @var int    $pages
 * @var int    $total
 */
View::partial('admin_header', ['adminTab' => $adminTab]);

$tabs = [
    'pending'   => ['Waiting', (int) $counts['pending']],
    'published' => ['Published', (int) $counts['published']],
    'all'       => ['All', (int) $counts['all']],
];
?>
<section class="section-pad-sm">
    <div class="container">
        <p class="text-muted">
            Reviews from people who asked a provider for a quote through Kopii go live on their own. Every other review waits here
            and does not count toward ratings or ranking until you approve it.
        </p>

        <nav class="filter-tabs" aria-label="Review status">
            <?php foreach ($tabs as $key => [$label, $n]): ?>
                <a class="filter-tab" href="<?= e(route_url('/admin/reviews', ['status' => $key])) ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>>
                    <?= e($label) ?> <span class="tnum">(<?= $n ?>)</span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (empty($reviews)): ?>
            <p class="empty-state">
                <?= $status === 'pending' ? 'No reviews are waiting. You are all caught up.' : 'No reviews here yet.' ?>
            </p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Provider</th>
                            <th scope="col">Reviewer</th>
                            <th scope="col">Rating</th>
                            <th scope="col">Review</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $rev): ?>
                            <tr>
                                <td><a href="<?= e(route_url('/admin/providers/' . (int) $rev['provider_id'])) ?>"><?= e($rev['business_name']) ?></a></td>
                                <td>
                                    <?= e($rev['reviewer_name']) ?>
                                    <span class="cell-sub">
                                        <?php if (!empty($rev['reviewer_city'])): ?><?= e($rev['reviewer_city']) ?> &middot; <?php endif; ?>
                                        <?= e(time_ago((string) $rev['created_at'])) ?>
                                    </span>
                                    <?php if (!empty($rev['service_hired'])): ?>
                                        <span class="cell-sub">Hired: <?= e($rev['service_hired']) ?></span>
                                    <?php endif; ?>
                                    <span class="cell-sub">
                                        <?php if (!empty($rev['reviewer_email'])): ?>
                                            Signed in &middot; email <?= empty($rev['reviewer_email_verified_at']) ? 'not confirmed' : 'confirmed' ?>
                                        <?php else: ?>
                                            No account
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="tnum"><?= (int) $rev['rating'] ?>/5</td>
                                <td class="review-text"><?= e($rev['comment']) ?></td>
                                <td>
                                    <?php if ($rev['status'] === 'published'): ?>
                                        <span class="badge badge-avail">Published</span>
                                        <?php if (!empty($rev['verified_request'])): ?>
                                            <span class="cell-sub">Requested a quote</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-type">Waiting</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cell-actions">
                                        <?php if ($rev['status'] !== 'published'): ?>
                                            <form method="POST" action="<?= e(route_url('/admin/reviews/moderate')) ?>">
                                                <?= csrf_field() ?>
                                                <?= admin_return_field() ?>
                                                <input type="hidden" name="review_id" value="<?= (int) $rev['id'] ?>">
                                                <input type="hidden" name="review_action" value="approve">
                                                <button type="submit" class="btn btn-outline btn-sm">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="<?= e(route_url('/admin/reviews/moderate')) ?>" data-confirm="Yes, delete">
                                            <?= csrf_field() ?>
                                            <?= admin_return_field() ?>
                                            <input type="hidden" name="review_id" value="<?= (int) $rev['id'] ?>">
                                            <input type="hidden" name="review_action" value="delete">
                                            <button type="submit" class="btn btn-danger-outline btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php View::partial('admin_pager', ['page' => $page, 'pages' => $pages, 'total' => $total, 'path' => '/admin/reviews', 'query' => ['status' => $status]]); ?>
    </div>
</section>
