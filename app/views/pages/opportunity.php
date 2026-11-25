<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array  $opp        from OpportunityModel::find()
 * @var bool   $isOpen     taking proposals right now
 * @var bool   $isOwner    the signed-in user posted it
 * @var ?array $provider   the signed-in user's provider profile, if any
 * @var ?array $myResponse their proposal on this opportunity, if any
 * @var array  $kinds      key => label
 */
$authUser = current_user();
$days     = OpportunityModel::daysLeft($opp);
$closing  = $days === 0 ? 'Closes today' : ($days === 1 ? 'Closes tomorrow' : 'Closes in ' . $days . ' days');
$hidden   = $opp['status'] === 'suspended' || !empty($opp['poster_suspended_at']);
$count    = (int) $opp['response_count'];
$statusNote = match (true) {
    $opp['status'] === 'filled'                          => 'This opportunity has been filled.',
    $opp['status'] === 'closed'                          => 'The person who posted this has closed it.',
    $opp['status'] === 'open' && !$isOpen                => 'The closing date has passed, so this is no longer taking proposals.',
    default                                              => '',
};
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="<?= e(route_url('/opportunities')) ?>">Opportunities</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page"><?= e(text_excerpt((string) $opp['title'], 60)) ?></li>
        </ol>
    </div>
</nav>

<section class="section-pad-sm">
    <div class="container">
        <?php if ($hidden): ?>
            <div class="alert alert-warning" role="status">The Kopii team has hidden this opportunity, so only the person who posted it and the administrators can see it.</div>
        <?php elseif ($statusNote !== ''): ?>
            <div class="alert alert-warning" role="status"><?= e($statusNote) ?></div>
        <?php endif; ?>

        <div class="profile-layout" style="margin-block-start:0;">
            <div>
                <div class="content-panel">
                    <div class="card-badges" style="margin-block-end:0.75rem;">
                        <span class="badge badge-featured"><?= e($kinds[$opp['kind']] ?? ucfirst((string) $opp['kind'])) ?></span>
                        <?php if (!empty($opp['category_name'])): ?>
                            <span class="badge badge-type"><?= e($opp['category_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h1 style="margin:0 0 1rem; font-size:var(--text-2xl); line-height:1.2; text-wrap:balance;"><?= e($opp['title']) ?></h1>
                    <div class="opp-body"><?= e($opp['description']) ?></div>
                </div>

                <section class="content-panel" id="proposal" aria-labelledby="proposal-heading">
                    <h2 id="proposal-heading">Send a proposal</h2>

                    <?php if ($isOwner): ?>
                        <p class="text-muted">This is your opportunity. <?= $count === 0 ? 'No proposals have arrived yet.' : 'You have ' . $count . ($count === 1 ? ' proposal' : ' proposals') . '.' ?></p>
                        <a class="btn btn-primary" href="<?= e(route_url('/dashboard/opportunities/' . (int) $opp['id'])) ?>">Manage proposals</a>

                    <?php elseif (!$isOpen): ?>
                        <p class="text-muted m-0">This opportunity is not taking proposals any more. <a href="<?= e(route_url('/opportunities')) ?>">See what is open</a>.</p>

                    <?php elseif (!$authUser): ?>
                        <p class="text-muted">Log in or create a free account to send a proposal. Your showcase profile is what the person who posted this will look at.</p>
                        <div style="display:flex; flex-wrap:wrap; gap:0.65rem;">
                            <a class="btn btn-primary" href="<?= e(route_url('/login')) ?>">Log in</a>
                            <a class="btn btn-outline" href="<?= e(route_url('/register')) ?>">Create an account</a>
                        </div>

                    <?php elseif ($provider === null): ?>
                        <p class="text-muted">To send a proposal you need a showcase profile, so the person who posted this can see who you are and how to reach you. It is free and takes a minute.</p>
                        <a class="btn btn-primary" href="<?= e(route_url('/dashboard')) ?>">Create my showcase profile</a>

                    <?php elseif ($provider['status'] !== 'active'): ?>
                        <p class="text-muted m-0">Your listing is hidden right now, so you cannot send proposals. Check your email for the reason.</p>

                    <?php elseif ($myResponse !== null): ?>
                        <p class="m-0">
                            <strong>You sent a proposal <?= e(time_ago((string) $myResponse['created_at'])) ?>.</strong>
                            Status: <span class="badge <?= $myResponse['status'] === 'hired' ? 'badge-verified' : 'badge-type' ?>"><?= e(ucfirst((string) $myResponse['status'])) ?></span>
                        </p>
                        <p class="m-0" style="margin-block-start:0.75rem;"><a href="<?= e(route_url('/dashboard/opportunities', ['tab' => 'proposals'])) ?>">See all my proposals</a></p>

                    <?php else: ?>
                        <p class="text-muted" style="margin-block-start:0;">Your proposal goes to the person who posted this, together with a link to your <strong><?= e($provider['business_name']) ?></strong> profile.</p>
                        <form method="POST" action="<?= e(route_url('/opportunities/' . (int) $opp['id'] . '/respond')) ?>" class="stack-form">
                            <?= csrf_field() ?>
                            <div class="visually-hidden" aria-hidden="true">
                                <label for="proposal_hp">Leave empty</label>
                                <input type="text" id="proposal_hp" name="proposal_hp" tabindex="-1" autocomplete="off">
                            </div>
                            <div class="field">
                                <label for="pr-message">Your message *</label>
                                <textarea id="pr-message" name="message" rows="6" minlength="20" maxlength="2000" required placeholder="How you would do this, similar work you have done, and anything you need from them."><?= e(old('message')) ?></textarea>
                            </div>
                            <div class="form-row-2">
                                <div class="field">
                                    <label for="pr-quote">Your price in UGX (optional)</label>
                                    <input type="text" inputmode="numeric" id="pr-quote" name="quote_ugx" value="<?= e(old('quote_ugx')) ?>" placeholder="e.g. 750000">
                                </div>
                                <div class="field">
                                    <label for="pr-time">How long it takes (optional)</label>
                                    <input type="text" id="pr-time" name="timeline" value="<?= e(old('timeline')) ?>" maxlength="80" placeholder="e.g. 10 days">
                                </div>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary">Send proposal</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="sticky-hire-card" aria-label="About this opportunity">
                <div class="rate-block">
                    <span class="rate-label">Budget</span>
                    <div class="rate-value tnum" style="font-size:var(--text-xl);"><?= e(budget_range($opp['budget_min_ugx'], $opp['budget_max_ugx'])) ?></div>
                </div>

                <?php if ($isOpen && !$isOwner): ?>
                    <div class="cta-stack">
                        <a class="btn btn-primary btn-lg btn-block" href="#proposal">Send a proposal</a>
                    </div>
                <?php endif; ?>

                <dl class="facts">
                    <div class="row-between">
                        <dt class="text-muted">Where</dt>
                        <dd class="dd-strong"><?= e($opp['city']) ?></dd>
                    </div>
                    <div class="row-between">
                        <dt class="text-muted">Closing</dt>
                        <dd class="dd-strong"><?= e(date('j M Y', (int) strtotime((string) $opp['closes_on']))) ?><?= $isOpen ? ' (' . e($closing) . ')' : '' ?></dd>
                    </div>
                    <div class="row-between">
                        <dt class="text-muted">Proposals</dt>
                        <dd class="dd-strong tnum"><?= $count ?></dd>
                    </div>
                    <div class="row-between">
                        <dt class="text-muted">Posted</dt>
                        <dd class="dd-strong"><?= e(time_ago((string) $opp['created_at'])) ?></dd>
                    </div>
                    <div class="row-between">
                        <dt class="text-muted">Posted by</dt>
                        <dd class="dd-strong"><?= e($opp['poster_name']) ?></dd>
                    </div>
                </dl>

                <div class="risk-free-box">
                    <?= svg_icon('shield', 20) ?>
                    <span><strong>Contact details stay private.</strong> The person who posted this sees your proposal and profile, and chooses whether to contact you.</span>
                </div>

                <?php if (!$hidden && !$isOwner): ?>
                    <?php View::partial('report_box', ['targetType' => 'opportunity', 'targetId' => (int) $opp['id'], 'noun' => 'opportunity']); ?>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>
