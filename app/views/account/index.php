<?php
defined('KOPII_APP') or exit('Direct access denied.');
/**
 * @var array  $account       the users row
 * @var ?array $provider      their listing, if they have one
 * @var bool   $emailVerified
 * @var bool   $isAdmin
 */
?>
<section class="dash-header">
    <div class="container">
        <div class="card-badges" style="margin-block-end:0.4rem;">
            <span class="badge badge-featured">Account</span>
        </div>
        <h1 style="margin:0; font-size:clamp(1.45rem, 2.6vw, 2rem); color:#fff;">Account settings</h1>
        <p style="margin:0.25rem 0 0; color:#cbd5e1; font-size:0.92rem;">
            <?= e($account['full_name']) ?> &middot; <?= e($account['email']) ?>
        </p>
    </div>
</section>

<section class="section-pad-sm">
    <div class="container account-stack">

        <div class="content-panel" id="details">
            <h2>Your details</h2>
            <form method="POST" action="<?= e(route_url('/account/details')) ?>" class="stack-form">
                <?= csrf_field() ?>
                <div class="form-row-2">
                    <div class="field">
                        <label for="ac-name">Full name *</label>
                        <input type="text" id="ac-name" name="full_name" value="<?= e($account['full_name']) ?>" maxlength="120" autocomplete="name" required>
                    </div>
                    <div class="field">
                        <label for="ac-phone">Phone (optional)</label>
                        <input type="tel" id="ac-phone" name="phone" value="<?= e($account['phone'] ?? '') ?>" maxlength="40" autocomplete="tel" placeholder="+256 77...">
                    </div>
                </div>
                <?php if ($provider): ?>
                    <p class="hint m-0">This is your private account name. The name and phone shown on your public listing are edited under <a href="<?= e(route_url('/dashboard', ['tab' => 'profile'])) ?>">Profile &amp; Branding</a>.</p>
                <?php endif; ?>
                <div><button type="submit" class="btn btn-primary">Save details</button></div>
            </form>
        </div>

        <div class="content-panel" id="email">
            <h2>Sign-in email</h2>
            <p style="margin-block-start:0;">
                <strong><?= e($account['email']) ?></strong>
                <span class="badge <?= $emailVerified ? 'badge-verified' : 'badge-type' ?>"><?= $emailVerified ? 'Confirmed' : 'Not confirmed' ?></span>
            </p>
            <?php if (!$emailVerified): ?>
                <form method="POST" action="<?= e(route_url('/verify-email/resend')) ?>" style="margin-block-end:1.25rem;">
                    <?= csrf_field() ?>
                    <p class="text-muted" style="margin-block-start:0;">Confirm it so quote requests sent before you signed up show in your account.</p>
                    <button type="submit" class="btn btn-outline btn-sm">Send the confirmation link again</button>
                </form>
            <?php endif; ?>

            <form method="POST" action="<?= e(route_url('/account/email')) ?>" class="stack-form">
                <?= csrf_field() ?>
                <h3 style="font-size:1rem; margin:0;">Change it</h3>
                <div class="form-row-2">
                    <div class="field">
                        <label for="ac-new-email">New email address *</label>
                        <input type="email" id="ac-new-email" name="new_email" maxlength="190" autocomplete="email" autocapitalize="none" spellcheck="false" required>
                    </div>
                    <div class="field">
                        <label for="ac-email-pw">Current password *</label>
                        <input type="password" id="ac-email-pw" name="current_password" autocomplete="current-password" required>
                    </div>
                </div>
                <p class="hint m-0">We send a confirmation link to the new address and a notice to the old one. You sign in with the new address from now on.</p>
                <div><button type="submit" class="btn btn-outline">Change email</button></div>
            </form>
        </div>

        <div class="content-panel" id="password">
            <h2>Password</h2>
            <form method="POST" action="<?= e(route_url('/account/password')) ?>" class="stack-form">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="ac-pw-now">Current password *</label>
                    <input type="password" id="ac-pw-now" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="form-row-2">
                    <div class="field">
                        <label for="ac-pw-new">New password *</label>
                        <input type="password" id="ac-pw-new" name="new_password" minlength="8" maxlength="200" autocomplete="new-password" required>
                        <span class="hint">At least 8 characters.</span>
                    </div>
                    <div class="field">
                        <label for="ac-pw-confirm">Type it again *</label>
                        <input type="password" id="ac-pw-confirm" name="confirm_password" minlength="8" maxlength="200" autocomplete="new-password" required>
                    </div>
                </div>
                <p class="hint m-0">Changing your password signs you out everywhere else.</p>
                <div><button type="submit" class="btn btn-outline">Change password</button></div>
            </form>
        </div>

        <div class="content-panel danger-panel" id="delete">
            <h2>Delete my account</h2>
            <?php if ($isAdmin): ?>
                <p class="m-0">Administrator accounts cannot be deleted here. Ask another administrator to change your role first.</p>
            <?php else: ?>
                <p style="margin-block-start:0;">This permanently deletes your account and removes what you published. <strong>It cannot be undone.</strong></p>
                <ul class="deliverables-list" style="margin-block-end:1rem;">
                    <?php if ($provider): ?>
                        <li><?= svg_icon('check', 16) ?> <span>Your listing <strong><?= e($provider['business_name']) ?></strong>, its portfolio, packages and photos</span></li>
                        <li><?= svg_icon('check', 16) ?> <span>Your shop products and their photos, and the quote requests you received</span></li>
                        <li><?= svg_icon('check', 16) ?> <span>Your proposals on opportunities</span></li>
                    <?php endif; ?>
                    <li><?= svg_icon('check', 16) ?> <span>Opportunities you posted, and the proposals they received</span></li>
                    <li><?= svg_icon('check', 16) ?> <span>Reviews you wrote</span></li>
                </ul>
                <p class="hint">Quote requests you sent to other providers stay in their inboxes (they are part of those providers' records) but are no longer linked to you.</p>

                <details class="report-box" style="margin-block-start:1rem;">
                    <summary><?= svg_icon('trash', 16) ?> I want to delete my account</summary>
                    <form method="POST" action="<?= e(route_url('/account/delete')) ?>" class="stack-form">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label for="ac-del-pw">Current password *</label>
                            <input type="password" id="ac-del-pw" name="current_password" autocomplete="current-password" required>
                        </div>
                        <div class="field">
                            <label for="ac-del-confirm">Type <strong>DELETE</strong> to confirm *</label>
                            <input type="text" id="ac-del-confirm" name="confirm_text" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
                        </div>
                        <button type="submit" class="btn btn-danger-outline">Delete my account for good</button>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    </div>
</section>
