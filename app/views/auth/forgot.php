<?php
defined('KOPII_APP') or exit('Direct access denied.');
?>
<section class="section-pad auth-body">
    <div class="container">
        <div class="auth-card auth-card-narrow">
            <div class="auth-head">
                <h1 class="auth-title">Forgot your password?</h1>
                <p class="auth-lede">
                    Enter the email address you registered with and we'll send you a link to choose a new password.
                </p>
            </div>

            <form method="POST" action="<?= e(route_url('/forgot-password')) ?>" class="stack-form">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="forgot-email">Email *</label>
                    <input
                        type="email"
                        id="forgot-email"
                        name="email"
                        value="<?= e(old('email')) ?>"
                        placeholder="you@studio.ug"
                        autocomplete="username"
                        autocorrect="off"
                        autocapitalize="none"
                        spellcheck="false"
                        enterkeyhint="send"
                        data-error="forgot-email-err"
                        required
                        autofocus
                    >
                    <div id="forgot-email-err" class="error-msg">Please enter your registered email address.</div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">Email me a reset link</button>

                <div style="padding-block-start:0.35rem; font-size:0.85rem; color:var(--graphite-700);">
                    Remembered it? <a href="<?= e(route_url('/login')) ?>"><strong>Back to log in</strong></a>
                </div>
            </form>
        </div>
    </div>
</section>
