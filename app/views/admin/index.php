<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array $attention pending_reviews, unverified_providers, unconfirmed_emails
 * @var array $week      accounts, listings, products, opportunities, proposals, inquiries, reviews (last 7 days)
 * @var array $stats     providers, portfolios, cities, reviews
 * @var int   $hidden    listings that are not public
 * @var array $recent    latest admin_log rows
 */
View::partial('admin_header', ['adminTab' => $adminTab]);
$allClear = (int) $attention['pending_reviews'] === 0 && (int) $attention['unverified_providers'] === 0;
?>
<section class="section-pad-sm">
    <div class="container">
        <h2 class="admin-section-title">Needs your attention</h2>

        <?php if ($allClear): ?>
            <p class="all-clear"><?= svg_icon('check', 16) ?> You are all caught up: no reviews are waiting and every live listing is Verified.</p>
        <?php endif; ?>

        <div class="attention-grid">
            <a class="attention-card<?= (int) $attention['pending_reviews'] > 0 ? ' is-urgent' : '' ?>" href="<?= e(route_url('/admin/reviews', ['status' => 'pending'])) ?>">
                <span class="attention-label">Reviews waiting</span>
                <strong class="tnum"><?= (int) $attention['pending_reviews'] ?></strong>
                <span class="attention-hint">Not public, and not counted in ratings, until you approve them.</span>
            </a>
            <a class="attention-card<?= (int) $attention['unverified_providers'] > 0 ? ' is-urgent' : '' ?>" href="<?= e(route_url('/admin/providers', ['status' => 'active', 'verified' => '0'])) ?>">
                <span class="attention-label">Listings to verify</span>
                <strong class="tnum"><?= (int) $attention['unverified_providers'] ?></strong>
                <span class="attention-hint">Live listings that do not have the Verified badge yet.</span>
            </a>
            <a class="attention-card" href="<?= e(route_url('/admin/users', ['email' => 'unconfirmed'])) ?>">
                <span class="attention-label">Emails to confirm</span>
                <strong class="tnum"><?= (int) $attention['unconfirmed_emails'] ?></strong>
                <span class="attention-hint">Accounts that have not confirmed their address. You can resend the link.</span>
            </a>
        </div>

        <h2 class="admin-section-title">Last 7 days</h2>
        <div class="kpi-grid">
            <div class="kpi-card">
                <span>New accounts</span>
                <strong class="tnum"><?= (int) $week['accounts'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>New listings</span>
                <strong class="tnum"><?= (int) $week['listings'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>New products</span>
                <strong class="tnum"><?= (int) $week['products'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>New opportunities</span>
                <strong class="tnum"><?= (int) $week['opportunities'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>Proposals sent</span>
                <strong class="tnum"><?= (int) $week['proposals'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>Quote requests &amp; enquiries</span>
                <strong class="tnum"><?= (int) $week['inquiries'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>New reviews</span>
                <strong class="tnum"><?= (int) $week['reviews'] ?></strong>
            </div>
        </div>

        <h2 class="admin-section-title">Marketplace</h2>
        <div class="kpi-grid">
            <div class="kpi-card">
                <span>Live listings</span>
                <strong class="tnum"><?= (int) $stats['providers'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>Hidden listings</span>
                <strong class="tnum"><?= (int) $hidden ?></strong>
            </div>
            <div class="kpi-card">
                <span>Portfolio items</span>
                <strong class="tnum"><?= (int) $stats['portfolios'] ?></strong>
            </div>
            <div class="kpi-card">
                <span>Published reviews</span>
                <strong class="tnum"><?= (int) $stats['reviews'] ?></strong>
            </div>
        </div>

        <h2 class="admin-section-title">Recent admin activity</h2>
        <?php if (empty($recent)): ?>
            <p class="empty-state">Nothing yet. Verifying a listing, pausing one or moderating a review will be recorded here.</p>
        <?php else: ?>
            <div class="admin-card">
                <ul class="log-list">
                    <?php foreach ($recent as $row): ?>
                        <li>
                            <span><?= e(AdminLogModel::describe($row)) ?></span>
                            <?php if ((string) $row['note'] !== ''): ?>
                                <span class="log-note">&ldquo;<?= e($row['note']) ?>&rdquo;</span>
                            <?php endif; ?>
                            <span class="log-meta"><?= e($row['admin_name'] ?? 'An administrator') ?> &middot; <?= e(time_ago((string) $row['created_at'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <p><a href="<?= e(route_url('/admin/activity')) ?>">See all activity</a></p>
        <?php endif; ?>
    </div>
</section>
