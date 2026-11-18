<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array $provider  row from AdminModel::providerDetail()
 * @var array $portfolio portfolio_items rows
 * @var array $packages  service_packages rows
 * @var array $history   admin_log rows about this listing
 */
View::partial('admin_header', ['adminTab' => $adminTab]);

$id          = (int) $provider['id'];
$status      = (string) $provider['status'];
$isVerified  = !empty($provider['is_verified']);
$isFeatured  = !empty($provider['is_featured']);
$phoneOk     = strlen(normalize_ug_phone((string) $provider['phone'])) === 12;
$hasPricing  = (int) $provider['starting_price_ugx'] > 0 || !empty($packages);
$website     = trim((string) ($provider['website'] ?? ''));
$moderateUrl = route_url('/admin/moderate');
$formBase    = static function (string $action) use ($id): string {
    return csrf_field() . admin_return_field()
        . '<input type="hidden" name="provider_id" value="' . $id . '">'
        . '<input type="hidden" name="mod_action" value="' . e($action) . '">';
};
?>
<section class="section-pad-sm">
    <div class="container">
        <a class="admin-back" href="<?= e(route_url('/admin/providers')) ?>">&larr; All listings</a>

        <div class="admin-detail">
            <div class="admin-stack">

                <section class="admin-card" aria-labelledby="listing-title">
                    <div class="status-line">
                        <span class="badge <?= $status === 'active' ? 'badge-avail' : 'badge-type' ?>"><?= e($status === 'active' ? 'Live' : ucfirst($status)) ?></span>
                        <?php if ($isVerified): ?>
                            <span class="badge badge-verified"><?= svg_icon('verified', 16) ?> Verified</span>
                        <?php else: ?>
                            <span class="badge badge-type">Not verified</span>
                        <?php endif; ?>
                        <?php if ($isFeatured): ?>
                            <span class="badge badge-featured">Featured</span>
                        <?php endif; ?>
                    </div>
                    <h2 id="listing-title"><?= e($provider['business_name']) ?></h2>
                    <p><?= e($provider['headline']) ?></p>
                    <p>
                        <?= e($provider['category_name']) ?> &middot; <?= e($provider['city']) ?><?= !empty($provider['neighborhood']) ? ', ' . e($provider['neighborhood']) : '' ?>
                        &middot; <a href="<?= e(route_url('/provider/' . $provider['slug'])) ?>">View the public page</a>
                    </p>
                </section>

                <section class="admin-card" aria-labelledby="verify-title">
                    <h2 id="verify-title">Verification check</h2>
                    <p>The site tells visitors the Kopii team checked these three things before adding the Verified badge. Check them here, then confirm.</p>

                    <div class="check-item">
                        <div class="check-head"><?= svg_icon('phone', 20) ?> 1. They can be reached</div>
                        <div class="check-evidence">
                            Phone <a href="<?= e(tel_url((string) $provider['phone'])) ?>"><?= e(display_ug_phone((string) $provider['phone'])) ?></a>
                            &middot; WhatsApp <a href="<?= e(whatsapp_url((string) $provider['whatsapp'])) ?>" target="_blank" rel="noopener noreferrer"><?= e(display_ug_phone((string) $provider['whatsapp'])) ?></a>
                        </div>
                        <?php if (!$phoneOk): ?>
                            <div class="check-flag">This does not look like a complete Ugandan number. Check it before verifying.</div>
                        <?php endif; ?>
                        <div class="check-evidence">
                            Owner&rsquo;s email: <?= empty($provider['owner_email_verified_at']) ? 'not confirmed yet' : 'confirmed' ?>
                        </div>
                    </div>

                    <div class="check-item">
                        <div class="check-head"><?= svg_icon('image', 20) ?> 2. The work is their own</div>
                        <?php if (empty($portfolio)): ?>
                            <div class="check-flag">No portfolio work uploaded yet.</div>
                        <?php else: ?>
                            <div class="check-evidence"><?= count($portfolio) ?> <?= count($portfolio) === 1 ? 'item' : 'items' ?>. Open a photo to see it full size.</div>
                            <div class="thumb-grid">
                                <?php foreach (array_slice($portfolio, 0, 12) as $item): ?>
                                    <a href="<?= e(asset_url((string) $item['image_path'])) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($item['title']) ?>">
                                        <img src="<?= e(thumb_url((string) $item['image_path'])) ?>" alt="<?= e($item['title']) ?>" loading="lazy" width="160" height="120">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="check-item">
                        <div class="check-head"><?= svg_icon('layers', 20) ?> 3. The pricing is clear</div>
                        <div class="check-evidence">Starting rate: <strong class="tnum"><?= e(format_ugx($provider['starting_price_ugx'])) ?></strong></div>
                        <?php if (!empty($packages)): ?>
                            <ul class="package-list">
                                <?php foreach ($packages as $pkg): ?>
                                    <li>
                                        <span><?= e($pkg['name']) ?></span>
                                        <span class="tnum"><?= e(format_ugx($pkg['price_ugx'])) ?> <?= e($pkg['price_unit']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!$hasPricing): ?>
                            <div class="check-flag">No starting rate and no packages.</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($isVerified): ?>
                        <p>
                            <strong>Verified</strong>
                            <?php if (!empty($provider['verified_at'])): ?>
                                on <?= e(date('j M Y', (int) strtotime((string) $provider['verified_at']))) ?><?= !empty($provider['verified_by_name']) ? ' by ' . e($provider['verified_by_name']) : '' ?>.
                            <?php else: ?>
                                before verification was recorded, so there is no date or name on file.
                            <?php endif; ?>
                        </p>
                        <form method="POST" action="<?= e($moderateUrl) ?>" class="form-stack" data-confirm="Yes, remove the badge">
                            <?= $formBase('unverify') ?>
                            <div class="field">
                                <label for="unverify-note">Why? (optional, kept in the log)</label>
                                <textarea id="unverify-note" name="note" rows="2" maxlength="500"></textarea>
                            </div>
                            <div><button type="submit" class="btn btn-danger-outline btn-sm">Remove Verified badge</button></div>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= e($moderateUrl) ?>" class="form-stack">
                            <?= $formBase('verify') ?>
                            <label class="check-row"><input type="checkbox" name="check_phone" value="1" required> <span>I contacted them, and the phone / WhatsApp number works.</span></label>
                            <label class="check-row"><input type="checkbox" name="check_work" value="1" required> <span>The portfolio looks like their own work (not stock or someone else&rsquo;s photos).</span></label>
                            <label class="check-row"><input type="checkbox" name="check_pricing" value="1" required> <span>They show a starting rate or clear UGX packages.</span></label>
                            <div class="field">
                                <label for="verify-note">Note (optional, kept in the log)</label>
                                <textarea id="verify-note" name="note" rows="2" maxlength="500"></textarea>
                            </div>
                            <div><button type="submit" class="btn btn-primary">Verify listing</button></div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="admin-card" aria-labelledby="history-title">
                    <h2 id="history-title">History</h2>
                    <?php if (empty($history)): ?>
                        <p>Nothing recorded for this listing yet.</p>
                    <?php else: ?>
                        <ul class="log-list">
                            <?php foreach ($history as $row): ?>
                                <li>
                                    <span><?= e(AdminLogModel::describe($row)) ?></span>
                                    <?php if ((string) $row['note'] !== ''): ?>
                                        <span class="log-note">&ldquo;<?= e($row['note']) ?>&rdquo;</span>
                                    <?php endif; ?>
                                    <span class="log-meta"><?= e($row['admin_name'] ?? 'An administrator') ?> &middot; <?= e(time_ago((string) $row['created_at'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

            <div class="admin-stack">

                <section class="admin-card" aria-labelledby="visibility-title">
                    <h2 id="visibility-title">Visibility</h2>

                    <?php if ($status === 'suspended'): ?>
                        <p>This listing is suspended along with its owner&rsquo;s account. Reinstate the account to bring it back (it returns paused).</p>
                        <p><a class="btn btn-outline btn-sm" href="<?= e(route_url('/admin/users', ['q' => $provider['owner_email']])) ?>">Open the account</a></p>
                    <?php elseif ($status === 'paused'): ?>
                        <p>This listing is paused: hidden from search and its public page.</p>
                        <form method="POST" action="<?= e($moderateUrl) ?>" class="form-stack">
                            <?= $formBase('activate') ?>
                            <div><button type="submit" class="btn btn-outline">Make it live again</button></div>
                        </form>
                    <?php else: ?>
                        <p>This listing is live. Pausing hides it from search and its public page, and emails the owner.</p>
                        <form method="POST" action="<?= e($moderateUrl) ?>" class="form-stack" data-confirm="Yes, pause and email">
                            <?= $formBase('pause') ?>
                            <div class="field">
                                <label for="pause-note">Reason (included in the email to the owner)</label>
                                <textarea id="pause-note" name="note" rows="2" maxlength="500"></textarea>
                            </div>
                            <div><button type="submit" class="btn btn-danger-outline btn-sm">Pause listing</button></div>
                        </form>
                    <?php endif; ?>

                    <form method="POST" action="<?= e($moderateUrl) ?>" class="form-stack">
                        <?= $formBase($isFeatured ? 'unfeature' : 'feature') ?>
                        <div><button type="submit" class="btn btn-outline btn-sm"><?= $isFeatured ? 'Remove from Featured' : 'Feature on the home page' ?></button></div>
                    </form>
                </section>

                <section class="admin-card" aria-labelledby="owner-title">
                    <h2 id="owner-title">Owner</h2>
                    <dl class="fact-list">
                        <dt>Name</dt><dd><?= e($provider['owner_name']) ?></dd>
                        <dt>Email</dt><dd><?= e($provider['owner_email']) ?></dd>
                        <dt>Email status</dt><dd><?= empty($provider['owner_email_verified_at']) ? 'Not confirmed' : 'Confirmed' ?></dd>
                        <?php if (!empty($provider['owner_phone'])): ?>
                            <dt>Account phone</dt><dd><?= e($provider['owner_phone']) ?></dd>
                        <?php endif; ?>
                        <dt>Account</dt><dd><?= empty($provider['owner_suspended_at']) ? 'Active' : 'Suspended' ?></dd>
                        <dt>Joined</dt><dd><?= e(time_ago((string) $provider['owner_created_at'])) ?></dd>
                    </dl>
                    <p><a href="<?= e(route_url('/admin/users', ['q' => $provider['owner_email']])) ?>">Manage this account</a></p>
                </section>

                <section class="admin-card" aria-labelledby="activity-title">
                    <h2 id="activity-title">Activity</h2>
                    <dl class="fact-list">
                        <dt>Quote requests</dt><dd class="tnum"><?= (int) $provider['inquiry_count'] ?></dd>
                        <dt>Published reviews</dt><dd class="tnum"><?= (int) $provider['published_reviews'] ?></dd>
                        <dt>Reviews waiting</dt><dd class="tnum"><?= (int) $provider['pending_reviews'] ?></dd>
                        <dt>Profile views</dt><dd class="tnum"><?= number_format((int) $provider['views_count']) ?></dd>
                        <dt>Listed</dt><dd><?= e(time_ago((string) $provider['created_at'])) ?></dd>
                        <?php if ($website !== ''): ?>
                            <dt>Website</dt>
                            <dd>
                                <?php if (preg_match('#^https?://#i', $website)): ?>
                                    <a href="<?= e($website) ?>" target="_blank" rel="noopener noreferrer"><?= e($website) ?></a>
                                <?php else: ?>
                                    <?= e($website) ?>
                                <?php endif; ?>
                            </dd>
                        <?php endif; ?>
                        <?php if (!empty($provider['instagram'])): ?>
                            <dt>Instagram</dt><dd><?= e($provider['instagram']) ?></dd>
                        <?php endif; ?>
                    </dl>
                    <?php if ((int) $provider['pending_reviews'] > 0): ?>
                        <p><a href="<?= e(route_url('/admin/reviews', ['status' => 'pending'])) ?>">Review the waiting reviews</a></p>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</section>
