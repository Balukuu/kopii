<?php
defined('KOPII_APP') or exit('Direct access denied.');
/** @var string $token */
?>
<section class="section-pad auth-body">
    <div class="container">
        <div class="auth-card auth-card-narrow">
            <div class="auth-head">
                <h1 class="auth-title">Choose a new password</h1>
                <p class="auth-lede">
                    Use at least 8 characters. You'll be asked to log in again afterwards.
                </p>
            </div>

            <form method="POST" action="<?= e(route_url('/reset-password')) ?>" class="stack-form">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="field">
                    <label for="reset-password">New password *</label>
                    <div class="password-wrap">
                        <input
                            type="password"
                            id="reset-password"
                            name="new_password"
                            placeholder="At least 8 characters"
                            autocomplete="new-password"
                            minlength="8"
                            enterkeyhint="next"
                            data-error="reset-pass-err"
                            required
                            autofocus
                        >
                        <button
                            type="button"
                            class="password-toggle"
                            data-toggle-password="reset-password"
                            aria-pressed="false"
                            aria-label="Show password"
                        >
                            <?= svg_icon('eye', 20, 'icon-eye') ?>
                            <?= svg_icon('eye-off', 20, 'icon-eye-off') ?>
                        </button>
                    </div>
                    <div id="reset-pass-err" class="error-msg">Please choose a password with at least 8 characters.</div>
                </div>

                <div class="field">
                    <label for="reset-confirm">Confirm new password *</label>
                    <input
                        type="password"
                        id="reset-confirm"
                        name="confirm_password"
                        placeholder="Type it again"
                        autocomplete="new-password"
                        minlength="8"
                        enterkeyhint="done"
                        data-error="reset-confirm-err"
                        required
                    >
                    <div id="reset-confirm-err" class="error-msg">Please type your new password again.</div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">Update password</button>
            </form>
        </div>
    </div>
</section>
