<?php
defined('KOPII_APP') or exit('Direct access denied.');
/** @var array $w */
$imgUrl = asset_url($w['image_path']);       // full size: opened in the lightbox
$thumbUrl = thumb_url($w['image_path']);     // small version: shown in the grid
$creatorUrl = !empty($w['provider_slug']) ? route_url('/provider/' . $w['provider_slug']) : '';
$creatorLabel = !empty($w['business_name']) ? ('By ' . $w['business_name'] . ' (' . ($w['provider_city'] ?? 'Uganda') . ')') : '';
?>
<article class="work-card">
    <button
        type="button"
        class="work-thumb-btn"
        data-lightbox-src="<?= e($imgUrl) ?>"
        data-lightbox-title="<?= e($w['title']) ?>"
        data-lightbox-tag="<?= e($w['project_tag'] ?? '') ?>"
        data-lightbox-desc="<?= e($w['description'] ?? '') ?>"
        data-lightbox-loc="<?= e($w['client_location'] ?? '') ?>"
        data-lightbox-creator="<?= e(!empty($w['business_name']) ? 'View ' . $w['business_name'] : '') ?>"
        data-lightbox-creator-url="<?= e($creatorUrl) ?>"
        aria-label="Inspect showcase work: <?= e($w['title']) ?>"
    >
        <img src="<?= e($thumbUrl) ?>" alt="<?= e($w['title']) ?>" width="640" height="480" loading="lazy">
        <?php if (!empty($w['project_tag'])): ?>
            <span class="work-tag-pill"><?= e($w['project_tag']) ?></span>
        <?php endif; ?>
    </button>
    <div class="work-card-meta">
        <h3><?= e($w['title']) ?></h3>
        <?php if (!empty($w['business_name'])): ?>
            <div class="work-creator-row">
                <a href="<?= e($creatorUrl) ?>"><?= e($w['business_name']) ?></a>
                <span><?= e($w['provider_city'] ?? '') ?></span>
            </div>
        <?php elseif (!empty($w['client_location'])): ?>
            <div class="work-creator-row">
                <span><?= svg_icon('map-pin', 16) ?> <?= e($w['client_location']) ?></span>
            </div>
        <?php endif; ?>
    </div>
</article>
