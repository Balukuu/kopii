<?php
defined('KOPII_APP') or exit('Direct access denied.');
/** @var array $p a row from ProductModel::search() or forProvider() */
[$stockLabel, $stockClass] = stock_badge((string) $p['stock_status']);
$showSeller = !isset($hideSeller) || !$hideSeller;
?>
<article class="product-card">
    <div class="product-media">
        <?php if (!empty($p['cover_path'])): ?>
            <img src="<?= e(thumb_url($p['cover_path'])) ?>" alt="" width="640" height="480" loading="lazy">
        <?php else: ?>
            <div class="product-media-empty">
                <?= svg_icon((string) ($p['category_icon'] ?? 'image'), 24) ?>
                <span><?= e($p['category_name']) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($p['stock_status'] !== 'in_stock'): ?>
            <span class="badge <?= e($stockClass) ?>"><?= e($stockLabel) ?></span>
        <?php endif; ?>
    </div>
    <div class="product-card-body">
        <h3 class="product-card-title">
            <a class="card-link" href="<?= e(route_url('/product/' . $p['slug'])) ?>"><?= e($p['name']) ?></a>
        </h3>
        <div class="product-price tnum">
            <?= e(product_price($p['price_ugx'])) ?>
            <?php if ((int) $p['price_ugx'] > 0 && $p['price_unit'] !== 'each'): ?>
                <small><?= e($p['price_unit']) ?></small>
            <?php endif; ?>
        </div>
        <?php if ($showSeller && !empty($p['business_name'])): ?>
            <div class="product-seller">
                <span><?= e($p['business_name']) ?></span>
                <?php if (!empty($p['is_verified'])): ?>
                    <span class="verified-expert-line" style="margin:0;"><?= svg_icon('verified', 16) ?> Verified</span>
                <?php endif; ?>
                <?php if (!empty($p['city'])): ?>
                    <span><?= svg_icon('map-pin', 16) ?> <?= e($p['city']) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</article>
