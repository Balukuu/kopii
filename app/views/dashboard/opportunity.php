<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array $opp       from OpportunityModel::find()
 * @var bool  $isOpen
 * @var array $responses from OpportunityModel::responses()
 * @var array $kinds
 */
$id = (int) $opp['id'];
if ($opp['status'] === 'suspended') {
    [$stateLabel, $stateClass] = ['Hidden by Kopii', 'badge-out'];
} elseif ($opp['status'] === 'filled') {
    [$stateLabel, $stateClass] = ['Filled', 'badge-verified'];
} elseif ($opp['status'] === 'closed') {
    [$stateLabel, $stateClass] = ['Closed', 'badge-type'];
} else {
    [$stateLabel, $stateClass] = $isOpen ? ['Open', 'badge-avail'] : ['Expired', 'badge-type'];
}

$statusButton = static function (int $responseId, string $status, string $label, string $class = 'btn-outline') : string {
    return '<form method="POST" action="' . e(route_url('/dashboard/opportunities/response')) . '">'
        . csrf_field()
        . '<input type="hidden" name="response_id" value="' . $responseId . '">'
        . '<input type="hidden" name="status" value="' . e($status) . '">'
        . '<button type="submit" class="btn ' . e($class) . ' btn-sm">' . e($label) . '</button></form>';
};
?>
<section class="dash-header">
    <div class="container">
        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem;">
            <div>
                <div class="card-badges" style="margin-block-end:0.4rem;">
                    <span class="badge <?= e($stateClass) ?>"><?= e($stateLabel) ?></span>
                    <span class="badge badge-featured"><?= e($kinds[$opp['kind']] ?? ucfirst((string) $opp['kind'])) ?></span>
                </div>
                <h1 style="margin:0; font-size:clamp(1.35rem, 2.4vw, 1.85rem); color:#fff; text-wrap:balance;"><?= e($opp['title']) ?></h1>
                <p style="margin:0.25rem 0 0; color:#cbd5e1; font-size:0.92rem;">
                    <?= e(budget_range($opp['budget_min_ugx'], $opp['budget_max_ugx'])) ?> &middot; <?= e($opp['city']) ?> &middot; closes <?= e(date('j M Y', (int) strtotime((string) $opp['closes_on']))) ?>
                </p>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                <a href="<?= e(route_url('/dashboard/opportunities')) ?>" class="btn btn-outline btn-sm">All my opportunities</a>
                <a href="<?= e(route_url('/opportunities/' . $id)) ?>" class="btn btn-outline btn-sm"><?= svg_icon('globe', 16) ?> View public page</a>
            </div>
        </div>
    </div>
</section>

<section class="section-pad-sm">
    <div class="container">
        <?php if ($opp['status'] === 'suspended'): ?>
            <div class="alert alert-warning" role="status">The Kopii team has hidden this opportunity, so providers cannot see it or send proposals. Check your email for the reason.</div>
        <?php endif; ?>

        <div class="profile-layout" style="margin-block-start:0;">
            <div>
                <div class="content-panel">
                    <h2>Proposals (<?= count($responses) ?>)</h2>
                    <?php if (empty($responses)): ?>
                        <p class="text-muted m-0">No proposals yet. You will get an email when one arrives.</p>
                    <?php else: ?>
                        <?php foreach ($responses as $r): ?>
                            <?php
                            $rid = (int) $r['id'];
                            $waText = 'Hi ' . $r['business_name'] . ', thanks for your proposal for "' . $opp['title'] . '" on Kopii.';
                            ?>
                            <article class="proposal-card<?= $r['status'] === 'hired' ? ' is-hired' : '' ?>">
                                <div class="proposal-head">
                                    <div>
                                        <div class="card-badges" style="margin-block-end:0.35rem;">
                                            <span class="badge <?= $r['status'] === 'hired' ? 'badge-verified' : ($r['status'] === 'shortlisted' ? 'badge-featured' : 'badge-type') ?>"><?= e(strtoupper((string) $r['status'])) ?></span>
                                            <?php if (!empty($r['is_verified'])): ?>
                                                <span class="badge badge-verified"><?= svg_icon('verified', 16) ?> Verified</span>
                                            <?php endif; ?>
                                        </div>
                                        <h3><a href="<?= e(route_url('/provider/' . $r['provider_slug'])) ?>"><?= e($r['business_name']) ?></a></h3>
                                        <span class="text-muted" style="font-size:0.86rem;"><?= e($r['provider_category']) ?> &middot; <?= e($r['provider_city']) ?> &middot; sent <?= e(time_ago((string) $r['created_at'])) ?></span>
                                    </div>
                                    <div style="text-align:end;">
                                        <?php if (!empty($r['quote_ugx'])): ?>
                                            <strong class="tnum" style="font-size:1.15rem;"><?= e(format_ugx($r['quote_ugx'])) ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">No price given</span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['timeline'])): ?>
                                            <div class="text-muted" style="font-size:0.86rem;"><?= e($r['timeline']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <p class="proposal-message"><?= e($r['message']) ?></p>

                                <div class="proposal-actions">
                                    <a href="<?= e(whatsapp_url((string) $r['provider_whatsapp'], $waText)) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm"><?= svg_icon('whatsapp', 16) ?> WhatsApp</a>
                                    <a href="<?= e(tel_url((string) $r['provider_phone'])) ?>" class="btn btn-outline btn-sm"><?= svg_icon('phone', 16) ?> Call</a>
                                    <?php if ($r['status'] !== 'hired'): ?>
                                        <?= $statusButton($rid, 'hired', 'Choose this provider', 'btn-primary') ?>
                                    <?php endif; ?>
                                    <?php if ($r['status'] !== 'shortlisted' && $r['status'] !== 'hired'): ?>
                                        <?= $statusButton($rid, 'shortlisted', 'Shortlist') ?>
                                    <?php endif; ?>
                                    <?php if ($r['status'] !== 'declined' && $r['status'] !== 'hired'): ?>
                                        <?= $statusButton($rid, 'declined', 'Decline', 'btn-ghost') ?>
                                    <?php elseif ($r['status'] === 'declined'): ?>
                                        <?= $statusButton($rid, 'new', 'Undo decline', 'btn-ghost') ?>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="content-panel">
                <h2>This opportunity</h2>
                <p class="text-muted" style="margin-block-start:0; font-size:0.9rem;">
                    <?= (int) $opp['views_count'] ?> <?= (int) $opp['views_count'] === 1 ? 'view' : 'views' ?> &middot; posted <?= e(time_ago((string) $opp['created_at'])) ?>
                </p>

                <?php if ($opp['status'] === 'suspended'): ?>
                    <p class="text-muted m-0">Only the Kopii team can make it visible again.</p>
                <?php else: ?>
                    <div style="display:grid; gap:0.5rem;">
                        <?php if ($opp['status'] === 'open' && $isOpen): ?>
                            <form method="POST" action="<?= e(route_url('/dashboard/opportunities/' . $id . '/status')) ?>" data-confirm="Yes, mark as filled">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="filled">
                                <button type="submit" class="btn btn-primary btn-block">I have found someone: mark as filled</button>
                            </form>
                            <form method="POST" action="<?= e(route_url('/dashboard/opportunities/' . $id . '/status')) ?>" data-confirm="Yes, close it">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="close">
                                <button type="submit" class="btn btn-outline btn-block">Close without choosing</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?= e(route_url('/dashboard/opportunities/' . $id . '/status')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reopen">
                                <button type="submit" class="btn btn-primary btn-block">Re-open this opportunity</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>
