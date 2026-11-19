<?php
defined('KOPII_APP') or exit('Direct access denied.');
?>
<section class="section-pad">
    <div class="container">
        <div class="section-head">
            <div>
                <h1 style="margin:0; font-size:1.85rem; color:var(--navy-900);">Hello, <?= e($user['full_name']) ?></h1>
                <p class="text-muted">Track your sent quote requests or launch a Freelancer / Business Showcase profile anytime.</p>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                <a href="<?= e(route_url('/explore')) ?>" class="btn btn-outline">Browse providers</a>
                <a href="<?= e(route_url('/shop')) ?>" class="btn btn-outline">Shop products</a>
            </div>
        </div>

        <div class="board-cta" style="margin-block-end:1.5rem;">
            <div>
                <h2 style="font-size:var(--text-lg);">Need something done?</h2>
                <p>
                    <?php if ($myOpportunities > 0): ?>
                        You have posted <?= (int) $myOpportunities ?> <?= $myOpportunities === 1 ? 'opportunity' : 'opportunities' ?><?= $newProposals > 0 ? ', with ' . (int) $newProposals . ' new ' . ($newProposals === 1 ? 'proposal' : 'proposals') . ' waiting' : '' ?>.
                    <?php else: ?>
                        Post a project, job, tender or supply request and providers send you proposals.
                    <?php endif; ?>
                </p>
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                <?php if ($myOpportunities > 0): ?>
                    <a class="btn btn-outline" href="<?= e(route_url('/dashboard/opportunities')) ?>">My opportunities</a>
                <?php endif; ?>
                <a class="btn btn-primary" href="<?= e(route_url('/opportunities/new')) ?>">Post an opportunity</a>
            </div>
        </div>

        <?php if (empty($emailVerified)): ?>
            <div class="alert alert-warning" role="status" style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:0.75rem; margin-block-end:1.5rem;">
                <span>
                    <strong>Confirm your email address.</strong>
                    We sent a link to <?= e($user['email']) ?>. Until you confirm it, only requests sent while you were signed in are shown here.
                </span>
                <form method="POST" action="<?= e(route_url('/verify-email/resend')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline btn-sm">Send the link again</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="profile-layout">
            <div class="content-panel">
                <h2>Your Sent Quote Requests (<?= count($sentInquiries) ?>)</h2>
                <?php if (empty($sentInquiries)): ?>
                    <p class="text-muted">You haven't sent any quote inquiries yet. Browse photographers and businesses on Kopii to get started!</p>
                <?php else: ?>
                    <?php foreach ($sentInquiries as $inq): ?>
                        <article class="lead-card">
                            <div style="display:flex; justify-content:space-between; gap:0.5rem; margin-block-end:0.4rem;">
                                <a href="<?= e(route_url('/provider/' . $inq['provider_slug'])) ?>"><strong><?= e($inq['business_name']) ?></strong></a>
                                <span class="badge badge-type"><?= e(ucfirst((string) $inq['status'])) ?></span>
                            </div>
                            <?php if (!empty($inq['product_name'])): ?>
                                <p style="margin:0 0 0.4rem; font-size:0.86rem;" class="text-muted">
                                    About:
                                    <?php if (!empty($inq['product_slug'])): ?>
                                        <a href="<?= e(route_url('/product/' . $inq['product_slug'])) ?>"><?= e($inq['product_name']) ?></a>
                                    <?php else: ?>
                                        <?= e($inq['product_name']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <p style="margin:0 0 0.5rem; font-size:0.92rem;"><?= e($inq['project_details']) ?></p>
                            <a href="<?= e(whatsapp_url((string) $inq['provider_whatsapp'], 'Hi ' . $inq['business_name'] . ', following up on my Kopii inquiry!')) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                                <?= svg_icon('whatsapp', 16) ?> Chat on WhatsApp
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <aside class="content-panel">
                <h2>Want to Showcase Your Own Work?</h2>
                <p class="text-muted" style="font-size:0.9rem;">Activate a free Freelancer or Business Showcase profile on your account in seconds.</p>
                <form method="POST" action="<?= e(route_url('/dashboard/become-provider')) ?>" class="stack-form">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="bp-name">Business or Freelance Name *</label>
                        <input type="text" id="bp-name" name="business_name" required>
                    </div>
                    <div class="field">
                        <label for="bp-cat">Category *</label>
                        <select id="bp-cat" name="category_id" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="bp-city">City in Uganda *</label>
                        <select id="bp-city" name="city">
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= e($c) ?>"><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="bp-phone">Phone / WhatsApp (+256) *</label>
                        <input type="tel" id="bp-phone" name="phone" placeholder="+256 77..." required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Activate My Showcase</button>
                </form>
            </aside>
        </div>
    </div>
</section>
