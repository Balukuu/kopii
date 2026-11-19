<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var string $tab          posted | proposals
 * @var ?array $provider
 * @var array  $posted       rows from OpportunityModel::forUser()
 * @var array  $proposals    rows from OpportunityModel::responsesByProvider()
 * @var int    $newProposals proposals waiting on what this account posted
 * @var array  $kinds
 */
$stateOf = static function (array $o): array {
    if ($o['status'] === 'suspended') {
        return ['Hidden by Kopii', 'badge-out'];
    }
    if ($o['status'] === 'filled') {
        return ['Filled', 'badge-verified'];
    }
    if ($o['status'] === 'closed') {
        return ['Closed', 'badge-type'];
    }
    return OpportunityModel::isOpen($o) ? ['Open', 'badge-avail'] : ['Expired', 'badge-type'];
};
?>
<section class="dash-header">
    <div class="container">
        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem;">
            <div>
                <h1 style="margin:0; font-size:clamp(1.45rem, 2.6vw, 2rem); color:#fff;">My opportunities</h1>
                <p style="margin:0.25rem 0 0; color:#cbd5e1; font-size:0.92rem;">What you posted for providers, and the proposals you sent.</p>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                <a href="<?= e(route_url('/dashboard')) ?>" class="btn btn-outline btn-sm">Back to dashboard</a>
                <a href="<?= e(route_url('/opportunities/new')) ?>" class="btn btn-primary btn-sm"><?= svg_icon('plus', 16) ?> Post an opportunity</a>
            </div>
        </div>

        <nav class="dash-tabs" aria-label="My opportunities">
            <a class="dash-tab" href="<?= e(route_url('/dashboard/opportunities')) ?>"<?= $tab === 'posted' ? ' aria-current="page"' : '' ?>>
                Posted by me (<?= count($posted) ?>)<?php if ($newProposals > 0): ?> <span class="tab-count tnum"><?= (int) $newProposals ?><span class="visually-hidden"> new proposals</span></span><?php endif; ?>
            </a>
            <?php if ($provider !== null): ?>
                <a class="dash-tab" href="<?= e(route_url('/dashboard/opportunities', ['tab' => 'proposals'])) ?>"<?= $tab === 'proposals' ? ' aria-current="page"' : '' ?>>
                    My proposals (<?= count($proposals) ?>)
                </a>
            <?php endif; ?>
        </nav>
    </div>
</section>

<section class="section-pad-sm">
    <div class="container">
        <?php if ($tab === 'posted'): ?>
            <?php if (empty($posted)): ?>
                <div class="board-cta">
                    <div>
                        <h2>Need something done?</h2>
                        <p>Post a project, job, tender or supply request and let providers send you proposals.</p>
                    </div>
                    <a class="btn btn-primary" href="<?= e(route_url('/opportunities/new')) ?>">Post an opportunity</a>
                </div>
            <?php else: ?>
                <div class="opp-list">
                    <?php foreach ($posted as $o): ?>
                        <?php [$stateLabel, $stateClass] = $stateOf($o); ?>
                        <article class="opp-card">
                            <div class="card-badges">
                                <span class="badge <?= e($stateClass) ?>"><?= e($stateLabel) ?></span>
                                <span class="badge badge-featured"><?= e($kinds[$o['kind']] ?? ucfirst((string) $o['kind'])) ?></span>
                                <?php if ((int) $o['new_count'] > 0): ?>
                                    <span class="badge badge-verified"><?= (int) $o['new_count'] ?> new</span>
                                <?php endif; ?>
                            </div>
                            <h3><a class="card-link" href="<?= e(route_url('/dashboard/opportunities/' . (int) $o['id'])) ?>"><?= e($o['title']) ?></a></h3>
                            <div class="opp-meta">
                                <span class="opp-budget tnum"><?= e(budget_range($o['budget_min_ugx'], $o['budget_max_ugx'])) ?></span>
                                <span><?= svg_icon('map-pin', 16) ?> <?= e($o['city']) ?></span>
                                <span><?= (int) $o['response_count'] ?> <?= (int) $o['response_count'] === 1 ? 'proposal' : 'proposals' ?></span>
                                <span><?= svg_icon('clock', 16) ?> Closes <?= e(date('j M Y', (int) strtotime((string) $o['closes_on']))) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <?php if (empty($proposals)): ?>
                <div class="board-cta">
                    <div>
                        <h2>No proposals yet</h2>
                        <p>Find a project, job or tender that fits what you do and send your price and timeline.</p>
                    </div>
                    <a class="btn btn-primary" href="<?= e(route_url('/opportunities')) ?>">Browse opportunities</a>
                </div>
            <?php else: ?>
                <?php foreach ($proposals as $r): ?>
                    <article class="proposal-card<?= $r['status'] === 'hired' ? ' is-hired' : '' ?>">
                        <div class="proposal-head">
                            <div>
                                <div class="card-badges" style="margin-block-end:0.35rem;">
                                    <span class="badge <?= $r['status'] === 'hired' ? 'badge-verified' : ($r['status'] === 'shortlisted' ? 'badge-featured' : 'badge-type') ?>"><?= e(ucfirst((string) $r['status'])) ?></span>
                                    <span class="badge badge-type"><?= e($kinds[$r['kind']] ?? ucfirst((string) $r['kind'])) ?></span>
                                    <?php if ($r['opportunity_status'] !== 'open'): ?>
                                        <span class="badge badge-type">Opportunity <?= e((string) $r['opportunity_status']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <h3><a href="<?= e(route_url('/opportunities/' . (int) $r['opportunity_id'])) ?>"><?= e($r['title']) ?></a></h3>
                            </div>
                            <span class="text-muted" style="font-size:0.86rem;">Sent <?= e(time_ago((string) $r['created_at'])) ?></span>
                        </div>
                        <p class="proposal-message"><?= e($r['message']) ?></p>
                        <div class="proposal-actions">
                            <?php if (!empty($r['quote_ugx'])): ?>
                                <span>Your price: <strong class="tnum"><?= e(format_ugx($r['quote_ugx'])) ?></strong></span>
                            <?php endif; ?>
                            <?php if (!empty($r['timeline'])): ?>
                                <span>Timeline: <strong><?= e($r['timeline']) ?></strong></span>
                            <?php endif; ?>
                            <?php if ($r['status'] !== 'hired'): ?>
                                <form method="POST" action="<?= e(route_url('/dashboard/opportunities/withdraw')) ?>" data-confirm="Yes, withdraw" style="margin-inline-start:auto;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="response_id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="btn btn-danger-outline btn-sm">Withdraw</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
