<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array  $plans     from PlanModel::all()
 * @var ?array $editing   the plan being edited, if any
 * @var array  $rates     from FeeModel::rates()
 * @var array  $renewals  from PlanModel::listingsOnPaidPlans()
 * @var int    $siteLimit the site-wide product limit
 */
View::partial('admin_header', ['adminTab' => $adminTab]);

$e         = $editing;
$editingId = $e !== null ? (int) $e['id'] : 0;
$isDefault = $e !== null && !empty($e['is_default']);
$today     = date('Y-m-d');
$example   = 500000;
$svcCut    = FeeModel::commissionFor($example, FeeModel::SERVICE);
$prodCut   = FeeModel::commissionFor($example, FeeModel::PRODUCT);

/** How a listing's plan stands today: label and badge class. */
$renewalState = static function (?string $ends) use ($today): array {
    if ($ends === null || $ends === '') {
        return ['No end date', 'badge-type'];
    }
    if ($ends < $today) {
        return ['Ended: back on the free plan', 'badge-type'];
    }
    $days = (int) round((strtotime($ends) - strtotime($today)) / 86400);
    if ($days <= 14) {
        return [$days === 0 ? 'Ends today' : 'Ends in ' . $days . ($days === 1 ? ' day' : ' days'), 'badge-featured'];
    }
    return ['Active', 'badge-avail'];
};
?>
<section class="section-pad-sm">
    <div class="container">
        <p class="text-muted">
            What a listing is on, and what Kopii would take from a booking. <strong>Kopii does not take payment through the site yet.</strong>
            A business pays you by Mobile Money, you confirm it, then give their listing the plan from its
            <a href="<?= e(route_url('/admin/providers')) ?>">Listings</a> page. When the end date passes, the listing returns to the free plan by itself and nothing is deleted.
        </p>

        <section class="admin-card" aria-labelledby="fees-title">
            <h2 id="fees-title">Commission rates</h2>
            <p>
                Starting rates for launch. They are kept here so the numbers are decided in one place; <strong>nothing is charged and the public site does not quote them</strong>
                until bookings and checkout are paid through Kopii.
            </p>
            <form method="POST" action="<?= e(route_url('/admin/fees/save')) ?>" class="stack-form">
                <?= csrf_field() ?>
                <div class="form-row-2">
                    <div class="field">
                        <label for="rate-service">Service bookings (%)</label>
                        <input type="text" inputmode="decimal" id="rate-service" name="service_rate" value="<?= e(FeeModel::label($rates['service'])) ?>" maxlength="6" required>
                        <span class="hint">Taken from the provider when a payment clears. On UGX <?= number_format($example) ?> that is UGX <?= number_format($svcCut['commission']) ?>, and the provider receives UGX <?= number_format($svcCut['payout']) ?>.</span>
                    </div>
                    <div class="field">
                        <label for="rate-product">Shop products (%)</label>
                        <input type="text" inputmode="decimal" id="rate-product" name="product_rate" value="<?= e(FeeModel::label($rates['product'])) ?>" maxlength="6" required>
                        <span class="hint">Taken from the seller once the shop has a checkout. On UGX <?= number_format($example) ?> that is UGX <?= number_format($prodCut['commission']) ?>. One rate for every category for now.</span>
                    </div>
                </div>
                <div class="cell-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Save rates</button>
                </div>
            </form>
        </section>

        <section class="admin-card" aria-labelledby="plans-title">
            <h2 id="plans-title">Plans</h2>
            <p>
                Every listing starts on the free plan. A paid plan costs a price for a number of months (0 is allowed, for a free founding-member period).
                A product limit only counts if you set one: leave it empty to use the site-wide limit of <?= (int) $siteLimit ?>.
            </p>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Plan</th>
                            <th scope="col">Price</th>
                            <th scope="col">Products</th>
                            <th scope="col">Listings on it</th>
                            <th scope="col">Status</th>
                            <th scope="col">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan): ?>
                            <tr>
                                <td>
                                    <strong><?= e($plan['name']) ?></strong>
                                    <?php if (!empty($plan['is_default'])): ?><span class="badge badge-type">Default</span><?php endif; ?>
                                    <?php if (!empty($plan['description'])): ?><span class="cell-sub"><?= e($plan['description']) ?></span><?php endif; ?>
                                </td>
                                <td class="tnum">
                                    <?php if (!empty($plan['is_default'])): ?>
                                        Free
                                    <?php else: ?>
                                        <?= (int) $plan['price_ugx'] > 0 ? 'UGX ' . number_format((int) $plan['price_ugx']) : 'Free' ?>
                                        <span class="cell-sub">for <?= (int) $plan['period_months'] ?> <?= (int) $plan['period_months'] === 1 ? 'month' : 'months' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="tnum"><?= $plan['product_limit'] !== null ? (int) $plan['product_limit'] : 'Site limit (' . (int) $siteLimit . ')' ?></td>
                                <td class="tnum"><?= (int) $plan['listings'] ?></td>
                                <td>
                                    <span class="badge <?= !empty($plan['is_active']) ? 'badge-avail' : 'badge-type' ?>"><?= !empty($plan['is_active']) ? 'On sale' : 'Retired' ?></span>
                                </td>
                                <td>
                                    <a class="btn btn-outline btn-sm" href="<?= e(route_url('/admin/plans', ['edit' => (string) (int) $plan['id']])) ?>#plan-form">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-card" id="plan-form" aria-labelledby="plan-form-title">
            <h2 id="plan-form-title"><?= $e !== null ? 'Edit the plan "' . e($e['name']) . '"' : 'Add a plan' ?></h2>
            <form method="POST" action="<?= e(route_url('/admin/plans/save')) ?>" class="stack-form">
                <?= csrf_field() ?>
                <?php if ($e !== null): ?>
                    <input type="hidden" name="plan_id" value="<?= $editingId ?>">
                <?php endif; ?>

                <div class="form-row-2">
                    <div class="field">
                        <label for="plan-name">Name *</label>
                        <input type="text" id="plan-name" name="name" value="<?= e($e['name'] ?? '') ?>" maxlength="<?= PlanModel::MAX_NAME ?>" required>
                    </div>
                    <div class="field">
                        <label for="plan-sort">Order</label>
                        <input type="text" inputmode="numeric" id="plan-sort" name="sort_order" value="<?= (int) ($e['sort_order'] ?? count($plans)) ?>" maxlength="3">
                        <span class="hint">Smaller numbers come first.</span>
                    </div>
                </div>

                <div class="field">
                    <label for="plan-desc">Short description</label>
                    <input type="text" id="plan-desc" name="description" value="<?= e($e['description'] ?? '') ?>" maxlength="<?= PlanModel::MAX_DESC ?>" placeholder="What the business gets, in one line">
                </div>

                <?php if (!$isDefault): ?>
                    <div class="form-row-2">
                        <div class="field">
                            <label for="plan-price">Price (UGX) *</label>
                            <input type="text" inputmode="numeric" id="plan-price" name="price_ugx" value="<?= $e !== null ? (int) $e['price_ugx'] : '' ?>" maxlength="11" required>
                            <span class="hint">Whole shillings. 0 is allowed.</span>
                        </div>
                        <div class="field">
                            <label for="plan-months">Covers how many months *</label>
                            <input type="text" inputmode="numeric" id="plan-months" name="period_months" value="<?= $e !== null ? (int) $e['period_months'] : '1' ?>" maxlength="2" required>
                            <span class="hint">1 to <?= PlanModel::MAX_MONTHS ?>. Used to suggest an end date when you give a listing this plan.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="field" style="max-inline-size:16rem;">
                    <label for="plan-limit">Shop products allowed</label>
                    <input type="text" inputmode="numeric" id="plan-limit" name="product_limit" value="<?= $e !== null && $e['product_limit'] !== null ? (int) $e['product_limit'] : '' ?>" maxlength="4" placeholder="Site limit (<?= (int) $siteLimit ?>)">
                    <span class="hint">Leave empty for the site-wide limit.</span>
                </div>

                <?php if (!$isDefault): ?>
                    <label class="check-row">
                        <input type="checkbox" name="is_active" value="1"<?= $e === null || !empty($e['is_active']) ? ' checked' : '' ?>>
                        <span>On sale: can be given to listings. Untick to retire it; listings already on it keep it until their end date.</span>
                    </label>
                <?php endif; ?>

                <div class="cell-actions">
                    <button type="submit" class="btn btn-primary btn-sm"><?= $e !== null ? 'Save changes' : 'Add plan' ?></button>
                    <?php if ($e !== null): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(route_url('/admin/plans')) ?>#plan-form">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="admin-card" aria-labelledby="renewals-title">
            <h2 id="renewals-title">Listings on a plan</h2>
            <?php if (empty($renewals)): ?>
                <p class="empty-state">No listing has been given a plan yet. Open a listing and use &ldquo;Plan and featuring&rdquo; to give it one.</p>
            <?php else: ?>
                <p>Soonest end date first, so you can chase renewals before they lapse.</p>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Listing</th>
                                <th scope="col">Plan</th>
                                <th scope="col">Ends</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($renewals as $r): ?>
                                <?php [$stateLabel, $stateClass] = $renewalState($r['plan_expires_on'] ?? null); ?>
                                <tr>
                                    <td><a href="<?= e(route_url('/admin/providers/' . (int) $r['id'])) ?>#plan-title"><strong><?= e($r['business_name']) ?></strong></a></td>
                                    <td><?= e($r['plan_name']) ?></td>
                                    <td class="tnum"><?= !empty($r['plan_expires_on']) ? e(date('j M Y', (int) strtotime((string) $r['plan_expires_on']))) : '&mdash;' ?></td>
                                    <td><span class="badge <?= e($stateClass) ?>"><?= e($stateLabel) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
