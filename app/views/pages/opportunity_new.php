<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var bool   $emailConfirmed
 * @var string $userEmail
 * @var array  $kinds
 * @var array  $categories
 * @var array  $cities
 * @var string $defaultCloses
 * @var string $minCloses
 * @var string $maxCloses
 */
$anywhere = OpportunityController::ANYWHERE;
?>
<section class="section-pad-sm">
    <div class="container" style="max-inline-size:52rem;">
        <nav aria-label="Breadcrumb" style="margin-block-end:1rem;">
            <ol class="breadcrumb-list">
                <li><a href="<?= e(route_url('/opportunities')) ?>">Opportunities</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page">Post an opportunity</li>
            </ol>
        </nav>

        <h1 style="margin:0 0 0.5rem; font-size:var(--text-2xl);">Post an opportunity</h1>
        <p class="text-muted" style="margin-block-start:0;">Tell providers what you need. They send proposals with a price and timeline, and you choose who to contact. Your phone number and email are not shown on the page.</p>

        <?php if (!$emailConfirmed): ?>
            <div class="alert alert-warning" role="status" style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:0.75rem;">
                <span><strong>Confirm your email address first.</strong> We sent a link to <?= e($userEmail) ?>. It keeps the board free of fake posts.</span>
                <form method="POST" action="<?= e(route_url('/verify-email/resend')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm">Send the link again</button>
                </form>
            </div>
        <?php else: ?>
            <div class="content-panel">
                <form method="POST" action="<?= e(route_url('/opportunities')) ?>" class="stack-form">
                    <?= csrf_field() ?>
                    <div class="visually-hidden" aria-hidden="true">
                        <label for="opp_confirm_hp">Leave empty</label>
                        <input type="text" id="opp_confirm_hp" name="opp_confirm_hp" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="op-kind">What kind of opportunity? *</label>
                            <select id="op-kind" name="kind" required>
                                <?php foreach ($kinds as $key => $label): ?>
                                    <option value="<?= e($key) ?>"<?= old('kind', 'project') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="op-cat">Category</label>
                            <select id="op-cat" name="category_id">
                                <option value="">Any</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['id'] ?>"<?= (int) old('category_id', '0') === (int) $cat['id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label for="op-title">Title *</label>
                        <input type="text" id="op-title" name="title" value="<?= e(old('title')) ?>" minlength="8" maxlength="160" required placeholder="e.g. Photographer for a wedding in Jinja on 14 March">
                    </div>

                    <div class="field">
                        <label for="op-desc">What do you need? *</label>
                        <textarea id="op-desc" name="description" rows="8" minlength="30" maxlength="4000" required placeholder="Describe the work, the date or deadline, what you will provide, and what a good proposal includes."><?= e(old('description')) ?></textarea>
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="op-min">Lowest budget in UGX (optional)</label>
                            <input type="text" inputmode="numeric" id="op-min" name="budget_min_ugx" value="<?= e(old('budget_min_ugx')) ?>" placeholder="e.g. 500000">
                        </div>
                        <div class="field">
                            <label for="op-max">Highest budget in UGX (optional)</label>
                            <input type="text" inputmode="numeric" id="op-max" name="budget_max_ugx" value="<?= e(old('budget_max_ugx')) ?>" placeholder="e.g. 900000">
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="field">
                            <label for="op-city">Where is the work? *</label>
                            <select id="op-city" name="city" required>
                                <option value="<?= e($anywhere) ?>"<?= old('city') === $anywhere ? ' selected' : '' ?>><?= e($anywhere) ?> (or remote)</option>
                                <?php foreach ($cities as $c): ?>
                                    <option value="<?= e($c) ?>"<?= old('city', 'Kampala') === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="op-closes">Accept proposals until</label>
                            <input type="date" id="op-closes" name="closes_on" value="<?= e(old('closes_on', $defaultCloses)) ?>" min="<?= e($minCloses) ?>" max="<?= e($maxCloses) ?>">
                        </div>
                    </div>

                    <div style="display:flex; flex-wrap:wrap; justify-content:flex-end; gap:0.65rem;">
                        <a class="btn btn-outline" href="<?= e(route_url('/opportunities')) ?>">Cancel</a>
                        <button type="submit" class="btn btn-primary">Post opportunity</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
