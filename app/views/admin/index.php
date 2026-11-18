<?php
defined('KOPII_APP') or exit('Direct access denied.');
?>
<section class="dash-header">
    <div class="container">
        <span class="badge badge-featured" style="margin-block-end:0.4rem;"><?= svg_icon('shield', 16) ?> Platform Administration</span>
        <h1 style="margin:0; color:#fff; font-size:1.85rem;">Kopii Marketplace Moderation</h1>
        <p style="margin:0.3rem 0 0; color:#cbd5e1;">Verify trusted Ugandan freelancers &amp; businesses, manage featured placements, and monitor activity.</p>
    </div>
</section>

<section class="section-pad-sm">
    <div class="container">
        <div class="kpi-grid">
            <div class="kpi-card">
                <span>Active Providers</span>
                <strong class="tnum"><?= (int) $stats['providers'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>Portfolio Items</span>
                <strong class="tnum"><?= (int) $stats['portfolios'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>Ugandan Cities</span>
                <strong class="tnum"><?= (int) $stats['cities'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>Published Reviews</span>
                <strong class="tnum"><?= (int) $stats['reviews'] ?></strong>
            </div>
        </div>

        <h2 class="m-0" style="margin-block-end:1rem;">Providers</h2>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Business / Freelancer</th>
                        <th scope="col">Category &amp; City</th>
                        <th scope="col">Work / Leads</th>
                        <th scope="col">Badges &amp; Status</th>
                        <th scope="col">Moderation Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($providers as $p): ?>
                        <tr>
                            <td>
                                <a href="<?= e(route_url('/provider/' . $p['slug'])) ?>"><strong><?= e($p['business_name']) ?></strong></a>
                                <div class="text-muted" style="font-size:0.8rem;"><?= e($p['owner_name']) ?> &middot; <?= e($p['owner_email']) ?></div>
                            </td>
                            <td>
                                <?= e($p['category_name']) ?>
                                <div class="text-muted" style="font-size:0.8rem;"><?= e($p['city']) ?> &middot; <?= e(format_ugx($p['starting_price_ugx'])) ?></div>
                            </td>
                            <td>
                                <strong class="tnum"><?= (int) $p['portfolio_count'] ?></strong> work &middot; <strong class="tnum"><?= (int) $p['inquiry_count'] ?></strong> leads
                            </td>
                            <td>
                                <div style="display:flex; flex-wrap:wrap; gap:0.3rem;">
                                    <span class="badge <?= $p['status'] === 'active' ? 'badge-avail' : 'badge-type' ?>"><?= e($p['status']) ?></span>
                                    <?php if (!empty($p['is_verified'])): ?>
                                        <span class="badge badge-verified">Verified</span>
                                    <?php endif; ?>
                                    <?php if (!empty($p['is_featured'])): ?>
                                        <span class="badge badge-featured">Featured</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; flex-wrap:wrap; gap:0.35rem;">
                                    <form method="POST" action="<?= e(route_url('/admin/moderate')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="mod_action" value="toggle_verified">
                                        <button type="submit" class="btn btn-outline btn-sm">
                                            <?= !empty($p['is_verified']) ? 'Unverify' : 'Verify' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= e(route_url('/admin/moderate')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="mod_action" value="toggle_featured">
                                        <button type="submit" class="btn btn-outline btn-sm">
                                            <?= !empty($p['is_featured']) ? 'Unfeature' : 'Feature' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= e(route_url('/admin/moderate')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="mod_action" value="toggle_status">
                                        <button type="submit" class="btn btn-danger-outline btn-sm">
                                            <?= $p['status'] === 'active' ? 'Pause' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 id="reviews" class="m-0" style="margin-block:2.5rem 0.5rem;">Reviews</h2>
        <p class="text-muted" style="margin-block-end:1rem;">
            <strong class="tnum"><?= (int) $pendingReviews ?></strong> waiting for approval.
            Reviews from people who requested a quote through Kopii go live automatically. All others wait here and do not count toward ratings until you approve them.
        </p>

        <?php if (empty($reviews)): ?>
            <p class="text-muted">No reviews yet.</p>
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
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $rev): ?>
                            <tr>
                                <td><a href="<?= e(route_url('/provider/' . $rev['provider_slug'])) ?>"><?= e($rev['business_name']) ?></a></td>
                                <td>
                                    <?= e($rev['reviewer_name']) ?>
                                    <div class="text-muted" style="font-size:0.8rem;"><?= e(time_ago((string) $rev['created_at'])) ?></div>
                                </td>
                                <td class="tnum"><?= (int) $rev['rating'] ?>/5</td>
                                <td style="max-inline-size:28rem;"><?= e($rev['comment']) ?></td>
                                <td>
                                    <?php if ($rev['status'] === 'published'): ?>
                                        <span class="badge badge-avail">Published</span>
                                        <?php if (!empty($rev['verified_request'])): ?>
                                            <div class="text-muted" style="font-size:0.8rem;">Requested a quote</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-type">Waiting</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; flex-wrap:wrap; gap:0.35rem;">
                                        <?php if ($rev['status'] !== 'published'): ?>
                                            <form method="POST" action="<?= e(route_url('/admin/reviews/moderate')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="review_id" value="<?= (int) $rev['id'] ?>">
                                                <input type="hidden" name="review_action" value="approve">
                                                <button type="submit" class="btn btn-outline btn-sm">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="<?= e(route_url('/admin/reviews/moderate')) ?>">
                                            <?= csrf_field() ?>
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
    </div>
</section>
