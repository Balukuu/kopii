<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array $product    from ProductModel::findBySlug(), with 'images'
 * @var bool  $isPublic   visible to everyone right now
 * @var bool  $isOwner    the signed-in user is the seller
 * @var array $stockLabels key => label
 * @var array $conditions  key => label
 * @var array $more        other products from this seller
 * @var array $similar     products from the same category
 */
$authUser  = current_user();
$images    = $product['images'];
[$stockLabel, $stockClass] = stock_badge((string) $product['stock_status']);
$canAsk    = $isPublic && !$isOwner && $product['stock_status'] !== 'out_of_stock';
$sellerUrl = route_url('/provider/' . $product['provider_slug']);
$waMessage = 'Hi ' . $product['business_name'] . ', I found "' . $product['name'] . '" on Kopii. Is it available?';
$descLines = (string) ($product['description'] ?? '');
?>
<nav class="breadcrumb-bar" aria-label="Breadcrumb">
    <div class="container">
        <ol class="breadcrumb-list">
            <li><a href="<?= e(route_url('/')) ?>">Kopii</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="<?= e(route_url('/shop')) ?>">Shop</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="<?= e(route_url('/shop', ['category' => $product['category_slug']])) ?>"><?= e($product['category_name']) ?></a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page"><?= e($product['name']) ?></li>
        </ol>
    </div>
</nav>

<section class="section-pad-sm">
    <div class="container">
        <?php if (!$isPublic): ?>
            <div class="alert alert-warning" role="status">
                <?php if ($product['status'] === 'suspended'): ?>
                    The Kopii team has hidden this product, so only you and the administrators can see it.
                <?php elseif ($product['status'] === 'paused'): ?>
                    This product is paused, so only you and the administrators can see it. You can list it again from your dashboard.
                <?php else: ?>
                    This seller's listing is hidden right now, so only you and the administrators can see this product.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="profile-layout" style="margin-block-start:0;">
            <div>
                <div class="content-panel">
                    <div class="product-gallery" data-gallery>
                        <div class="product-gallery-main">
                            <?php if (!empty($images)): ?>
                                <img data-gallery-main src="<?= e(asset_url($images[0]['image_path'])) ?>" alt="<?= e($product['name']) ?>" width="1200" height="900">
                            <?php else: ?>
                                <div class="product-media-empty">
                                    <?= svg_icon((string) $product['category_icon'], 24) ?>
                                    <span>No photo yet</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (count($images) > 1): ?>
                            <div class="product-gallery-thumbs" role="group" aria-label="Product photos">
                                <?php foreach ($images as $i => $img): ?>
                                    <button type="button" data-gallery-thumb data-src="<?= e(asset_url($img['image_path'])) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?> aria-label="Show photo <?= $i + 1 ?> of <?= count($images) ?>">
                                        <img src="<?= e(thumb_url($img['image_path'])) ?>" alt="" width="160" height="160" loading="lazy">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-panel">
                    <h2>About this product</h2>
                    <?php if (trim($descLines) !== ''): ?>
                        <div class="opp-body"><?= e($descLines) ?></div>
                    <?php else: ?>
                        <p class="text-muted m-0">The seller has not added a description. Ask them anything you want to know.</p>
                    <?php endif; ?>

                    <dl class="product-facts">
                        <dt>Category</dt>
                        <dd><a href="<?= e(route_url('/shop', ['category' => $product['category_slug']])) ?>"><?= e($product['category_name']) ?></a></dd>
                        <dt>Condition</dt>
                        <dd><?= e($conditions[$product['item_condition']] ?? ucfirst((string) $product['item_condition'])) ?></dd>
                        <dt>Availability</dt>
                        <dd><?= e($stockLabels[$product['stock_status']] ?? $stockLabel) ?></dd>
                        <dt>Sold from</dt>
                        <dd><?= e($product['city']) ?><?= !empty($product['neighborhood']) ? ', ' . e($product['neighborhood']) : '' ?>, Uganda</dd>
                    </dl>
                </div>

                <?php if (!empty($more)): ?>
                    <section class="content-panel" aria-labelledby="more-heading">
                        <h2 id="more-heading">More from <?= e($product['business_name']) ?></h2>
                        <div class="product-grid">
                            <?php foreach ($more as $p): ?>
                                <?php View::partial('product_card', ['p' => $p, 'hideSeller' => true]); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($similar)): ?>
                    <section class="content-panel" aria-labelledby="similar-heading">
                        <h2 id="similar-heading">Similar products</h2>
                        <div class="product-grid">
                            <?php foreach ($similar as $p): ?>
                                <?php View::partial('product_card', ['p' => $p]); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="sticky-hire-card" aria-label="Buy <?= e($product['name']) ?>">
                <h1 style="margin:0 0 0.5rem; font-size:var(--text-xl); line-height:1.25; text-wrap:balance;"><?= e($product['name']) ?></h1>
                <div class="card-badges">
                    <span class="badge <?= e($stockClass) ?>"><?= e($stockLabel) ?></span>
                    <?php if ($product['item_condition'] !== 'new'): ?>
                        <span class="badge badge-type"><?= e($conditions[$product['item_condition']] ?? '') ?></span>
                    <?php endif; ?>
                </div>

                <div class="price-line">
                    <strong class="tnum"><?= e(product_price($product['price_ugx'])) ?></strong>
                    <?php if ((int) $product['price_ugx'] > 0 && $product['price_unit'] !== 'each'): ?>
                        <span><?= e($product['price_unit']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($isOwner): ?>
                    <div class="cta-stack">
                        <a class="btn btn-primary btn-block" href="<?= e(route_url('/dashboard', ['tab' => 'products', 'edit' => (string) (int) $product['id']])) ?>">Edit this product</a>
                    </div>
                <?php elseif ($canAsk): ?>
                    <div class="cta-stack">
                        <button type="button" class="btn btn-primary btn-lg btn-block" data-open-modal="product-modal">
                            <?= svg_icon('mail', 20) ?> Message the seller
                        </button>
                        <a href="<?= e(whatsapp_url((string) $product['provider_whatsapp'], $waMessage)) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp-outline btn-lg btn-block">
                            <?= svg_icon('whatsapp', 20) ?> Chat on WhatsApp
                        </a>
                        <a href="<?= e(tel_url((string) $product['provider_phone'])) ?>" class="btn btn-ghost btn-block">
                            <?= svg_icon('phone', 20) ?> Call <?= e(display_ug_phone((string) $product['provider_phone'])) ?>
                        </a>
                    </div>
                <?php elseif ($isPublic): ?>
                    <p class="text-muted" style="margin:0 0 1rem;">This item is out of stock. You can still ask the seller when it will be back.</p>
                    <div class="cta-stack">
                        <a href="<?= e(whatsapp_url((string) $product['provider_whatsapp'], $waMessage)) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp-outline btn-block">
                            <?= svg_icon('whatsapp', 20) ?> Ask on WhatsApp
                        </a>
                    </div>
                <?php endif; ?>

                <div class="risk-free-box">
                    <?= svg_icon('shield', 20) ?>
                    <span><strong>Check before you pay.</strong> Kopii takes no payment. Agree price and delivery with the seller, meet in a public place and inspect the item first, and never pay in full for something you have not seen. <a href="<?= e(route_url('/safety')) ?>">Safety tips</a></span>
                </div>

                <dl class="facts">
                    <div class="row-between">
                        <dt class="text-muted">Seller</dt>
                        <dd class="dd-strong"><a href="<?= e($sellerUrl) ?>"><?= e($product['business_name']) ?></a></dd>
                    </div>
                    <?php if (!empty($product['is_verified'])): ?>
                        <div class="row-between">
                            <dt class="text-muted">Status</dt>
                            <dd class="dd-strong verified-expert-line" style="margin:0;"><?= svg_icon('verified', 16) ?> Verified seller</dd>
                        </div>
                    <?php endif; ?>
                    <div class="row-between">
                        <dt class="text-muted">Typical reply</dt>
                        <dd class="dd-strong"><?= e($product['response_time']) ?></dd>
                    </div>
                </dl>

                <?php if ($isPublic && !$isOwner): ?>
                    <?php View::partial('report_box', ['targetType' => 'product', 'targetId' => (int) $product['id'], 'noun' => 'product']); ?>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php if ($canAsk): ?>
    <dialog id="product-modal" class="modal-box" closedby="any" aria-labelledby="product-modal-title"<?= old('client_name') !== '' ? ' data-autoopen' : '' ?>>
        <div class="modal-head">
            <h2 id="product-modal-title">Message <?= e($product['business_name']) ?></h2>
            <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close message dialog">
                <?= svg_icon('close', 20) ?>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-block:0 1rem; font-size:0.9rem;">
                About <strong><?= e($product['name']) ?></strong>. Your message goes into the seller's Kopii inbox with your contact details.
            </p>
            <form method="POST" action="<?= e(route_url('/product/' . $product['slug'] . '/inquire')) ?>" class="stack-form">
                <?= csrf_field() ?>
                <div class="visually-hidden" aria-hidden="true">
                    <label for="website_confirm_hp">Leave empty</label>
                    <input type="text" id="website_confirm_hp" name="website_confirm_hp" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-row-2">
                    <div class="field">
                        <label for="pq-name">Your name *</label>
                        <input type="text" id="pq-name" name="client_name" value="<?= e(old('client_name', $authUser['full_name'] ?? '')) ?>" autocomplete="name" required>
                    </div>
                    <div class="field">
                        <label for="pq-phone">Phone / WhatsApp *</label>
                        <input type="tel" id="pq-phone" name="client_phone" value="<?= e(old('client_phone')) ?>" placeholder="+256 77..." autocomplete="tel" required>
                    </div>
                </div>
                <div class="field">
                    <label for="pq-email">Email (optional)</label>
                    <input type="email" id="pq-email" name="client_email" value="<?= e(old('client_email', $authUser['email'] ?? '')) ?>" autocomplete="email" autocapitalize="none" spellcheck="false">
                </div>
                <div class="field">
                    <label for="pq-loc">Deliver to (optional)</label>
                    <input type="text" id="pq-loc" name="event_location" value="<?= e(old('event_location')) ?>" placeholder="e.g. Ntinda, Kampala">
                </div>
                <div class="field">
                    <label for="pq-details">Your message *</label>
                    <textarea id="pq-details" name="project_details" minlength="8" required placeholder="How many do you need? When? Any questions about the item?"><?= e(old('project_details', 'Hello, I am interested in "' . $product['name'] . '". Is it available?')) ?></textarea>
                </div>
                <p class="hint form-privacy">Your details go to the seller so they can reply. See our <a href="<?= e(route_url('/privacy')) ?>">Privacy Policy</a>.</p>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem;">
                    <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Send message</button>
                </div>
            </form>
        </div>
    </dialog>
<?php endif; ?>
