<?php
defined('KOPII_APP') or exit('Direct access denied.');
/** @var array $p */
$shots = $p['portfolio_preview'] ?? [];
$shotCount = count($shots);
$profileUrl = route_url('/provider/' . $p['slug']);
$tags = array_slice(array_filter(array_map('trim', explode(',', (string) ($p['specialties'] ?? '')))), 0, 3);
$matchReasons = !empty($p['match_reasons']) && is_array($p['match_reasons']) ? array_slice($p['match_reasons'], 0, 3) : [];
$waGreeting = 'Hi ' . $p['business_name'] . '! I reviewed your dossier on Kopii and would like to inquire about hiring you.';
?>
<article class="provider-card">
    <div class="card-work-strip<?= $shotCount <= 1 ? ' single-shot' : '' ?>">
        <?php if ($shotCount === 0): ?>
            <?php if (!empty($p['cover_path'])): ?>
                <img class="shot-main" src="<?= e(thumb_url($p['cover_path'])) ?>" alt="<?= e($p['business_name']) ?> portfolio showcase" width="640" height="360" loading="lazy">
            <?php else: ?>
                <div class="card-work-placeholder">
                    <?= svg_icon($p['category_icon'] ?? 'camera', 24) ?>
                    <span><?= e($p['category_name']) ?></span>
                </div>
            <?php endif; ?>
        <?php elseif ($shotCount === 1): ?>
            <img class="shot-main" src="<?= e(thumb_url($shots[0]['image_path'])) ?>" alt="<?= e($shots[0]['title']) ?>" width="640" height="360" loading="lazy">
        <?php else: ?>
            <img class="shot-main" src="<?= e(thumb_url($shots[0]['image_path'])) ?>" alt="<?= e($shots[0]['title']) ?>" width="480" height="360" loading="lazy">
            <img src="<?= e(thumb_url($shots[1]['image_path'])) ?>" alt="<?= e($shots[1]['title']) ?>" width="320" height="180" loading="lazy">
            <?php if (isset($shots[2])): ?>
                <img src="<?= e(thumb_url($shots[2]['image_path'])) ?>" alt="<?= e($shots[2]['title']) ?>" width="320" height="180" loading="lazy">
            <?php else: ?>
                <img src="<?= e(thumb_url($shots[0]['image_path'])) ?>" alt="" width="320" height="180" loading="lazy">
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($p['match_score'])): ?>
            <span class="card-match-pill"><?= (int) $p['match_score'] ?>% Match</span>
        <?php endif; ?>
    </div>

    <header>
        <div class="avatar-status-wrap" aria-hidden="true">
            <div class="avatar-badge">
                <?php if (!empty($p['avatar_path'])): ?>
                    <img src="<?= e(thumb_url($p['avatar_path'])) ?>" alt="" width="50" height="50" loading="lazy">
                <?php else: ?>
                    <?= e(initials($p['business_name'])) ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($p['is_available'])): ?>
                <span class="status-dot" title="Available for hire"></span>
            <?php endif; ?>
        </div>

        <div class="card-title-wrap">
            <h3>
                <a class="card-link" href="<?= e($profileUrl) ?>"><?= e($p['business_name']) ?></a>
            </h3>

            <?php if (!empty($p['is_verified'])): ?>
                <div class="verified-expert-line">
                    <?= svg_icon('verified', 16) ?>
                    <span><strong>Verified</strong> &middot; <?= e($p['category_name']) ?></span>
                </div>
            <?php else: ?>
                <div class="unverified-role-line">
                    <?= svg_icon($p['category_icon'] ?? 'sparkles', 16) ?>
                    <span><?= e($p['category_name']) ?> <?= $p['provider_type'] === 'business' ? 'Studio' : 'Specialist' ?></span>
                </div>
            <?php endif; ?>

            <div class="card-meta-line">
                <?php if (ProviderModel::isFeaturedNow($p)): ?>
                    <span class="badge badge-featured">Featured</span>
                <?php endif; ?>
                <span><?= svg_icon('map-pin', 16) ?> <?= e($p['city']) ?><?= !empty($p['neighborhood']) ? ', ' . e($p['neighborhood']) : '' ?></span>
                <?php if (!empty($p['years_experience'])): ?>
                    <span><?= (int) $p['years_experience'] ?>+ yrs exp</span>
                <?php endif; ?>
                <?php if ((int) ($p['review_count'] ?? 0) > 0): ?>
                    <span class="rating-pill"><?= svg_icon('star', 16) ?> <?= e(number_format((float) $p['avg_rating'], 1)) ?> (<?= (int) $p['review_count'] ?>)</span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="provider-card-body">
        <p class="card-headline"><?= e($p['headline']) ?></p>

        <?php if (!empty($matchReasons)): ?>
            <div class="expertise-block">
                <span class="expertise-label">Why They Match Your Brief</span>
                <div class="tag-row">
                    <?php foreach ($matchReasons as $reason): ?>
                        <span class="tag-chip"><?= e($reason) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif (!empty($tags)): ?>
            <div class="expertise-block">
                <span class="expertise-label">Expertise</span>
                <div class="tag-row">
                    <?php foreach ($tags as $tag): ?>
                        <span class="tag-chip"><?= e($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <div class="card-footer-inner">
            <div class="price-tag">
                <span>Starting Rate</span>
                <strong class="tnum"><?= e(format_ugx($p['starting_price_ugx'])) ?></strong>
            </div>
            <div class="card-actions">
                <button
                    type="button"
                    class="btn btn-outline btn-sm shortlist-btn"
                    data-provider-id="<?= (int) $p['id'] ?>"
                    aria-pressed="false"
                    aria-label="Save <?= e($p['business_name']) ?> to compare"
                >
                    <span data-shortlist-label>Compare</span>
                </button>
                <a
                    href="<?= e(whatsapp_url((string) $p['whatsapp'], $waGreeting)) ?>"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-whatsapp btn-sm"
                    aria-label="WhatsApp <?= e($p['business_name']) ?>"
                >
                    <?= svg_icon('whatsapp', 16) ?> WhatsApp
                </a>
            </div>
        </div>
    </footer>
</article>
